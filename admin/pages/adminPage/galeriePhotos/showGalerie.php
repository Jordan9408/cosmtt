<?php
/**
 * =============================================================================
 * GALERIE PHOTOS - PAGE D'ADMINISTRATION (VERSION POO - REFACTORISÉE)
 * =============================================================================
 * 
 * Ce fichier est le point d'entrée principal responsable de :
 * - La logique métier (AJAX, formulaires, récupération des données)
 * - L'inclusion des ressources nécessaires
 * - Le chargement de la vue appropriée
 * 
 * STRUCTURE :
 * - showGalerie.php (ce fichier) : logique PHP
 * - showGalerie.view.php : template HTML
 * - css/style_galerie.css : styles spécifiques
 * - js/galerieImg.js : scripts client
 * 
 * FONCTIONNALITÉS :
 * - AJAX: Vérification d'existence d'événement
 * - AJAX: Modification titre/date d'événement (edit_event)
 * - AJAX: Suppression d'événement (delete_event)
 * - AJAX: Suppression d'un média (delete_media)
 * - AJAX: Réordonnement des médias (reorder_media)
 * - Upload: Compression client, conversion WebP serveur, miniatures
 * - Sécurité: Validation magic bytes, scan malware, ClamAV optionnel
 * 
 * =============================================================================
 * DÉPENDANCES
 * =============================================================================
 * - connect_ddb.php (connexion base de données)
 * - header.php (en-tête HTML)
 * - security.php (sécurité)
 * - session_check.php (vérification session)
 * - GalerieController (contrôleur métier)
 * =============================================================================
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
require_once PAGES_PATH . 'connect_ddb.php';
require_once PARTS_PATH . 'header.php';
require_once INCLUDES_PATH . 'security.php';
require_once INCLUDES_PATH . 'session_check.php';
require_once CLASSPAGES_PATH . 'GalerieController.php';

// =========================================================================
// SECTION 2: GESTION DES MESSAGES FLASH
// =========================================================================

$flashMessage = null;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// =========================================================================
// SECTION 3: INITIALISATION DU CONTRÔLEUR
// =========================================================================

$csrfTokenForJs = $_SESSION['csrf_token'] ?? '';
$galerieController = new GalerieController($conn, $csrfTokenForJs);
$galerieManager = $galerieController->getGalerieManager();

// =========================================================================
// SECTION 4: TRAITEMENT DES REQUÊTES AJAX
// =========================================================================

$galerieController->handleRequest();

// =========================================================================
// SECTION 5: TRAITEMENT DU FORMULAIRE PRINCIPAL
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    $formResult = $galerieController->handleFormSubmission();
    
    if (!empty($formResult['message']) && $formResult['redirect']) {
        $_SESSION['flash_message'] = $formResult['message'];
    }

    if ($formResult['redirect']) {
        $redirectPage = isset($_POST['current_page']) ? max(1, (int)$_POST['current_page']) : 1;
        
        $checkData = $galerieManager->getEventsPaginated($redirectPage);
        if ($redirectPage > $checkData['total_pages']) {
            $redirectPage = $checkData['total_pages'];
        }
        
        $redirectUrl = $_SERVER['PHP_SELF'];
        if ($redirectPage > 1) {
            $redirectUrl .= '?page=' . $redirectPage;
        }
        
        header("Location: " . $redirectUrl);
        exit();
    }
}

// =========================================================================
// SECTION 6: PRÉPARATION DES DONNÉES POUR L'AFFICHAGE
// =========================================================================

$displayError = null;

// Vérifier les erreurs de taille de requête
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && 
    isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $displayError = "❌ Erreur : Le volume total des fichiers envoyés dépasse la limite du serveur (" . ini_get('post_max_size') . "). Veuillez envoyer moins de photos à la fois.";
}

// Récupération paginée des événements
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

try {
    $data = $galerieManager->getEventsPaginated($currentPage);
    $events = $data['events'];
    $mediaByEvent = $data['media'];
    $totalPages = $data['total_pages'];
} catch (Exception $e) {
    $displayError = "Erreur lors de la récupération de la galerie : " . htmlspecialchars($e->getMessage());
    $events = [];
    $mediaByEvent = [];
    $totalPages = 1;
    $currentPage = 1;
}

// =========================================================================
// SECTION 7: CHARGEMENT DE LA VUE
// =========================================================================

require_once CLASSPAGES_PATH . 'GalerieView.php';

require_once dirname(__DIR__, 2) . '/parts/footer.php';