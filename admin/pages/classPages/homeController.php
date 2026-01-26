<?php
/**
 * Contrôleur pour la page d'accueil de l'administration
 */
class homeController
{
    private $errors;
    private $successMessage;
    private $userFirstName;
    private $role;
    private $links;

    public function __construct()
    {
        $this->errors = [];
        $this->successMessage = '';
        $this->userFirstName = '';
        $this->role = '';
        $this->links = [];
    }

    /**
     * Vérifie l'authentification de l'utilisateur
     */
    public function checkAuthentication()
    {
        if (!isset($_SESSION['firstName'])) {
            header('Location: ../../connexion.php');
            exit;
        }
        $this->userFirstName = $_SESSION['firstName'];
    }

    /**
     * Détermine le rôle de l'utilisateur
     */
    public function determineRole()
    {
        if ($_SESSION['role'] == 'superAdmin') {
            $this->role = 'superAdmin';
        } elseif ($_SESSION['role'] == 'admin') {
            $this->role = 'admin';
        }
    }

    /**
     * Génère les liens du menu en fonction du rôle
     */
    public function generateMenuLinks()
    {
        if ($this->role === 'superAdmin') {
            $this->links[] = '<li><a href="./user/showUser.php">Liste des Admins</a></li>';
            $this->links[] = '<li><a href="./infosClub/addInfoscCub.php">Infos Club</a></li>';
        }

        if ($this->role == 'admin' || $this->role == 'superAdmin') {
            $this->links[] = '<li><a href="./article/addArticle.php">Article</a></li>';
            $this->links[] = '<li><a href="./adminPage/galeriePhotos/showGalerie.php">Galerie Photos</a></li>';
            $this->links[] = '<li><a href="./adminPage/championnat/homeChampionnat.php">Championnat</a></li>';
            $this->links[] = '<li><a href="./adminPage/joueurs/showClassement.php">Classements</a></li>';
        }
    }

    /**
     * Affiche le message de bienvenue
     */
    public function displayWelcomeMessage()
    {
        echo '<h1>Bonjour ' . ucfirst($this->userFirstName) . '</h1>';
    }

    /**
     * Affiche le menu
     */
    public function displayMenu()
    {
        echo '<div id="menu_btn"><ul>' . implode('', $this->links) . '</ul></div>';
    }

    /**
     * Traite la page d'accueil complète
     */
    public function handleHomePage()
    {
        $this->checkAuthentication();
        $this->determineRole();
        $this->generateMenuLinks();
        $this->displayWelcomeMessage();
        $this->displayMenu();
    }

    // Getters
    public function getErrors() { return $this->errors; }
    public function getSuccessMessage() { return $this->successMessage; }
    public function getUserFirstName() { return $this->userFirstName; }
    public function getRole() { return $this->role; }
    public function getLinks() { return $this->links; }
}
?>
