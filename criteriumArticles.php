<?php 
include('./html_partials/header.php');
require_once('./admin/pages/connect_ddb.php');
require_once('./admin/pages/classPages/article/ArticleManager.php');

/**
 * Contrôleur pour la page des articles du Critérium.
 */
class CriteriumArticlesController {
    private ArticleManager $articleManager;
    private int $currentPage = 1;
    private array $articles = [];
    private int $totalPages = 1;

    /**
     * Constructeur du contrôleur.
     * 
     * @param ArticleManager $articleManager Le gestionnaire d'articles.
     */
    public function __construct(ArticleManager $articleManager) {
        $this->articleManager = $articleManager;
    }

    /**
     * Gère la requête HTTP (pagination et récupération des données).
     */
    public function handleRequest(): void {
        $this->currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        
        try {
            $data = $this->articleManager->getArticlesPaginated($this->currentPage);
            $this->articles   = $data['articles'] ?? [];
            $this->totalPages = $data['total_pages'] ?? 1;
        } catch (Exception $e) {
            $this->articles   = [];
            $this->totalPages = 1;
            $this->currentPage = 1;
        }
    }

    /**
     * Retourne la liste des articles.
     */
    public function getArticles(): array {
        return $this->articles;
    }

    /**
     * Retourne la page courante.
     */
    public function getCurrentPage(): int {
        return $this->currentPage;
    }

    /**
     * Retourne le nombre total de pages.
     */
    public function getTotalPages(): int {
        return $this->totalPages;
    }

    /**
     * Formate une date en français.
     */
    public function formatDate(string $date): string {
        return $this->articleManager->formatDateFrench($date);
    }
}

// Initialisation et exécution du contrôleur
$articleManager = new ArticleManager($conn);
$controller = new CriteriumArticlesController($articleManager);
$controller->handleRequest();
?>

<link rel="stylesheet" href="/cosmtt/admin/css/colors_group.css">
<link rel="stylesheet" href="/cosmtt/admin/css/style_galerie.css">
<link rel="stylesheet" href="/cosmtt/admin/css/style_article.css">

<main>
    <h2 class="title-bar">Critérium</h2>

    <!-- Modal plein écran pour agrandir l'image -->
    <div class="image-modal" id="imageModal" aria-hidden="true">
        <span class="lightbox-close" id="imageModalClose" aria-label="Fermer">×</span>
        <img id="lightboxImage" alt="Image en plein écran">
    </div>


    <div id="articlesList" class="article-list">
        <?php if (empty($controller->getArticles())): ?>
        <p style="text-align: center; margin-top: 30px;">Aucun article trouvé pour le moment.</p>
        <?php else: ?>
        <?php foreach ($controller->getArticles() as $article): ?>
        <div class="article-card" id="article-<?= (int)$article['id'] ?>">
            <div class="section-header">
                <h3 class="article-title">
                    <span class="event-title-text"><?= htmlspecialchars($article['title_article']) ?> -</span>
                    <span
                        class="event-date-display"><?= $controller->formatDate($article['date_evmt_article']) ?></span>
                </h3>
            </div>

            <div class="publication-info">Publié le <?= $controller->formatDate($article['date_creation_article']) ?>
            </div>

            <?php if (!empty($article['img_article'])): ?>
            <?php
                $imgFile = (string)$article['img_article'];
                $imgDisk = __DIR__ . '/docs/articles/articlesPhotos/' . $imgFile;
                $imgVer  = is_file($imgDisk) ? (int)filemtime($imgDisk) : time();
                $imgSrc  = '/cosmtt/docs/articles/articlesPhotos/' . htmlspecialchars($imgFile) . '?v=' . $imgVer;
            ?>
            <img src="<?= $imgSrc ?>" alt="Illustration" class="article-image" loading="lazy" style="cursor:pointer;"
                data-full-src="<?= htmlspecialchars($imgSrc, ENT_QUOTES) ?>">
            <?php endif; ?>

            <div class="article-content-preview" style="text-align: justify; line-height: 1.6;">
                <?= nl2br(htmlspecialchars($article['contenu_article'])) ?>
            </div>

            <?php 
            $pdfDiskPath = __DIR__ . '/docs/articles/resultats/' . ($article['pdf_article'] ?? '');
            if (!empty($article['pdf_article']) && is_file($pdfDiskPath)): 
            ?>
            <div class="file-info liens-groupes">
                <a href="/cosmtt/downloadPdf.php?file=<?= urlencode($article['pdf_article']) ?>"
                    class="lien-article" target="_blank">
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

    <?php if ($controller->getTotalPages() > 1): ?>
    <div class="pagination">
        <a href="<?= $controller->getCurrentPage() > 1 ? '?page=' . ($controller->getCurrentPage() - 1) : '#' ?>"
            class="btn_fleche btnPopup-primary <?= $controller->getCurrentPage() <= 1 ? 'disabled' : '' ?>">◄</a>

        <div class="pagination-info"><?= $controller->getCurrentPage() ?> / <?= $controller->getTotalPages() ?></div>

        <a href="<?= $controller->getCurrentPage() < $controller->getTotalPages() ? '?page=' . ($controller->getCurrentPage() + 1) : '#' ?>"
            class="btn_fleche btnPopup-primary <?= $controller->getCurrentPage() >= $controller->getTotalPages() ? 'disabled' : '' ?>">►</a>
    </div>
    <?php endif; ?>
</main>

<?php include('./html_partials/footer.php'); ?>