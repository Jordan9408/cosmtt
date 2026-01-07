<?php

// Inclusion des fichiers nécessaires
require_once 'admin/pages/connect_ddb.php';

// Appel des Classes
define('CLASSPAGES_PATH', 'admin/pages/classPages/competition/');
require_once CLASSPAGES_PATH . 'ChampionnatManager.php';
require_once CLASSPAGES_PATH . 'EquipeManager.php';
require_once CLASSPAGES_PATH . 'RencontreManager.php';

// Gestion de la sélection de la poule
$poule = $_GET['poule'] ?? 'pouleA';

if (!in_array($poule, ['pouleA', 'pouleB'])) {
    $poule = 'pouleA';
}

// Récupération des données
$championnatManager = new ChampionnatManager($conn);
$classement = $championnatManager->calculerClassement($poule);
$rencontreManager = new RencontreManager($conn);
$journees = $rencontreManager->getJourneesByPoule($poule);
$rencontres = $rencontreManager->getRencontresByPoule($poule);

// Séparation des journées "Aller" et "Retour"
$journeesAller = [];
$journeesRetour = [];
foreach ($journees as $journee) {
    if (isset($journee['type_journee']) && $journee['type_journee'] === 'retour') {
        $journeesRetour[] = $journee;
    } else {
        $journeesAller[] = $journee;
    }
}

/**
 * Fonction pour échapper le HTML
 */
function escapeHtml(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Championnat - Affichage</title>
    <link rel="stylesheet" href="admin/pages/adminPage/championnat/rencontres/champ.css">
    <style>
        /* Mise en page du calendrier en 2 colonnes */
        .deux-colonnes-calendrier {
            display: flex;
            justify-content: space-between;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 23em;
        }

        .colonne-aller,
        .colonne-retour {
            flex: 1;
            min-width: 45%;
        }

        .colonne-titre {
            text-align: center;
            background-color: #3f51b5;
            color: white;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        @media (max-width: 768px) {
            .deux-colonnes-calendrier {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>

<body>

    <div class="title-bar">CHAMPIONNAT</div>

    <!-- Sélection de la poule -->
    <div class="poule-select">
        <form method="get" action="">
            <label for="poule">&nbsp;</label>
            <select name="poule" id="poule" onchange="this.form.submit()">
                <option value="pouleA" <?= $poule === 'pouleA' ? 'selected' : '' ?>>Poule A</option>
                <option value="pouleB" <?= $poule === 'pouleB' ? 'selected' : '' ?>>Poule B</option>
            </select>
        </form>
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
            <?php foreach ($classement as $nomEquipe => $stats): ?>
                <tr>
                    <td><?= escapeHtml($nomEquipe) ?></td>
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
    <?php if (!empty($journeesAller) || !empty($journeesRetour)): ?>
        <div class="deux-colonnes-calendrier">

            <!-- Colonne Aller -->
            <div class="colonne-aller">
                <?php foreach ($journeesAller as $journee): ?>
                    <div class="journee-wrapper">
                        <table class="journee-table">
                            <thead>
                                <tr>
                                    <th colspan="4">
                                        <?= $journee['journee_id'] ?>
                                        (<?= date('d/m', strtotime($journee['dateDebut'])) ?> au
                                        <?= date('d/m', strtotime($journee['dateFin'])) ?>)
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
                                <?php
                                $currentJourneeId = $journee['id'];
                                $matchesForJournee = array_filter($rencontres, fn($match) => $match['journee_id'] == $currentJourneeId);

                                foreach ($matchesForJournee as $match):
                                ?>
                                    <tr class="tabJourn">
                                        <td><?= $match['equipe1'] ? ucfirst(escapeHtml($match['equipe1'])) : '' ?></td>
                                        <td><?= $match['score1'] !== null ? $match['score1'] : '' ?></td>
                                        <td><?= $match['score2'] !== null ? $match['score2'] : '' ?></td>
                                        <td><?= $match['equipe2'] ? ucfirst(escapeHtml($match['equipe2'])) : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Colonne Retour -->
            <div class="colonne-retour">
                <?php foreach ($journeesRetour as $journee): ?>
                    <div class="journee-wrapper">
                        <table class="journee-table">
                            <thead>
                                <tr>
                                    <th colspan="4">
                                        <?= $journee['journee_id'] ?>
                                        (<?= date('d/m', strtotime($journee['dateDebut'])) ?> au
                                        <?= date('d/m', strtotime($journee['dateFin'])) ?>)
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
                                <?php
                                $currentJourneeId = $journee['id'];
                                $matchesForJournee = array_filter($rencontres, fn($match) => $match['journee_id'] == $currentJourneeId);

                                foreach ($matchesForJournee as $match):
                                ?>
                                    <tr class="tabJourn">
                                        <td><?= $match['equipe1'] ? ucfirst(escapeHtml($match['equipe1'])) : '' ?></td>
                                        <td><?= $match['score1'] !== null ? $match['score1'] : '' ?></td>
                                        <td><?= $match['score2'] !== null ? $match['score2'] : '' ?></td>
                                        <td><?= $match['equipe2'] ? ucfirst(escapeHtml($match['equipe2'])) : '' ?></td>
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

</body>

</html>