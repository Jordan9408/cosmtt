<?php
/**
 * =============================================================================
 * SHOW ARTICLES - PAGE D'ADMINISTRATION (VERSION POO - REFACTORISÉE)
 * =============================================================================
 * 
 * Ce fichier est le point d'entrée principal responsable de :
 * - La logique métier (AJAX, formulaires, récupération des données)
 * - L'inclusion des ressources nécessaires
 * - Le chargement de la vue appropriée
 * 
 * STRUCTURE :
 * - showArticles.php (ce fichier) : logique PHP
 * - ArticleView.php : template HTML
 * - ArticleController.php : contrôleur métier
 * - ArticleManager.php : couche données
 * 
 * FONCTIONNALITÉS :
 * - AJAX: Suppression d'article (delete_article)
 * - AJAX: Récupération d'article pour édition (get_article)
 * - AJAX: Vérification doublon titre/date (check_article)
 * - Upload image : validation sécurité, conversion WebP, stockage sans miniature
 * - Upload PDF : validation sécurité, compression Ghostscript, stockage
 * - Contenu : validation anti-code (HTML, JS, PHP interdit)
 * - Sécurité : CSRF, magic bytes, scan malware, ClamAV optionnel
 * 
 * =============================================================================
 * DÉPENDANCES
 * =============================================================================
 * - connect_ddb.php (connexion base de données)
 * - header.php (en-tête HTML)
 * - security.php (sécurité)
 * - session_check.php (vérification session)
 * - ArticleController (contrôleur métier)
 * =============================================================================
 */

// =========================================================================
// SECTION 1: INITIALISATION ET INCLUDES
// =========================================================================

// Définir les chemins pour les includes
define('PAGES_PATH', dirname(__DIR__, 2) . '/');
define('PARTS_PATH', PAGES_PATH . 'parts/');
define('INCLUDES_PATH', dirname(__DIR__, 3) . '/includes/');
define('CLASSPAGES_PATH', PAGES_PATH . 'classPages/article/');

// Inclure les dépendances
require_once PAGES_PATH . 'connect_ddb.php';
require_once PARTS_PATH . 'header.php';
require_once INCLUDES_PATH . 'security.php';
require_once INCLUDES_PATH . 'session_check.php';
require_once CLASSPAGES_PATH . 'ArticleController.php';

// =========================================================================
// SECTION 2: GESTION DES MESSAGES FLASH
// =========================================================================

$flashMessage = null;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// =========================================================================
// SECTION 3: INITIALISATION DU CONTRÔLEUR
// =========================================================================

$csrfTokenForJs = $_SESSION['csrf_token'] ?? '';
$articleController = new ArticleController($conn, $csrfTokenForJs);
$articleManager = $articleController->getArticleManager();

// =========================================================================
// SECTION 4: DÉTECTION AJAX
// =========================================================================

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch';

// =========================================================================
// SECTION 5: VÉRIFICATION ERREURS VOLUME UPLOAD
// =========================================================================

$displayError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) &&
    isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $displayError = "❌ Erreur : Le volume total des fichiers envoyés dépasse la limite du serveur (" 
        . ini_get('post_max_size') . "). Veuillez réduire la taille des fichiers.";
}

// Si post_max_size dépassé en AJAX → réponse JSON directe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax && !empty($displayError)) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode(
        ['success' => false, 'message' => $displayError],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
    exit;
}

// =========================================================================
// SECTION 6: TRAITEMENT DES REQUÊTES AJAX (DELETE, GET, CHECK)
// =========================================================================

$articleController->handleRequest();



// =========================================================================
// SECTION 7: TRAITEMENT DU FORMULAIRE (AJAX)
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax 
    && isset($_POST['action']) && $_POST['action'] === 'add_article') {

    $formResult = $articleController->handleFormSubmission();

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $redirectPage = max(1, (int)($_POST['current_page'] ?? 1));
    $redirectUrl = $_SERVER['PHP_SELF'] . '?page=' . $redirectPage;

    echo json_encode([
        'success'      => (bool)($formResult['success'] ?? false),
        'message'      => (string)($formResult['message'] ?? ''),
        'redirect_url' => ($formResult['success'] ?? false) ? $redirectUrl : null
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}

// =========================================================================
// SECTION 8: TRAITEMENT DU FORMULAIRE (CLASSIQUE, NON-AJAX)
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isAjax
    && isset($_POST['action']) && $_POST['action'] === 'add_article') {

    $formResult = $articleController->handleFormSubmission();

    if ($formResult['success']) {
        $_SESSION['flash_message'] = $formResult['message'];
        $currentPage = max(1, (int)($_POST['current_page'] ?? 1));
        header("Location: {$_SERVER['PHP_SELF']}?page=$currentPage");
        exit;
    }

    // En cas d'erreur : on affiche sans redirect
    $flashMessage = $formResult['message'] ?? null;
}

// =========================================================================
// SECTION 9: PRÉPARATION DES DONNÉES POUR L'AFFICHAGE
// =========================================================================

$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

try {
    $data = $articleManager->getArticlesPaginated($currentPage);
    $articles   = $data['articles'];
    $totalPages = $data['total_pages'];
} catch (Exception $e) {
    $displayError = "Erreur lors de la récupération des articles : " . htmlspecialchars($e->getMessage());
    $articles   = [];
    $totalPages = 1;
    $currentPage = 1;
}

// =========================================================================
// SECTION 10: CHARGEMENT DE LA VUE
// =========================================================================

require_once CLASSPAGES_PATH . 'ArticleView.php';

require_once dirname(__DIR__, 2) . '/parts/footer.php';
