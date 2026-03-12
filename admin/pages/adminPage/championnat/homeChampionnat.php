<?php
session_start();
// Incluez le fichier d'en-tête si nécessaire
require_once dirname(__DIR__, 2) . '/connect_ddb.php';
require_once dirname(__DIR__, 2) . '/parts/header.php';

if (!isset($_SESSION['firstName'])) {
    header('Location: ../../connexion.php');
    exit; // N'oubliez pas d'arrêter l'exécution du script après une redirection
}

$userFirstName = $_SESSION['firstName'];
echo '<h1>Bonjour ' . ucfirst($userFirstName) . '</h1>';
echo '<main>'; 

echo '<div class="flex-container"">';

$role = ''; // Initialisez la variable $role à une valeur par défaut

if ($_SESSION['role'] == 'superAdmin') {
    $role = 'superAdmin';
} elseif ($_SESSION['role'] == 'admin') {
    $role = 'admin';
}

// Définir des liens en fonction du rôle de l'utilisateur
$links = [];

if ($role == 'admin' || $role == 'superAdmin') {
    $links[] = '<li><a href="./equipes/showEquipe.php">Les Equipes</a></li>';
    $links[] = '<li><a href="./rencontres/showChampionnat.php">Championnat</a></li>';
}
echo '<style>
    .flex-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: start;
    }

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
echo '<div id="menu_btn"><ul>' . implode('', $links) . '</ul></div></div>';
?>

<?php
// Inclusion du footer
include_once dirname(__DIR__, 3) . '/html_partials/footer.php';
?>