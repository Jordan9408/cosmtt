<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/includes/security.php';

// Seul un superAdmin connecté peut initialiser/créer les tables
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'superAdmin') {
    http_response_code(403);
    die('Accès interdit : privilèges superAdmin requis.');
}

require_once 'connect_ddb.php';
require_once dirname(__DIR__, 2) . '/include/IpPseudonymizer.php';
require_once dirname(__DIR__) . '/includes/DatabaseInitializer.php';

// =========================================================================
// POINT D'ENTRÉE DU SCRIPT AVEC CONTRÔLE CSRF STRICT & MÉTHODE POST
// =========================================================================

// Vérification de la disponibilité de la connexion PDO globale fournie par connect_ddb.php
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    die('Erreur critique : la connexion à la base de données est introuvable.');
}

$statusMessage = null;
$statusType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$submittedToken)) {
        http_response_code(403);
        die('Erreur de sécurité : Jeton CSRF invalide ou expiré.');
    }

    try {
        (new DatabaseInitializer($conn))->initializeAll();
        $conn->exec('ALTER TABLE `statistiques_visites` MODIFY `ip` VARCHAR(64) NOT NULL');
        IpPseudonymizer::migrateStoredIps($conn);
    } catch (PDOException $e) {
        error_log('Erreur critique d\'initialisation SQL : ' . $e->getMessage());
        http_response_code(500);
        die('Une erreur est survenue lors de la configuration de la base de données. Consultez les logs.');
    }

    $statusMessage = '✅ Toutes les tables ont été vérifiées et initialisées avec succès !';
    $statusType = 'success';
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration BDD — COSMTT</title>
    <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
    <style>
        .admin-box {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            font-family: sans-serif;
        }
        .admin-box h1 {
            font-size: 1.4rem;
            margin-bottom: 20px;
            color: #333;
        }
        .admin-box p {
            color: #555;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .btn-submit {
            display: inline-block;
            background: #1976d2;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-submit:hover {
            background: #1565c0;
        }
        .btn-back {
            display: inline-block;
            margin-left: 15px;
            color: #666;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .btn-back:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-box">
        <h1>Initialisation & Vérification des Tables</h1>

        <?php if ($statusMessage): ?>
            <div class="alert alert-<?= htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <p>
            Cette opération vérifie la présence des tables nécessaires (statistiques, utilisateurs, équipes, rencontres, classements) 
            et applique les éventuelles migrations de schéma de manière sécurisée.
        </p>

        <form method="POST" action="create_tab.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-submit">Lancer l'initialisation / vérification</button>
            <a href="home.php" class="btn-back">Retour au panneau d'administration</a>
        </form>
    </div>
</body>
</html>