<?php
/**
 * VUE : Gestion de la Galerie Photos
 * Variables disponibles depuis showGalerie.php :
 * - $csrfTokenForJs
 * - $flashMessage
 * - $displayError
 * - $currentPage
 * - $events
 * - $mediaByEvent
 * - $totalPages
 * - $galerieManager
 */
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
    <!-- <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie_popup.css"> -->
    <!-- <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie_img.css"> -->
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
                        <input type="hidden" name="current_page" id="currentPageHidden" value="<?= (int)$currentPage ?>">
                        
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
                            <p class="file-size-info">Images : JPG, JPEG, PNG (Max: 15 Images)</p>
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
                            <button type="submit" class="btn btnPopup-primary" id="submitBtn">Ajouter</button>
                            <button type="button" class="btn btnPopup-primary" id="cancelPopup">Annuler</button>
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
                        <!-- <hr> -->

                        <div class="section-header">
                            <h3 class="titre_photos">
                                <span class="event-titre-display"><?= htmlspecialchars($event['titre'], ENT_QUOTES, 'UTF-8') ?></span>
                                &nbsp;-&nbsp;
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
                                    
                                    $dateEvent = preg_replace('/[^0-9]/', '', $event['date_evenement']);
                                    $mediaIdPadded = str_pad($media['id'], 4, '0', STR_PAD_LEFT);
                                    $altText = 'IMG_' . $dateEvent . '_' . $mediaIdPadded . '_mini';
                                    ?>
                                    
                                    <?php if (strpos($media['type_media'], 'image/') === 0): ?>
                                        <div class="photo-edit-wrapper" data-media-id="<?= (int)$media['id'] ?>" draggable="true">
                                            <a class="photos" href="javascript:void(0)" onclick="openLightbox(event, <?= $index ?>, <?= (int)$event['id'] ?>)">
                                                <img class="photo_galerie" src="<?= $thumbPath ?>" alt="<?= $altText ?>" title="Cliquez pour agrandir">
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
    <div id="lightbox" class="lightbox-overlay" style="display:none;">
        <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
        <div class="lightbox-content">
            <a class="lightbox-prev" onclick="changeImage(-1)">&#10094;</a>
            <img id="lightboxImage" src="" alt="Image en plein écran">
            <a class="lightbox-next" onclick="changeImage(1)">&#10095;</a>
        </div>
        <div class="lightbox-counter" id="lightboxCounter"></div>
    </div>

    <script src="/cosmtt/admin/js/app.js"></script>
    <script src="/cosmtt/admin/js/galerieImg.js"></script>
    
    <script>
        // Définir les variables globales pour les modules
        window.CSRF_TOKEN = <?= json_encode($csrfTokenForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        window.MAX_IMAGE_SIZE = <?= GalerieManager::MAX_IMAGE_SIZE ?>;
        window.EVENT_IMAGES = <?= json_encode($mediaByEvent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    </script>

    <?php if (isset($flashMessage) && !empty($flashMessage)): ?>
    <script>
        console.log('Flash message:', <?php echo json_encode($flashMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
    </script>
    <?php endif; ?>

</body>
</html>
