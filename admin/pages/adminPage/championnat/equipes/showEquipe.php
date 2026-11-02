<?php
// 1. Inclusions de sécurité et de configuration de l'environnement
require_once dirname(__DIR__, 4) . '/includes/security.php';
require_once dirname(__DIR__, 4) . '/includes/session_check.php';
require_once dirname(__DIR__, 4) . '/includes/ErrorMessages.php';
require_once dirname(__DIR__, 3) . '/connect_ddb.php';

// 2. Chargement et exécution du Header (génère ou vérifie le jeton CSRF en session)
require_once dirname(__DIR__, 3) . '/parts/header.php';

// 3. Inclusion du contrôleur original
require_once dirname(__DIR__, 3) . '/classPages/equipes/EquipeController.php';
use Admin\ClassPages\Equipes\EquipeController;

/**
 * Classe PageShowEquipe
 * Chef d'orchestre chargé d'initialiser, traiter et afficher la page de gestion des équipes.
 */
class PageShowEquipe 
{
    private ?string $message = null;
    private mixed $manager = null;
    private string $csrfToken = '';

    /**
     * Le constructeur utilise la promotion de propriété (PHP 8+)
     * pour injecter automatiquement la connexion PDO et le rôle de l'utilisateur.
     */
    public function __construct(
        private mixed $dbConnection, 
        private string $userRole
    ) {}

    /**
     * Étape 1 : Récupération sécurisée du jeton CSRF après l'inclusion du Header
     */
    private function init(): void 
    {
        $this->csrfToken = $_SESSION['csrf_token'] ?? '';
    }

    /**
     * Étape 2 : Transmission des données au contrôleur et exécution de la logique métier
     */
    private function handle(): void 
    {
        $controller = new EquipeController($this->dbConnection, $this->csrfToken, $this->userRole);
        
        $this->message = $controller->handleRequest();
        $this->manager = $controller->getManager();
    }

    /**
     * Étape 3 : Déballage des variables et inclusion de l'interface graphique (Vue + Footer)
     */
    private function render(): void 
    {
        // Extraction locale pour assurer une compatibilité transparente avec "showEquipe.view.php"
        $csrfToken = $this->csrfToken;
        $message = $this->message;
        $manager = $this->manager;

        // Rendu de la vue
        require_once dirname(__DIR__, 3) . '/classPages/equipes/showEquipe.view.php';

        // Rendu du pied de page
        include_once dirname(__DIR__, 3) . '/parts/footer.php';
    }

    /**
     * Point d'entrée principal orchestrant le cycle de vie complet de la page
     */
    public function run(): void 
    {
        $this->init();
        $this->handle();
        $this->render();
    }
}

// --- Instanciation et exécution automatique ---
// $conn (PDO) et $role proviennent de connect_ddb.php et session_check.php
$page = new PageShowEquipe($conn, $role);
$page->run();