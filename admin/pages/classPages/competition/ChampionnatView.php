<?php

declare(strict_types=1);

/**
 * Classe gérant uniquement le rendu HTML du championnat et du calendrier.
 */
class ChampionnatView
{
    /**
     * Échappe les chaînes de caractères pour le HTML.
     */
    private function escape(string $string): string
    {
        return ChampionnatViewController::escapeHtml($string);
    }

    /**
     * Génère les lignes de tableau pour une liste de matchs.
     *
     * @param array<int, array<string, mixed>> $matchesForJournee
     * @param array<int, array<string, mixed>> $equipes
     * @param array<string, array<int, mixed>> $postedScores
     */
    private function renderMatchRows(
        array $matchesForJournee, 
        array $equipes, 
        bool $isEditMode, 
        string $role, 
        array $postedScores, 
        string $journeeName
    ): void {
        foreach ($matchesForJournee as $match) {
            $matchId = (int)$match['id'];
            echo '<tr class="tabJourn" data-journee-name="' . $this->escape($journeeName) . '">';

            if ($isEditMode) {
                // --- Équipe 1 (Domicile) ---
                if ($role === 'superAdmin') {
                    $displayEquipe1 = $postedScores['equipe1'][$matchId] ?? $match['equipe1'];
                    echo '<td><select name="equipe1[' . $matchId . ']" class="selectEquipes">';
                    echo '<option value="">Domicile</option>';
                    echo '<option value="Aucune équipe"' . ($displayEquipe1 === null || $displayEquipe1 === 'Aucune équipe' ? ' selected' : '') . '>Aucune équipe</option>';
                    foreach ($equipes as $equipe) {
                        $selected = ($displayEquipe1 === $equipe['NomEquipe']) ? ' selected' : '';
                        echo '<option value="' . $this->escape($equipe['NomEquipe']) . '"' . $selected . '>' . $this->escape($equipe['NomEquipe']) . '</option>';
                    }
                    echo '</select></td>';
                } else {
                    echo '<td>' . ($match['equipe1'] ? ucfirst($this->escape($match['equipe1'])) : '') . '</td>';
                }

                // --- Scores ---
                if (in_array($role, ['admin', 'superAdmin'], true)) {
                    $score1Value = $postedScores['score1'][$matchId] ?? ($match['score1'] !== null ? $match['score1'] : '');
                    $score2Value = $postedScores['score2'][$matchId] ?? ($match['score2'] !== null ? $match['score2'] : '');
                    echo '<td><input type="number" name="score1[' . $matchId . ']" value="' . htmlspecialchars((string)$score1Value) . '" min="0" max="14" class="score"></td>';
                    echo '<td><input type="number" name="score2[' . $matchId . ']" value="' . htmlspecialchars((string)$score2Value) . '" min="0" max="14" class="score"></td>';
                } else {
                    echo '<td>' . ($match['score1'] !== null ? $match['score1'] : '') . '</td>';
                    echo '<td>' . ($match['score2'] !== null ? $match['score2'] : '') . '</td>';
                }

                // --- Équipe 2 (Extérieur) ---
                if ($role === 'superAdmin') {
                    $displayEquipe2 = $postedScores['equipe2'][$matchId] ?? $match['equipe2'];
                    echo '<td><select name="equipe2[' . $matchId . ']" class="selectEquipes">';
                    echo '<option value="">Extérieur</option>';
                    echo '<option value="Aucune équipe"' . ($displayEquipe2 === null || $displayEquipe2 === 'Aucune équipe' ? ' selected' : '') . '>Aucune équipe</option>';
                    foreach ($equipes as $equipe) {
                        $selected = ($displayEquipe2 === $equipe['NomEquipe']) ? ' selected' : '';
                        echo '<option value="' . $this->escape($equipe['NomEquipe']) . '"' . $selected . '>' . $this->escape($equipe['NomEquipe']) . '</option>';
                    }
                    echo '</select></td>';
                } else {
                    echo '<td>' . ($match['equipe2'] ? ucfirst($this->escape($match['equipe2'])) : '') . '</td>';
                }

            } else {
                // Mode lecture seule
                echo '<td>' . ($match['equipe1'] ? ucfirst($this->escape($match['equipe1'])) : '') . '</td>';
                echo '<td>' . ($match['score1'] !== null ? $match['score1'] : '') . '</td>';
                echo '<td>' . ($match['score2'] !== null ? $match['score2'] : '') . '</td>';
                echo '<td>' . ($match['equipe2'] ? ucfirst($this->escape($match['equipe2'])) : '') . '</td>';
            }

            echo '</tr>';
        }
    }

    /**
     * Rendu des colonnes du calendrier (Aller ou Retour).
     */
    private function renderColonneCalendrier(array $journees, array $rencontres, array $equipes, bool $isEditMode, string $role, array $postedScores, string $poule): void
    {
        foreach ($journees as $journee) {
            $dateDebut = date('d/m', strtotime($journee['dateDebut']));
            $dateFin = date('d/m', strtotime($journee['dateFin']));
            ?>
<div class="journee-wrapper">
    <table class="journee-table">
        <thead>
            <tr>
                <th colspan="4">
                    <?= htmlspecialchars((string)$journee['journee_id']) ?> (<?= $dateDebut ?> au <?= $dateFin ?>)
                    <?php if ($isEditMode && $role === 'superAdmin'): ?>
                    <a href="#"
                        class="delete-link delete-link-journee"
                        data-journee-id="<?= (int)$journee['id'] ?>"
                        data-journee-nom="<?= htmlspecialchars((string)$journee['journee_id'], ENT_QUOTES, 'UTF-8') ?>"
                        data-poule="<?= htmlspecialchars($poule, ENT_QUOTES, 'UTF-8') ?>"
                        data-csrf="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        title="Supprimer la journée et ses matchs">
                        <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" class="delete-icons">
                    </a>
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
                        $matchesForJournee = array_filter($rencontres, static fn($match): bool => $match['journee_id'] == $currentJourneeId);
                        $this->renderMatchRows($matchesForJournee, $equipes, $isEditMode, $role, $postedScores, (string)$journee['journee_id']);
                        ?>
        </tbody>
    </table>
</div>
<?php 
        }
    }

    /**
     * Rendu HTML global de la page.
     */
    public function render(array $data): void
    {
        // Déstructuration sécurisée des variables du tableau global
        $poule            = $data['poule'] ?? 'pouleA';
        $isEditMode       = (bool)($data['isEditMode'] ?? false);
        $errors           = $data['errors'] ?? [];
        $successMessage   = $data['successMessage'] ?? '';
        $classement       = $data['classement'] ?? [];
        $rencontres       = $data['rencontres'] ?? [];
        $equipes          = $data['equipes'] ?? [];
        $postedScores     = $data['postedScores'] ?? [];
        $journeesAller    = $data['journeesAller'] ?? [];
        $journeesRetour   = $data['journeesRetour'] ?? [];
        $role             = $data['role'] ?? 'user';
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
    <div class="title-bar-wrapper">
        <div class="title-bar">CHAMPIONNAT</div>

        <div class="poule-select">
            <form method="get" action="">
                <label for="poule">&nbsp;</label>
                <select name="poule" id="poule">
                    <option value="pouleA" <?= $poule === 'pouleA' ? 'selected' : '' ?>>Poule A</option>
                    <option value="pouleB" <?= $poule === 'pouleB' ? 'selected' : '' ?>>Poule B</option>
                </select>
            </form>
        </div>
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
                <td><?= $this->escape((string)$nomEquipe) ?></td>
                <td><?= (int)$stats['points'] ?></td>
                <td><?= (int)$stats['journees'] ?></td>
                <td><?= (int)$stats['victoires'] ?></td>
                <td><?= (int)$stats['defaites'] ?></td>
                <td><?= (int)$stats['nuls'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="title-bar">CALENDRIER</div>
    <div class="lienPopup">
        <?php if ($isEditMode): ?>
        <a href="#" id="btnSaveChampionnat" class="lienShowPopup">Enregistrer</a>
        <a href="?poule=<?= urlencode($poule) ?>" class="lienShowPopup">Annuler</a>
        <?php else: ?>
        <a href="#" id="showPopup" class="lienShowPopup">Ajouter Journée</a>
        <?php if (in_array($role, ['admin', 'superAdmin'], true)): ?>
        <a href="?poule=<?= urlencode($poule) ?>&edit=1" class="lienShowPopup">Modifier Journée</a>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <main>
        <div class="popup" id="teamPopup" style="display: none;">
            <div class="popup-card">
                <div class="popup-content">
                    <h3>Journée</h3>
                    <form method="POST" action="" class="form-ajout">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="poule" value="<?= htmlspecialchars($poule) ?>">
                        <input type="hidden" name="send" value="Ajouter">
                        <div class="form-group">
                            <div class="numJournée">
                                <input type="text" name="journée" class="input-popup Journée" placeholder="Journée 1"
                                    required>
                                <input type="date" name="dateDebut" class="input-popup dateDebut" required>
                                <input type="date" name="dateFin" class="input-popup dateFin" required>
                            </div>
                            <div class="matchs">
                                <select name="equipe1[]" class="input-popup selectEquipes">
                                    <option value="">Domicile</option>
                                    <option value="Aucune équipe">Aucune équipe</option>
                                    <?php foreach ($equipes as $equipe): ?>
                                    <option value="<?= $this->escape($equipe['NomEquipe']) ?>">
                                        <?= $this->escape($equipe['NomEquipe']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="score1[]" class="input-popup score" min="0" max="14">
                                <input type="number" name="score2[]" class="input-popup score" min="0" max="14">
                                <select name="equipe2[]" class="input-popup selectEquipes">
                                    <option value="">Extérieur</option>
                                    <option value="Aucune équipe">Aucune équipe</option>
                                    <?php foreach ($equipes as $equipe): ?>
                                    <option value="<?= $this->escape($equipe['NomEquipe']) ?>">
                                        <?= $this->escape($equipe['NomEquipe']) ?></option>
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
                            <input type="submit" class="input-submit" name="send" value="Ajouter">
                            <input type="button" id="cancelPopup" class="input-submit" value="Annuler">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php if ($isEditMode): ?>
    <form id="editForm" method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="poule" value="<?= htmlspecialchars($poule) ?>">
        <input type="hidden" name="saveEdits" value="1">
        <?php endif; ?>

        <?php if (!empty($journeesAller) || !empty($journeesRetour)): ?>
        <div class="deux-colonnes-calendrier">
            <div class="colonne-aller">
                <?php $this->renderColonneCalendrier($journeesAller, $rencontres, $equipes, $isEditMode, $role, $postedScores, $poule); ?>
            </div>

            <div class="colonne-retour">
                <?php $this->renderColonneCalendrier($journeesRetour, $rencontres, $equipes, $isEditMode, $role, $postedScores, $poule); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isEditMode): ?>
    </form>
    <?php endif; ?>

    <?php if (empty($journeesAller) && empty($journeesRetour)): ?>
    <p style="text-align: center;">Aucune journée n'a été programmée pour le moment.</p>
    <?php endif; ?>

    <?php require_once dirname(__DIR__, 2) . '/parts/footer.php'?>
    
    <script type="application/json" id="championnatData">
    <?= json_encode([
        'isEditMode' => (bool)$isEditMode,
        'errors' => !empty($errors) ? $errors : null,
        'posted' => !empty($_POST) ? $_POST : null,
        'successMessage' => !empty($successMessage) ? $successMessage : null,
        'journeesExistantes' => array_map(static function(array $j): array {
            return [
                'id'        => (int)$j['id'],
                'nom'       => (string)$j['journee_id'],
                'dateDebut' => (string)$j['dateDebut'],
                'dateFin'   => (string)$j['dateFin'],
                'poule'     => (string)$j['poule'],
                'type'      => (string)($j['type_journee'] ?? ''),
            ];
        }, array_merge($journeesAller, $journeesRetour)),
        'rencontresExistantes' => array_map(static function(array $r): array {
            return [
                'journee_id' => (int)$r['journee_id'],
                'equipe1'    => (string)($r['equipe1'] ?? ''),
                'equipe2'    => (string)($r['equipe2'] ?? ''),
            ];
        }, $rencontres)
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    </script>
    <script src="/cosmtt/admin/js/championnat.js"></script>
</body>

</html>
<?php
    }
}