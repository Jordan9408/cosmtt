<?php
/**
 * Classe ShowEquipeView
 * Gère l'affichage complet de la vue de la liste des équipes.
 */
class ShowEquipeView 
{
    private array $formData;
    private bool $shouldOpenPopup;

    /**
     * Le constructeur déclare et initialise automatiquement les dépendances (PHP 8+)
     */
    public function __construct(
        private ?string $message,
        private ?object $manager,
        private string $csrfToken
    ) {
        // Récupération et nettoyage des données de formulaire en session
        $this->formData = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_data']);

        // Détermination si la popup doit être ouverte
        $this->shouldOpenPopup = isset($_GET['openPopup']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($this->message));
    }

    /**
     * Génère et affiche le code HTML complet de la page
     */
    public function render(): void 
    {
        ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des équipes</title>
    <link rel="stylesheet" href="/cosmtt/admin/css/popup.css">
</head>

<body>
    <div class="title-bar">Liste des équipes</div>

    <main<?= !empty($this->message) ? ' data-alert="' . htmlspecialchars($this->message, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        <a href="#" class="lienPopup" id="showPopup">Ajouter une équipe</a>

        <div class="popup" id="teamPopup" <?= $this->shouldOpenPopup ? 'style="display: block;"' : '' ?>>
            <div class="popup-card">
                <div class="popup-content">
                    <h3>AJOUTER UNE ÉQUIPE</h3>
                    <form method="POST" action="" class="form-ajout">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($this->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-group">
                            <input type="text" name="nomEquipe" class="input-popup" placeholder="Nom de l'équipe"
                                value="<?= htmlspecialchars($this->formData['nomEquipe'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                required>
                            <label for="poule_ajout">Division:</label>
                            <select name="poule" id="poule_ajout" required>
                                <option value="PouleA"
                                    <?= (isset($this->formData['poule']) && $this->formData['poule'] === 'PouleA') ? 'selected' : '' ?>>
                                    Poule A</option>
                                <option value="PouleB"
                                    <?= (isset($this->formData['poule']) && $this->formData['poule'] === 'PouleB') ? 'selected' : '' ?>>
                                    Poule B</option>
                            </select>
                        </div>
                        <input type="submit" value="Ajouter" class="input-submit">
                        <input type="button" id="cancelPopup" class="input-submit" value="Annuler">
                    </form>
                </div>
            </div>
        </div>

        <div class="table-container">
            <?php if ($this->manager): ?>
            <section class="show-tab">
                <?php $this->manager->displayEquipes("PouleA", $this->csrfToken); ?>
            </section>
            <section class="show-tab">
                <?php $this->manager->displayEquipes("PouleB", $this->csrfToken); ?>
            </section>
            <?php endif; ?>
        </div>
    </main>

    <?php 
        require_once dirname(__DIR__, 2) . '/parts/footer.php'
    ?>
</body>

</html>
<?php
    }
}

// --- Instanciation et exécution automatique ---
// Les variables $message, $manager et $csrfToken proviennent du fichier showEquipe.php
$view = new ShowEquipeView($message ?? null, $manager ?? null, $csrfToken ?? '');
$view->render();