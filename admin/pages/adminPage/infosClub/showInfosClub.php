<?php
declare(strict_types=1);

// Définition des rôles autorisés (accès superAdmin strict - [H-01])
$requiredRoles = ['superAdmin'];
require_once dirname(__DIR__, 3) . '/includes/security.php';

// Défense en profondeur : vérification stricte du rôle superAdmin
if ($role !== 'superAdmin') {
    http_response_code(403);
    header('Location: /cosmtt/admin/pages/home.php?unauthorized=1');
    exit;
}
// =========================================================================
// Définition des chemins
// =========================================================================

define('PAGES_PATH', dirname(__DIR__, 2) . '/');
define('PARTS_PATH', PAGES_PATH . 'parts/');
define('INCLUDES_PATH', dirname(__DIR__, 3) . '/includes/');
define('CLASSPAGES_PATH', PAGES_PATH . 'classPages/club/');

// =========================================================================
// Inclusion des dépendances
// =========================================================================

require_once INCLUDES_PATH . 'security.php';
require_once INCLUDES_PATH . 'session_check.php';
require_once INCLUDES_PATH . 'ErrorMessages.php';
require_once PAGES_PATH . 'connect_ddb.php';
require_once PARTS_PATH . 'header.php';
require_once CLASSPAGES_PATH . 'InfosClubController.php';
require_once CLASSPAGES_PATH . 'InfosClubManager.php';
require_once CLASSPAGES_PATH . 'Validations.php';

// =========================================================================
// Initialisation
// =========================================================================

use Admin\ClassPages\Club\InfosClubController;
use Admin\ClassPages\Club\InfosClubManager;

$manager = new InfosClubManager($conn);
$controller = new InfosClubController($manager);

// =========================================================================
// Variables d'affichage
// =========================================================================

$successMessage = isset($_GET['success']) ? (string)$_GET['success'] : '';
$errorMessage = '';
$postData = [];
$isEditMode = false;

// =========================================================================
// 1) Traitement du formulaire de mise à jour
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_infos'])) {
    $csrfToken = $_SESSION['csrf_token'] ?? '';
    $result = $controller->processFormSubmission($_POST, $csrfToken);

    if (!empty($result['success'])) {
        header('Location: showInfosClub.php?success=' . urlencode((string)($result['message'] ?? '')));
        exit;
    }

    $errorMessage = (string)($result['message'] ?? '');
    $postData = is_array($result['postData'] ?? null) ? $result['postData'] : [];
    $isEditMode = true;
}

// =========================================================================
// 2) Récupération des informations actuelles du club
// =========================================================================

$infos = $controller->getInfosClub();

// =========================================================================
// Gestion du mode édition
// =========================================================================

if (isset($_GET['edit']) && $_GET['edit'] == '1') {
    $isEditMode = true;
}

if ($errorMessage !== '' || !empty($postData)) {
    $isEditMode = true;
}

// =========================================================================
?>

<!-- Inclusion du CSS avec le bon chemin (3 niveaux de parent pour remonter à /admin/) -->
<link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
<link rel="stylesheet" href="/cosmtt/admin/css/style_infosClub.css">

<div class="title-bar">Gestion des informations du club</div>

<script src="/cosmtt/admin/js/infosClub.js"></script>

<div class="lienPopup">
    <?php if ($isEditMode): ?>
    <a href="#" id="save-club-btn" class="lienShowPopup">Enregistrer</a>
    <a href="showInfosClub.php" class="lienShowPopup">Annuler</a>
    <?php else: ?>
    <a href="?edit=1" class="lienShowPopup">Modifier</a>
    <?php endif; ?>
</div>

<main class="admin-grid"<?= !empty($successMessage) ? ' data-success="' . htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') . '"' : (!empty($errorMessage) ? ' data-error="' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '"' : '') ?>>
    <form action="" method="POST">
        <input type="hidden" name="update_infos" value="1">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <!-- Ligne 1 : Horaires et Licences -->
        <div class="admin-row">
            <div class="card">
                <div class="card-header">
                    <img src="/cosmtt/img/calendar.svg" width="20">
                    <h3> Horaires d'entraînement</h3>
                </div>
                <?php if ($isEditMode): ?>
                <div class="form-group">
                    <textarea name="horaires" rows="4"
                        placeholder="Ex: Vendredi : 20h30 et 23h00"><?= htmlspecialchars($postData['horaires'] ?? $infos['horaires']) ?></textarea>
                </div>
                <?php else: ?>
                <div class="form-group horaires-section">
                    <div class="horaires-display">
                        <?= nl2br(htmlspecialchars($infos['horaires'] ?: 'Non renseigné', ENT_QUOTES, 'UTF-8')) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="card-header">
                    <img src="/cosmtt/img/euro.svg" width="20">
                    <h3> Prix des licences</h3>
                </div>
                <table class="admin-table-price">
                    <tr>
                        <th rowspan="2">UFOLEP</th>
                        <td>Adultes</td>
                        <td>
                            <?php if ($isEditMode): ?>
                            <input type="text" name="tarif_adulte"
                                value="<?= htmlspecialchars($postData['tarif_adulte'] ?? $infos['tarif_adulte'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Ex: 50">
                            <?php else: ?>
                            <?= $infos['tarif_adulte'] ? htmlspecialchars($infos['tarif_adulte']) . ' €' : 'Non renseigné' ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Mineurs</td>
                        <td>
                            <?php if ($isEditMode): ?>
                            <input type="text" name="tarif_mineurs"
                                value="<?= htmlspecialchars($postData['tarif_mineurs'] ?? $infos['tarif_mineurs'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Ex: 35">
                            <?php else: ?>
                            <?= $infos['tarif_mineurs'] ? htmlspecialchars($infos['tarif_mineurs']) . ' €' : 'Non renseigné' ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Ligne 2 : Google Map et Adresse -->
        <div class="admin-row" style="margin-top: 20px;">
            <div class="card">
                <div class="card-header">
                    <?php if ($isEditMode): ?>
                    <h3>Carte
                        <span class="info-icon" title="Pour intégrer une carte Google Maps suit les étapes :
- Va sur Google Maps
- Cherche l’endroit que tu veux afficher (ex: Gymnase)
- Clique sur “Partager” (le bouton avec une flèche)
- Choisis “Intégrer une carte”
- Copie le lien (code iframe) qu’on te donne
- Coller le code ci-dessous">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                    </h3>
                    <?php else: ?>
                    <h3>Carte</h3>
                    <?php endif; ?>
                </div>

                <?php if ($isEditMode): ?>
                <div class="form-group">
                    <textarea name="map_url" rows="4" style="height: 127px;"
                        placeholder="Collez ici l'URL src de l'iframe Google Maps"><?= htmlspecialchars($infos['map_url']) ?></textarea>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <?php 
                        $rawContent = trim($infos['map_url'] ?? '');
                        if (!empty($rawContent)):
                            $mapUrl = '';

                            // Extraction pour compatibilité ascendante si un <iframe> entier est encore en base
                            if (str_contains($rawContent, '<iframe')) {
                                preg_match('/src=["\']([^"\']+)["\']/', $rawContent, $match);
                                $mapUrl = $match[1] ?? '';
                            } else {
                                $mapUrl = $rawContent;
                            }

                            if (!empty($mapUrl)):
                                $mapValidation = \Admin\ClassPages\Club\Validations::validateMapUrl($mapUrl);
                                if ($mapValidation['valid'] && filter_var($mapUrl, FILTER_VALIDATE_URL)):
                        ?>
                    <iframe src="<?= htmlspecialchars($mapUrl, ENT_QUOTES, 'UTF-8') ?>" width="100%" height="300"
                        style="border:0;" allowfullscreen loading="lazy" referrerpolicy="no-referrer"
                        title="Carte du club"></iframe>
                    <?php else: ?>
                    <p>URL de la carte invalide ou non renseignée.</p>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Adresse</h3>
                </div>
                <?php if ($isEditMode): ?>
                <div class="form-group">
                    <textarea name="nom_club" rows="2"
                        placeholder="Club"><?= htmlspecialchars($infos['nom_club']) ?></textarea>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <p style="font-weight: bold;">
                        <?= nl2br(htmlspecialchars($infos['nom_club'] ?: 'Non renseigné', ENT_QUOTES, 'UTF-8')) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($isEditMode): ?>
                <div class="form-group">
                    <textarea name="adresse" rows="2"
                        placeholder="Adresse Postale"><?= htmlspecialchars($infos['adresse']) ?></textarea>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <p><?= nl2br(htmlspecialchars($infos['adresse'] ?: 'Non renseigné', ENT_QUOTES, 'UTF-8')) ?></p>
                </div>
                <?php endif; ?>

                <p><a href="/cosmtt/index.php#contact"
                        style="display: flex; justify-content: start; width: fit-content; text-decoration: underline; color: var(--sixth-color);">Formulaire
                        de contact</a></p>
            </div>
        </div>
    </form>
</main>

<?php
    // Footer
    require_once dirname(__DIR__, 2) . '/parts/footer.php';
?>