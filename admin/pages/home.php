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
    $links[] = '<li><a href="./adminPage/galeriePhotos/showGalerie.php">Galerie Photos</a></li>';
    $links[] = '<li><a href="./adminPage/championnat/homeChampionnat.php">Championnat</a></li>';
    $links[] = '<li><a href="./adminPage/joueurs/showClassement.php">Classements</a></li>';
}

echo '<div id="menu_btn"><ul>' . implode('', $links) . '</ul></div>';
