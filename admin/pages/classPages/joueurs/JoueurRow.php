<?php

/**
 * Représente une ligne de joueur dans le tableau
 */
class JoueurRow
{
    private array $joueur;
    private bool $isEditMode;
    private bool $isSuperAdmin;
    private string $rowClass;

    public function __construct(array $joueur, bool $isEditMode, bool $isSuperAdmin, string $rowClass)
    {
        $this->joueur = $joueur;
        $this->isEditMode = $isEditMode;
        $this->isSuperAdmin = $isSuperAdmin;
        $this->rowClass = $rowClass;
    }

    /**
     * Affiche la ligne
     */
    public function render(): void
    {
        ?>
        <tr class="<?= $this->rowClass ?>">
            <?php if ($this->isEditMode): ?>
                <?php $this->renderEditMode(); ?>
            <?php else: ?>
                <?php $this->renderDisplayMode(); ?>
            <?php endif; ?>
        </tr>
        <?php
    }

    /**
     * Affiche la ligne en mode édition
     */
    private function renderEditMode(): void
    {
        $id = $this->joueur['ID'];
        ?>
        <div class="centrer_nom">
            <input type="hidden" name="joueur_id[<?= $id ?>]" value="<?= $id ?>">
            <input type="text" name="nom[<?= $id ?>]" value="<?= $this->escape($this->joueur['Nom']) ?>" class="edit-input edit-input-nom" placeholder="Nom">
            <input type="text" name="prenom[<?= $id ?>]" value="<?= $this->escape($this->joueur['Prenom']) ?>" class="edit-input edit-input-nom" placeholder="Prénom">
        </td>
        <td>
            <input type="number" name="classement[<?= $id ?>]" value="<?= $this->joueur['Classement'] ?>" class="edit-input edit-input-classement" min="1" max="99">
        </td>
        <td>
            <input type="number" name="points_debut_saison[<?= $id ?>]" value="<?= $this->joueur['PointsDebutSaison'] ?>" class="edit-input edit-input-pts-debut-saison" min="0">
        </td>
        <td>
            <input type="number" name="points_mensuels[<?= $id ?>]" value="<?= $this->joueur['PointsMensuels'] ?>" class="edit-input edit-input-pts-mensuel" min="0">
        </td>
        <td class="<?= $this->getEvolutionClass() ?> evolution"><?= $this->getEvolution() ?></td>
        <?php if ($this->isSuperAdmin): ?>
            <td class="delete">
                <a href="javascript:void(0);" 
                   onclick="if(confirm('Êtes-vous sûr de vouloir supprimer ce joueur ?')) { window.location.href='?delete_id=<?= $id ?>'; }"
                   title="Supprimer">
                    <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" class="delete-icon">
                </a>
            </td>
        <?php endif; ?>
        <?php
    }

    /**
     * Affiche la ligne en mode affichage
     */
    private function renderDisplayMode(): void
    {
        ?>
        <td class="td_nom"><?= strtoupper($this->escape($this->joueur['Nom'])) ?> <?= $this->escape($this->joueur['Prenom']) ?></td>
        <td><?= $this->joueur['Classement'] ?></td>
        <td><?= $this->joueur['PointsDebutSaison'] ?></td>
        <td><?= $this->joueur['PointsMensuels'] ?></td>
        <td class="<?= $this->getEvolutionClass() ?>"><?= $this->getEvolution() ?></td>
        <?php
    }

    /**
     * Calcule l'évolution des points
     */
    private function getEvolution(): int
    {
        return $this->joueur['PointsMensuels'] - $this->joueur['PointsDebutSaison'];
    }

    /**
     * Détermine la classe CSS pour l'évolution
     */
    private function getEvolutionClass(): string
    {
        $evolution = $this->getEvolution();
        
        if ($evolution > 0) {
            return 'pointsgagne';
        } elseif ($evolution < 0) {
            return 'pointsperdu';
        }
        
        return '';
    }

    /**
     * Échappe le HTML
     */
    private function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}