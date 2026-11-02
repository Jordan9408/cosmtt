<?php

declare(strict_types=1);

/**
 * Renderer pour le tableau des joueurs.
 *
 * Responsabilité unique : produire le balisage HTML du tableau
 * à partir d'un objet ClassementViewData.
 */
final class JoueurTableRenderer
{
    /** Colonnes fixes, toujours affichées. */
    private const array COLONNES_FIXES = [
        'NOM PRÉNOM',
        'CLASSEMENT',
        "POINTS\nDébut de saison",
        'POINTS MENSUELS',
        'EVOLUTION MENSUELLE',
    ];

    /** Séquence des classes CSS alternées pour les lignes. */
    private const array ROW_CLASSES = ['tabcouleur1', 'tabcouleur2'];

    /**
     * Affiche le tableau complet.
     */
    public function render(ClassementViewData $data): void
    {
        echo '<table id="tabjoueurs"><tbody>';
        $this->renderHeader($data);
        $this->renderRows($data);
        echo '</tbody></table>';
    }

    /**
     * Affiche la ligne d'en-tête.
     */
    private function renderHeader(ClassementViewData $data): void
    {
        $cellules = array_map(
            static fn(string $label): string =>
                '<th>' . nl2br(htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</th>',
            self::COLONNES_FIXES
        );

        if ($data->isEditMode && $data->isSuperAdmin()) {
            $cellules[] = '<th>SUPPRIMER</th>';
        }

        echo '<tr>' . implode('', $cellules) . '</tr>';
    }

    /**
     * Affiche toutes les lignes de joueurs.
     */
    private function renderRows(ClassementViewData $data): void
    {
        $isSuperAdmin = $data->isSuperAdmin();

        foreach ($data->joueurs as $index => $joueur) {
            $rowClass = self::ROW_CLASSES[$index % 2];

            (new JoueurRow(
                joueur: $joueur,
                isEditMode: $data->isEditMode,
                isSuperAdmin: $isSuperAdmin,
                rowClass:    $rowClass,
                csrfToken:   $data->csrfToken,
            ))->render();
        }
    }
}