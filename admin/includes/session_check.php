<?php
// session_check.php

// Vérifiez si une session est déjà démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification
// Si l'utilisateur n'est pas connecté (par exemple, si 'firstName' n'est pas dans la session)
if (!isset($_SESSION['firstName'])) {
    // Redirige vers la page de connexion
    header('Location: /cosmtt/connexion.php');
    exit; // Arrête l'exécution du script après la redirection
}

// Si l'utilisateur est connecté, définissez les variables courantes
$userFirstName = $_SESSION['firstName'];
$userRole = $_SESSION['role'] ?? ''; // Utilisez l'opérateur de coalescence nul pour une valeur par défaut

// Vous pouvez également définir la variable $role ici pour plus de clarté dans les fichiers clients
$role = '';
if ($userRole == 'superAdmin') {
    $role = 'superAdmin';
} elseif ($userRole == 'admin') {
    $role = 'admin';
}

// Les variables $userFirstName et $role sont maintenant disponibles dans le fichier qui inclut session_check.php