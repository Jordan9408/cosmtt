<?php

declare(strict_types=1);

// Inclusion des fichiers de sécurité et session en premier
require_once dirname(__DIR__, 4) . '/includes/security.php';
require_once dirname(__DIR__, 4) . '/includes/session_check.php';

// Inclusion de la connexion et de l'en-tête
require_once dirname(__DIR__, 3) . '/connect_ddb.php';
require_once dirname(__DIR__, 3) . '/parts/header.php';

// Définition du chemin des classes
define('CLASSPAGES_PATH', dirname(__DIR__, 3) . '/classPages/competition/');

// Chargement des classes requises
require_once CLASSPAGES_PATH . 'JourneeValidator.php';
require_once CLASSPAGES_PATH . 'JourneeManager.php';
require_once CLASSPAGES_PATH . 'JourneeController.php';
require_once CLASSPAGES_PATH . 'EquipeManager.php';
require_once CLASSPAGES_PATH . 'RencontreManager.php';
require_once CLASSPAGES_PATH . 'ChampionnatManager.php';
require_once CLASSPAGES_PATH . 'ChampionnatViewController.php';
require_once CLASSPAGES_PATH . 'ChampionnatView.php';

/**
 * Classe gérant le contrôleur de page pour orchestrer la logique et appeler la vue.
 */
class ShowChampionnatPage 
{
    private ChampionnatViewController $viewController;
    private ChampionnatView $view;

    /**
     * Utilisation de la promotion de propriété de PHP 8 pour $role
     */
    public function __construct(PDO $conn, private string $role)
    {
        $this->viewController = new ChampionnatViewController($conn, $this->role);
        $this->view = new ChampionnatView();
    }

    /**
     * Exécute la logique de la page et effectue le rendu.
     */
    public function run(): void
    {
        // Traitement des requêtes et chargement des données
        $this->viewController->handleRequests();
        $this->viewController->loadData();

        // Récupération des journées triées
        $journeesParType = $this->viewController->getJourneesByType();

        // Préparation des données pour la vue
        $viewData = [
            'poule'              => $this->viewController->getPoule(),
            'isEditMode'         => $this->viewController->isEditMode(),
            'errors'             => $this->viewController->getErrors(),
            'successMessage'     => $this->viewController->getSuccessMessage(),
            'classement'         => $this->viewController->getClassement(),
            'rencontres'         => $this->viewController->getRencontres(),
            'equipes'            => $this->viewController->getEquipes(),
            'postedScores'       => $this->viewController->getPostedScores(),
            'isOddNumberOfTeams' => $this->viewController->isOddNumberOfTeams(),
            'journeesAller'      => $journeesParType['aller'] ?? [],
            'journeesRetour'     => $journeesParType['retour'] ?? [],
            'role'               => $this->role
        ];

        // Rendu final via la classe de vue
        $this->view->render($viewData);
    }
}

// Instanciation et exécution de la page
$page = new ShowChampionnatPage($conn, $role);
$page->run();