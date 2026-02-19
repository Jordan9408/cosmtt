<?php

// Inclusion des fichiers nécessaires pour la connexion à la base de données et l'en-tête
require_once __DIR__ . './connect_ddb.php';
require_once __DIR__ . './parts/header.php';

// Inclusion du fichier de sécurité et de vérification de session
require_once dirname(__DIR__, 1) . '/includes/security.php';
require_once dirname(__DIR__, 1) . '/includes/session_check.php'; 


// Initialisation des variables pour les erreurs et les messages de succès
$errors = [];
$successMessage = '';


if (!isset($_SESSION['firstName'])) {
    header('Location: ../../connexion.php');
    exit; // N'oubliez pas d'arrêter l'exécution du script après une redirection
}

$userFirstName = $_SESSION['firstName'];
echo '<h1>Bonjour ' . ucfirst($userFirstName) . '</h1>';

$role = ''; // Initialisez la variable $role à une valeur par défaut

if ($_SESSION['role'] == 'superAdmin') {
    $role = 'superAdmin';
} elseif ($_SESSION['role'] == 'admin') {
    $role = 'admin';
}

// Définir des liens en fonction du rôle de l'utilisateur
$links = [];

if ($role === 'superAdmin') {
    $links[] = '<li><a href="./user/showUser.php">Liste des Admins</a></li>';
    $links[] = '<li><a href="./infosClub/addInfoscCub.php">Infos Club</a></li>';
}

if ($role == 'admin' || $role == 'superAdmin') {
    $links[] = '<li><a href="./article/addArticle.php">Article</a></li>';
    $links[] = '<li><a href="./adminPage/galeriePhotos/galerieShow.php">Galerie Photos</a></li>';
    $links[] = '<li><a href="./adminPage/championnat/homeChampionnat.php">Championnat</a></li>';
    $links[] = '<li><a href="./adminPage/joueurs/showClassement.php">Classements</a></li>';
}

echo '<style>
    #menu_btn ul {
        display: flex;
    justify-content: center;
    flex-wrap: wrap;
    padding-left: 0;
    margin-top: 20px;
    list-style: none;
    gap: 28px;
    }
</style>';
echo '<div id="menu_btn"><ul>' . implode('', $links) . '</ul></div>';

// Afficher les statistiques de visites
try {
    $today = date('Y-m-d');
    $month = date('Y-m');
    $week_start = date('Y-m-d', strtotime('monday this week'));

    // Visites aujourd'hui
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM statistiques_visites WHERE DATE(date_visite) = ?");
    $stmt->execute([$today]);
    $todayVisits = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Visites cette semaine
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM statistiques_visites WHERE date_visite >= ?");
    $stmt->execute([$week_start . ' 00:00:00']);
    $weekVisits = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Visites ce mois
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM statistiques_visites WHERE DATE_FORMAT(date_visite, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthVisits = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $todayVisits = 0;
    $weekVisits = 0;
    $monthVisits = 0;
}
if ($role === 'superAdmin') {
    echo '<div style="display: flex; justify-content: center; gap: 30px; margin-top: 50px; color: grey; font-style: italic;">';
    echo '<a href="./stats.php" style="color: inherit; text-decoration: none;">Nbres de visites mois : ' . $monthVisits . '</a>';
    echo '</div>';
}
?>
<?php
// Inclusion du footer
include_once dirname(__DIR__, 1) . '/html_partials/footer.php';
?>
