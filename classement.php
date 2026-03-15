<?php
include('./html_partials/header.php');

// Inclusion des fichiers nécessaires
require_once __DIR__ . '/admin/pages/connect_ddb.php';
require_once __DIR__ . '/admin/pages/classPages/joueurs/JoueurManager.php';

// Autoloader pour les classes du module joueur
spl_autoload_register(function ($className) {
    $classFile = __DIR__ . '/admin/pages/classPages/joueurs/' . $className . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Récupération des données
$joueurManager = new JoueurManager($conn);
$joueurs = $joueurManager->getAllJoueurs();

// Préparation des données pour la vue
// On simule les données nécessaires pour le renderer, sans les fonctionnalités d'édition
$viewData = new ClassementViewData(
    $joueurs,
    false, // isEditMode
    false, // canEdit
    'guest', // role
    [],    // errors
    ''     // successMessage
);

?>

<main id="clas_joueurs">
    <h2 class="title-bar classement_joueurs">Classement des joueurs du club</h2>
    <?php
    // Rendu du tableau des joueurs
    $tableRenderer = new JoueurTableRenderer();
    $tableRenderer->render($viewData);
    ?>

    <!-- <h2 class="classement_joueurs">Barème de points</h2> -->
    <?php
    // Rendu du tableau de barème
    $baremeRenderer = new BaremeTableRenderer();
    $baremeRenderer->render();
    ?>
</main>

<?php include('./html_partials/footer.php'); ?>