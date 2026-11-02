<?php
/**
 * =============================================================================
 * ARTICLE VIEW - GESTION DE L'AFFICHAGE (POO)
 * =============================================================================
 */

declare(strict_types=1);

class ArticleView
{
    public function render(array $data): void
    {
        extract($data);
        ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Articles</title>
    <link rel="stylesheet" href="/cosmtt/admin/css/colors_group.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/popup.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_article.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/lightbox.css">
</head>

<body>
    <div class="title-bar">GESTION DES ARTICLES</div>
    <main<?= !empty($flashMessage) ? ' data-alert="' . htmlspecialchars((string)$flashMessage, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        <a href="#" class="lienPopup" id="showPopup">➕ Ajouter un article</a>

        <div class="popup" id="addPopup">
            <div class="popup-card">
                <div class="popup-content">
                    <div class="title-bar" id="popupTitle">Ajouter un article</div>
                    <form method="POST" action="" class="form-ajout" id="articleForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars((string)$csrfTokenForJs, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="add_article">
                        <input type="hidden" name="article_mode" id="articleMode" value="add">
                        <input type="hidden" name="article_id" id="articleIdHidden" value="">
                        <input type="hidden" name="current_page" id="currentPageHidden"
                            value="<?= (int)$currentPage ?>">
                        <input type="hidden" name="pdf_delete_flag" id="pdfDeleteFlag" value="0">

                        <div class="form-group-inline">
                            <input type="text" name="title_article" id="titre_article" class="input-popup"
                                placeholder="Titre de l'article" required>
                            <input type="date" name="date_evmt_article" id="date_article" class="input-popup" required>
                        </div>

                        <div class="docs-input-popup">
                            <label for="imageInput">Image (JPG/JPEG/PNG) :</label>
                            <input type="file" name="image_article" id="imageInput" accept=".jpg,.jpeg,.png">
                            <div id="imagePreview"></div>
                        </div>

                        <div class="form-group-vertical">
                            <label for="contenu_article">Contenu :</label>
                            <textarea name="contenu_article" id="contenu_article" rows="6" class="input-popup"
                                placeholder="Texte de l'article" required></textarea>
                        </div>

                        <div class="docs-input-popup">
                            <label for="pdfInput">Résultats (PDF uniquement) (facultatif) :</label>
                            <input type="file" name="pdf_article" id="pdfInput" accept=".pdf">
                            <div id="pdfPreview"></div>
                        </div>

                        <div class="popup-buttons">
                            <button type="submit" class="btn btnPopup-primary" id="submitBtn">Ajouter</button>
                            <button type="button" class="btn btnPopup-primary" id="cancelPopup">Annuler</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (!empty($displayError)): ?>
        <p class="error-message"><?= htmlspecialchars($displayError) ?></p>
        <?php endif; ?>

        <div id="articlesList" class="article-list">
            <?php if (empty($articles)): ?>
            <p class="empty-message">Aucun article trouvé.</p>
            <?php else: ?>
            <?php foreach ($articles as $article): ?>
            <?php 
                $eventDate    = $articleManager?->formatDateFrench($article['date_evmt_article'])    ?? htmlspecialchars((string)$article['date_evmt_article'], ENT_QUOTES, 'UTF-8');
                $creationDate = $articleManager?->formatDateFrench($article['date_creation_article']) ?? htmlspecialchars((string)$article['date_creation_article'], ENT_QUOTES, 'UTF-8');
            ?>
            <div class="article-card" data-article-id="<?= (int)$article['id'] ?>">
                <div class="section-header">
                    <h3 class="article-title">
                        <?= htmlspecialchars($article['title_article']) ?>
                        <span class="event-date-display">- <?= $eventDate ?></span>
                    </h3>
                    <div class="btn-icons-article">
                        <button class="btn-icon btn-icon-edit" title="Modifier"
                            data-article-id="<?= (int)$article['id'] ?>">
                            <img src="/cosmtt/img/icones/write.png" alt="Modifier" width="18">
                        </button>
                        <button class="btn-icon btn-icon-delete" title="Supprimer"
                            data-article-id="<?= (int)$article['id'] ?>"
                            data-article-title="<?= htmlspecialchars($article['title_article'], ENT_QUOTES, 'UTF-8') ?>">
                            <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" width="18">
                        </button>
                    </div>
                </div>

                <div class="publication-info">Publié le <?= $creationDate ?></div>
                <?php if (!empty($article['img_article'])): ?>
                <?php
                    $imgFile = (string)$article['img_article'];
                    $imgDisk = $articleManager?->getImageDir() . $imgFile;
                    $imgVer  = (is_string($imgDisk) && is_file($imgDisk)) ? (int)filemtime($imgDisk) : time();
                ?>
                <img src="/cosmtt/docs/articles/articlesPhotos/<?= htmlspecialchars($imgFile) ?>?v=<?= $imgVer ?>"
                    alt="Illustration" class="article-image" title="Cliquez pour agrandir">
                <?php endif; ?>
                <div class="article-content-preview">
                    <?= nl2br(htmlspecialchars($article['contenu_article'])) ?>
                </div>
                <?php 
                $pdfDiskPath = dirname(__DIR__, 4) . '/docs/articles/resultats/' . ($article['pdf_article'] ?? '');
                if (!empty($article['pdf_article']) && is_file($pdfDiskPath)): 
                ?>
                <div class="file-info liens-groupes">
                    <a href="/cosmtt/downloadPdf.php?file=<?= urlencode($article['pdf_article']) ?>"
                        class="lien-article" target="_blank">📄 Résultats
                        complets (PDF)</a>
                </div>
                <?php endif; ?>
                <div class="file-info liens-groupes">
                    <a href="/cosmtt/galeries_photos.php?title=<?= urlencode($article['title_article']) ?>&date=<?= urlencode($article['date_evmt_article']) ?>"
                        class="lien-article" target="_blank">📸 Photos</a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <a href="<?= $currentPage > 1 ? '?page=' . ($currentPage - 1) : '#' ?>"
                class="btn btn_fleche btnPopup-primary <?= $currentPage <= 1 ? 'disabled' : '' ?>">◄</a>
            <div class="pagination-info"><?= $currentPage ?> / <?= $totalPages ?></div>
            <a href="<?= $currentPage < $totalPages ? '?page=' . ($currentPage + 1) : '#' ?>"
                class="btn btn_fleche btnPopup-primary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">►</a>
        </div>
        <?php endif; ?>

        <?php
            // Footer
            require_once dirname(__DIR__, 2) . '/parts/footer.php';
        ?>

        <!-- Lightbox image plein écran (agrandissement) -->
        <div id="imageModal" class="lightbox-overlay" style="display:none; background-color: rgba(0, 0, 0, 0.9);">
            <span class="lightbox-close" id="imageModalClose">&times;</span>

            <div class="lightbox-content">
                <img id="lightboxImage" src="" alt="Image en plein écran">
            </div>
        </div>


    </main>

    <script src="/cosmtt/admin/js/article.js"></script>
</body>

</html>
<?php
    }
}