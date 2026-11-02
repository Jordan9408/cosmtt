<?php

namespace Admin\ClassPages\Equipes;

use EquipeManager;
use Admin\Includes\ErrorMessages;
use PDO;

require_once __DIR__ . '/EquipesManager.php';

/**
 * Classe EquipeController
 * Gère la logique métier et les actions liées aux équipes (Ajout, Suppression).
 */
class EquipeController
{
    private EquipeManager $manager;
    
    // Constantes internes pour éviter les chaînes magiques répétées
    private const SUCCESS_KEY = 'AddSuccess';
    private const REDIRECT_TARGET = 'showEquipe.php';

    /**
     * Le constructeur utilise la promotion de propriété (PHP 8+)
     * pour injecter et assigner directement les dépendances.
     */
    public function __construct(
        private PDO $conn, 
        private string $csrfToken, 
        private string $role
    ) {
        $this->manager = new EquipeManager($this->conn);
    }

    /**
     * Gère les requêtes entrantes (Point d'entrée du contrôleur)
     */
    public function handleRequest(): ?string
    {
        // 1. Gestion de l'ajout (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nomEquipe'])) {
            return $this->handleAddTeam();
        }

        // 2. Gestion de la suppression (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
            return $this->handleDeleteTeam();
        }

        // 3. Gestion des messages de redirection
        if (isset($_GET['message'])) {
            return ErrorMessages::get($_GET['message']);
        }

        return null;
    }

    /**
     * Logique d'ajout d'une équipe
     */
    private function handleAddTeam(): string
    {
        if (!$this->isCsrfValid($_POST['csrf_token'] ?? '')) {
            return ErrorMessages::get('CSRF_INVALID');
        }

        $nom = $_POST['nomEquipe'] ?? '';
        $poule = $_POST['poule'] ?? '';
        
        $resultKey = $this->manager->ajouterEquipe($nom, $poule);
        
        // Pattern PRG (Post-Redirect-Get) : Sauvegarde temporaire en session si échec
        if ($resultKey !== self::SUCCESS_KEY) {
            $_SESSION['form_data'] = [
                'nomEquipe' => $nom,
                'poule'     => $poule
            ];
        }

        $openPopup = ($resultKey !== self::SUCCESS_KEY);
        $this->redirectWithMessage($resultKey, $openPopup);
    }

    /**
     * Logique de suppression d'une équipe
     */
    private function handleDeleteTeam(): string
    {
        // Vérification stricte des autorisations d'accès
        if (!in_array($this->role, ['superAdmin', 'admin'], true)) {
            header('HTTP/1.1 403 Forbidden');
            die('Accès non autorisé');
        }

        if (!$this->isCsrfValid($_POST['csrf_token'] ?? '')) {
            return ErrorMessages::get('CSRF_INVALID');
        }

        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        if ($id && $id > 0) {
            $resultKey = $this->manager->supprimerEquipe($id);
            $this->redirectWithMessage($resultKey);
        }

        return ErrorMessages::get('ID_INVALID');
    }

    /**
     * Accès au manager pour l'affichage dans la vue
     */
    public function getManager(): EquipeManager
    {
        return $this->manager;
    }

    /**
     * Vérifie la validité du token CSRF fourni
     */
    private function isCsrfValid(string $token): bool
    {
        return hash_equals($this->csrfToken, $token);
    }

    /**
     * Centralise et exécute les redirections de l'application
     */
    private function redirectWithMessage(string $messageKey, bool $openPopup = false): never
    {
        $url = self::REDIRECT_TARGET . "?message=" . urlencode($messageKey);
        
        if ($openPopup) {
            $url .= '&openPopup=1';
        }

        header("Location: " . $url);
        exit;
    }
}