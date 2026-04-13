<?php 
include('./html_partials/header.php');
require_once './admin/pages/connect_ddb.php';
require_once './admin/pages/classPages/article/ArticleManager.php';

$articleManager = new ArticleManager($conn);

/**
 * Convertit une date française au format YYYY-MM-DD
 * Exemple: "8 janvier 2023" -> "2023-01-08"
 */
function convertFrenchDateToISO(string $frenchDate): ?string {
    $frenchDate = trim($frenchDate);
    
    // Tableau de correspondance mois français -> numéro
    $monthsMap = [
        'janvier' => '01', 'février' => '02', 'mars' => '03', 'avril' => '04',
        'mai' => '05', 'juin' => '06', 'juillet' => '07', 'août' => '08',
        'septembre' => '09', 'octobre' => '10', 'novembre' => '11', 'décembre' => '12'
    ];
    
    // Pattern pour extraire jour, mois (texte) et année
    // Accepte: "8 janvier 2023", "08 janvier 2023", "1er janvier 2023", etc.
    if (preg_match('/(\d{1,2})(?:er)?\s+([a-z]+)\s+(\d{4})/i', $frenchDate, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $monthText = strtolower($matches[2]);
        $year = $matches[3];
        
        $month = $monthsMap[$monthText] ?? null;
        
        if ($month) {
            return "$year-$month-$day";
        }
    }
    
    return null;
}

// Gestion de la pagination
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

try {
    $data = $articleManager->getArticlesPaginated($currentPage);
    $articles   = $data['articles'];
    $totalPages = $data['total_pages'];
} catch (Exception $e) {
    $articles   = [];
    $totalPages = 1;
    $currentPage = 1;
}
?>

<!-- <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css"> -->
<link rel="stylesheet" href="/cosmtt/admin/css/colors_group.css">
<link rel="stylesheet" href="/cosmtt/admin/css/style_galerie.css">
<link rel="stylesheet" href="/cosmtt/admin/css/style_article.css">

<main>
    <h2 class="title-bar">Critérium</h2>

    <div id="articlesList" class="article-list">
        <?php if (empty($articles)): ?>
            <p style="text-align: center; margin-top: 30px;">Aucun article trouvé pour le moment.</p>
        <?php else: ?>
            <?php foreach ($articles as $article): ?>
                <div class="article-card">
                    <div class="section-header">
                        <h3 class="article-title">
                            <?= htmlspecialchars($article['title_article']) ?>
                            <span class="event-date-display">- <?= $articleManager->formatDateFrench($article['date_evmt_article']) ?></span>
                        </h3>
                    </div>

                    <div class="publication-info">Publié le <?= $articleManager->formatDateFrench($article['date_creation_article']) ?></div>
                    
                    <?php if (!empty($article['img_article'])): ?>
                        <img src="/cosmtt/docs/articles/articlesPhotos/<?= htmlspecialchars($article['img_article']) ?>" 
                             alt="Illustration" class="article-image">
                    <?php endif; ?>
                    
                    <div class="article-content-preview" style="text-align: justify; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($article['contenu_article'])) ?>
                    </div>
                    
                    <?php if (!empty($article['pdf_article'])): ?>
                        <div class="file-info liens-groupes">
                            <a href="/cosmtt/docs/articles/resultats/<?= htmlspecialchars($article['pdf_article']) ?>" 
                               download="<?= htmlspecialchars($article['pdf_article']) ?>" 
                               class="lien-article">
                               📄 Résultats complets (PDF)
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="file-info liens-groupes" style="margin-top: 5px;">
                        <a href="/cosmtt/galeries_photos.php?title=<?= urlencode($article['title_article']) ?>&date=<?= urlencode($article['date_evmt_article']) ?>" 
                           class="lien-article" target="_blank">📸 Photos</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <a href="<?= $currentPage > 1 ? '?page=' . ($currentPage - 1) : 'javascript:void(0)' ?>" 
               class="btn_fleche btnPopup-primary <?= $currentPage <= 1 ? 'disabled' : '' ?>">◄</a>

            <div class="pagination-info"><?= $currentPage ?> / <?= $totalPages ?></div>

            <a href="<?= $currentPage < $totalPages ? '?page=' . ($currentPage + 1) : 'javascript:void(0)' ?>" 
               class="btn_fleche btnPopup-primary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">►</a>
        </div>
    <?php endif; ?>
</main>

<?php include('./html_partials/footer.php') ?>