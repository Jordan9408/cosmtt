/**
 * Gestion du comportement de la page des articles
 */

document.addEventListener('DOMContentLoaded', function() {
    // Eléments DOM
    const showPopupBtn = document.getElementById('showPopup');
    const addPopup = document.getElementById('addPopup');
    const cancelBtn = document.getElementById('cancelPopup');
    const articleForm = document.getElementById('articleForm');
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');
    const pdfInput = document.getElementById('pdfInput');
    const pdfPreview = document.getElementById('pdfPreview');
    const pdfDeleteFlag = document.getElementById('pdfDeleteFlag');
    const popupTitle = document.getElementById('popupTitle');
    const submitBtn = document.getElementById('submitBtn');
    const articleMode = document.getElementById('articleMode');
    const articleIdHidden = document.getElementById('articleIdHidden');
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');

    // ===== GESTION POPUP =====
    if (showPopupBtn) {
        showPopupBtn.onclick = () => {
            resetPopupForm();
            addPopup.style.display = 'flex';
        };
    }

    if (cancelBtn) {
        cancelBtn.onclick = () => addPopup.style.display = 'none';
    }

    window.onclick = (e) => { 
        if (e.target === addPopup) addPopup.style.display = 'none'; 
    };

    function resetPopupForm() {
        articleForm.reset();
        popupTitle.textContent = 'Ajouter un article';
        submitBtn.textContent = 'Ajouter';
        articleMode.value = 'add';
        articleIdHidden.value = '';
        imagePreview.innerHTML = '';
        pdfPreview.innerHTML = '';
        if (pdfDeleteFlag) pdfDeleteFlag.value = '0';
    }

    // ===== PREVIEW IMAGE =====
    if (imageInput) {
        imageInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
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
                    img.onload = function() {
                        applyOrientationClass(this);
                    };
                    imagePreview.innerHTML = '';
                    imagePreview.appendChild(img);
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.innerHTML = '';
            }
        };
    }

    // ===== VALIDATION PDF =====
    if (pdfInput) {
        pdfInput.onchange = (e) => {
            if (pdfDeleteFlag) pdfDeleteFlag.value = '0';
            const file = e.target.files[0];
            pdfPreview.innerHTML = '';
            
            if (file) {
                if (file.type !== 'application/pdf') {
                    alert('🚫 Format non autorisé. Format accepté : PDF uniquement.');
                    pdfInput.value = '';
                    return;
                }
                
                const maxSize = 2 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('⚠️ Le fichier est trop lourd. Taille maximale autorisée : 2 Mo.');
                    pdfInput.value = '';
                    return;
                }
                
                if (file.size === 0) {
                    alert('❌ Fichier vide ou corrompu.');
                    pdfInput.value = '';
                    return;
                }
                
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
                        <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" class="pdf-remove-btn" id="removePdfBtn">
                    </div>
                `;
                document.getElementById('removePdfBtn').onclick = () => {
                    pdfInput.value = ''; 
                    pdfPreview.innerHTML = ''; 
                    if (pdfDeleteFlag) pdfDeleteFlag.value = '0';
                };
            }
        };
    }

    // ===== SOUMISSION DU FORMULAIRE =====
    if (articleForm) {
        articleForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi en cours...';

            try {
                const formData = new FormData(articleForm);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'fetch' }
                });

                const data = await response.json();
                if (!data.success) {
                    alert(data.message || 'Une erreur est survenue.');
                } else {
                    addPopup.style.display = 'none';
                    window.location.reload();
                }
            } catch (err) {
                alert('❌ Erreur : ' + err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = articleMode.value === 'edit' ? 'Modifier' : 'Ajouter';
            }
        });
    }

    // ===== LOGIQUE D'ORIENTATION ET MODAL =====
    detectImageOrientation();
    attachImageClickEvents();

    if (imageModal) {
        imageModal.addEventListener('click', (e) => {
            if (e.target === imageModal) closeImageModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeImageModal();
    });

    // Rendre la pagination fluide
    document.addEventListener('click', function(e) {
        const link = e.target.closest('.pagination a:not(.disabled)');
        if (link && link.href && !link.href.includes('javascript:void(0)')) {
            e.preventDefault();
            const url = new URL(link.href);
            const page = url.searchParams.get('page') || 1;
            refreshArticlesList(page);
        }
    });

    window.onpopstate = function() {
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page') || 1;
        refreshArticlesList(page);
    };
});

// Fonctions globales (accessibles via onclick dans le HTML)

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
            document.getElementById('popupTitle').textContent = 'Modifier l\'article';
            document.getElementById('submitBtn').textContent = 'Modifier';
            document.getElementById('articleMode').value = 'edit';
            document.getElementById('articleIdHidden').value = art.id;
            document.getElementById('titre_article').value = art.title_article;
            document.getElementById('date_article').value = art.date_evmt_article;
            document.getElementById('contenu_article').value = art.contenu_article;

            if (art.img_article) {
                const img = document.createElement('img');
                img.src = `/cosmtt/docs/articles/articlesPhotos/${art.img_article}?t=${new Date().getTime()}`;
                img.alt = 'Illustration';
                img.onload = function() { applyOrientationClass(this); };
                const preview = document.getElementById('imagePreview');
                preview.innerHTML = '';
                preview.appendChild(img);
            }

            if (art.pdf_article) {
                const preview = document.getElementById('pdfPreview');
                preview.innerHTML = `
                    <div class="pdf-info">
                        <svg class="pdf-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><path d="M14.5 13L12 15.5L9.5 13V13A1.5 1.5 0 0 1 11 11.5a1.5 1.5 0 0 1 3 0 1.5 1.5 0 0 1-.5 1.5z"/><path d="M12 15l1.5 1.5L16 15v1a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1z"/></svg>
                        <span>${art.pdf_article} ✅</span>
                        <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" class="pdf-remove-btn" onclick="document.getElementById('pdfInput').value = ''; document.getElementById('pdfPreview').innerHTML = ''; document.getElementById('pdfDeleteFlag').value = '1';">
                    </div>
                `;
            }
            document.getElementById('addPopup').style.display = 'flex';
        }
    } catch (err) {
        alert('❌ Erreur lors de la récupération.');
    }
}

async function deleteArticle(id, title) {
    if (!confirm(`Supprimer l'article "${title}" ?\nCette action est irréversible.`)) return;

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
            let currentPage = parseInt(document.getElementById('currentPageHidden').value) || 1;
            let targetPage = data.total_pages < currentPage ? Math.max(1, data.total_pages) : currentPage;
            refreshArticlesList(targetPage);
        } else {
            alert(data.message || '❌ Erreur lors de la suppression.');
        }
    } catch (err) {
        alert('❌ Erreur réseau.');
    }
}

function applyOrientationClass(img) {
    img.classList.remove('portrait', 'landscape');
    if (img.naturalHeight > img.naturalWidth) {
        img.classList.add('portrait');
    } else {
        img.classList.add('landscape');
    }
}

function detectImageOrientation() {
    document.querySelectorAll('.article-image').forEach(img => {
        if (img.complete) applyOrientationClass(img);
        else img.addEventListener('load', function() { applyOrientationClass(this); });
    });
}

function attachImageClickEvents() {
    document.querySelectorAll('.article-image').forEach(img => {
        img.onclick = function() { openImageModal(this.src); };
    });
}

async function refreshArticlesList(page) {
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('page', page);

        const response = await fetch(url.toString(), {
            headers: { 'X-Requested-With': 'fetch' }
        });

        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');

        document.getElementById('articlesList').innerHTML = doc.getElementById('articlesList').innerHTML;
        
        const newPagination = doc.querySelector('.pagination');
        const currentPagination = document.querySelector('.pagination');
        if (newPagination && currentPagination) currentPagination.innerHTML = newPagination.innerHTML;
        else if (currentPagination) currentPagination.remove();

        document.getElementById('currentPageHidden').value = page;
        window.history.pushState({ page: page }, '', url.toString());
        document.getElementById('articlesList').scrollIntoView({ behavior: 'smooth', block: 'start' });

        setTimeout(() => {
            detectImageOrientation();
            attachImageClickEvents();
        }, 100);
    } catch (err) {
        window.location.reload();
    }
}