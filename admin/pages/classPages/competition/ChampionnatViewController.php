<?php
/**
 * Classe ChampionnatViewController
 * Gère l'affichage et les interactions de la page championnat
 */
class ChampionnatViewController
{
    private PDO $conn;
    private string $poule;
    private bool $isEditMode;
    private array $errors = [];
    private array $pageErrors = [];
    private ?string $successMessage = '';
    private string $role;
    
    private ChampionnatManager $championnatManager;
    private RencontreManager $rencontreManager;
    private EquipeManager $equipeManager;
    private JourneeController $journeeController;
    
    private array $classement = [];
    private array $journees = [];
    private array $rencontres = [];
    private array $equipes = [];
    private array $postedScores = [];
    
    public function __construct(PDO $connection, string $role)
    {
        $this->conn = $connection;
        $this->role = $role;
        
        // Initialisation des managers
        $this->championnatManager = new ChampionnatManager($connection);
        $this->rencontreManager = new RencontreManager($connection);
        $this->equipeManager = new EquipeManager($connection);
        $this->journeeController = new JourneeController($connection);
        
        // Initialisation des données de base
        $this->initializePoule();
        $this->initializeEditMode();
    }
    
    /**
     * Initialise la poule sélectionnée
     */
    private function initializePoule(): void
    {
        $this->poule = $_POST['poule'] ?? $_GET['poule'] ?? 'pouleA';
        
        if (!in_array($this->poule, ['pouleA', 'pouleB'])) {
            $this->poule = 'pouleA';
        }
    }
    
    /**
     * Initialise le mode édition
     */
    private function initializeEditMode(): void
    {
        $this->isEditMode = isset($_GET['edit']) && $_GET['edit'] == '1';
    }
    
    /**
     * Traite toutes les requêtes POST et GET
     */
    public function handleRequests(): void
    {
        // Gestion de l'ajout d'une journée
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
            $this->handleAddJournee();
        }
        
        // Gestion de la modification des rencontres
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveEdits'])) {
            $this->handleUpdateRencontres();
        }
        
        // Gestion de la suppression d'une journée
        if (isset($_GET['delete_journee_id']) && $this->role === 'superAdmin') {
            $this->handleDeleteJournee();
        }
        
        // Récupérer un message de succès après redirection
        if (isset($_GET['successMessage']) && empty($this->errors)) {
            $this->successMessage = $_GET['successMessage'];
        }
        
        // Garder les données POST en cas d'erreur
        $this->handlePostedScores();
    }
    
    /**
     * Gère l'ajout d'une journée
     */
    private function handleAddJournee(): void
    {
        $result = $this->journeeController->handlePost($_POST);
        $this->errors = $result['errors'];
        $this->successMessage = $result['success'] ?? '';
    }
    
    /**
     * Gère la mise à jour des rencontres
     */
    private function handleUpdateRencontres(): void
    {
        $equipesList = $this->equipeManager->getEquipesByPoule($this->poule);
        $result = $this->rencontreManager->mettreAJourRencontres($_POST, $equipesList, $this->poule, $this->role);
        $this->errors = $result['errors'];
        $this->successMessage = $result['success'] ?? '';
        
        // DEBUG : Afficher les erreurs dans les logs PHP
        if (!empty($this->errors)) {
            error_log("=== ERREURS DE VALIDATION ===");
            error_log("Nombre de journées avec erreurs : " . count($this->errors));
            foreach ($this->errors as $journeeName => $journeeErrors) {
                error_log("Erreurs pour la journée '" . $journeeName . "':");
                foreach ($journeeErrors as $error) {
                    error_log("  - " . $error);
                }
            }
            error_log("=============================");
        }
        
        if (empty($this->errors)) {
            // Succès : rediriger sans edit=1 pour revenir en mode affichage
            header("Location: ?poule=" . urlencode($this->poule) . "&successMessage=" . urlencode($this->successMessage));
            exit;
        } else {
            // Rester en mode édition si erreurs
            $_GET['edit'] = '1';
            $this->isEditMode = true;
        }
    }
    
    /**
     * Gère la suppression d'une journée
     */
    private function handleDeleteJournee(): void
    {
        $deleteJourneeId = (int)$_GET['delete_journee_id'];
        
        if ($deleteJourneeId > 0) {
            try {
                $journeeManagerDelete = new JourneeManager($this->conn);
                $journeeManagerDelete->supprimerJourneeEtRencontres($deleteJourneeId);
                $this->successMessage = "";
                
                // Rediriger pour nettoyer l'URL
                header("Location: ?poule=" . urlencode($this->poule) . "&edit=1&successMessage=" . urlencode($this->successMessage));
                exit;
            } catch (PDOException $e) {
                $this->errors[] = "Erreur lors de la suppression de la journée : " . $e->getMessage();
            }
        } else {
            $this->errors[] = "ID de journée invalide pour la suppression.";
        }
    }
    
    /**
     * Garde les scores postés en cas d'erreur
     */
    private function handlePostedScores(): void
    {
        if (!empty($_POST['score1'])) {
            $this->postedScores['score1'] = $_POST['score1'];
        }
        if (!empty($_POST['score2'])) {
            $this->postedScores['score2'] = $_POST['score2'];
        }
    }
    
    /**
     * Charge toutes les données nécessaires à l'affichage
     */
    public function loadData(): void
    {
        $this->classement = $this->championnatManager->calculerClassement($this->poule);
        $this->journees = $this->rencontreManager->getJourneesByPoule($this->poule);
        $this->rencontres = $this->rencontreManager->getRencontresByPoule($this->poule);
        $this->equipes = $this->equipeManager->getEquipesByPoule($this->poule);
    }
    
    /**
     * Sépare les journées en "Aller" et "Retour"
     */
    public function getJourneesByType(): array
    {
        $journeesAller = [];
        $journeesRetour = [];
        
        foreach ($this->journees as $journee) {
            if (isset($journee['type_journee']) && $journee['type_journee'] === 'retour') {
                $journeesRetour[] = $journee;
            } else {
                $journeesAller[] = $journee;
            }
        }
        
        return [
            'aller' => $journeesAller,
            'retour' => $journeesRetour
        ];
    }
    
    /**
     * Vérifie si le nombre d'équipes est impair
     */
    public function isOddNumberOfTeams(): bool
    {
        return (count($this->equipes) % 2) !== 0;
    }
    
    // Getters pour les propriétés privées
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
     * Fonction pour échapper le HTML
     */
    public static function escapeHtml(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>