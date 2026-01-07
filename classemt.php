<?php
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
    ?>