<?php

use Admin\Includes\ErrorMessages;

/**
 * Vue pour l'affichage du classement
 */
final class ClassementView
{
    /**
     * Affiche la page complète.
     */
    public function render(ClassementViewData $data): void
    {
        $this->renderHeader();
        $this->renderTitleBar();
        $this->renderActionLinks($data);
        $this->renderPopup($data);
        $this->renderMainContent($data);
        $this->renderBaremeTable();
        $this->renderScripts($data);
        $this->renderFooter();
    }


    /**
     * Affiche l'en-tête HTML
     */
    private function renderHeader(): void
    {
        ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement des Joueurs</title>
    <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_joueurs.css">
</head>

<body>
    <?php
    }

    /**
     * Affiche la barre de titre
     */
    private function renderTitleBar(): void
    {
        ?>
    <div class="title-bar">CLASSEMENT DES JOUEURS</div>
    <?php
    }

    /**
     * Affiche les liens d'action
     */
    private function renderActionLinks(ClassementViewData $data): void
    {
        ?>
    <div class="lienPopup">
        <?php if ($data->isEditMode): ?>
        <a href="#" id="btnSaveClassement" class="lienShowPopup">Enregistrer</a>
        <a href="showClassement.php" class="lienShowPopup">Annuler</a>
        <?php else: ?>
        <?php if ($data->canEdit): ?>
        <a href="#" id="showPopup" class="lienShowPopup">Ajouter Joueur</a>
        <a href="?edit=1" class="lienShowPopup">Modifier</a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    }

    /**
     * Affiche le popup d'ajout
     */
    private function renderPopup(ClassementViewData $data): void
    {
        $mainAttr = $data->hasSuccess() ? ' data-success="' . htmlspecialchars($data->successMessage, ENT_QUOTES, 'UTF-8') . '"' : '';
        ?>
    <main id="clas_joueurs"<?= $mainAttr ?>>
        <div class="popup" id="teamPopup" style="display: none;">
            <div class="popup-card">
                <div class="popup-content">
                    <h3>Ajouter un Joueur</h3>
                    <form method="POST" action="" class="form-ajout">
                        <input type="hidden" name="csrf_token" value="<?= $data->csrfToken ?>">
                        <div class="form-group">
                            <label for="nom">Nom :</label>
                            <input type="text" id="nom" name="nom" class="input-popup" placeholder="Nom" required>
                            <label for="prenom">Prénom :</label>
                            <input type="text" id="prenom" name="prenom" class="input-popup" placeholder="Prénom"
                                required>
                            <label for="classement">Classement :</label>
                            <input type="number" id="classement" name="classement" class="input-popup"
                                placeholder="Classement" required min="5" max="20">
                            <label for="points_debut_saison">Points début saison :</label>
                            <input type="number" id="points_debut_saison" name="points_debut_saison" class="input-popup"
                                placeholder="Points début saison" required min="500">
                            <label for="points_mensuels">Points mensuels :</label>
                            <input type="number" id="points_mensuels" name="points_mensuels" class="input-popup"
                                placeholder="Points mensuels" required min="500">
                            <input type="submit" value="Ajouter" class="input-submit" name="saveJoueur">
                            <input type="button" id="cancelPopup" class="input-submit" value="Annuler">
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche le contenu principal (tableau)
     */
    private function renderMainContent(ClassementViewData $data): void
    {
        if ($data->isEditMode) {
            echo '<form id="editForm" method="POST" action="">';
            echo '<input type="hidden" name="saveJoueur" value="1">';
            echo '<input type="hidden" name="csrf_token" value="' . $data->csrfToken . '">';
        }

        $tableRenderer = new JoueurTableRenderer();
        $tableRenderer->render($data);

        if ($data->isEditMode) {
            echo '</form>';
        }

        echo '</main>';
    }

    /**
     * Affiche le tableau du barème
     */
    private function renderBaremeTable(): void
    {
        $baremeRenderer = new BaremeTableRenderer();
        $baremeRenderer->render();
    }

    /**
     * Affiche les scripts JavaScript
     */
    private function renderScripts(ClassementViewData $data): void
    {
        ?>
        <?php if ($data->hasErrors()): ?>
        <script type="application/json" id="classementErrorsData">
            <?= json_encode($data->errors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
        </script>
        <?php endif; ?>
        <script src="/cosmtt/admin/js/classement.js"></script>
        <?php
    }

    /**
     * Affiche le pied de page
     */
    private function renderFooter(): void
    {
        ?>
        <?php require_once dirname(__DIR__, 2) . '/parts/footer.php'; ?>
        <?php
    }
}