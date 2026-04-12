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

    <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/colors_group.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/popup.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie.css">
    <style>
        .popup-content {
            /* padding: 20px; */
            /* max-height: 80vh; */
            /* overflow-y: auto; */
            text-align: start;
        }
        .article-list { 
            display: grid;

            justify-items: center;
            gap: 20px;
            margin: 20px 0;
        }
        .article-card { 
            width: 40em;
            max-width: 40em;
            margin: 0 auto;
            border: 1px solid #ddd; 
            padding: 20px; 
            margin-bottom: 10px;
            border-radius: 8px;
            text-align: center;
        }
        .article-title { 
            font-size: 1.4em; 
            margin-bottom: 10px;
            font-style: italic;
        }
        .event-date-display {
            font-size: 0.9em;
            color: var(--thirteenth-color);
            font-weight: normal;
            font-style: italic;
        }
        /* font-size: 0.9em; color: #666; */
        .article-date { 
            color: #666;
            margin-bottom: 15px; 
        }
        .article-image { 
            width: auto;
            height: 200px;
            object-fit: contain;
            border-radius: 4px;
            display: block;
            margin: 10px auto;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        /* .article-image:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        } */
        
        /* Portrait et paysage - même hauteur */
        .article-image.portrait {
            height: 200px;
        }
        
        .article-image.landscape {
            height: 200px;
        }
        
        /* Modal pour agrandissement */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
        }
        
        .image-modal.active {
            display: flex;
        }
        
        .image-modal-content {
            position: relative;
            max-width: 90vw;
            max-height: 90vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-modal-content img {
            width: auto;
            height: 85vh;
            object-fit: contain;
            border-radius: 4px;
        }
        
        .image-modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 32px;
            font-weight: bold;
            color: white;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
            transition: transform 0.2s ease;
        }
        
        .image-modal-close:hover {
            transform: scale(1.2);
        }

        .article-content-preview { 
            margin: 10px 0; 
            color: #555; 
        }
        .section-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0;
        }
        .file-info { 
            font-size: 0.9em; 
            color: #888; 
            margin-top: 5px; 
        }
        .publication-info {
            color: #666;
            font-size: 0.9em;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .lienArticles a,
        .liens-groupes .lien-article {
            font-size: 1em;
            padding: 0px;
            text-decoration: none;
            background: #f9f9f9;
            color: blue;
            display: block;
            transition: background-color 0.3s, color 0.3s;
            width: auto;
        }
        .liens-groupes {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }
        .docs-input-popup {
            display: flex;
            justify-content: flex-start;
            flex-direction: column;
            gap: 5px;
        }
        #pdfPreview {
            margin-top: 5px;
        }
        .pdf-info {
            color: green;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pdf-icon {
            width: 20px;
            height: 20px;
            vertical-align: middle;
        }
        .pdf-remove-btn {
            width: 18px;
            cursor: pointer;
            margin: 0;
            transition: transform 0.2s ease;
        }
        .pdf-remove-btn:hover {
            transform: scale(1.1);
        }
        .btn-icons-article {
            display: flex;
            position: absolute;
            right: 27em;
        }
        
        #imagePreview img {
            max-width: 120px;
            max-height: 180px;
            height: auto;
            width: auto;
            object-fit: contain;
            border-radius: 4px;
            margin-top: 5px;
            /* display: block; */
        }
        
        #imagePreview img.portrait {
            max-width: 80px;
            max-height: 180px;
        }
        
        #imagePreview img.landscape {
            max-width: 120px;
            max-height: 90px;
        }
    </style>
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
        <!-- MODAL AGRANDISSEMENT IMAGE                                     -->
        <!-- ============================================================= -->
        <div class="image-modal" id="imageModal">
            <div class="image-modal-content">
                <button class="image-modal-close" onclick="closeImageModal()">&times;</button>
                <img id="modalImage" src="" alt="Image agrandie">
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
                                 alt="Illustration" class="article-image" title="Agrandir l'image">
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
        // Variables globales
        window.CSRF_TOKEN = <?= json_encode($csrfTokenForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        // ===== POPUP =====
        const showPopupBtn = document.getElementById('showPopup');
        const addPopup = document.getElementById('addPopup');
        const cancelBtn = document.getElementById('cancelPopup');
        const articleForm = document.getElementById('articleForm');
        const imageInput = document.getElementById('imageInput');
        const imagePreview = document.getElementById('imagePreview');
        const popupTitle = document.getElementById('popupTitle');
        const submitBtn = document.getElementById('submitBtn');
        const articleMode = document.getElementById('articleMode');
        const articleIdHidden = document.getElementById('articleIdHidden');

        // Ouvrir le popup en mode ajout
        showPopupBtn.onclick = () => {
            resetPopupForm();
            addPopup.style.display = 'flex';
        };

        // Fermer le popup
        cancelBtn.onclick = () => addPopup.style.display = 'none';
        window.onclick = (e) => { if (e.target === addPopup) addPopup.style.display = 'none'; };

        // Réinitialiser le formulaire
        function resetPopupForm() {
            articleForm.reset();
            popupTitle.textContent = 'Ajouter un article';
            submitBtn.textContent = 'Ajouter';
            articleMode.value = 'add';
            articleIdHidden.value = '';
            imagePreview.innerHTML = '';
            pdfPreview.innerHTML = '';
            document.getElementById('pdfDeleteFlag').value = '0';
        }

        // ===== PREVIEW IMAGE =====
        imageInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                // Vérification côté client
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format non autorisé. Formats acceptés : JPG, JPEG ou PNG');
                    imageInput.value = '';
                    imagePreview.innerHTML = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (ev) => {
                    const img = document.createElement('img');
                    img.src = ev.target.result;
                    img.alt = 'Illustration';
                    
                    // Détecter l'orientation une fois chargée
                    img.onload = function() {
                        img.classList.remove('portrait', 'landscape');
                        if (this.naturalHeight > this.naturalWidth) {
                            img.classList.add('portrait');
                        } else {
                            img.classList.add('landscape');
                        }
                    };
                    
                    imagePreview.innerHTML = '';
                    imagePreview.appendChild(img);
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.innerHTML = '';
            }
        };

        // ===== VALIDATION PDF CLIENT-SIDE (facultatif) =====
        const pdfInput = document.getElementById('pdfInput');
        const pdfPreview = document.getElementById('pdfPreview');
        const pdfDeleteFlag = document.getElementById('pdfDeleteFlag');
        
        pdfInput.onchange = (e) => {
            // Si l'utilisateur sélectionne un nouveau PDF, réinitialiser le flag de suppression
            pdfDeleteFlag.value = '0';
            const file = e.target.files[0];
            pdfPreview.innerHTML = ''; // Reset
            
            if (file) {
                // 1. FORMAT PDF UNIQUEMENT
                if (file.type !== 'application/pdf') {
                    alert('🚫 Format non autorisé. Format accepté : PDF uniquement.');
                    pdfInput.value = '';
                    return;
                }
                
                // 2. TAILLE MAX 2 Mo
                const maxSize = 2 * 1024 * 1024; // 2 Mo
                if (file.size > maxSize) {
                    alert('⚠️ Le fichier est trop lourd. Taille maximale autorisée : 2 Mo.');
                    pdfInput.value = '';
                    return;
                }
                
                // 3. FICHIER VIDE
                if (file.size === 0) {
                    alert('❌ Fichier vide ou corrompu.');
                    pdfInput.value = '';
                    return;
                }
                
                // ✅ VALIDE : afficher preview
                const sizeKo = (file.size / 1024).toFixed(0);
                pdfPreview.innerHTML = `
                    <div class="pdf-info">
                        <svg class="pdf-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                            <path d="M14.5 13L12 15.5L9.5 13V13A1.5 1.5 0 0 1 11 11.5a1.5 1.5 0 0 1 3 0 1.5 1.5 0 0 1-.5 1.5z"/>
                            <path d="M12 15l1.5 1.5L16 15v1a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1z"/>
                        </svg>
                        <span>${file.name} (${sizeKo} Ko) ✅</span>
                        <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" class="pdf-remove-btn" onclick="pdfInput.value = ''; pdfPreview.innerHTML = ''; pdfDeleteFlag.value = '0';">
                    </div>
                `;
            }
        };

        // ===== SOUMISSION DU FORMULAIRE (AJAX) =====
articleForm.addEventListener('submit', async function(e) {

            e.preventDefault();
            
            submitBtn.disabled = true;
            const isEditMode = articleMode.value === 'edit';
            submitBtn.textContent = 'Envoi en cours...';

            try {
                const formData = new FormData(articleForm);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'fetch'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const responseText = await response.text();
                console.log('Response:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was:', responseText);
                    throw new Error('Réponse serveur invalide');
                }
                
                if (!data.success) {
                    alert(data.message || 'Une erreur est survenue.');
                } else {
                    addPopup.style.display = 'none';
                    // Recharger la page pour afficher les modifications
                    window.location.reload();
                    await refreshArticlesList(document.getElementById('currentPageHidden').value);
                }
            } catch (err) {
                console.error('Erreur soumission:', err);
                alert('❌ Erreur : ' + err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = articleMode.value === 'edit' ? 'Modifier' : 'Ajouter';
            }
        });

        // ===== MODIFIER UN ARTICLE =====
        async function editArticle(id) {
            try {
                const formData = new FormData();
                formData.append('action', 'get_article');
                formData.append('id', id);
                formData.append('csrf_token', window.CSRF_TOKEN);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'fetch' }
                });

                const data = await response.json();

                if (data.success && data.article) {
                    const art = data.article;
                    popupTitle.textContent = 'Modifier l\'article';
                    submitBtn.textContent = 'Modifier';
                    articleMode.value = 'edit';
                    articleIdHidden.value = art.id;

                    document.getElementById('titre_article').value = art.title_article;
                    document.getElementById('date_article').value = art.date_evmt_article;
                    document.getElementById('contenu_article').value = art.contenu_article;

                    // Afficher l'image existante
                    if (art.img_article) {
                        const img = document.createElement('img');
                        img.src = `/cosmtt/docs/articles/articlesPhotos/${art.img_article}?t=${new Date().getTime()}`; // Ajout d'un timestamp pour éviter le cache
                        img.alt = 'Illustration';
                        
                        // Détecter l'orientation une fois chargée
                        img.onload = function() {
                            img.classList.remove('portrait', 'landscape');
                            if (this.naturalHeight > this.naturalWidth) {
                                img.classList.add('portrait');
                            } else {
                                img.classList.add('landscape');
                            }
                        };
                        
                        imagePreview.innerHTML = '';
                        imagePreview.appendChild(img);
                    }

                    // Afficher le PDF existant
                    if (art.pdf_article) {
                        const fileSize = art.pdf_size || 0; // Récupérer la taille si disponible
                        const sizeKo = (fileSize / 1024).toFixed(0);
                        pdfPreview.innerHTML = `
                            <div class="pdf-info">
                                <svg class="pdf-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14,2 14,8 20,8"/>
                                    <path d="M14.5 13L12 15.5L9.5 13V13A1.5 1.5 0 0 1 11 11.5a1.5 1.5 0 0 1 3 0 1.5 1.5 0 0 1-.5 1.5z"/>
                                    <path d="M12 15l1.5 1.5L16 15v1a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1z"/>
                                </svg>
                                <span>${art.pdf_article}${sizeKo > 0 ? ' (' + sizeKo + ' Ko)' : ''} ✅</span>
                                <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" class="pdf-remove-btn" onclick="pdfInput.value = ''; pdfPreview.innerHTML = ''; pdfDeleteFlag.value = '1';">
                            </div>
                        `;
                    } else {
                        pdfPreview.innerHTML = '';
                    }

                    addPopup.style.display = 'flex';

                } else {
                    alert(data.message || 'Erreur lors de la récupération de l\'article.');
                }
            } catch (err) {
                console.error('Erreur editArticle:', err);
                alert('❌ Erreur réseau.');
            }
        }

        // ===== SUPPRIMER UN ARTICLE =====
        async function deleteArticle(id, title) {

            if (!confirm(`Supprimer l'article "${title}" ?\nCette action est irréversible.`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete_article');
                formData.append('id', id);
                formData.append('csrf_token', window.CSRF_TOKEN);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'fetch' }
                });

                const data = await response.json();

                if (data.success) {
                    // Calculer la page cible
                    let currentPage = parseInt(document.getElementById('currentPageHidden').value) || 1;
                    let targetPage = currentPage;

                    // Si la page actuelle est devenue supérieure au nombre total de pages, on recule
                    if (data.total_pages < currentPage) {
                        targetPage = Math.max(1, data.total_pages);
                    }

                    // Rafraîchir dynamiquement le contenu
                    await refreshArticlesList(targetPage);
                } else {
                    alert(data.message || '❌ Erreur lors de la suppression.');
                }
            } catch (err) {
                console.error('Erreur deleteArticle:', err);
                alert('❌ Erreur réseau.');
            }
        }

        // ===== GESTION DU MODAL D'AGRANDISSEMENT =====
        const imageModal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');

        /**
         * Ouvre le modal avec l'image agrandie
         */
        function openImageModal(src) {
            modalImage.src = src;
            imageModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        /**
         * Ferme le modal d'agrandissement
         */
        function closeImageModal() {
            imageModal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Fermer au clic en dehors de l'image
        imageModal.addEventListener('click', function(e) {
            if (e.target === imageModal) {
                closeImageModal();
            }
        });

        // Fermer avec la touche Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });

        /**
         * Attache les événements de clic aux images de la liste
         */
        function attachImageClickEvents() {
            const images = document.querySelectorAll('.article-image');
            images.forEach(img => {
                // Retirer les anciens événements en clonant
                const newImg = img.cloneNode(true);
                img.parentNode.replaceChild(newImg, img);
                
                // Ajouter le nouvel événement
                newImg.addEventListener('click', function() {
                    openImageModal(this.src);
                });
            });
        }

        /**
         * Rafraîchit dynamiquement la liste des articles et la pagination
         * sans recharger toute la page (AJAX partial refresh).
         */
        async function refreshArticlesList(page) {
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('page', page);

                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'fetch' }
                });

                if (!response.ok) throw new Error('Erreur de chargement');

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Mettre à jour la liste des articles
                const newList = doc.getElementById('articlesList');
                if (newList) {
                    document.getElementById('articlesList').innerHTML = newList.innerHTML;
                }

                // Mettre à jour la pagination
                const newPagination = doc.querySelector('.pagination');
                const currentPagination = document.querySelector('.pagination');
                
                if (newPagination && currentPagination) {
                    currentPagination.innerHTML = newPagination.innerHTML;
                } else if (newPagination && !currentPagination) {
                    // Si la pagination n'existait pas (1 seule page) mais qu'elle doit exister (peu probable en suppression)
                    const main = document.querySelector('main');
                    main.appendChild(newPagination);
                } else if (!newPagination && currentPagination) {
                    // Si elle existait mais ne doit plus exister
                    currentPagination.remove();
                }

                // Mettre à jour le champ caché du numéro de page
                const hiddenPage = document.getElementById('currentPageHidden');
                if (hiddenPage) hiddenPage.value = page;

                // Mettre à jour l'URL sans recharger la page
                window.history.pushState({ page: page }, '', url.toString());

                // Faire défiler vers le haut de la liste pour montrer le changement
                document.getElementById('articlesList').scrollIntoView({ behavior: 'smooth', block: 'start' });

                // Réappliquer la détection d'orientation des images
                setTimeout(() => detectImageOrientation(), 100);

                // Réattacher les événements de clic aux images
                setTimeout(() => attachImageClickEvents(), 100);

            } catch (err) {
                console.error('Erreur refreshArticlesList:', err);
                // En cas d'erreur AJAX critique, on force un reload classique
                window.location.reload();
            }
        }

        // Ecouter les changements d'historique (boutons précédent/suivant du navigateur)
        window.onpopstate = function(event) {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 1;
            refreshArticlesList(page);
        };

        // Rendre la pagination fluide (AJAX) lors du clic sur les flèches
        document.addEventListener('click', function(e) {
            const link = e.target.closest('.pagination a:not(.disabled)');
            if (link && link.href && !link.href.includes('javascript:void(0)')) {
                e.preventDefault();
                const url = new URL(link.href);
                const page = url.searchParams.get('page') || 1;
                refreshArticlesList(page);
            }
        });

        // ===== DÉTECTION ORIENTATION IMAGES =====
        /**
         * Détecte l'orientation de chaque image (portrait/paysage)
         * et ajoute la classe CSS appropriée
         */
        function detectImageOrientation() {
            const images = document.querySelectorAll('.article-image');
            images.forEach(img => {
                // Attendre que l'image soit chargée
                if (img.complete) {
                    applyOrientationClass(img);
                } else {
                    img.addEventListener('load', function() {
                        applyOrientationClass(this);
                    });
                }
            });
        }

        function applyOrientationClass(img) {
            // Supprimer les classes existantes
            img.classList.remove('portrait', 'landscape');
            
            // Déterminer l'orientation
            if (img.naturalHeight > img.naturalWidth) {
                img.classList.add('portrait');
            } else {
                img.classList.add('landscape');
            }
        }

        // Appeler au chargement initial
        document.addEventListener('DOMContentLoaded', function() {
            detectImageOrientation();
            attachImageClickEvents();
        });
    </script>

    <?php if (isset($flashMessage) && !empty($flashMessage)): ?>
    <script>
        alert(<?= json_encode($flashMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
    </script>
    <?php endif; ?>

</body>
</html>
