<?php

declare(strict_types=1);

// Vérification des fichiers requis
if (!file_exists('html_partials/header.php')) {
    echo 'Erreur: Le fichier header.php est manquant.';
    exit;
}

if (!file_exists('html_partials/footer.php')) {
    echo 'Erreur: Le fichier footer.php est manquant.';
    exit;
}

require_once 'admin/pages/connect_ddb.php';

define('CLASSPAGES_PATH', 'admin/pages/classPages/competition/');
require_once CLASSPAGES_PATH . 'ChampionnatManager.php';
require_once CLASSPAGES_PATH . 'EquipeManager.php';
require_once CLASSPAGES_PATH . 'RencontreManager.php';

/**
 * Prépare et expose les données nécessaires à l'affichage du championnat.
 */
final class ChampionnatPage
{
    private const POULES_VALIDES = ['pouleA', 'pouleB'];

    public readonly string $poule;
    public readonly array $classement;
    public readonly array $journeesAller;
    public readonly array $journeesRetour;
    public readonly array $rencontres;

    public function __construct(PDO $conn)
    {
        $this->poule = $this->resoudrePoule($_GET['poule'] ?? '');

        $championnatManager  = new ChampionnatManager($conn);
        $rencontreManager    = new RencontreManager($conn);

        $this->classement = $championnatManager->calculerClassement($this->poule);
        $this->rencontres = $rencontreManager->getRencontresByPoule($this->poule);

        [$this->journeesAller, $this->journeesRetour] = $this->partitionnerJournees(
            $rencontreManager->getJourneesByPoule($this->poule)
        );
    }

    private function resoudrePoule(string $poule): string
    {
        return in_array($poule, self::POULES_VALIDES, true) ? $poule : 'pouleA';
    }

    /** @return array{0: array, 1: array} */
    private function partitionnerJournees(array $journees): array
    {
        $aller  = [];
        $retour = [];

        foreach ($journees as $journee) {
            if (($journee['type_journee'] ?? '') === 'retour') {
                $retour[] = $journee;
            } else {
                $aller[] = $journee;
            }
        }

        return [$aller, $retour];
    }
}

/**
 * Utilitaires d'affichage pour la vue championnat.
 */
final class ChampionnatView
{
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function score(int|string|null $score): string
    {
        return $score !== null ? (string)$score : '';
    }

    public static function equipe(?string $nom): string
    {
        return $nom !== null ? ucfirst(self::e($nom)) : '';
    }

    public static function date(string $date, string $format): string
    {
        return date($format, strtotime($date));
    }

    /** @param array $rencontres */
    public static function filtrerParJournee(array $rencontres, int $journeeId): array
    {
        return array_values(array_filter($rencontres, fn($m) => (int)$m['journee_id'] === $journeeId));
    }
}

// --- Initialisation ---
$page = new ChampionnatPage($conn);

include_once 'html_partials/header.php';

?>

<title>Championnat - Affichage</title>
<!-- <link rel="stylesheet" href="css/style.css"> -->
<link rel="stylesheet" href="admin/css/style_championnat.css">

<main>
    <div id="align-center">
        <p><img src="./img/logo-ufolep.jpg" alt="Logo_Ufolep" class="ufolep"></p>
        <div class="title-bar-wrapper">
            <h2 class="title-bar">CHAMPIONNAT</h2>
            <div class="poule-select">
                <form method="get" action="" id="select_poule">
                    <label for="poule">&nbsp;</label>
                    <select name="poule" id="poule">
                        <option value="pouleA" <?= $page->poule === 'pouleA' ? 'selected' : '' ?>>Poule A</option>
                        <option value="pouleB" <?= $page->poule === 'pouleB' ? 'selected' : '' ?>>Poule B</option>
                    </select>
                </form>
            </div>
        </div>

        <!-- Tableau de classement -->
        <table class="classement">
            <thead>
                <tr>
                    <th>Nom équipe</th>
                    <th>Points</th>
                    <th>Journées</th>
                    <th>Victoires</th>
                    <th>Défaites</th>
                    <th>Nul</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($page->classement as $nomEquipe => $stats): ?>
                <tr>
                    <td><?= ChampionnatView::e($nomEquipe) ?></td>
                    <td><?= $stats['points'] ?></td>
                    <td><?= $stats['journees'] ?></td>
                    <td><?= $stats['victoires'] ?></td>
                    <td><?= $stats['defaites'] ?></td>
                    <td><?= $stats['nuls'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="title-bar">CALENDRIER</div>

        <!-- Affichage des journées -->
        <?php if (!empty($page->journeesAller) || !empty($page->journeesRetour)): ?>
        <div class="deux-colonnes-calendrier" style="margin: 20px auto 0;">

            <!-- Colonne Aller -->
            <div class="colonne-aller">
                <?php foreach ($page->journeesAller as $journee): ?>
                <?php $matches = ChampionnatView::filtrerParJournee($page->rencontres, (int)$journee['id']); ?>
                <div class="journee-wrapper">
                    <table class="journee-table">
                        <thead>
                            <tr>
                                <th colspan="4">
                                    <?= ChampionnatView::e($journee['journee_id']) ?>
                                    (<?= ChampionnatView::date($journee['dateDebut'], 'd/m') ?> au
                                    <?= ChampionnatView::date($journee['dateFin'], 'd/m') ?>)
                                </th>
                            </tr>
                            <tr>
                                <th>Équipe</th>
                                <th>Score</th>
                                <th>Score</th>
                                <th>Équipe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $match): ?>
                            <tr class="tabJourn">
                                <td><?= ChampionnatView::equipe($match['equipe1']) ?></td>
                                <td><?= ChampionnatView::score($match['score1']) ?></td>
                                <td><?= ChampionnatView::score($match['score2']) ?></td>
                                <td><?= ChampionnatView::equipe($match['equipe2']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Colonne Retour -->
            <div class="colonne-retour">
                <?php foreach ($page->journeesRetour as $journee): ?>
                <?php $matches = ChampionnatView::filtrerParJournee($page->rencontres, (int)$journee['id']); ?>
                <div class="journee-wrapper">
                    <table class="journee-table">
                        <thead>
                            <tr>
                                <th colspan="4">
                                    <?= ChampionnatView::e($journee['journee_id']) ?>
                                    (<?= ChampionnatView::date($journee['dateDebut'], 'd/m') ?> au
                                    <?= ChampionnatView::date($journee['dateFin'], 'd/m') ?>)
                                </th>
                            </tr>
                            <tr>
                                <th>Équipe</th>
                                <th>Score</th>
                                <th>Score</th>
                                <th>Équipe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $match): ?>
                            <tr class="tabJourn">
                                <td><?= ChampionnatView::equipe($match['equipe1']) ?></td>
                                <td><?= ChampionnatView::score($match['score1']) ?></td>
                                <td><?= ChampionnatView::score($match['score2']) ?></td>
                                <td><?= ChampionnatView::equipe($match['equipe2']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php else: ?>
        <p style="text-align: center;">Aucune journée n'a été programmée pour le moment.</p>
        <?php endif; ?>

    </div>
</main>

<?php include_once 'html_partials/footer.php'; ?>