<?php

// Fichier de sécurité et d'initialisation de session

// 1. Configuration des en-têtes de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// 2. Vérification de l'authentification (doit être fait après l'inclusion du connect_ddb.php
// si celui-ci démarre la session, sinon démarrez la session ici)

// require_once __DIR__ . '/../../../connect_ddb.php'; // Ce fichier doit être géré par l'application appelante
// require_once __DIR__ . '/../../../parts/header.php'; // Ce fichier doit être géré par l'application appelante

// // Début de session si non démarrée, nécessaire pour $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// 3. Vérification de l'authentification (supposant que $_SESSION['user_id'] est initialisé ailleurs)
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Accès non autorisé');
}

// 4. Génération d'un token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 5. Récupération et standardisation du rôle de l'utilisateur
$role = $_SESSION['role'] ?? 'guest';
if (is_array($role)) {
    if (in_array('superAdmin', $role)) {
        $role = 'superAdmin';
    } else {
        $role = $role[0] ?? 'guest';
    }
}
// Le $role est maintenant prêt à être utilisé.