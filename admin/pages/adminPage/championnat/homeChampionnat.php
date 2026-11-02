<?php
// =========================================================================
// 1. INITIALISATION (Sécurité et Structure)
// =========================================================================
require_once dirname(__DIR__, 3) . '/includes/security.php';
require_once dirname(__DIR__, 3) . '/includes/session_check.php';
require_once dirname(__DIR__, 2) . '/connect_ddb.php';
require_once dirname(__DIR__, 2) . '/parts/header.php';

// =========================================================================
// 2. CLASSE COMPOSANT HOME
// =========================================================================
class HomeChampionnat {
    
    /**
     * Promotion des propriétés dans le constructeur (PHP 8+)
     * Plus besoin de déclarer et d'assigner manuellement les variables.
     */
    public function __construct(
        private string $userFirstName, 
        private string $role
    ) {}

    /**
     * Retourne uniquement les DONNÉES des liens (pas de HTML ici, pure logique métier)
     */
    private function getNavLinks(): array {
        if (!in_array($this->role, ['admin', 'superAdmin'], true)) {
            return [];
        }

        return [
            ['url' => './equipes/showEquipe.php', 'label' => 'Les Equipes'],
            ['url' => './rencontres/showChampionnat.php', 'label' => 'Championnat']
        ];
    }

    /**
     * Gère l'affichage complet de la page
     */
    public function render(): void {
        $welcomeName = htmlspecialchars(ucfirst($this->userFirstName), ENT_QUOTES, 'UTF-8');
        $links = $this->getNavLinks();
        
        // On ferme PHP momentanément pour injecter du HTML/CSS propre sans "echo"
        ?>
<!-- CSS menu_btn géré par admin/css/style_admin.css (pas de style inline) -->

<h1>Bonjour <?= $welcomeName ?></h1>
<main>


    <div class="flex-container">
        <div id="menu_btn">
            <ul>
                <?php foreach ($links as $link): ?>
                <li><a href="<?= $link['url'] ?>"><?= $link['label'] ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

</main>
<?php
    }
}

// =========================================================================
// 3. EXÉCUTION
// =========================================================================
$homePage = new HomeChampionnat($userFirstName ?? '', $role ?? '');
$homePage->render();

// Inclusion du footer
include_once dirname(__DIR__, 3) . '/pages/parts/footer.php';