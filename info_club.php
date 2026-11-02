<?php
declare(strict_types=1);

require_once './admin/pages/connect_ddb.php';
include './html_partials/header.php';

/**
 * Classe InfosClubPage
 * Gère l'accès et le formatage des données pour l'affichage public du club.
 */
final class InfosClubPage
{
    /** @var array<string, string> */
    private array $infos = [];

    public function __construct(PDO $db)
    {
        $this->infos = $this->loadInfos($db);
    }

    /**
     * Charge les informations du club depuis la base.
     *
     * @return array<string, string>
     */
    private function loadInfos(PDO $db): array
    {
        try {
            $stmt = $db->query('SELECT * FROM infos_club WHERE id = 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return [];
            }

            // Assure un tableau string => string (pour éviter les warnings d'accès ultérieurs)
            $clean = [];
            foreach ($row as $k => $v) {
                $clean[(string)$k] = is_string($v) ? $v : (string)$v;
            }
            return $clean;
        } catch (PDOException) {
            // Silencieusement ignoré pour éviter de bloquer le site en cas de panne de base de données
            return [];
        }
    }

    public function getNomClub(): string
    {
        return $this->infos['nom_club'] ?? '';
    }

    public function getHoraires(): string
    {
        return $this->infos['horaires'] ?? '';
    }

    public function getTarifAdulte(): string
    {
        return $this->infos['tarif_adulte'] ?? '';
    }

    public function getTarifMineurs(): string
    {
        return $this->infos['tarif_mineurs'] ?? '';
    }

    public function getMapUrl(): string
    {
        return $this->infos['map_url'] ?? '';
    }

    public function getAdresse(): string
    {
        return $this->infos['adresse'] ?? '';
    }

    /**
     * Extrait et valide l'URL source depuis un code iframe ou un lien brut avec validation de domaine strict.
     */
    public function getCleanMapUrl(): string
    {
        $raw = trim($this->getMapUrl());
        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, '<iframe')) {
            preg_match('/src=["\']([^"\']+)["\']/', $raw, $match);
            $raw = $match[1] ?? '';
        }

        if (!filter_var($raw, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parts = parse_url($raw);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host   = strtolower($parts['host'] ?? '');
        $path   = $parts['path'] ?? '';

        if ($scheme !== 'https') {
            return '';
        }

        $allowedHosts = [
            ['www.google.com',          '/maps/embed'],
            ['maps.googleapis.com',     '/maps/'],
            ['www.openstreetmap.org',   '/export/embed.html'],
            ['www.bing.com',            '/maps/embed'],
        ];

        foreach ($allowedHosts as [$allowedHost, $requiredPath]) {
            if ($host === $allowedHost && str_starts_with($path, $requiredPath)) {
                return $raw;
            }
        }

        return '';
    }
}

$page = new InfosClubPage($conn);
?>

<!-- Inclusion du CSS -->
<link rel="stylesheet" href="./css/style.css">

<div class="title-bar">Informations du club</div>

<main class="admin-grid" id="main_info_club">
    <!-- Ligne 1 : Horaires et Licences -->
    <div class="admin-row">
        <div class="card">
            <div class="card-header">
                <img src="/cosmtt/img/calendar.svg" alt="Calendrier">
                <h3>Horaires d'entraînement</h3>
            </div>
            <div class="form-group horaires-section">
                <div class="horaires-display">
                    <?= nl2br(htmlspecialchars($page->getHoraires() ?: 'Non renseigné', ENT_QUOTES, 'UTF-8')) ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <img src="/cosmtt/img/euro.svg" alt="Euro">
                <h3>Prix des licences</h3>
            </div>
            <table class="admin-table-price">
                <tr>
                    <th rowspan="2">UFOLEP</th>
                    <td>Adultes</td>
                    <td><?= $page->getTarifAdulte() ? htmlspecialchars($page->getTarifAdulte()) . ' €' : 'Non renseigné' ?>
                    </td>
                </tr>
                <tr>
                    <td>Mineurs</td>
                    <td><?= $page->getTarifMineurs() ? htmlspecialchars($page->getTarifMineurs()) . ' €' : 'Non renseigné' ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Ligne 2 : Google Map et Adresse -->
    <div class="admin-row">
        <div class="card">
            <div class="form-group">
                <?php
                $mapUrl = $page->getCleanMapUrl();
                if (!empty($mapUrl) && filter_var($mapUrl, FILTER_VALIDATE_URL)):
                ?>
                <iframe src="<?= htmlspecialchars($mapUrl, ENT_QUOTES, 'UTF-8') ?>" class="map-frame" allowfullscreen
                    loading="lazy" referrerpolicy="no-referrer" title="Carte du club"></iframe>
                <?php else: ?>
                <p>URL de la carte non renseignée ou invalide.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="form-group">
                <p style="font-weight: bold;">
                    <?= nl2br(htmlspecialchars($page->getNomClub() ?: 'Non renseigné', ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
            <div class="form-group">
                <p><?= nl2br(htmlspecialchars($page->getAdresse() ?: 'Non renseigné', ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
            <p><a href="/cosmtt/index.php#contact"
                    style="display: flex; justify-content: start; width: fit-content; text-decoration: underline; color: var(--sixth-color);">Formulaire
                    de contact</a></p>
        </div>
    </div>
</main>

<?php include './html_partials/footer.php'; ?>