<?php
/**
 * VUE : Gestion des Articles
 * Variables disponibles depuis showArticles.php :
 * - $csrfTokenForJs
 * - $flashMessage
 * - $displayError
 * - $currentPage
 * - $articles
 * - $totalPages
 * - $articleManager
 */
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Articles</title>

    <!-- <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css"> -->
    <link rel="stylesheet" href="/cosmtt/admin/css/colors_group.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/popup.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_article.css">
</head>

<body>

    <div class="title-bar">GESTION DES ARTICLES</div>

    <main>
        <a href="javascript:void(0);" class="lienPopup" id="showPopup">➕ Ajouter un article</a>

        <!-- ============================================================= -->
        <!-- POPUP AJOUT / MODIFICATION (Même structure que GalerieView)    -->
        <!-- ============================================================= -->
        <div class="popup" id="addPopup">
            <div class="popup-card">
                <div class="popup-content">
                    <div class="title-bar" id="popupTitle">Ajouter un article</div>
                    
                    <form method="POST" action="" class="form-ajout" id="articleForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenForJs, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="add_article">
                        <input type="hidden" name="article_mode" id="articleMode" value="add">
                        <input type="hidden" name="article_id" id="articleIdHidden" value="">
                        <input type="hidden" name="current_page" id="currentPageHidden" value="<?= (int)$currentPage ?>">
                        <input type="hidden" name="pdf_delete_flag" id="pdfDeleteFlag" value="0">

                        <div class="form-group-inline">
                            <input type="text" name="title_article" id="titre_article" class="input-popup" placeholder="Titre de l'article" required>
                            <input type="date" name="date_evmt_article" id="date_article" class="input-popup" required>
                        </div>

                        <div class="docs-input-popup">
                            <label for="imageInput">Image (JPG/JPEG/PNG) :</label>
                            <input type="file" name="image_article" id="imageInput" accept=".jpg,.jpeg,.png">
                            <div id="imagePreview"></div>
                        </div>

                        <div style="justify-content: flex-start; display: flex; flex-direction: column; gap: 5px;">
                            <label for="contenu_article">Contenu :</label>
                            <textarea name="contenu_article" id="contenu_article" rows="6" class="input-popup" placeholder="Texte de l'article" required></textarea>
                        </div>

                        <div class="docs-input-popup">
                            <label for="pdfInput">PDF résultats (facultatif) :</label>
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

        <!-- ============================================================= -->
        <!-- MESSAGES                                                       -->
        <!-- ============================================================= -->
        <?php if (isset($displayError) && $displayError): ?>
            <p style="color: red; text-align: center;"><?= htmlspecialchars($displayError) ?></p>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- LISTE DES ARTICLES                                             -->
        <!-- ============================================================= -->
        <div id="articlesList" class="article-list">
            <?php if (empty($articles)): ?>
                <p style="text-align: center; margin-top: 30px;">Aucun article trouvé.</p>
            <?php else: ?>
                <?php foreach ($articles as $article): ?>
                    <div class="article-card" data-article-id="<?= (int)$article['id'] ?>">
                        <div class="section-header">
                            <h3 class="article-title">
                                <?= htmlspecialchars($article['title_article']) ?>
                                <span class="event-date-display">- <?= $articleManager->formatDateFrench($article['date_evmt_article']) ?></span>
                            </h3>
                            <div class="btn-icons-article">
                                <button class="btn-icon btn-icon-edit" title="Modifier" onclick="editArticle(<?= (int)$article['id'] ?>)">
                                    <img src="/cosmtt/img/icones/write.png" alt="Modifier" width="18">
                                </button>
                                <button class="btn-icon btn-icon-delete" title="Supprimer" onclick="deleteArticle(<?= (int)$article['id'] ?>, '<?= htmlspecialchars($article['title_article'], ENT_QUOTES) ?>')">
                                    <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" width="18">
                                </button>
                            </div>
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
                            <a href="/cosmtt/galeries_photos.php?title=<?= urlencode($article['title_article']) ?>&date=<?= urlencode($article['date_evmt_article']) ?>" class="lien-article" target="_blank">📸 Photos</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ============================================================= -->
        <!-- PAGINATION                                                     -->
        <!-- ============================================================= -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <a href="<?= $currentPage > 1 ? '?page=' . ($currentPage - 1) : 'javascript:void(0)' ?>" 
                   class="btn btn_fleche btnPopup-primary <?= $currentPage <= 1 ? 'disabled' : '' ?>">◄</a>

                <div class="pagination-info"><?= $currentPage ?> / <?= $totalPages ?></div>

                <a href="<?= $currentPage < $totalPages ? '?page=' . ($currentPage + 1) : 'javascript:void(0)' ?>" 
                   class="btn btn_fleche btnPopup-primary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">►</a>
            </div>
        <?php endif; ?>
    </main>

    <!-- ================================================================= -->
    <!-- SCRIPTS                                                            -->
    <!-- ================================================================= -->
    <script src="/cosmtt/admin/js/app.js"></script>
    <script>
        // Initialisation des données PHP pour le JavaScript externe
        window.CSRF_TOKEN = <?= json_encode($csrfTokenForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <script src="/cosmtt/admin/js/article.js"></script>

    <?php if (isset($flashMessage) && !empty($flashMessage)): ?>
    <script>
        alert(<?= json_encode($flashMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
    </script>
    <?php endif; ?>

</body>
</html>