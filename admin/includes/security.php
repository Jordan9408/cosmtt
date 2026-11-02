<?php

// Fichier de sécurité et d'initialisation de session

// 1. Configuration des en-têtes de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Génération d'un nonce unique pour cette requête
$cspNonce = bin2hex(random_bytes(16));

// Content Security Policy stricte
// header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$cspNonce'; style-src 'self' 'nonce-$cspNonce'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");



// 2. Vérification de l'authentification (doit être fait après l'inclusion du connect_ddb.php
// si celui-ci démarre la session, sinon démarrez la session ici)

// require_once __DIR__ . '/../../../connect_ddb.php'; // Ce fichier doit être géré par l'application appelante
// require_once __DIR__ . '/../../../parts/header.php'; // Ce fichier doit être géré par l'application appelante

// // Début de session si non démarrée, nécessaire pour $_SESSION
require_once dirname(__DIR__, 2) . '/include/session_boot.php';


// 3. Vérification de l'authentification canonique
// On garde une seule clé de contrôle d'authentification pour éviter l'incohérence.
$baseConnexionUrl = ($appPath ?? '/cosmtt/') . 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Merci de vous connecter pour accéder à cette page.";
    header('Location: ' . $baseConnexionUrl);
    exit;
}

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superAdmin'], true)) {
    header('Location: ' . $baseConnexionUrl . '?unauthorized=1');
    exit;
}

// Optionnel : Vérification de l'inactivité
$inactiveLimit = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactiveLimit)) {
    session_unset();
    session_destroy();
    header('Location: /cosmtt/connexion.php?expired=1');
    exit;
}
$_SESSION['last_activity'] = time(); // Met à jour le timestamp de la dernière activité

// 4. Génération et régénération périodique d'un token CSRF
// Le token est régénéré lors de la connexion (dans Auth::login())
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 5. Récupération et standardisation du rôle de l'utilisateur
$role = $_SESSION['role'] ?? 'guest';
if (is_array($role)) {
    if (in_array('superAdmin', $role, true)) {
        $role = 'superAdmin';
    } else {
        $role = $role[0] ?? 'guest';
    }
}
// Le $role est maintenant prêt à être utilisé.

// 6. Contrôle d'accès basé sur les rôles requis ($requiredRoles) [H-01]
if (isset($requiredRoles) && is_array($requiredRoles) && !empty($requiredRoles)) {
    $userRoles = is_array($_SESSION['role']) ? $_SESSION['role'] : [$role];
    $isAuthorized = false;
    foreach ($requiredRoles as $reqRole) {
        if (in_array($reqRole, $userRoles, true) || $role === $reqRole) {
            $isAuthorized = true;
            break;
        }
    }

    if (!$isAuthorized) {
        error_log(sprintf(
            '[SECURITY][RBAC] Accès non autorisé refusé : utilisateur ID %s (rôle: %s) vers %s (rôles requis: %s)',
            (string)($_SESSION['user_id'] ?? 'inconnu'),
            (string)$role,
            $_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'inconnu'),
            implode(', ', $requiredRoles)
        ));

        http_response_code(403);
        header('Location: /cosmtt/admin/pages/home.php?unauthorized=1');
        exit;
    }
}