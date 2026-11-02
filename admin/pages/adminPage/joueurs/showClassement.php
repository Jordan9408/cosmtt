<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Résolution des racines de l'arborescence
// ---------------------------------------------------------------------------

/** Racine du module courant (deux niveaux au-dessus de ce fichier). */
const MODULE_ROOT  = __DIR__ . '/../..';

/** Racine de l'application (trois niveaux au-dessus de ce fichier). */
const APP_ROOT     = __DIR__ . '/../../..';

/** Répertoire contenant les classes du module classement/joueurs. */
const JOUEURS_DIR  = MODULE_ROOT . '/classPages/joueurs/';

// ---------------------------------------------------------------------------
// Dépendances obligatoires chargées explicitement
// ---------------------------------------------------------------------------

require_once APP_ROOT    . '/includes/security.php';
require_once APP_ROOT    . '/includes/session_check.php';
require_once MODULE_ROOT . '/connect_ddb.php';
require_once MODULE_ROOT . '/parts/header.php';
require_once JOUEURS_DIR . 'JoueurManager.php';

// ---------------------------------------------------------------------------
// Autoloader : résout les classes du module joueurs à la demande
// ---------------------------------------------------------------------------

spl_autoload_register(static function (string $className): void {
    $file = JOUEURS_DIR . $className . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

// ---------------------------------------------------------------------------
// Point d'entrée : délègue intégralement au contrôleur
// ---------------------------------------------------------------------------

$csrfToken = $_SESSION['csrf_token'] ?? '';
(new ClassementController($conn, $role, $csrfToken))->handleRequest();

// ---------------------------------------------------------------------------
// Pied de page commun
// ---------------------------------------------------------------------------

require_once MODULE_ROOT . '/parts/footer.php';