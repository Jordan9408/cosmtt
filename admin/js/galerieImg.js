/**
 * galerieImg.js
 * Scripts de gestion de la galerie photos (admin)
 * 
 * Fonctionnalités :
 * - Gestion du formulaire d'ajout/modification d'événement
 * - Upload et prévisualisation d'images
 * - Drag & drop pour réorganiser les images
 * - Vérification de conflits
 * - Compression client des images
 * - Lightbox pour afficher les images en plein écran
 */

const galerieAdmin = (() => {
    // Variables globales du module
    let selectedFiles = [];
    let currentEditEventId = null;
    let conflictCheckResult = null;
    let CSRF_TOKEN = '';
    let MAX_IMAGE_SIZE = 0;
    let eventImages = {};
    let currentImageIndex = 0;
    let currentImageList = [];

    // =====================================================================
    // INITIALISATION ET CONFIGURATION
    // =====================================================================

    function init(config) {
        CSRF_TOKEN = config.csrfToken;
        MAX_IMAGE_SIZE = config.maxImageSize;
        eventImages = config.eventImages;

        setupEventListeners();
        setupDragDropZone();
        initLightbox();
    }

    // =====================================================================
    // GESTION DES ÉVÉNEMENTS
    // =====================================================================

    function setupEventListeners() {
        // Bouton "Ajouter un événement"
        const showPopupBtn = document.getElementById('showPopup');
        if (showPopupBtn) {
            showPopupBtn.addEventListener('click', () => {
                resetPopupToAddMode();
                document.getElementById('addPopup').style.display = 'block';
            });
        }

        // Bouton "Annuler" du formulaire
        const cancelBtn = document.getElementById('cancelPopup');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                if (selectedFiles.length === 0 || 
                    confirm('Êtes-vous sûr de vouloir annuler ? Les images sélectionnées seront perdues.')) {
                    document.getElementById('addPopup').style.display = 'none';
                    resetForm();
                }
            });
        }

        // Soumission du formulaire
        const form = document.getElementById('eventForm');
        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }
    }

    function setupDragDropZone() {
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        if (!dropZone) return;

        // Empêcher les comportements par défaut
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        // Feedback visuel
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => 
                dropZone.classList.add('drag-over'));
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => 
                dropZone.classList.remove('drag-over'));
        });

        // Gestion du drop
        dropZone.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files));

        // Gestion du change du file input
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                handleFiles(this.files);
            });
        }
    }

    // =====================================================================
    // GESTION DES FICHIERS
    // =====================================================================

    function handleFiles(files) {
        Array.from(files).forEach(file => {
            // Vérifier que c'est une image
            const isImage = file.type.match('image.*');
            if (!isImage) {
                alert(`Format non supporté: ${file.name}`);
                return;
            }

            // Vérifier la taille
            if (file.size > MAX_IMAGE_SIZE) {
                alert(`Image trop lourde: ${file.name}`);
                return;
            }

            // Vérifier les doublons
            if (selectedFiles.some(f => f.name === file.name)) {
                alert(`L'image est déjà ajoutée: ${file.name}`);
                return;
            }

            selectedFiles.push(file);

            // Créer l'élément de prévisualisation
            const div = document.createElement('div');
            const eventMode = document.getElementById('eventMode').value;
            div.className = 'preview-item' + (eventMode === 'edit' ? ' new-image' : '');
            div.draggable = true;
            div.dataset.name = file.name;

            if (eventMode === 'edit') {
                const existingGrid = document.getElementById('existingMediaGrid');
                existingGrid.appendChild(div);
            } else {
                document.getElementById('previewGrid').appendChild(div);
            }

            createPreview(file, div);
        });
    }

    function createPreview(file, div) {
        const reader = new FileReader();
        reader.onload = (e) => {
            div.innerHTML = `
                <img src="${e.target.result}">
                <button type="button" class="remove-btn" onclick="galerieAdmin.removeFile('${file.name}', this)">×</button>
                <button type="button" class="move-btn move-left" onclick="galerieAdmin.moveItem(this, -1)">❮</button>
                <button type="button" class="move-btn move-right" onclick="galerieAdmin.moveItem(this, 1)">❯</button>
                <div class="file-info">${(file.size/1024/1024).toFixed(2)} Mo</div>
            `;
            // Réinitialiser le drag & drop pour le nouvel élément
            initItemDragDrop(div);
        };
        reader.readAsDataURL(file);
    }

    // =====================================================================
    // GESTION D'ÉDITION D'ÉVÉNEMENT
    // =====================================================================

    async function startEditEvent(btn) {
        const eventId = btn.dataset.id;
        const titre = btn.dataset.titre;
        const date = btn.dataset.date;

        currentEditEventId = eventId;

        document.getElementById('popupTitle').textContent = 'Modifier l\'événement';
        document.getElementById('eventMode').value = 'edit';
        document.getElementById('eventIdHidden').value = eventId;
        document.getElementById('titre_evenement').value = titre;
        document.getElementById('date_evenement').value = date;
        document.getElementById('mediaIdsToDelete').value = '';
        document.getElementById('submitBtn').textContent = 'Modifier';

        await loadExistingImages(eventId);

        selectedFiles = [];
        document.getElementById('previewGrid').innerHTML = '';

        document.getElementById('addPopup').style.display = 'block';
    }

    async function loadExistingImages(eventId) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_event_images');
            formData.append('event_id', eventId);
            formData.append('csrf_token', CSRF_TOKEN);

            const response = await fetch(window.location.href, { 
                method: 'POST', 
                body: formData 
            });
            const data = await response.json();

            if (data.success && data.images && data.images.length > 0) {
                const existingGrid = document.getElementById('existingMediaGrid');
                existingGrid.innerHTML = '';

                data.images.forEach(image => {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.dataset.mediaId = image.id;
                    div.draggable = true;
                    div.innerHTML = `
                        <img src="${image.thumb}" alt="Photo existante">
                        <button type="button" class="remove-btn" onclick="galerieAdmin.removeExistingFile(${image.id}, this)">×</button>
                        <button type="button" class="move-btn move-left" onclick="galerieAdmin.moveItem(this, -1)" title="Déplacer à gauche">❮</button>
                        <button type="button" class="move-btn move-right" onclick="galerieAdmin.moveItem(this, 1)" title="Déplacer à droite">❯</button>
                        <div class="file-info">Existant</div>
                    `;
                    existingGrid.appendChild(div);
                    initItemDragDrop(div);
                });

                document.getElementById('existingMediaSection').style.display = 'block';
            } else {
                document.getElementById('existingMediaSection').style.display = 'none';
            }
        } catch (err) {
            console.error('Erreur chargement images existantes:', err);
            document.getElementById('existingMediaSection').style.display = 'none';
        }
    }

    function removeExistingFile(mediaId, btn) {
        btn.parentElement.remove();

        const deleteListInput = document.getElementById('mediaIdsToDelete');
        const currentList = deleteListInput.value.split(',').filter(id => id !== '');
        if (!currentList.includes(String(mediaId))) {
            currentList.push(String(mediaId));
        }
        deleteListInput.value = currentList.join(',');
    }

    function removeFile(name, btn) {
        selectedFiles = selectedFiles.filter(f => f.name !== name);
        btn.parentElement.remove();
    }

    // =====================================================================
    // RÉORGANISATION DES IMAGES
    // =====================================================================

    function moveItem(btn, direction) {
        const item = btn.closest('.preview-item');
        if (!item) return;

        if (direction === -1) {
            const prev = item.previousElementSibling;
            if (prev) {
                prev.before(item);
            }
        } else {
            const next = item.nextElementSibling;
            if (next) {
                next.after(item);
            }
        }
    }

    function initItemDragDrop(item) {
        let draggedItem = null;
        const container = item.parentElement;

        item.addEventListener('dragstart', (e) => {
            draggedItem = item;
            setTimeout(() => item.classList.add('dragging'), 0);
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            container.querySelectorAll('.drag-over').forEach(el => 
                el.classList.remove('drag-over'));
        });

        container.addEventListener('dragover', (e) => {
            if (!draggedItem) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        container.addEventListener('dragenter', e => {
            const target = e.target.closest('.preview-item');
            if (target && target !== draggedItem) {
                target.classList.add('drag-over');
            }
        });

        container.addEventListener('dragleave', e => {
            const target = e.target.closest('.preview-item');
            if (target) {
                target.classList.remove('drag-over');
            }
        });

        container.addEventListener('drop', (e) => {
            if (!draggedItem) return;
            e.preventDefault();
            e.stopPropagation();

            const dropTarget = e.target.closest('.preview-item');
            if (dropTarget) dropTarget.classList.remove('drag-over');

            if (dropTarget && dropTarget !== draggedItem && container.contains(dropTarget)) {
                const rect = dropTarget.getBoundingClientRect();
                const isAfter = (e.clientX - rect.left) > (rect.width / 2);
                dropTarget.parentNode.insertBefore(draggedItem, 
                    isAfter ? dropTarget.nextSibling : dropTarget);
            }
        });
    }

    // =====================================================================
    // VÉRIFICATION ET SOUMISSION DU FORMULAIRE
    // =====================================================================

    async function checkForConflicts(titre, date, excludeEventId = null) {
        try {
            const formData = new FormData();
            formData.append('action', 'check_event');
            formData.append('titre', titre);
            formData.append('date', date);
            formData.append('csrf_token', CSRF_TOKEN);
            if (excludeEventId) {
                formData.append('exclude_id', excludeEventId);
            }

            const response = await fetch(window.location.href, { 
                method: 'POST', 
                body: formData 
            });
            if (!response.ok) throw new Error('Erreur réseau');
            return await response.json();
        } catch (err) {
            console.error("Erreur vérification événement:", err);
            return null;
        }
    }

    async function handleFormSubmit(e) {
        e.preventDefault();
        const submitBtn = document.getElementById('submitBtn');
        const eventMode = document.getElementById('eventMode').value;
        submitBtn.disabled = true;
        submitBtn.textContent = eventMode === 'edit' ? 'Modification...' : 'Ajout...';

        let finalOrder = [];
        const orderedNewFiles = [];

        // Récupérer l'ordre des fichiers
        if (eventMode === 'edit') {
            const grid = document.getElementById('existingMediaGrid');

            grid.querySelectorAll('.preview-item').forEach(item => {
                if (item.dataset.mediaId) {
                    finalOrder.push({ type: 'existing', id: item.dataset.mediaId });
                } else if (item.dataset.name) {
                    finalOrder.push({ type: 'new', name: item.dataset.name });
                }
            });
        } else { // Mode ajout
            const grid = document.getElementById('previewGrid');
            grid.querySelectorAll('.preview-item').forEach(item => {
                if (item.dataset.name) {
                    finalOrder.push({ type: 'new', name: item.dataset.name });
                }
            });
        }

        // Réordonner le tableau selectedFiles
        finalOrder.forEach(item => {
            if (item.type === 'new') {
                const file = selectedFiles.find(f => f.name === item.name);
                if (file) {
                    orderedNewFiles.push(file);
                }
            }
        });
        selectedFiles = orderedNewFiles;
        document.getElementById('finalOrderInput').value = JSON.stringify(finalOrder);
        
        // Vérifier les images
        if (eventMode === 'add' && selectedFiles.length === 0) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Ajouter';
            alert('Ajoutez au moins une image avant de soumettre.');
            return;
        }

        const titre = document.getElementById('titre_evenement').value.trim();
        const date = document.getElementById('date_evenement').value;

        if (!titre || !date) {
            submitBtn.disabled = false;
            submitBtn.textContent = eventMode === 'edit' ? 'Modifier' : 'Ajouter';
            alert('Veuillez renseigner le titre et la date de l\'événement.');
            return;
        }

        const excludeId = eventMode === 'edit' ? currentEditEventId : null;
        const checkResult = await checkForConflicts(titre, date, excludeId);

        if (!checkResult) {
            // Laisser le serveur gérer
        } else {
            if (checkResult.same_title_and_date) {
                const msg = `Un événement "${titre}" existe déjà à cette date.\n\nVoulez-vous ajouter ces images à cet événement existant ?`;
                if (!confirm(msg)) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = eventMode === 'edit' ? 'Modifier' : 'Ajouter';
                    return;
                }
                document.getElementById('existingEventId').value = checkResult.id;
                document.getElementById('forceAdd').value = '0';
            } else if (checkResult.same_title_same_season) {
                const conflict = checkResult.conflicts.find(c => c.type === 'same_title_same_season');
                alert('❌ ' + (eventMode === 'edit' ? 'MODIFICATION' : 'AJOUT') + ' IMPOSSIBLE\n\n' + 
                    conflict.message + '\n\nVous ne pouvez pas avoir deux événements avec le même titre dans la même saison.');
                submitBtn.disabled = false;
                submitBtn.textContent = eventMode === 'edit' ? 'Modifier' : 'Ajouter';
                return;
            } else if (checkResult.same_date) {
                const conflict = checkResult.conflicts.find(c => c.type === 'same_date');
                alert('❌ ' + (eventMode === 'edit' ? 'MODIFICATION' : 'AJOUT') + ' IMPOSSIBLE\n\n' + 
                    conflict.message + '\n\nVous ne pouvez pas avoir deux événements à la même date.');
                submitBtn.disabled = false;
                submitBtn.textContent = eventMode === 'edit' ? 'Modifier' : 'Ajouter';
                return;
            } else {
                document.getElementById('existingEventId').value = '';
                document.getElementById('forceAdd').value = '0';
            }
        }

        const loading = document.getElementById('loadingOverlay');
        const progress = document.getElementById('progressText');
        loading.classList.add('show');
        submitBtn.textContent = eventMode === 'edit' ? 'Modification...' : 'Ajout...';

        try {
            const processedFiles = [];
            
            for (let i = 0; i < selectedFiles.length; i++) {
                const file = selectedFiles[i];
                progress.textContent = `Traitement : ${Math.round(((i+1) / selectedFiles.length) * 100)}%`;
                const data = await compressImage(file);
                processedFiles.push({ name: file.name, type: file.type, data: data });
            }
            
            document.getElementById('compressedFilesData').value = JSON.stringify(processedFiles);
            document.getElementById('fileInput').value = '';
            document.getElementById('eventForm').submit();
            
        } catch (err) {
            console.error(err);
            alert('Erreur lors du traitement des images.');
            loading.classList.remove('show');
            submitBtn.disabled = false;
            submitBtn.textContent = eventMode === 'edit' ? 'Modifier' : 'Ajouter';
        }
    }

    function compressImage(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    let w = img.width;
                    let h = img.height;
                    const max = 1920;
                    
                    if (w > max || h > max) {
                        const ratio = Math.min(max / w, max / h);
                        w *= ratio;
                        h *= ratio;
                    }
                    
                    canvas.width = Math.floor(w);
                    canvas.height = Math.floor(h);
                    ctx.drawImage(img, 0, 0, Math.floor(w), Math.floor(h));
                    resolve(canvas.toDataURL('image/jpeg', 0.9));
                };
                img.onerror = () => reject(new Error("Erreur chargement image"));
                img.src = e.target.result;
            };
            reader.onerror = () => reject(new Error("Erreur lecture fichier"));
            reader.readAsDataURL(file);
        });
    }

    // =====================================================================
    // SUPPRESSION D'ÉVÉNEMENT
    // =====================================================================

    async function deleteEvent(eventId, titre) {
        if (!confirm(`⚠️ Êtes-vous sûr de vouloir supprimer l'événement "${titre}" et toutes ses photos ?\n\nCette action est irréversible.`)) {
            return;
        }

        try {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = parseInt(urlParams.get('page') || '1', 10);

            const formData = new FormData();
            formData.append('action', 'delete_event');
            formData.append('id', eventId);
            formData.append('current_page', currentPage);
            formData.append('csrf_token', CSRF_TOKEN);

            const resp = await fetch(window.location.href, { 
                method: 'POST', 
                body: formData 
            });
            const data = await resp.json();

            if (data.success) {
                const baseUrl = window.location.pathname;

                if (data.total_events === 0) {
                    window.location.href = baseUrl;
                } else {
                    let redirectPage;
                    if (currentPage < data.total_pages) {
                        redirectPage = currentPage;
                    } else {
                        redirectPage = data.total_pages;
                    }
                    
                    if (redirectPage > 1) {
                        window.location.href = baseUrl + '?page=' + redirectPage;
                    } else {
                        window.location.href = baseUrl;
                    }
                }
            } else {
                alert('❌ Erreur lors de la suppression : ' + (data.message || 'Erreur inconnue'));
            }
        } catch (err) {
            console.error(err);
            alert('❌ Erreur réseau lors de la suppression.');
        }
    }

    function resetPopupToAddMode() {
        document.getElementById('popupTitle').textContent = 'Ajouter un événement';
        document.getElementById('eventMode').value = 'add';
        document.getElementById('eventIdHidden').value = '';
        document.getElementById('existingMediaSection').style.display = 'none';
        document.getElementById('existingMediaGrid').innerHTML = '';
        currentEditEventId = null;
        resetForm();
    }

    function resetForm() {
        document.getElementById('eventForm').reset();
        selectedFiles = [];
        document.getElementById('previewGrid').innerHTML = '';
        document.getElementById('existingEventId').value = '';
        document.getElementById('forceAdd').value = '0';
        document.getElementById('mediaIdsToDelete').value = '';
        conflictCheckResult = null;

        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Ajouter';
        }
    }

    // =====================================================================
    // LIGHTBOX
    // =====================================================================

    function initLightbox() {
        const lightbox = document.getElementById('lightbox');
        if (!lightbox) return;

        // Clavier
        document.addEventListener('keydown', function(e) {
            if (lightbox.style.display === 'flex') {
                if (e.key === 'Escape') {
                    closeLightbox();
                } else if (e.key === 'ArrowLeft') {
                    changeImage(-1);
                } else if (e.key === 'ArrowRight') {
                    changeImage(1);
                }
            }
        });

        // Click sur le fond
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });
    }

    function openLightbox(event, index, eventId) {
        event.preventDefault();
        const lightbox = document.getElementById('lightbox');
        const lightboxImage = document.getElementById('lightboxImage');

        const eventKey = String(eventId);

        if (!eventImages || !eventImages[eventKey]) {
            console.error('Aucune image trouvée pour l\'événement:', eventId);
            alert('Erreur: Impossible d\'afficher l\'image.');
            return;
        }

        currentImageList = eventImages[eventKey].map(media => 
            media.chemin_fichier || media.chemin_miniature
        );

        currentImageIndex = index;
        updateLightbox();
        lightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        const lightbox = document.getElementById('lightbox');
        lightbox.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function changeImage(direction) {
        currentImageIndex += direction;
        if (currentImageIndex >= currentImageList.length) {
            currentImageIndex = 0;
        } else if (currentImageIndex < 0) {
            currentImageIndex = currentImageList.length - 1;
        }
        updateLightbox();
    }

    function updateLightbox() {
        const lightboxImage = document.getElementById('lightboxImage');
        const lightboxCounter = document.getElementById('lightboxCounter');

        if (currentImageList.length > 0 && currentImageList[currentImageIndex]) {
            lightboxImage.src = currentImageList[currentImageIndex];
            lightboxCounter.textContent = `${currentImageIndex + 1} / ${currentImageList.length}`;
        }
    }

    // =====================================================================
    // EXPOSE PUBLIC
    // =====================================================================

    return {
        init,
        startEditEvent,
        removeExistingFile,
        removeFile,
        moveItem,
        openLightbox,
        closeLightbox,
        changeImage,
        deleteEvent
    };
})();

// Exposer certaines fonctions au window pour les onclick du HTML
window.startEditEvent = galerieAdmin.startEditEvent;
window.removeExistingFile = galerieAdmin.removeExistingFile;
window.removeFile = galerieAdmin.removeFile;
window.moveItem = galerieAdmin.moveItem;
window.openLightbox = galerieAdmin.openLightbox;
window.closeLightbox = galerieAdmin.closeLightbox;
window.changeImage = galerieAdmin.changeImage;
window.deleteEvent = galerieAdmin.deleteEvent;

// Exécution au chargement du DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        galerieAdmin.init({
            csrfToken: window.CSRF_TOKEN || '',
            maxImageSize: window.MAX_IMAGE_SIZE || 0,
            eventImages: window.EVENT_IMAGES || {}
        });
    });
} else {
    // Si le script est chargé après le DOM
    galerieAdmin.init({
        csrfToken: window.CSRF_TOKEN || '',
        maxImageSize: window.MAX_IMAGE_SIZE || 0,
        eventImages: window.EVENT_IMAGES || {}
    });
}
