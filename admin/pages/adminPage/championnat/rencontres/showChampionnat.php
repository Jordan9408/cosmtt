<?php

// Inclusion des fichiers nécessaires pour la connexion à la base de données et l'en-tête
require_once dirname(__DIR__, 3) . '/connect_ddb.php';
require_once dirname(__DIR__, 3) . '/parts/header.php';

// Inclusion du fichier de sécurité
require_once dirname(__DIR__, 4) . '/includes/security.php';

// Appel des Classes
define('CLASSPAGES_PATH', dirname(__DIR__, 3) . '/classPages/competition/');

require_once CLASSPAGES_PATH . 'JourneeValidator.php';
require_once CLASSPAGES_PATH . 'JourneeManager.php';
require_once CLASSPAGES_PATH . 'JourneeController.php';
require_once CLASSPAGES_PATH . 'EquipeManager.php';
require_once CLASSPAGES_PATH . 'RencontreManager.php';
require_once CLASSPAGES_PATH . 'ChampionnatManager.php';
require_once CLASSPAGES_PATH . 'ChampionnatViewController.php';

// Initialisation du contrôleur
$viewController = new ChampionnatViewController($conn, $role);

// Traitement des requêtes
$viewController->handleRequests();

// Chargement des données
$viewController->loadData();

// Récupération des données pour l'affichage
$poule = $viewController->getPoule();
$isEditMode = $viewController->isEditMode();
$errors = $viewController->getErrors();
$successMessage = $viewController->getSuccessMessage();
$classement = $viewController->getClassement();
$rencontres = $viewController->getRencontres();
$equipes = $viewController->getEquipes();
$postedScores = $viewController->getPostedScores();
$isOddNumberOfTeams = $viewController->isOddNumberOfTeams();

// Séparation des journées Aller/Retour
$journeesParType = $viewController->getJourneesByType();
$journeesAller = $journeesParType['aller'];
$journeesRetour = $journeesParType['retour'];

// Fonction d'échappement HTML
function escapeHtml(string $string): string
{
    return ChampionnatViewController::escapeHtml($string);
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Championnat</title>
    <link rel="stylesheet" href="/cosmtt/admin/css/style_championnat.css">
</head>

<body>

    <div class="title-bar">CHAMPIONNAT</div>

    <div class="poule-select">
        <form method="get" action="">
            <label for="poule">&nbsp;</label>
            <select name="poule" id="poule" onchange="this.form.submit()">
                <option value="pouleA" <?= $poule === 'pouleA' ? 'selected' : '' ?>>Poule A</option>
                <option value="pouleB" <?= $poule === 'pouleB' ? 'selected' : '' ?>>Poule B</option>
            </select>
        </form>
    </div>

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
    <div class="lienPopup">
        <?php if ($isEditMode): ?>
            <a href="javascript:void(0);" class="lienShowPopup" onclick="checkAndSubmit();">Enregistrer</a>
            <a href="?poule=<?= htmlspecialchars($poule) ?>" class="lienShowPopup">Annuler</a>
        <?php else: ?>
            <a href="javascript:void(0);" id="showPopup" class="lienShowPopup">Ajouter Journée</a>
            <?php if (in_array($role, ['admin', 'superAdmin'])): ?>
                <a href="?poule=<?= htmlspecialchars($poule) ?>&edit=1" class="lienShowPopup">Modifier Journée</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <main>
        <div class="popup" id="teamPopup" style="display: none;">
            <div class="popup-card">
                <div class="popup-content">
                    <h3>Journée</h3>
                    <form method="POST" action="" class="form-ajout">
                        <input type="hidden" name="poule" value="<?= htmlspecialchars($poule) ?>">
                        <div class="form-group">
                            <div class="numJournée">
                                <input type="text" name="journée" class="input-popup Journée" placeholder="Journée 1"
                                    required>
                                <input type="date" name="dateDebut" class="input-popup dateDebut" placeholder="dd/mm"
                                    required>
                                <input type="date" name="dateFin" class="input-popup dateFin" placeholder="dd/mm"
                                    required>
                            </div>
                            <div class="matchs">
                                <select name="equipe1[]" class="input-popup selectEquipes">
                                    <option value="">Domicile</option>
                                    <option value="Aucune équipe">Aucune équipe</option>
                                    <?php foreach ($equipes as $equipe): ?>
                                        <option value="<?= escapeHtml($equipe['NomEquipe']) ?>">
                                            <?= escapeHtml($equipe['NomEquipe']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="score1[]" class="input-popup score" min="0" max="14">
                                <input type="number" name="score2[]" class="input-popup score" min="0" max="14">
                                <select name="equipe2[]" class="input-popup selectEquipes">
                                    <option value="">Extérieur</option>
                                    <option value="Aucune équipe">Aucune équipe</option>
                                    <?php foreach ($equipes as $equipe): ?>
                                        <option value="<?= escapeHtml($equipe['NomEquipe']) ?>">
                                            <?= escapeHtml($equipe['NomEquipe']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="buttons-row">
                                <input type="button" class="ajoutRencontreBtn" value="+">
                                <input type="button" class="supRencontreBtn" value="-">
                            </div>
                            <div class="type-journee-select buttons-row">
                                <input type="hidden" name="type_journee" id="typeJourneeInput" value="aller">
                                <button type="button" id="typeAllerBtn" class="type-btn selected">Aller</button>
                                <button type="button" id="typeRetourBtn" class="type-btn">Retour</button>
                            </div>
                            <input type="submit"class="input-submit" name="send" value="Ajouter">
                            <input type="button" id="cancelPopup" class="input-submit" value="Annuler">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php
    // Démarrer le formulaire d'édition UNIQUEMENT si on est en mode édition
    if ($isEditMode): ?>
        <form id="editForm" method="POST" action="">
            <input type="hidden" name="poule" value="<?= htmlspecialchars($poule) ?>">
            <input type="hidden" name="saveEdits" value="1">
        <?php endif; ?>

        <?php if (!empty($journeesAller) || !empty($journeesRetour)): ?>
            <div class="deux-colonnes-calendrier">

                <div class="colonne-aller">
                    <?php foreach ($journeesAller as $journee): ?>
                        <div class="journee-wrapper">
                            <table class="journee-table">
                                <thead>
                                    <tr>
                                        <th colspan="4">
                                            <?= $journee['journee_id'] ?> (<?= date('d/m', strtotime($journee['dateDebut'])) ?>
                                            au <?= date('d/m', strtotime($journee['dateFin'])) ?>)
                                            <?php if ($isEditMode && $role === 'superAdmin'): ?>
                                                <a type="hidden" href="javascript:void(0);"
                                                    onclick="confirmerSuppression(<?= $journee['id'] ?>, '<?= $journee['journee_id'] ?>', '<?= urlencode($poule) ?>');"
                                                    class="delete-link" title="Supprimer la journée et ses matchs"><img
                                                        src="/cosmtt/img/icones/remove.png" alt="Supprimer"
                                                        class="delete-icons"></a>
                                            <?php endif; ?>
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

                                    foreach ($matchesForJournee as $match) {
                                        echo '<tr class="tabJourn">';

                                        if ($isEditMode) {
                                            // Logique pour le mode édition
                                            if ($role === 'superAdmin') {
                                                // Équipe 1 (Domicile) - Modifiable par superAdmin
                                                echo '<td><select name="equipe1[' . $match['id'] . ']" class="selectEquipes">';
                                                echo '<option value="">Domicile</option>';
                                                echo '<option value="Aucune équipe"' . ($match['equipe1'] === null ? ' selected' : '') . '>Aucune équipe</option>';
                                                foreach ($equipes as $equipe) {
                                                    $selected = ($match['equipe1'] === $equipe['NomEquipe']) ? ' selected' : '';
                                                    echo '<option value="' . escapeHtml($equipe['NomEquipe']) . '"' . $selected . '>' . escapeHtml($equipe['NomEquipe']) . '</option>';
                                                }
                                                echo '</select></td>';
                                            } else {
                                                // Équipe 1 (Domicile) - Non modifiable
                                                echo '<td>' . ($match['equipe1'] ? ucfirst(escapeHtml($match['equipe1'])) : '') . '</td>';
                                            }

                                            if (in_array($role, ['admin', 'superAdmin'])) {
                                                // Scores - Modifiables par admin ou superAdmin
                                                $score1Value = isset($postedScores['score1'][$match['id']]) ? $postedScores['score1'][$match['id']] : ($match['score1'] !== null ? $match['score1'] : '');
                                                $score2Value = isset($postedScores['score2'][$match['id']]) ? $postedScores['score2'][$match['id']] : ($match['score2'] !== null ? $match['score2'] : '');
                                                echo '<td><input type="number" name="score1[' . $match['id'] . ']" value="' . htmlspecialchars($score1Value) . '" min="0" max="14" class="score"></td>';
                                                echo '<td><input type="number" name="score2[' . $match['id'] . ']" value="' . htmlspecialchars($score2Value) . '" min="0" max="14" class="score"></td>';
                                            } else {
                                                // Scores - Non modifiables
                                                echo '<td>' . ($match['score1'] !== null ? $match['score1'] : '') . '</td>';
                                                echo '<td>' . ($match['score2'] !== null ? $match['score2'] : '') . '</td>';
                                            }

                                            if ($role === 'superAdmin') {
                                                // Équipe 2 (Extérieur) - Modifiable par superAdmin
                                                echo '<td><select name="equipe2[' . $match['id'] . ']" class="selectEquipes">';
                                                echo '<option value="">Extérieur</option>';
                                                echo '<option value="Aucune équipe"' . ($match['equipe2'] === null ? ' selected' : '') . '>Aucune équipe</option>';
                                                foreach ($equipes as $equipe) {
                                                    $selected = ($match['equipe2'] === $equipe['NomEquipe']) ? ' selected' : '';
                                                    echo '<option value="' . escapeHtml($equipe['NomEquipe']) . '"' . $selected . '>' . escapeHtml($equipe['NomEquipe']) . '</option>';
                                                }
                                                echo '</select></td>';
                                            } else {
                                                // Équipe 2 (Extérieur) - Non modifiable
                                                echo '<td>' . ($match['equipe2'] ? ucfirst(escapeHtml($match['equipe2'])) : '') . '</td>';
                                            }
                                        } else {
                                            // Logique pour le mode d'affichage 
                                            echo '<td>' . ($match['equipe1'] ? ucfirst(escapeHtml($match['equipe1'])) : '') . '</td>';
                                            echo '<td>' . ($match['score1'] !== null ? $match['score1'] : '') . '</td>';
                                            echo '<td>' . ($match['score2'] !== null ? $match['score2'] : '') . '</td>';
                                            echo '<td>' . ($match['equipe2'] ? ucfirst(escapeHtml($match['equipe2'])) : '') . '</td>';
                                        }
                                        echo '</tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="colonne-retour">
                    <?php foreach ($journeesRetour as $journee): ?>
                        <div class="journee-wrapper">
                            <table class="journee-table">
                                <thead>
                                    <tr>
                                        <th colspan="4">
                                            <?= $journee['journee_id'] ?> (<?= date('d/m', strtotime($journee['dateDebut'])) ?>
                                            au <?= date('d/m', strtotime($journee['dateFin'])) ?>)
                                            <?php if ($isEditMode && $role === 'superAdmin'): ?>
                                                <a href="javascript:void(0);"
                                                    onclick="confirmerSuppression(<?= $journee['id'] ?>, '<?= $journee['journee_id'] ?>', '<?= urlencode($poule) ?>');"
                                                    class="delete-link" title="Supprimer la journée et ses matchs"><img
                                                        src="/cosmtt/img/icones/remove.png" alt="Supprimer"
                                                        class="delete-icons"></a>
                                            <?php endif; ?>
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

                                    foreach ($matchesForJournee as $match) {
                                        echo '<tr class="tabJourn">';

                                        if ($isEditMode) {
                                            // Logique pour le mode édition
                                            if ($role === 'superAdmin') {
                                                // Équipe 1 (Domicile) - Modifiable par superAdmin
                                                echo '<td><select name="equipe1[' . $match['id'] . ']" class="selectEquipes">';
                                                echo '<option value="">Domicile</option>';
                                                echo '<option value="Aucune équipe"' . ($match['equipe1'] === null ? ' selected' : '') . '>Aucune équipe</option>';
                                                foreach ($equipes as $equipe) {
                                                    $selected = ($match['equipe1'] === $equipe['NomEquipe']) ? ' selected' : '';
                                                    echo '<option value="' . escapeHtml($equipe['NomEquipe']) . '"' . $selected . '>' . escapeHtml($equipe['NomEquipe']) . '</option>';
                                                }
                                                echo '</select></td>';
                                            } else {
                                                // Équipe 1 (Domicile) - Non modifiable
                                                echo '<td>' . ($match['equipe1'] ? ucfirst(escapeHtml($match['equipe1'])) : '') . '</td>';
                                            }

                                            if (in_array($role, ['admin', 'superAdmin'])) {
                                                // Scores - Modifiables par admin ou superAdmin
                                                $score1Value = isset($postedScores['score1'][$match['id']]) ? $postedScores['score1'][$match['id']] : ($match['score1'] !== null ? $match['score1'] : '');
                                                $score2Value = isset($postedScores['score2'][$match['id']]) ? $postedScores['score2'][$match['id']] : ($match['score2'] !== null ? $match['score2'] : '');
                                                echo '<td><input type="number" name="score1[' . $match['id'] . ']" value="' . htmlspecialchars($score1Value) . '" min="0" max="14" class="score"></td>';
                                                echo '<td><input type="number" name="score2[' . $match['id'] . ']" value="' . htmlspecialchars($score2Value) . '" min="0" max="14" class="score"></td>';
                                            } else {
                                                // Scores - Non modifiables
                                                echo '<td>' . ($match['score1'] !== null ? $match['score1'] : '') . '</td>';
                                                echo '<td>' . ($match['score2'] !== null ? $match['score2'] : '') . '</td>';
                                            }

                                            if ($role === 'superAdmin') {
                                                // Équipe 2 (Extérieur) - Modifiable par superAdmin
                                                echo '<td><select name="equipe2[' . $match['id'] . ']" class="selectEquipes">';
                                                echo '<option value="">Extérieur</option>';
                                                echo '<option value="Aucune équipe"' . ($match['equipe2'] === null ? ' selected' : '') . '>Aucune équipe</option>';
                                                foreach ($equipes as $equipe) {
                                                    $selected = ($match['equipe2'] === $equipe['NomEquipe']) ? ' selected' : '';
                                                    echo '<option value="' . escapeHtml($equipe['NomEquipe']) . '"' . $selected . '>' . escapeHtml($equipe['NomEquipe']) . '</option>';
                                                }
                                                echo '</select></td>';
                                            } else {
                                                // Équipe 2 (Extérieur) - Non modifiable
                                                echo '<td>' . ($match['equipe2'] ? ucfirst(escapeHtml($match['equipe2'])) : '') . '</td>';
                                            }
                                        } else {
                                            // Logique pour le mode d'affichage
                                            echo '<td>' . ($match['equipe1'] ? ucfirst(escapeHtml($match['equipe1'])) : '') . '</td>';
                                            echo '<td>' . ($match['score1'] !== null ? $match['score1'] : '') . '</td>';
                                            echo '<td>' . ($match['score2'] !== null ? $match['score2'] : '') . '</td>';
                                            echo '<td>' . ($match['equipe2'] ? ucfirst(escapeHtml($match['equipe2'])) : '') . '</td>';
                                        }
                                        echo '</tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // Fermer le formulaire d'édition UNIQUEMENT si on est en mode édition
        if ($isEditMode): ?>
        </form>
    <?php endif; ?>

    <?php if (empty($journeesAller) && empty($journeesRetour)): ?>
        <p style="text-align: center;">Aucune journée n'a été programmée pour le moment.</p>
    <?php endif; ?>
    <?php
        // Inclusion du footer
        include_once dirname(__DIR__, 4) . '/pages/parts/footer.php';
    ?>
    <script src="/cosmtt/admin/js/app.js"></script>
    <script src="/cosmtt/admin/js/championnat.js"></script>

    <script>
        // 1. Affichage des alertes avec le nouveau format groupé
        <?php if (!empty($errors)): ?>
            window.addEventListener('DOMContentLoaded', function() {
                const errors = <?= json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>;
                let alertMessage = '';
                
                console.log('=== DEBUG ERREURS ===');
                console.log('Structure des erreurs PHP:', errors);
                
                for (const journeeName in errors) {
                    // Ajoute un saut de ligne avant la nouvelle journée, sauf pour la première
                    if (alertMessage !== '') {
                        alertMessage += '\n';
                    }
                    alertMessage += `Dans la ${journeeName}:\n`;
                    errors[journeeName].forEach(error => {
                        alertMessage += `- ${error}\n`;
                    });
                }
                
                // Enlever le dernier '\n' pour un affichage plus propre
                if (alertMessage.endsWith('\n')) {
                    alertMessage = alertMessage.slice(0, -1);
                }

                console.log('Message d\'alerte final:\n', alertMessage);
                console.log('===================');

                alert(alertMessage);
            });
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            window.addEventListener('DOMContentLoaded', function() {
                alert(<?= json_encode($successMessage, JSON_UNESCAPED_UNICODE); ?>);
            });
        <?php endif; ?>

        // 2. Transmission des variables PHP vers JS
        window.isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
    </script>
</body>

</html>