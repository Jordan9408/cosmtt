<?php

declare(strict_types=1);

/**
 * Contrôleur pour la page d'accueil de l'administration
 */
class homeController
{
    private string $userFirstName;
    private string $role;
    private array $links = [];
    private int $todayVisits = 0;
    private int $weekVisits = 0;
    private int $monthVisits = 0;

    /**
     * Constructeur avec injection de la connexion PDO et des données utilisateur
     */
    public function __construct(
        private PDO $conn,
        string $userFirstName = '',
        string $role = ''
    ) {
        // Récupération des informations (via paramètres ou repli sur la session)
        $this->userFirstName = $userFirstName !== '' ? $userFirstName : (string) ($_SESSION['firstName'] ?? '');
        $this->role = $role !== '' ? $role : (string) ($_SESSION['role'] ?? '');
    }

    /**
     * Génère les liens du menu en fonction du rôle de l'utilisateur
     */
    public function generateMenuLinks(): void
    {
        if ($this->role === 'superAdmin') {
            $this->links[] = '<li><a href="./user/showUser.php">Liste des Admins</a></li>';
            $this->links[] = '<li><a href="./adminPage/infosClub/showInfosClub.php">Infos Club</a></li>';
        }

        // Vérification stricte des rôles autorisés (PHP 8)
        if (in_array($this->role, ['admin', 'superAdmin'], true)) {
            $this->links[] = '<li><a href="./adminPage/article/showArticles.php">Articles Critérium</a></li>';
            $this->links[] = '<li><a href="./adminPage/galeriePhotos/showGalerie.php">Galerie Photos</a></li>';
            $this->links[] = '<li><a href="./adminPage/championnat/homeChampionnat.php">Championnat</a></li>';
            $this->links[] = '<li><a href="./adminPage/joueurs/showClassement.php">Classements</a></li>';
        }
    }

    /**
     * Calcule les statistiques de visites depuis la base de données
     */
    public function calculateStatistics(): void
    {
        try {
            $today = date('Y-m-d');
            $month = date('Y-m');
            $week_start = date('Y-m-d', strtotime('monday this week'));

            // Visites aujourd'hui
            $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM statistiques_visites WHERE DATE(date_visite) = ?");
            $stmt->execute([$today]);
            $this->todayVisits = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            // Visites cette semaine
            $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM statistiques_visites WHERE date_visite >= ?");
            $stmt->execute([$week_start]);
            $this->weekVisits = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            // Visites ce mois
            $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM statistiques_visites WHERE DATE_FORMAT(date_visite, '%Y-%m') = ?");
            $stmt->execute([$month]);
            $this->monthVisits = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        } catch (PDOException) {
            // En PHP 8, il n'est plus obligatoire de capturer la variable d'exception si elle n'est pas utilisée
            $this->todayVisits = 0;
            $this->weekVisits = 0;
            $this->monthVisits = 0;
        }
    }

    /**
     * Affiche le message de bienvenue sécurisé
     */
    public function displayWelcomeMessage(): void
    {
        echo '<h1>Bonjour ' . htmlspecialchars(ucfirst($this->userFirstName), ENT_QUOTES, 'UTF-8') . '</h1>';
    }

    /**
     * Affiche le menu avec ses styles CSS d'origine inchangés
     */
    public function displayMenu(): void
    {
        echo '<div id="menu_btn"><ul>' . implode('', $this->links) . '</ul></div>';
    }

    /**
     * Affiche le bloc de statistiques si l'utilisateur possède le rôle superAdmin
     */
    public function displayStatistics(): void
    {
        if ($this->role === 'superAdmin') {
            echo '<div style="display: flex; justify-content: center; gap: 30px; margin-top: 50px; color: grey; font-style: italic;">';
            echo '<a href="./stats.php" style="color: inherit; text-decoration: none;">Nbres de visites mois : ' . $this->monthVisits . '</a>';
            echo '</div>';
        }
    }

    /**
     * Orchestre le traitement et le rendu de la page d'accueil
     */
    public function handleHomePage(): void
    {
        $this->displayWelcomeMessage();
        
        $alertAttr = '';
        if (isset($_GET['unauthorized'])) {
            $msg = htmlspecialchars(
                "Accès refusé : vous ne disposez pas des privilèges nécessaires pour accéder à cette page.",
                ENT_QUOTES,
                'UTF-8'
            );
            $alertAttr = ' data-alert="' . $msg . '"';
        }

        echo '<main' . $alertAttr . '>'; 
        $this->generateMenuLinks();
        $this->displayMenu();
        $this->calculateStatistics();
        $this->displayStatistics();
        echo '</main>';
    }

    // Getters stricts
    public function getUserFirstName(): string { return $this->userFirstName; }
    public function getRole(): string { return $this->role; }
    public function getLinks(): array { return $this->links; }
    public function getTodayVisits(): int { return $this->todayVisits; }
    public function getWeekVisits(): int { return $this->weekVisits; }
    public function getMonthVisits(): int { return $this->monthVisits; }
}
?>