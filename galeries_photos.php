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
    <h2 class="title-bar" id="galerie">Galerie</h2>
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

<!-- CSS & JS -->
<style>
    main {
        margin-top: -23px;
    }
    /* Styles pour la page galerie */
    #galerie_photos {
        width: 90%;
        margin: 20px auto;
        max-width: 1400px;
    }

    .galerie-event {
        margin-bottom: 40px;
    }

    .titre_photos {
        text-align: center;
        font-size: 1.8em;
        margin-bottom: 20px;
        color: #333;
    }

    .event-date-display {
        font-size: 0.9em;
        color: #666;
        font-weight: normal;
    }

    .photo-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: center;
    }

    .photo-wrapper {
        /* flex-basis: calc(25% - 15px); */
        /* 4 images par ligne */
        box-sizing: border-box;
    }

    .photo_galerie {
        /* width: 100%; */
        height: 133px;
        object-fit: cover;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        cursor: pointer;
    }

    .photo_galerie:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    @media (max-width: 1200px) {
        .photo-wrapper {
            flex-basis: calc(33.33% - 15px);
        }

        /* 3 images */
    }

    @media (max-width: 900px) {
        .photo-wrapper {
            flex-basis: calc(50% - 15px);
        }

        /* 2 images */
    }

    @media (max-width: 600px) {
        .photo-wrapper {
            flex-basis: 100%;
        }

        /* 1 image */
    }

    /* Styles de la Pagination */
    .pagination {
        text-align: center;
        margin-top: 40px;
        padding: 20px 0;
    }

    .btn-pagination {
        display: inline-block;
        padding: 10px 20px;
        margin: 0 10px;
        
        color: #000;
        text-decoration: none;
        border-radius: 5px;
        transition: background-color 0.3s;
    }

    a:visited.btn-pagination {
        color: #000;
    }
    
    .pagination-info {
        display: inline-block;
        vertical-align: middle;
        font-size: 1.2em;
    }

    /* Styles de la Lightbox */
    .lightbox-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        z-index: 10000;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    .lightbox-content {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 95%;
        height: 90%;
    }

    #lightboxImage {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        border-radius: 5px;
    }

    .lightbox-close,
    .lightbox-prev,
    .lightbox-next {
        cursor: pointer;
        position: absolute;
        color: white;
        font-size: 40px;
        font-weight: normal;
        transition: 0.3s;
        user-select: none;
    }

    .lightbox-close:hover,
    .lightbox-prev:hover,
    .lightbox-next:hover {
        color: #bbb;
    }

    .lightbox-close {
        top: 10px;
        right: 15px;
        font-size: 50px;
        background: transparent;
        border: none;
        padding: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10001;
        color: white;
        font-weight: normal;
        box-sizing: border-box;
    }

    .lightbox-prev,
    .lightbox-next {
        top: 50%;
        transform: translateY(-50%);
        padding: 16px;
    }

    .lightbox-prev {
        left: 10px;
    }

    .lightbox-next {
        right: 10px;
    }

    .lightbox-counter {
        color: #f1f1f1;
        font-size: 16px;
        padding: 10px 0;
    }
</style>

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
