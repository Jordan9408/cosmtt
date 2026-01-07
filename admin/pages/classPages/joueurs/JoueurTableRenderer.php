<?php

/**
 * Renderer pour le tableau des joueurs
 */
class JoueurTableRenderer
{
    /**
     * Affiche le tableau complet
     */
    public function render(ClassementViewData $data): void
    {
        ?>
        <table id="tabjoueurs">
            <tbody>
                <?php $this->renderHeader($data); ?>
                <?php $this->renderRows($data); ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Affiche l'en-tête du tableau
     */
    private function renderHeader(ClassementViewData $data): void
    {
        ?>
        <tr>
            <th>NOM PRÉNOM</th>
            <th>CLASSEMENT</th>
            <th>POINTS<br>Début de saison</th>
            <th>POINTS MENSUELS</th>
            <th>EVOLUTION MENSUELLE</th>
            <?php if ($data->isEditMode && $data->isSuperAdmin()): ?>
                <th>SUPPRIMER</th>
            <?php endif; ?>
        </tr>
        <?php
    }

    /**
     * Affiche toutes les lignes
     */
    private function renderRows(ClassementViewData $data): void
    {
        $rowClass = 'tabcouleur1';
        
        foreach ($data->joueurs as $joueur) {
            $joueurRow = new JoueurRow($joueur, $data->isEditMode, $data->isSuperAdmin(), $rowClass);
            $joueurRow->render();
            
            $rowClass = ($rowClass === 'tabcouleur1') ? 'tabcouleur2' : 'tabcouleur1';
        }
    }
}