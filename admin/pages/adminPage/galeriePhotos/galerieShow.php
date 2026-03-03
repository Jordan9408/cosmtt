<?php
/**
 * =============================================================================
 * GALERIE PHOTOS - PAGE D'ADMINISTRATION (VERSION POO)
 * =============================================================================
 * 
 * Ce fichier permet de gérer les événements photo et l'upload d'images.
 * Les images sont converties en WebP et des miniatures sont générées.
 * 
 * FONCTIONNALITÉS :
 * - AJAX: Vérification d'existence d'événement
 * - AJAX: Modification titre/date d'événement (edit_event)
 * - AJAX: Suppression d'événement (delete_event)
 * - AJAX: Suppression d'un média (delete_media)
 * - AJAX: Réordonnement des médias (reorder_media)
 * - Upload: Compression client, conversion WebP serveur, miniatures
 * - Sécurité: Validation magic bytes, scan malware, ClamAV optionnel
 * 
 * =============================================================================
 * INFORMATIONS DE DÉVELOPPEMENT
 * =============================================================================
 * - Version: POO (Orientée Objet)
 * - Dépendances : GalerieController, GalerieManager, ImageProcessor
 * - Sessions : Vérification via session_check.php et security.php
 * =============================================================================
 */

// =============================================================================
// SECTION 1: INCLUDES ET CONFIGURATION
// =============================================================================

require_once dirname(__DIR__, 2) . '/connect_ddb.php';
require_once dirname(__DIR__, 2) . '/parts/header.php';
require_once dirname(__DIR__, 3) . '/includes/security.php';
require_once dirname(__DIR__, 3) . '/includes/session_check.php';
require_once dirname(__DIR__, 2) . '/classPages/galerie/GalerieController.php';

// ---------------------------------------------------------------------
// Gestion des messages flash après redirection (Pattern PRG)
// ---------------------------------------------------------------------
$flashMessage = null;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// ---------------------------------------------------------------------
// Initialisation du contrôleur
// ---------------------------------------------------------------------
$csrfTokenForJs = $_SESSION['csrf_token'] ?? '';
$galerieController = new GalerieController($conn, $csrfTokenForJs);

// =============================================================================
// SECTION 2: TRAITEMENT DES REQUETES AJAX
// =============================================================================

$galerieController->handleRequest();

// =============================================================================
// SECTION 3: TRAITEMENT DU FORMULAIRE PRINCIPAL
// =============================================================================

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    
    // Traiter le formulaire via le contrôleur
    $formResult = $galerieController->handleFormSubmission();
    
    if (!empty($formResult['message'])) {
        $message = $formResult['message'];
        
        // Stocker le message en session si nécessaire
        if ($formResult['redirect'] && !empty($message)) {
            $_SESSION['flash_message'] = $message;
        }
    }

    // Redirection après soumission
    if ($formResult['redirect']) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// =============================================================================
// SECTION 4: RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE
// =============================================================================

// Gestion des erreurs de taille de requête
$displayError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && 
    isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $displayError = "❌ Erreur : Le volume total des fichiers envoyés dépasse la limite du serveur (" . ini_get('post_max_size') . "). Veuillez envoyer moins de photos à la fois.";
}

// Récupération paginée des événements
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$galerieManager = $galerieController->getGalerieManager();

try {
    $data = $galerieManager->getEventsPaginated($currentPage);
    $events = $data['events'];
    $mediaByEvent = $data['media'];
    $totalPages = $data['total_pages'];
} catch (Exception $e) {
    $displayError = "Erreur lors de la récupération de la galerie : " . htmlspecialchars($e->getMessage());
    $events = [];
    $mediaByEvent = [];
    $totalPages = 1;
    $currentPage = 1;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la Galerie Photos</title>
    
    <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/colors_group.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/popup.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie_popup.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/lightbox.css">
    
    <style>
        .sections-container, .medias-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
        }
        
        .section-card, .media-card {
            border: 1px solid var(--fifth-color);
            padding: 15px;
            border-radius: 8px;
            width: 300px;
        }
        
        .media-card img {
            max-width: 100%;
            height: auto;
        }
        
        .actions {
            margin-top: 10px;
        }
        
        .actions a {
            margin-right: 10px;
            text-decoration: none;
            color: var(--primary-color);
        }
        
        .actions a:hover {
            text-decoration: underline;
        }
        
        .move-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgb(255, 254, 254);
            color: var(--fourth-color);
            border: 1px solid #ccc;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            z-index: 10;
            transition: all 0.2s;
            padding: 0;
            line-height: 1;
            opacity: 0;
        }
        
        .move-btn:hover {
            background-color: var(--fourth-color);
            color: var(--primary-color);
            border-color: var(--fourth-color);
        }
        
        .move-left { left: 5px; }
        .move-right { right: 5px; }
        
        .preview-item:hover .move-btn {
            opacity: 1;
        }
        
        #galerie_photos {
            width: 70em;
            margin: 0 auto 40px auto; 
        }
        
        .titre_photos {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .photo {
            display: flex;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 10px;
            margin: 1em 39px 1em 39px;
            width: fit-content;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        .photos {
            height: 133px;
            max-width: 200px;
            overflow: hidden;
            width: fit-content;
            display: flex;
            align-items: flex-start;
        }
        
        .photo_galerie {
            width: auto;
            height: 100%;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .section-header .titre_photos {
            margin: 0;
            display: flex;
            align-items: center;
            line-height: 1.2;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            line-height: 1;
            transition: background 0.2s, transform 0.1s;
            flex-shrink: 0;
            align-self: center;
            width: 32px;
            margin-top: 2px;
        }

        .btn-icon img {
            display: block;
            width: 24px;
        }

        .btn-icons-events {
            display: flex;
            position: absolute;
            right: 13em;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 30px 0;
        }
        
        .pagination a.btn_fleche {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            text-decoration: none;
        }
        
        .pagination a.btn_fleche:active {
            background-color: var(--primary-color);
            /* border: 1px solid var(--primary-color); */
            
        }
        
        .pagination span.btn {
            background-color: var(--primary-color);
            color: white;
            cursor: default;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }
        
        .pagination a.btn_fleche.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            min-width: 80px;
            text-align: center;
            font-weight: bold;
            color: var(--fifth-color);
        }

        /* Styles pour le glisser-déposer */
        .preview-item.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }
        
        .preview-item.drag-over {
            border: 2px dashed var(--fourth-color);
            border-radius: 8px;
            transform: scale(1.02);
        }
    </style>
</head>

<body>

    <div class="title-bar">GESTION DE LA GALERIE PHOTOS</div>

    <main>
        <a href="javascript:void(0);" class="lienPopup" id="showPopup">Ajouter un événement</a>

        <div class="popup" id="addPopup">
            <div class="popup-card">
                <div class="popup-content">
                    <div class="title-bar" id="popupTitle">Ajouter un événement</div>
                    
                    <form method="POST" action="" class="form-ajout" id="eventForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenForJs, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="add_event">
                        <input type="hidden" name="event_mode" id="eventMode" value="add">
                        <input type="hidden" name="event_id" id="eventIdHidden" value="">
                        
                        <div class="form-group-inline">
                            <input type="text" name="titre_evenement" id="titre_evenement" class="input-popup" placeholder="Titre de l'événement" required>
                            <input type="date" name="date_evenement" id="date_evenement" class="input-popup" required>
                        </div>

                        <div class="file-upload-zone" id="dropZone">
                            <div style="font-size: 48px; margin-bottom: 10px;">📁</div>
                            <p><strong>Glissez et déposez vos photos ici</strong></p>
                            <p>ou</p>
                            <div class="upload-btn" onclick="document.getElementById('fileInput').click()">
                                ➕ Choisir les photos
                            </div>
                            <input type="file" id="fileInput" name="files[]" class="file-input-hidden" multiple accept=".jpg,.jpeg,.png">
                            <p class="file-size-info">Images : JPG, JPEG, PNG</p>
                        </div>

                        <div id="previewGrid" class="preview-grid"></div>

                        <div id="existingMediaSection" style="display: none; margin-top: 20px;">
                            <div id="existingMediaGrid" class="preview-grid" style="gap: 10px;"></div>
                        </div>

                        <input type="hidden" name="compressed_files_data" id="compressedFilesData">
                        <input type="hidden" name="existing_event_id" id="existingEventId">
                        <input type="hidden" name="force_add" id="forceAdd" value="0">
                        <input type="hidden" name="media_ids_to_delete" id="mediaIdsToDelete" value="">
                        <input type="hidden" name="final_order" id="finalOrderInput" value="">

                        <div class="popup-buttons">
                            <button type="button" class="btn btnPopup-primary" id="cancelPopup">Annuler</button>
                            <button type="submit" class="btn btnPopup-primary" id="submitBtn">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-content">
                <div class="spinner"></div>
                <p>Compression et téléchargement en cours...</p>
                <p id="progressText">0%</p>
            </div>
        </div>

        <div id="galerie_photos">
            <?php if (isset($displayError)): ?>
                <p style="color: red; text-align: center;"><?= htmlspecialchars($displayError) ?></p>
            <?php endif; ?>

            <?php if (empty($events)): ?>
                <p style="text-align: center; margin-top: 30px;">Aucun événement n'a été trouvé dans la galerie.</p>
            <?php else: ?>
                <?php foreach ($events as $event): ?>

                    <section class="galerie" data-event-id="<?= (int)$event['id'] ?>">
                        <hr>

                        <div class="section-header">
                            <h3 class="titre_photos">
                                <span class="event-titre-display"><?= htmlspecialchars($event['titre'], ENT_QUOTES, 'UTF-8') ?></span>
                                &nbsp;–&nbsp;
                                <span class="event-date-display"><?= $galerieManager->formatDateFrench($event['date_evenement']) ?></span>
                            </h3>
                            <div class="btn-icons-events">
                                <button type="button" class="btn-icon btn-icon-edit" title="Modifier cet événement" onclick="startEditEvent(this)" data-id="<?= (int)$event['id'] ?>" data-titre="<?= htmlspecialchars($event['titre'], ENT_QUOTES | JSON_HEX_APOS) ?>" data-date="<?= htmlspecialchars($event['date_evenement'], ENT_QUOTES) ?>">
                                    <img src="/cosmtt/img/icones/write.png" alt="Modifier" width="18">
                                </button>

                                <button type="button" class="btn-icon btn-icon-delete" title="Supprimer cet événement" onclick="deleteEvent(<?= (int)$event['id'] ?>, '<?= htmlspecialchars($event['titre'], ENT_QUOTES | JSON_HEX_APOS) ?>')">
                                    <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" width="18">
                                </button>
                            </div>
                        </div>

                        <div class="photo" data-event-id="<?= (int)$event['id'] ?>">
                            <?php if (!empty($mediaByEvent[$event['id']])): ?>
                                <?php foreach ($mediaByEvent[$event['id']] as $index => $media): ?>
                                    <?php
                                    $fullPath = htmlspecialchars($media['chemin_fichier'], ENT_QUOTES, 'UTF-8');
                                    $thumbPath = !empty($media['chemin_miniature']) ? htmlspecialchars($media['chemin_miniature'], ENT_QUOTES, 'UTF-8') : $fullPath;
                                    ?>
                                    <?php if (strpos($media['type_media'], 'image/') === 0): ?>
                                        <div class="photo-edit-wrapper" data-media-id="<?= (int)$media['id'] ?>" draggable="true">
                                            <a class="photos" href="javascript:void(0)" onclick="openLightbox(event, <?= $index ?>, <?= (int)$event['id'] ?>)">
                                                <img class="photo_galerie" src="<?= $thumbPath ?>" alt="Photo de l'événement <?= htmlspecialchars($event['titre'], ENT_QUOTES, 'UTF-8') ?>" title="Cliquez pour agrandir">
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Aucune photo pour cet événement.</p>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <a href="<?= $currentPage > 1 ? '?page=' . ($currentPage - 1) : 'javascript:void(0)' ?>" class="btn btn_fleche btnPopup-primary <?= $currentPage <= 1 ? 'disabled' : '' ?>">◄</a>

                    <div class="pagination-info"><?= $currentPage ?> / <?= $totalPages ?></div>

                    <a href="<?= $currentPage < $totalPages ? '?page=' . ($currentPage + 1) : 'javascript:void(0)' ?>" class="btn btn_fleche btnPopup-primary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">►</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="/cosmtt/admin/js/app.js"></script>
    <script>
        // Token CSRF pour les requêtes AJAX
        const CSRF_TOKEN = <?= json_encode($csrfTokenForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        
        let selectedFiles = [];
        const MAX_IMAGE_SIZE = <?= GalerieManager::MAX_IMAGE_SIZE ?>;
        let conflictCheckResult = null;
        let currentEditEventId = null;

        document.getElementById('showPopup')?.addEventListener('click', () => {
            resetPopupToAddMode();
            document.getElementById('addPopup').style.display = 'block';
        });

        document.getElementById('cancelPopup')?.addEventListener('click', () => {
            if (selectedFiles.length === 0 || confirm('Êtes-vous sûr de vouloir annuler ? Les images sélectionnées seront perdues.')) {
                document.getElementById('addPopup').style.display = 'none';
                resetForm();
            }
        });

        function resetPopupToAddMode() {
            document.getElementById('popupTitle').textContent = 'Ajouter un événement';
            document.getElementById('eventMode').value = 'add';
            document.getElementById('eventIdHidden').value = '';
            document.getElementById('existingMediaSection').style.display = 'none';
            document.getElementById('existingMediaGrid').innerHTML = '';
            currentEditEventId = null;
            resetForm();
        }

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

                const response = await fetch(window.location.href, { method: 'POST', body: formData });
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
                            <button type="button" class="remove-btn" onclick="removeExistingFile(${image.id}, this)">×</button>
                            <button type="button" class="move-btn move-left" onclick="moveItem(this, -1)" title="Déplacer à gauche">❮</button>
                            <button type="button" class="move-btn move-right" onclick="moveItem(this, 1)" title="Déplacer à droite">❯</button>
                            <div class="file-info">Existant</div>
                        `;
                        existingGrid.appendChild(div);
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

        window.removeExistingFile = function(mediaId, btn) {
            btn.parentElement.remove();

            const deleteListInput = document.getElementById('mediaIdsToDelete');
            const currentList = deleteListInput.value.split(',').filter(id => id !== '');
            if (!currentList.includes(String(mediaId))) {
                currentList.push(String(mediaId));
            }
            deleteListInput.value = currentList.join(',');
        };

        window.moveItem = function(btn, direction) {
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
        };

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

        const dropZone = document.getElementById('dropZone');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => { e.preventDefault(); e.stopPropagation(); });
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'));
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'));
        });
        
        dropZone.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files));
        document.getElementById('fileInput').addEventListener('change', function() { handleFiles(this.files); });

        function handleFiles(files) {
            Array.from(files).forEach(file => {
                const isImage = file.type.match('image.*');
                
                if (!isImage) return alert(`Format non supporté: ${file.name}`);
                if (file.size > MAX_IMAGE_SIZE) return alert(`Image trop lourde: ${file.name}`);
                if (selectedFiles.some(f => f.name === file.name)) return alert(`L'image est déjà ajoutée: ${file.name}`);

                selectedFiles.push(file);
                
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
                    <button type="button" class="remove-btn" onclick="removeFile('${file.name}', this)">×</button>
                    <button type="button" class="move-btn move-left" onclick="moveItem(this, -1)">❮</button>
                    <button type="button" class="move-btn move-right" onclick="moveItem(this, 1)">❯</button>
                    <div class="file-info">${(file.size/1024/1024).toFixed(2)} Mo</div>
                `;
            };
            reader.readAsDataURL(file);
        }

        window.removeFile = function(name, btn) {
            selectedFiles = selectedFiles.filter(f => f.name !== name);
            btn.parentElement.remove();
        };

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

                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                if (!response.ok) throw new Error('Erreur réseau');
                return await response.json();
            } catch (err) {
                console.error("Erreur vérification événement:", err);
                return null;
            }
        }

        document.getElementById('eventForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            const eventMode = document.getElementById('eventMode').value;
            submitBtn.disabled = true;
            submitBtn.textContent = eventMode === 'edit' ? 'Modification...' : 'Ajout...';

            let finalOrder = [];
            const orderedNewFiles = [];

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

            // Réordonner le tableau selectedFiles pour correspondre à l'ordre visuel des nouvelles images
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
            
            if (eventMode === 'add' && selectedFiles.length === 0) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Ajouter';
                return alert('Ajoutez au moins une image avant de soumettre.');
            }

            const titre = document.getElementById('titre_evenement').value.trim();
            const date = document.getElementById('date_evenement').value;

            if (!titre || !date) {
                submitBtn.disabled = false;
                submitBtn.textContent = eventMode === 'edit' ? 'Modifier' : 'Ajouter';
                return alert('Veuillez renseigner le titre et la date de l\'événement.');
            }

            const excludeId = eventMode === 'edit' ? currentEditEventId : null;
            const checkResult = await checkForConflicts(titre, date, excludeId);

            if (!checkResult) {
                // Si la vérification échoue, on laisse le serveur gérer
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
                    alert('❌ ' + (eventMode === 'edit' ? 'MODIFICATION' : 'AJOUT') + ' IMPOSSIBLE\n\n' + conflict.message + '\n\nVous ne pouvez pas avoir deux événements avec le même titre dans la même saison.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = eventMode === 'edit' ? 'Modifier' : 'Ajouter';
                    return;
                } else if (checkResult.same_date) {
                    const conflict = checkResult.conflicts.find(c => c.type === 'same_date');
                    alert('❌ ' + (eventMode === 'edit' ? 'MODIFICATION' : 'AJOUT') + ' IMPOSSIBLE\n\n' + conflict.message + '\n\nVous ne pouvez pas avoir deux événements à la même date.');
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
                this.submit();
                
            } catch (err) {
                console.error(err);
                alert('Erreur lors du traitement des images.');
                loading.classList.remove('show');
                submitBtn.disabled = false;
                submitBtn.textContent = eventMode === 'edit' ? 'Modifier' : 'Ajouter';
            }
        });

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

                const resp = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.success) {
                    const baseUrl = window.location.pathname;

                    if (data.total_events === 0) {
                        window.location.href = baseUrl;
                    } else {
                        window.location.href = baseUrl + '?page=' + data.new_page;
                    }
                } else {
                    alert('❌ Erreur lors de la suppression : ' + (data.message || 'Erreur inconnue'));
                }
            } catch (err) {
                console.error(err);
                alert('❌ Erreur réseau lors de la suppression.');
            }
        }

        /**
         * Initialise le drag & drop pour le réordonnement dans un conteneur de vignettes
         * (utilisé dans la popup: previewGrid & existingMediaGrid)
         */
        function initPopupReorderGrid(container) {
            if (!container) return;

            let draggedItem = null;

            container.addEventListener('dragstart', (e) => {
                const item = e.target.closest('.preview-item');
                if (!item || !container.contains(item)) return;

                draggedItem = item;
                setTimeout(() => draggedItem.classList.add('dragging'), 0);
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', 'dragged'); // Pour Firefox
            });

            container.addEventListener('dragend', () => {
                if (draggedItem) {
                    draggedItem.classList.remove('dragging');
                    draggedItem = null;
                }
                container.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
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
                    dropTarget.parentNode.insertBefore(draggedItem, isAfter ? dropTarget.nextSibling : dropTarget);
                }
            });
        }

        // Initialisation du drag & drop dans la popup (prévisualisations & médias existants)
        document.addEventListener('DOMContentLoaded', () => {
            const previewGrid = document.getElementById('previewGrid');
            const existingMediaGrid = document.getElementById('existingMediaGrid');

            if (previewGrid) {
                initPopupReorderGrid(previewGrid);
            }
            if (existingMediaGrid) {
                initPopupReorderGrid(existingMediaGrid);
            }
        });
    </script>

    <?php if (isset($flashMessage) && !empty($flashMessage)): ?>
    <script>
        console.log('Flash message:', <?php echo json_encode($flashMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
    </script>
    <?php endif; ?>

    <div id="lightbox" class="lightbox-overlay" style="display:none;">
        <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
        <div class="lightbox-content">
            <a class="lightbox-prev" onclick="changeImage(-1)">&#10094;</a>
            <img id="lightboxImage" src="" alt="Image en plein écran">
            <a class="lightbox-next" onclick="changeImage(1)">&#10095;</a>
        </div>
        <div class="lightbox-counter" id="lightboxCounter"></div>
    </div>

    <script>
        const lightbox = document.getElementById('lightbox');
        const lightboxImage = document.getElementById('lightboxImage');
        const lightboxCounter = document.getElementById('lightboxCounter');
        let currentImageIndex = 0;
        let currentImageList = [];

        const eventImages = <?= json_encode($mediaByEvent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function openLightbox(event, index, eventId) {
            event.preventDefault();
            currentImageList = eventImages[eventId].map(media => media.chemin_fichier);
            currentImageIndex = index;
            updateLightbox();
            lightbox.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
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
            if (currentImageList.length > 0) {
                lightboxImage.src = currentImageList[currentImageIndex];
                lightboxCounter.textContent = `${currentImageIndex + 1} / ${currentImageList.length}`;
            }
        }

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

        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });
    </script>

</body>
</html>
