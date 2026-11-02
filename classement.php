<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// En-tête commun (HTML <head>, navigation publique, etc.)
// ---------------------------------------------------------------------------

include('./html_partials/header.php');

// ---------------------------------------------------------------------------
// Dépendances obligatoires
// ---------------------------------------------------------------------------

$adminPagesDir = __DIR__ . '/admin/pages/';
$joueursDir    = $adminPagesDir . 'classPages/joueurs/';

require_once $adminPagesDir . 'connect_ddb.php';
require_once $joueursDir . 'JoueurManager.php';

// ---------------------------------------------------------------------------
// Autoloader : résout les autres classes du module joueurs à la demande
// (ClassementViewData, JoueurTableRenderer, JoueurRow, BaremeTableRenderer...)
// ---------------------------------------------------------------------------

spl_autoload_register(static function (string $className) use ($joueursDir): void {
    $file = $joueursDir . $className . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

// ---------------------------------------------------------------------------
// Page publique : classement des joueurs (lecture seule)
// ---------------------------------------------------------------------------

/**
 * Page publique affichant le classement des joueurs du club.
 *
 * Contrairement à la version d'administration (showClassement.php), cette
 * page est strictement en lecture seule : pas d'édition, pas de CSRF, et un
 * rôle "guest" fixe pour tous les visiteurs.
 */
final class ClassementPublicPage
{
    private const string ROLE_GUEST = 'guest';

    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Affiche la section complète : tableau des joueurs puis barème de points.
     */
    public function render(): void
    {
        $viewData = $this->buildViewData();
        ?>
<section id="clas_joueurs">
    <h2 class="title-bar">Classement des joueurs du club</h2>
    <?php $this->renderJoueursTable($viewData); ?>

    <!-- <h2 class="classement_joueurs">Barème de points</h2> -->
    <?php $this->renderBaremeTable(); ?>
</section>
<?php
    }

    /**
     * Construit l'objet de transfert de données utilisé par les renderers.
     * Aucune fonctionnalité d'édition n'est exposée sur cette page publique.
     */
    private function buildViewData(): ClassementViewData
    {
        $joueurManager = new JoueurManager($this->conn);

        return new ClassementViewData(
            joueurs: $joueurManager->getAllJoueurs(),
            isEditMode: false,
            canEdit: false,
            role: self::ROLE_GUEST,
        );
    }

    /**
     * Affiche le tableau des joueurs.
     */
    private function renderJoueursTable(ClassementViewData $viewData): void
    {
        (new JoueurTableRenderer())->render($viewData);
    }

    /**
     * Affiche le tableau du barème de points.
     */
    private function renderBaremeTable(): void
    {
        (new BaremeTableRenderer())->render();
    }
}

// ---------------------------------------------------------------------------
// Point d'entrée
// ---------------------------------------------------------------------------

(new ClassementPublicPage($conn))->render();

include('./html_partials/footer.php');