<?php
// session_check.php

// Vérifiez si une session est déjà démarrée
require_once dirname(__DIR__, 2) . '/include/session_boot.php';

// Vérification de l'authentification canonique : un utilisateur connecté doit avoir un user_id valide.
if (!isset($_SESSION['user_id'])) {
    header('Location: /cosmtt/connexion.php');
    exit;
}

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superAdmin'], true)) {
    header('Location: /cosmtt/connexion.php');
    exit;
}

// Si l'utilisateur est connecté, définissez les variables courantes
$userFirstName = $_SESSION['firstName'] ?? '';
$userRole = $_SESSION['role'] ?? '';

$role = '';
if ($userRole === 'superAdmin') {
    $role = 'superAdmin';
} elseif ($userRole === 'admin') {
    $role = 'admin';
}

// Les variables $userFirstName et $role sont maintenant disponibles dans le fichier qui inclut session_check.php