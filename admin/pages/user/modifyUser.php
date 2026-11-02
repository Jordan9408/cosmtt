<?php
// 1. Vérification de session et inclusions globales de l'application
require_once dirname(__DIR__, 2) . '/includes/session_check.php';
include_once dirname(__DIR__, 1) . '/parts/header.php';
include_once dirname(__DIR__, 3) . '/config/config.php';
include_once dirname(__DIR__, 1) . '/connect_ddb.php';

// 2. Correction des chemins pour les dépendances (Depuis admin/pages/user)
require_once dirname(__DIR__, 1) . '/class/User.php'; 
require_once dirname(__DIR__, 1) . '/classPages/club/Validations.php';
require_once dirname(__DIR__, 2) . '/includes/ErrorMessages.php';

// 3. Inclusion de la structure MVC propre aux utilisateurs
require_once dirname(__DIR__, 1) . '/classPages/users/ModifyUserManager.php';
require_once dirname(__DIR__, 1) . '/classPages/users/ModifyUserView.php';
require_once dirname(__DIR__, 1) . '/classPages/users/ModifyUserController.php';

use Admin\ClassPages\Users\ModifyUserManager;
use Admin\ClassPages\Users\ModifyUserView;
use Admin\ClassPages\Users\ModifyUserController;

// 4. Initialisation et injection des dépendances
$userModel  = new User($conn);
$manager    = new ModifyUserManager($userModel);
$view       = new ModifyUserView();
$controller = new ModifyUserController($manager, $view, $role ?? '', (int)($_SESSION['user_id'] ?? 0));

// 5. Exécution du contrôleur (qui gère la logique et l'affichage)
$controller->handleRequest();