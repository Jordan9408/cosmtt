<?php
declare(strict_types=1);

// 1. Inclusions globales des dépendances existantes
// Définition des rôles autorisés (accès superAdmin strict - [SEC-01])
$requiredRoles = ['superAdmin'];
require_once dirname(__DIR__, 2) . '/includes/security.php';
require_once dirname(__DIR__, 2) . '/includes/session_check.php';

// Défense en profondeur : vérification stricte du rôle superAdmin
if (($role ?? '') !== 'superAdmin') {
    http_response_code(403);
    header('Location: /cosmtt/admin/pages/home.php?unauthorized=1');
    exit;
}
require_once dirname(__DIR__) . '/connect_ddb.php';
require_once dirname(__DIR__) . '/class/User.php';
require_once dirname(__DIR__) . '/classPages/club/Validations.php';
require_once dirname(__DIR__, 2) . '/includes/ErrorMessages.php';

// 2. Inclusion de la nouvelle architecture MVC pour l'ajout
require_once dirname(__DIR__) . '/classPages/users/AddUserManager.php';
require_once dirname(__DIR__) . '/classPages/users/AddUserView.php';
require_once dirname(__DIR__) . '/classPages/users/AddUserController.php';

use Admin\ClassPages\Users\AddUserManager;
use Admin\ClassPages\Users\AddUserView;
use Admin\ClassPages\Users\AddUserController;

// 3. Initialisation et injection des dépendances
$userModel  = new User($conn);
$manager    = new AddUserManager($userModel);
$view       = new AddUserView();
$controller = new AddUserController($manager, $view, $role ?? '');

// 4. Exécution de la page
$controller->handleRequest();