<?php

use Admin\Includes\ErrorMessages;

/**
 * Contrôleur principal pour la gestion du classement des joueurs
 */
final class ClassementController
{
    private $conn;
    private $joueurManager;
    private $view;
    private string $role;
    private string $csrfToken;

    /**
     * @var list<string>
     */
    private array $errors = [];

    private string $successMessage = '';

    public function __construct($conn, string $role, string $csrfToken = '')
    {
        $this->conn = $conn;
        $this->role = $role;
        $this->csrfToken = $csrfToken;

        $this->joueurManager = new JoueurManager($conn);
        $this->view = new ClassementView();
    }

    /**
     * Point d'entrée principal du contrôleur.
     */
    public function handleRequest(): void
    {
        $this->handlePost();
        $this->handleDelete();
        $this->handleSuccessMessage();
        $this->render();
    }

    /**
     * Gestion des requêtes POST (ajout/modification).
     */
    private function handlePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['saveJoueur'])) {
            return;
        }

        if (!$this->isValidCsrfToken($_POST['csrf_token'] ?? null, 'JOUER_CSRF_INVALID')) {
            return;
        }

        try {
            $result = $this->isUpdateMode()
                ? $this->joueurManager->updateJoueur($_POST)
                : $this->joueurManager->addJoueur($_POST);

            $this->successMessage = $result['success'] ?? '';
            $this->errors = $result['errors'] ?? [];

            if (empty($this->errors)) {
                $this->redirect('showClassement.php?success=' . urlencode($this->successMessage));
            }
        } catch (Exception $e) {
            error_log("Erreur classement : " . $e->getMessage());
            $this->errors[] = ErrorMessages::get('GENERIC_ERROR');
        }
    }

    /**
     * Gestion de la suppression.
     */
    private function handleDelete(): void
    {
        if (!isset($_POST['delete_id']) || $this->role !== 'superAdmin') {
            return;
        }

        if (!$this->isValidCsrfToken($_POST['csrf_token'] ?? null, 'JOUER_DELETE_INVALID_TOKEN')) {
            return;
        }

        $deleteId = (int)$_POST['delete_id'];
        if ($deleteId <= 0) {
            return;
        }

        try {
            $this->joueurManager->deleteJoueur($deleteId);
            $this->redirect('showClassement.php?edit=1&success=' . urlencode(''));
        } catch (Exception $e) {
            error_log("Erreur suppression joueur : " . $e->getMessage());
            $this->errors[] = ErrorMessages::get('JOUER_DELETE_FAILED');
        }
    }

    /**
     * Récupération du message de succès après redirection.
     */
    private function handleSuccessMessage(): void
    {
        if (isset($_GET['success'])) {
            $this->successMessage = (string)$_GET['success'];
        }
    }

    /**
     * Vérifie si on est en mode modification.
     */
    private function isUpdateMode(): bool
    {
        return isset($_POST['joueur_id']) && !empty($_POST['joueur_id']);
    }

    /**
     * Vérifie si on est en mode édition.
     */
    private function isEditMode(): bool
    {
        return isset($_GET['edit']) && $_GET['edit'] == '1';
    }

    /**
     * Vérifie si l'utilisateur peut modifier.
     */
    private function canEdit(): bool
    {
        return in_array($this->role, ['admin', 'superAdmin'], true);
    }

    /**
     * Valide un token CSRF.
     *
     * @param string|null $providedToken
     */
    private function isValidCsrfToken(?string $providedToken, string $errorKey): bool
    {
        if ($providedToken === null || !hash_equals($this->csrfToken, $providedToken)) {
            $this->errors[] = ErrorMessages::get($errorKey);
            return false;
        }

        return true;
    }

    /**
     * Redirige vers une URL.
     */
    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Affiche la page.
     */
    private function render(): void
    {
        $joueurs = $this->joueurManager->getAllJoueurs();

        $viewData = new ClassementViewData(
            $joueurs,
            $this->isEditMode(),
            $this->canEdit(),
            $this->role,
            $this->errors,
            $this->successMessage,
            $this->csrfToken
        );

        $this->view->render($viewData);
    }
}

