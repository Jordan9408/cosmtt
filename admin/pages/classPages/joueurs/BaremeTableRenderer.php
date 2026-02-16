<?php

/**
 * Renderer pour le tableau du barème de points
 */
class BaremeTableRenderer
{
    private array $baremeData = [
        ['ecart' => '0-24', 'vn' => '+6', 'dn' => '-5', 'va' => '+6', 'da' => '-5', 'class' => 'tabcouleur3'],
        ['ecart' => '25-49', 'vn' => '+5.5', 'dn' => '-4.5', 'va' => '+7', 'da' => '-6', 'class' => 'tabcouleur4'],
        ['ecart' => '50-99', 'vn' => '+5', 'dn' => '-4', 'va' => '+8', 'da' => '-7', 'class' => 'tabcouleur3'],
        ['ecart' => '100-149', 'vn' => '+4', 'dn' => '-3', 'va' => '+10', 'da' => '-8', 'class' => 'tabcouleur4'],
        ['ecart' => '150-199', 'vn' => '+3', 'dn' => '-2', 'va' => '+13', 'da' => '-10', 'class' => 'tabcouleur3'],
        ['ecart' => '200-299', 'vn' => '+2', 'dn' => '-1', 'va' => '+17', 'da' => '-12.5', 'class' => 'tabcouleur4'],
        ['ecart' => '300-399', 'vn' => '+1', 'dn' => '-0.5', 'va' => '+22', 'da' => '-16', 'class' => 'tabcouleur3'],
        ['ecart' => '400-499', 'vn' => '+0.5', 'dn' => '0', 'va' => '+28', 'da' => '-20', 'class' => 'tabcouleur4'],
        ['ecart' => '500+', 'vn' => '0', 'dn' => '0', 'va' => '+40', 'da' => '-29', 'class' => 'tabcouleur3'],
    ];

    /**
     * Affiche le tableau du barème
     */
    public function render(): void
    {
        ?>
        <div class="title-bar">Barème de points</div>
        <main class="clas_joueurs">
            <table id="bareme">
                <tbody id="tabpoints">
                    <?php $this->renderHeader(); ?>
                    <?php $this->renderRows(); ?>
                </tbody>
            </table>
        </main>
        <?php
    }

    /**
     * Affiche l'en-tête du tableau
     */
    private function renderHeader(): void
    {
        ?>
        <tr>
            <th colspan="5">COMPTAGE DES POINTS</th>
        </tr>
        <tr>
            <th>écart de points</th>
            <th>VICTOIRES NORMALES</th>
            <th>DÉFAITES NORMALES</th>
            <th>VICTOIRES ANORMALES</th>
            <th>DÉFAITES ANORMALES</th>
        </tr>
        <?php
    }

    /**
     * Affiche les lignes de données
     */
    private function renderRows(): void
    {
        foreach ($this->baremeData as $row) {
            $this->renderRow($row);
        }
    }

    /**
     * Affiche une ligne de données
     */
    private function renderRow(array $row): void
    {
        ?>
        <tr class="<?= $row['class'] ?>">
            <td><?= $row['ecart'] ?></td>
            <td><?= $row['vn'] ?></td>
            <td><?= $row['dn'] ?></td>
            <td><?= $row['va'] ?></td>
            <td><?= $row['da'] ?></td>
        </tr>
        <?php
    }
}