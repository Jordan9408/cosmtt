<?php

/**
 * Vue pour l'affichage du classement
 */
class ClassementView
{
    /**
     * Affiche la page complète
     */
    public function render(ClassementViewData $data): void
    {
        $this->renderHeader();
        $this->renderTitleBar();
        $this->renderActionLinks($data);
        $this->renderPopup();
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
                <a href="javascript:void(0);" class="lienShowPopup" onclick="if(validateEditForm()) { document.getElementById('editForm').submit(); }">Enregistrer</a>
                <a href="showClassement.php" class="lienShowPopup">Annuler</a>
            <?php else: ?>
                <?php if ($data->canEdit): ?>
                    <a href="javascript:void(0);" id="showPopup" class="lienShowPopup">Ajouter Joueur</a>
                    <a href="?edit=1" class="lienShowPopup">Modifier</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Affiche le popup d'ajout
     */
    private function renderPopup(): void
    {
        ?>
        <main id="clas_joueurs">
            <div class="popup" id="teamPopup" style="display: none;">
                <div class="popup-card">
                    <div class="popup-content">
                        <h3>Ajouter un Joueur</h3>
                        <form method="POST" action="" class="form-ajout">
                            <div class="form-group">
                                <label for="nom">Nom :</label>
                                <input type="text" id="nom" name="nom" class="input-popup" placeholder="Nom" required>
                                <label for="prenom">Prénom :</label>
                                <input type="text" id="prenom" name="prenom" class="input-popup" placeholder="Prénom" required>
                                <label for="classement">Classement :</label>
                                <input type="number" id="classement" name="classement" class="input-popup" placeholder="Classement" required min="5" max="20">
                                <label for="points_debut_saison">Points début saison :</label>
                                <input type="number" id="points_debut_saison" name="points_debut_saison" class="input-popup" placeholder="Points début saison" required min="500">
                                <label for="points_mensuels">Points mensuels :</label>
                                <input type="number" id="points_mensuels" name="points_mensuels" class="input-popup" placeholder="Points mensuels" required min="500">
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
        <script src="/cosmtt/admin/js/app.js"></script>
        <script src="/cosmtt/admin/js/classement.js"></script>
        <?php if ($data->hasErrors()): ?>
            <script>
                const errorMessages = <?= json_encode($data->errors); ?>;
                document.addEventListener('DOMContentLoaded', function() {
                    errorMessages.forEach(msg => {
                        const div = document.createElement('div');
                        div.className = 'error-notification';
                        div.textContent = msg;
                        document.body.prepend(div);
                    });
                });
            </script>
        <?php endif; ?>
        <?php
    }

    /**
     * Affiche le pied de page
     */
    private function renderFooter(): void
    {
        ?>
        </body>
        </html>
        <?php
    }
}