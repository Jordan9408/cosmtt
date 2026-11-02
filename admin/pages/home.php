<?php
// Inclusion des contrôles de sécurité et de session en priorité
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session_check.php';

// Inclusion de la connexion DB et de l'en-tête
require_once __DIR__ . '/connect_ddb.php';

if ($role === 'superAdmin') {
	require_once dirname(__DIR__) . '/includes/DatabaseInitializer.php';

	try {
		(new DatabaseInitializer($conn))->initializeIfNeeded();
	} catch (Throwable $e) {
		error_log('[DatabaseInitializer] ' . $e->getMessage());
		http_response_code(500);
		die('Impossible de vérifier la structure de la base de données. Consultez les logs du serveur.');
	}
}

require_once __DIR__ . '/parts/header.php';
require_once __DIR__ . '/classPages/homeController.php';

// Instanciation du contrôleur (les variables de session sont lues automatiquement en interne si non spécifiées)
$controller = new homeController($conn, $userFirstName, $role);
$controller->handleHomePage();

// ... avant l'inclusion du footer.php ...
include_once __DIR__ . '/parts/footer.php';