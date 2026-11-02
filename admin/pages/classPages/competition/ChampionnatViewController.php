<?php

declare(strict_types=1);

/**
 * Gère l'affichage, les actions et l'orchestration de la page championnat (Poule, Édition, Suppression).
 */
class ChampionnatViewController
{
    private string $poule;
    private bool $isEditMode;
    
    /** @var array<string, string[]> */
    private array $errors = [];
    private ?string $successMessage = '';
    
    private ChampionnatManager $championnatManager;
    private RencontreManager $rencontreManager;
    private EquipeManager $equipeManager;
    private JourneeController $journeeController;
    
    private array $classement = [];
    private array $journees = [];
    private array $rencontres = [];
    private array $equipes = [];
    private array $postedScores = [];
    
    /**
     * Utilisation de la promotion de propriété de constructeur (PHP 8+)
     */
    public function __construct(private PDO $conn, private string $role)
    {
        $this->championnatManager = new ChampionnatManager($this->conn);
        $this->rencontreManager = new RencontreManager($this->conn);
        $this->equipeManager = new EquipeManager($this->conn);
        $this->journeeController = new JourneeController($this->conn);
        
        $this->initializePoule();
        $this->initializeEditMode();
    }
    
    /**
     * Initialise la poule sélectionnée avec contrôle de validité.
     */
    private function initializePoule(): void
    {
        $this->poule = $_POST['poule'] ?? $_GET['poule'] ?? 'pouleA';
        
        if (!in_array($this->poule, ['pouleA', 'pouleB'], true)) {
            $this->poule = 'pouleA';
        }
    }
    
    /**
     * Initialise le mode édition avec une comparaison stricte.
     */
    private function initializeEditMode(): void
    {
        $this->isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1';
    }
    
    /**
     * Traite toutes les requêtes POST et GET (Routeur interne).
     */
    public function handleRequests(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($requestMethod === 'POST') {
            if (isset($_POST['send'])) {
                $this->handleAddJournee();
            }
            if (isset($_POST['saveEdits'])) {
                $this->handleUpdateRencontres();
            }
            if (isset($_POST['delete_journee_id']) && $this->role === 'superAdmin') {
                $this->handleDeleteJournee();
            }
        }
        
        if (isset($_GET['successMessage']) && empty($this->errors)) {
            $this->successMessage = (string)$_GET['successMessage'];
        }
        
        $this->handlePostedScores();
    }
    
    /**
     * Gère l'ajout d'une journée.
     */
    private function handleAddJournee(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $this->errors['Général'][] = "Jeton de sécurité CSRF invalide ou expiré.";
            return;
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $result = $this->journeeController->handlePost($_POST);
        $this->errors = $result['errors'] ?? [];
        $this->successMessage = $result['success'] ?? '';
    }
    
    /**
     * Gère la mise à jour des rencontres.
     */
    private function handleUpdateRencontres(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $this->errors['Général'][] = "Jeton de sécurité CSRF invalide ou expiré.";
            return;
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $equipesList = $this->equipeManager->getEquipesByPoule($this->poule);
        $result = $this->rencontreManager->mettreAJourRencontres($_POST, $equipesList, $this->poule, $this->role);
        
        $this->errors = $result['errors'] ?? [];
        $this->successMessage = $result['success'] ?? '';
        
        if (empty($this->errors)) {
            header('Location: ?poule=' . urlencode($this->poule) . '&successMessage=' . urlencode($this->successMessage));
            exit;
        }

        $_GET['edit'] = '1';
        $this->isEditMode = true;
    }
    
    /**
     * Gère la suppression d'une journée.
     */
    private function handleDeleteJournee(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $this->errors['Général'][] = "Jeton de sécurité CSRF invalide ou expiré.";
            return;
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $deleteId = (int)($_POST['delete_journee_id'] ?? 0);
        
        if ($deleteId > 0) {
            try {
                $journeeManagerDelete = new JourneeManager($this->conn);
                $journeeManagerDelete->supprimerJourneeEtRencontres($deleteId);
                $this->successMessage = '';
                
                header('Location: ?poule=' . urlencode($this->poule) . '&edit=1&successMessage=' . urlencode($this->successMessage));
                exit;
            } catch (PDOException $e) {
                error_log("Erreur suppression journée : " . $e->getMessage());
                $this->errors['Général'][] = "Erreur lors de la suppression de la journée.";
            }
        } else {
            $this->errors['Général'][] = "ID de journée invalide pour la suppression.";
        }
    }
    
    /**
     * Conserve les scores et les sélections d'équipes postés en cas d'erreur de validation.
     */
    private function handlePostedScores(): void
    {
        foreach (['score1', 'score2', 'equipe1', 'equipe2'] as $field) {
            if (!empty($_POST[$field])) {
                $this->postedScores[$field] = $_POST[$field];
            }
        }
    }
    
    /**
     * Charge toutes les données nécessaires à l'affichage.
     */
    public function loadData(): void
    {
        $this->classement = $this->championnatManager->calculerClassement($this->poule);
        $this->journees = $this->rencontreManager->getJourneesByPoule($this->poule);
        $this->rencontres = $this->rencontreManager->getRencontresByPoule($this->poule);
        $this->equipes = $this->equipeManager->getEquipesByPoule($this->poule);
    }
    
    /**
     * Récupère et sépare les journées en "Aller" et "Retour".
     * * @return array{aller: array, retour: array}
     */
    public function getJourneesByType(): array
    {
        $separated = ['aller' => [], 'retour' => []];
        
        foreach ($this->journees as $journee) {
            $type = (isset($journee['type_journee']) && $journee['type_journee'] === 'retour') ? 'retour' : 'aller';
            $separated[$type][] = $journee;
        }
        
        return $separated;
    }
    
    /**
     * Vérifie si le nombre d'équipes est impair.
     */
    public function isOddNumberOfTeams(): bool
    {
        return (count($this->equipes) % 2) !== 0;
    }
    
    // --- Getters ---
    public function getPoule(): string { return $this->poule; }
    public function isEditMode(): bool { return $this->isEditMode; }
    public function getErrors(): array { return $this->errors; }
    public function getSuccessMessage(): string { return $this->successMessage ?? ''; }
    public function getRole(): string { return $this->role; }
    public function getClassement(): array { return $this->classement; }
    public function getJournees(): array { return $this->journees; }
    public function getRencontres(): array { return $this->rencontres; }
    public function getEquipes(): array { return $this->equipes; }
    public function getPostedScores(): array { return $this->postedScores; }
    
    /**
     * Échappe proprement les chaînes pour la protection XSS.
     */
    public static function escapeHtml(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}