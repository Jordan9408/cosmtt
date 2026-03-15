<?php
include('./html_partials/header.php');
require_once __DIR__ . '/admin/pages/connect_ddb.php';
require_once __DIR__ . '/admin/pages/classPages/galerie/GalerieManager.php';

// Initialisation du gestionnaire de galerie
$galerieManager = new GalerieManager($conn);
$displayError = null;

// Récupération des données pour l'affichage
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

try {
    // On utilise la méthode de pagination existante
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

// Préparation des données pour le JavaScript de la lightbox
$eventImagesForJs = [];
foreach ($mediaByEvent as $eventId => $mediaItems) {
    $eventImagesForJs[$eventId] = [];
    foreach ($mediaItems as $media) {
        // On inclut seulement les images dans la lightbox
        if (strpos($media['type_media'], 'image/') === 0) {
            $eventImagesForJs[$eventId][] = [
                'full' => htmlspecialchars($media['chemin_fichier'], ENT_QUOTES, 'UTF-8'),
                'thumb' => !empty($media['chemin_miniature']) ? htmlspecialchars($media['chemin_miniature'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($media['chemin_fichier'], ENT_QUOTES, 'UTF-8')
            ];
        }
    }
}
?>
<main>
    <h2 class="h2_galerie" id="galerie">Galerie</h2>
    <div id="galerie_photos">

        <?php if (isset($displayError)) : ?>
            <p style="color: red; text-align: center;"><?= $displayError ?></p>
        <?php endif; ?>

        <?php if (empty($events)) : ?>
            <p style="text-align: center; margin-top: 30px;">Aucun événement n'a été trouvé dans la galerie.</p>
        <?php else : ?>
            <?php foreach ($events as $event) : ?>
                <section class="galerie-event" data-event-id="<?= (int)$event['id'] ?>">
                    <h3 class="titre_photos">
                        <?= htmlspecialchars($event['titre'], ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;-&nbsp;
                        <span class="event-date-display"><?= $galerieManager->formatDateFrench($event['date_evenement']) ?></span>
                    </h3>
                    <div class="photo-grid">
                        <?php if (!empty($mediaByEvent[$event['id']])) : ?>
                            <?php foreach ($mediaByEvent[$event['id']] as $index => $media) : ?>
                                <?php
                                $fullPath = htmlspecialchars($media['chemin_fichier'], ENT_QUOTES, 'UTF-8');
                                $thumbPath = !empty($media['chemin_miniature']) ? htmlspecialchars($media['chemin_miniature'], ENT_QUOTES, 'UTF-8') : $fullPath;
                                $altText = 'Photo de l\'événement ' . htmlspecialchars($event['titre'], ENT_QUOTES, 'UTF-8');
                                ?>

                                <?php if (strpos($media['type_media'], 'image/') === 0) : ?>
                                    <div class="photo-wrapper">
                                        <a href="javascript:void(0)" onclick="openLightbox(event, <?= $index ?>, <?= (int)$event['id'] ?>)">
                                            <img class="photo_galerie" src="<?= $thumbPath ?>" alt="<?= $altText ?>" title="Cliquez pour agrandir">
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p>Aucun média pour cet événement.</p>
                        <?php endif; ?>
                    </div>
                    <!-- <hr> -->
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($totalPages > 1) : ?>
            <div class="pagination">
                <a href="<?= $currentPage > 1 ? '?page=' . ($currentPage - 1) : 'javascript:void(0)' ?>" class="btn-pagination <?= $currentPage <= 1 ? 'disabled' : '' ?>">◄</a>
                <span class="pagination-info"><?= $currentPage ?> / <?= $totalPages ?></span>
                <a href="<?= $currentPage < $totalPages ? '?page=' . ($currentPage + 1) : 'javascript:void(0)' ?>" class="btn-pagination <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">►</a>
            </div>
        <?php endif; ?>

    </div>

    <!-- Structure de la Lightbox -->
    <div id="lightbox" class="lightbox-overlay" style="display:none;">
        <button class="lightbox-close" onclick="closeLightbox()" aria-label="Fermer">&times;</button>
        <div class="lightbox-content">
            <a class="lightbox-prev" onclick="changeImage(-1)">&#10094;</a>
            <img id="lightboxImage" src="" alt="Image en plein écran">
            <a class="lightbox-next" onclick="changeImage(1)">&#10095;</a>
        </div>
        <div class="lightbox-counter" id="lightboxCounter"></div>
    </div>
</main>



<script>
    // Passer les données PHP au JavaScript
    window.EVENT_IMAGES = <?= json_encode($eventImagesForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    let currentImageIndex = 0;
    let currentEventId = null;
    let currentImageList = [];

    function openLightbox(event, index, eventId) {
        event.preventDefault();
        currentEventId = eventId;
        currentImageList = window.EVENT_IMAGES[eventId] || [];

        if (currentImageList.length === 0) {
            console.error("Aucune image trouvée pour l'événement ID:", eventId);
            return;
        }

        currentImageIndex = index;
        document.getElementById('lightbox').style.display = 'flex';
        updateLightboxImage();
    }

    function closeLightbox() {
        document.getElementById('lightbox').style.display = 'none';
    }

    function changeImage(direction) {
        currentImageIndex += direction;
        if (currentImageIndex >= currentImageList.length) {
            currentImageIndex = 0;
        }
        if (currentImageIndex < 0) {
            currentImageIndex = currentImageList.length - 1;
        }
        updateLightboxImage();
    }

    function updateLightboxImage() {
        if (currentImageList.length > 0) {
            const image = currentImageList[currentImageIndex];
            document.getElementById('lightboxImage').src = image.full;
            document.getElementById('lightboxCounter').innerText = `${currentImageIndex + 1} / ${currentImageList.length}`;
        }
    }

    // Navigation au clavier pour la lightbox
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('lightbox').style.display === 'flex') {
            if (e.key === 'ArrowRight') {
                changeImage(1);
            } else if (e.key === 'ArrowLeft') {
                changeImage(-1);
            } else if (e.key === 'Escape') {
                closeLightbox();
            }
        }
    });
</script>

<?php include('./html_partials/footer.php') ?>