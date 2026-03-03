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
    echo '<script>alert("Vous n\'avez pas les autorisations pour accéder cette page. \nMerci de vous connecter."); window.location.href = "/cosmtt/connexion.php";</script>';
    exit;
}

// Optionnel : Vérification de l'inactivité
$inactiveLimit = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactiveLimit)) {
    session_unset();
    session_destroy();
    echo '<script>alert("Votre session a expiré en raison d\'inactivité. \nVeuillez vous reconnecter."); window.location.href = "/cosmtt/connexion.php";</script>';
    exit;
}
$_SESSION['last_activity'] = time(); // Met à jour le timestamp de la dernière activité

// Optionnel : Vérification du rôle de l'utilisateur
if (isset($_SESSION['role']) && !in_array($_SESSION['role'], ['admin', 'superAdmin'])) {
    echo '<script>alert("Vous n\'avez pas les autorisations pour accéder cette page."); window.location.href = "/cosmtt/connexion.php";</script>';
    exit;
} elseif (!isset($_SESSION['role'])) {
    // Si le rôle n'est pas défini, rediriger vers la page de connexion
    echo '<script>alert("Rôle utilisateur non défini. \nVeuillez vous connecter."); window.location.href = "/cosmtt/connexion.php";</script>';
    exit;
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