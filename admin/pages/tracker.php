<?php
// Inclusion des fichiers nécessaires
require_once __DIR__ . '/connect_ddb.php';
require_once __DIR__ . '/parts/header.php';

// Inclusion du fichier de sécurité et de vérification de session
require_once dirname(__DIR__, 1) . '/includes/security.php';
require_once dirname(__DIR__, 1) . '/includes/session_check.php';

if (!isset($_SESSION['firstName'])) {
    header('Location: ../../connexion.php');
    exit;
}

$userFirstName = $_SESSION['firstName'];

// Fonction pour hasher l'IP
    function hashIP($ip) {
        return hash('sha256', $ip);
    }
// Détection Appareil
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$device = 'PC';
if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $user_agent)) $device = 'Tablette';
elseif (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $user_agent)) $device = 'Mobile';
elseif (preg_match('/(smart-tv|googletv|appletv|hbbtv|povw|netcast)/i', $user_agent)) $device = 'TV';

// Détection Navigateur
$browser = 'Autre';
if (preg_match('/Edge|Edg/i', $user_agent)) $browser = 'Edge';
elseif (preg_match('/Firefox/i', $user_agent)) $browser = 'Firefox';
elseif (preg_match('/Qwant/i', $user_agent)) $browser = 'Qwant';
elseif (preg_match('/Chrome/i', $user_agent)) $browser = 'Chrome';
elseif (preg_match('/Safari/i', $user_agent)) $browser = 'Safari';
elseif (preg_match('/Brave/i', $user_agent)) $browser = 'brave';

$ip = $_SERVER['REMOTE_ADDR'];

// INSERTION OU MISE À JOUR (si l'IP/Date existe déjà)
$sql = "INSERT INTO statistiques_visites (ip, date_visite, heure_visite, appareil, navigateur) 
        VALUES (:ip, CURDATE(), CURTIME(), :device, :browser)
        ON DUPLICATE KEY UPDATE 
        heure_visite = CURTIME(), 
        appareil = :device, 
        navigateur = :browser";

$stmt = $conn->prepare($sql);
$stmt->execute([
    ':ip' => $ip,
    ':device' => $device,
    ':browser' => $browser
]);
?>