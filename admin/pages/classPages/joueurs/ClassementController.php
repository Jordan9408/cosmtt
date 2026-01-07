<?php

/**
 * Contrôleur principal pour la gestion du classement des joueurs
 */
class ClassementController
{
    private $conn;
    private $joueurManager;
    private $view;
    private $role;
    private $errors = [];
    private $successMessage = '';

    public function __construct($conn, string $role)
    {
        $this->conn = $conn;
        $this->role = $role;
        $this->joueurManager = new JoueurManager($conn);
        $this->view = new ClassementView();
    }

    /**
     * Point d'entrée principal du contrôleur
     */
    public function handleRequest(): void
    {
        $this->handlePost();
        $this->handleDelete();
        $this->handleSuccessMessage();
        $this->render();
    }

    /**
     * Gestion des requêtes POST (ajout/modification)
     */
    private function handlePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveJoueur'])) {
            try {
                $result = $this->isUpdateMode() 
                    ? $this->joueurManager->updateJoueur($_POST)
                    : $this->joueurManager->addJoueur($_POST);
                
                $this->successMessage = $result['success'];
                $this->errors = $result['errors'];
                
                if (empty($this->errors)) {
                    $this->redirect('showClassement.php?success=' . urlencode($this->successMessage));
                }
            } catch (Exception $e) {
                $this->errors[] = "Erreur : " . $e->getMessage();
            }
        }
    }

    /**
     * Gestion de la suppression
     */
    private function handleDelete(): void
    {
        if (isset($_GET['delete_id']) && $this->role === 'superAdmin') {
            $deleteId = (int)$_GET['delete_id'];
            if ($deleteId > 0) {
                try {
                    $this->joueurManager->deleteJoueur($deleteId);
                    $this->redirect('showClassement.php?edit=1&success=' . urlencode('SuppressionOK'));
                } catch (Exception $e) {
                    $this->errors[] = "Erreur lors de la suppression : " . $e->getMessage();
                }
            }
        }
    }

    /**
     * Récupération du message de succès après redirection
     */
    private function handleSuccessMessage(): void
    {
        if (isset($_GET['success'])) {
            $this->successMessage = $_GET['success'];
        }
    }

    /**
     * Vérifie si on est en mode modification
     */
    private function isUpdateMode(): bool
    {
        return isset($_POST['joueur_id']) && !empty($_POST['joueur_id']);
    }

    /**
     * Vérifie si on est en mode édition
     */
    private function isEditMode(): bool
    {
        return isset($_GET['edit']) && $_GET['edit'] == '1';
    }

    /**
     * Vérifie si l'utilisateur peut modifier
     */
    private function canEdit(): bool
    {
        return in_array($this->role, ['admin', 'superAdmin']);
    }

    /**
     * Redirige vers une URL
     */
    private function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    /**
     * Affiche la page
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
            $this->successMessage
        );
        
        $this->view->render($viewData);
    }
}