<?php

require_once __DIR__ . '/../include/session_boot.php';

require_once('./config/config.php');
require_once('./admin/pages/connect_ddb.php');
require_once __DIR__ . '/../include/IpPseudonymizer.php';

require_once __DIR__ . '/../include/TrafficTracker.php';

final class VisitorTracker
{
    public function __construct(private PDO $conn)
    {
    }

    public function track(string $ip): void
    {
        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO visitors (ip_address, visit_date) VALUES (?, ?)'
            );
            $stmt->execute([$ip, date('Y-m-d H:i:s')]);
        } catch (PDOException $e) {
            // Consignez les erreurs si nécessaire
        }
    }
}

$rawIp = $_SERVER['REMOTE_ADDR'] ?? null;
$ip = IpPseudonymizer::hash($rawIp);

// Tracking du trafic global (table statistiques_visites pour Rapport de Trafic)
// On enregistre une visite par heure et par session pour éviter de surcharger à chaque rafraîchissement
$now = time();
$lastTracked = (int) ($_SESSION['last_visit_tracked'] ?? 0);
if (($now - $lastTracked) >= 3600) {
    (new TrafficTracker($conn))->logVisit();
    (new VisitorTracker($conn))->track($ip);
    $_SESSION['last_visit_tracked'] = $now;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COSMVTT</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/animation.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nanum+Brush+Script&family=Raleway:wght@800&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Nanum+Brush+Script&family=Open+Sans&family=Raleway:wght@800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
        integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <div class="loader"></div>
    <header>
        <nav>
            <div class="toggle">
                <i class="fas fa-bars ouvrir"></i>
                <!-- <i class="fas fa-times fermer"></i> -->
            </div>
            <ul id="menu">
                <li>
                    <a href="./index.php">HOME</a>
                </li>
                <li>
                    <a href="./info_club.php">INFO CLUB</a>
                </li>
                <li>
                    <a href="./galeries_photos.php">GALERIE</a>
                </li>
                <li>
                    <a href="#" class="sousMenu">CHAMPIONNAT</a>
                    <ul class="sousmenu">
                        <li><a href="./championnat.php">CHAMPIONNAT</a></li>
                        <li><a href="./classement.php">CLASSEMENT</a></li>
                        <li><a href="./criteriumArticles.php">CRITERIUM</a></li>
                    </ul>
                </li>
                <li>
                    <a href="./index.php#contact">CONTACT</a>
                </li>
                <li>
                    <a href="./connexion.php">CONNEXION</a>
                </li>
            </ul>

        </nav>
        <a href="./index.php"><img src="./img/logo_COSM.webp" alt="Logo COSMVTT" class="logo-cosmtt"></a>


    </header>