<?php

// Inclusion des fichiers nécessaires
require_once dirname(__DIR__, 2) . '/connect_ddb.php';
require_once dirname(__DIR__, 2) . '/parts/header.php';
require_once dirname(__DIR__, 3) . '/includes/security.php';
require_once dirname(__DIR__, 3) . '/includes/session_check.php';
require_once dirname(__DIR__, 2) . '/classPages/joueurs/JoueurManager.php';

// Autoloader pour les nouvelles classes
spl_autoload_register(function ($className) {
    $classesDir = dirname(__DIR__,2) . '/classPages/joueurs/';
    $classFile = $classesDir . $className . '.php';
    
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Initialisation et exécution du contrôleur
$controller = new ClassementController($conn, $role);
$controller->handleRequest();

require_once dirname(__DIR__, 2) . '/parts/footer.php';