<?php

declare(strict_types=1);

/**
 * =============================================================================
 * GALERIE PHOTOS - PAGE D'ADMINISTRATION (POO)
 * =============================================================================
 *
 * Ce fichier reste le point d'entrée de la page, mais la logique est
 * encapsulée pour améliorer la structure/lisibilité (sans changer l'UI).
 */

// =========================================================================
// SECTION 1: INITIALISATION ET INCLUDES
// =========================================================================

// Définir les chemins pour les includes
define('PAGES_PATH', dirname(__DIR__, 2) . '/');
define('PARTS_PATH', PAGES_PATH . 'parts/');
define('INCLUDES_PATH', dirname(__DIR__, 3) . '/includes/');
define('CLASSPAGES_PATH', PAGES_PATH . 'classPages/galerie/');

// Inclure les dépendances
require_once INCLUDES_PATH . 'security.php';
require_once INCLUDES_PATH . 'session_check.php';
require_once PAGES_PATH . 'connect_ddb.php';
require_once PARTS_PATH . 'header.php';
require_once CLASSPAGES_PATH . 'GalerieController.php';

// Connexion PDO (fourni par connect_ddb.php)
if (!isset($conn) || !($conn instanceof PDO)) {
    throw new RuntimeException('Connexion base de données ($conn) indisponible.');
}

final class ShowGaleriePage
{
    /**
     * Classe “page-controller” (front controller) : ne modifie ni l'UI ni les templates.
     */

    private PDO $conn;
    private string $csrfTokenForJs;
    private GalerieController $galerieController;
    private GalerieManager $galerieManager;

    public function __construct(PDO $conn, string $csrfTokenForJs)
    {
        $this->conn = $conn;
        $this->csrfTokenForJs = $csrfTokenForJs;

        $this->galerieController = new GalerieController($this->conn, $this->csrfTokenForJs);
        $this->galerieManager = $this->galerieController->getGalerieManager();
    }

    public function run(): void
    {
        $flashMessage = $this->bootstrapFlashMessage();
        $this->handleAjax();
        $this->handlePrimaryForm();

        $viewData = $this->prepareViewData();
        extract($viewData); // -> $displayError, $currentPage, $events, $mediaByEvent, $totalPages

        $csrfTokenForJs = $this->csrfTokenForJs; // Pour la vue
        $galerieManager = $this->galerieManager; // Pour la vue

        require_once CLASSPAGES_PATH . 'GalerieView.php';
        require_once dirname(__DIR__, 2) . '/parts/footer.php';
    }

    private function bootstrapFlashMessage(): ?string
    {
        $flashMessage = null;
        if (isset($_SESSION['flash_message'])) {
            $flashMessage = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
        }

        return $flashMessage;
    }

    private function handleAjax(): void
    {
        $this->galerieController->handleRequest();
    }

    private function handlePrimaryForm(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['action']) || $_POST['action'] !== 'add_event') {
            return;
        }

        $formResult = $this->galerieController->handleFormSubmission();

        if (!empty($formResult['message']) && ($formResult['redirect'] ?? false)) {
            $_SESSION['flash_message'] = $formResult['message'];
        }

        if ($formResult['redirect'] ?? false) {
            $redirectPage = isset($_POST['current_page']) ? max(1, (int)$_POST['current_page']) : 1;

            $checkData = $this->galerieManager->getEventsPaginated($redirectPage);
            if ($redirectPage > ($checkData['total_pages'] ?? 1)) {
                $redirectPage = (int)$checkData['total_pages'];
            }

            $redirectUrl = $_SERVER['PHP_SELF'];
            if ($redirectPage > 1) {
                $redirectUrl .= '?page=' . $redirectPage;
            }

            header('Location: ' . $redirectUrl);
            exit();
        }
    }

    private function prepareViewData(): array
    {
        $displayError = null;

        // Vérifier les erreurs de taille de requête
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) &&
            isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0
        ) {
            $displayError = "❌ Erreur : Le volume total des fichiers envoyés dépasse la limite du serveur (" . ini_get('post_max_size') . "). Veuillez envoyer moins de photos à la fois.";
        }

        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        try {
            $data = $this->galerieManager->getEventsPaginated($currentPage);
            $events = $data['events'];
            $mediaByEvent = $data['media'];
            $totalPages = $data['total_pages'];
        } catch (Exception $e) {
            error_log('Erreur lors de la récupération de la galerie : ' . $e->getMessage());
            $displayError = 'Une erreur est survenue lors de la récupération de la galerie.';
            $events = [];
            $mediaByEvent = [];
            $totalPages = 1;
            $currentPage = 1;
        }

        return [
            'displayError' => $displayError,
            'currentPage' => $currentPage,
            'events' => $events,
            'mediaByEvent' => $mediaByEvent,
            'totalPages' => $totalPages,
        ];
    }
}

// =========================================================================
// POINT D'ENTRÉE
// =========================================================================

$csrfTokenForJs = $_SESSION['csrf_token'] ?? '';
$page = new ShowGaleriePage($conn, $csrfTokenForJs);
$page->run();