<?php
// Inclusion du fichier de sécurité et de vérification de session
require_once dirname(__DIR__, 1) . '/includes/security.php';
require_once dirname(__DIR__, 1) . '/includes/session_check.php';

// Inclusion de la connexion DB et de l'en-tête
require_once __DIR__ . '/connect_ddb.php';
require_once dirname(__DIR__, 2) . '/include/TrafficTracker.php';
require_once __DIR__ . '/parts/header.php';

$userFirstName = $_SESSION['firstName'] ?? '';

// Exécution du tracking pour l'admin
$tracker = new TrafficTracker($conn);
$tracker->logVisit();
?>