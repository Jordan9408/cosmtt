<?php
include('./html_partials/header.php');
require_once __DIR__ . '/admin/pages/connect_ddb.php';
require_once __DIR__ . '/admin/pages/classPages/galerie/GalerieManager.php';

// Initialisation du gestionnaire de galerie
$galerieManager = new GalerieManager($conn);
$displayError = null;
$targetEventId = null;

// Récupération des données pour l'affichage
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Vérifier si on cherche un événement spécifique par titre et date
if (isset($_GET['title']) && isset($_GET['date'])) {
    $searchTitle = isset($_GET['title']) ? (string)$_GET['title'] : '';
    $searchDate = isset($_GET['date']) ? (string)$_GET['date'] : '';
    
    // Nettoyer et décoder les paramètres
    $searchTitle = trim(urldecode($searchTitle));
    $searchDate = trim(urldecode($searchDate));
    
    if (!empty($searchTitle) && !empty($searchDate)) {
        // Chercher l'événement correspondant
        $foundEvent = $galerieManager->findEventByTitleAndDate($searchTitle, $searchDate);
        
        if ($foundEvent) {
            $targetEventId = $foundEvent['id'];
            // Calculer la bonne page pour cet événement
            $currentPage = $galerieManager->getEventPageById($targetEventId);
        }
    }
}

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
                            <?php 
                            $imageIndex = 0; // Compteur séparé pour les images uniquement
                            foreach ($mediaByEvent[$event['id']] as $media) : 
                            ?>
                                <?php
                                    $fullPath = htmlspecialchars($media['chemin_fichier'], ENT_QUOTES, 'UTF-8');
                                    $thumbPath = !empty($media['chemin_miniature']) ? htmlspecialchars($media['chemin_miniature'], ENT_QUOTES, 'UTF-8') : $fullPath;
                                    $dateEvent = preg_replace('/[^0-9]/', '', $event['date_evenement']);
                                    $mediaIdPadded = str_pad($media['id'], 4, '0', STR_PAD_LEFT);
                                    $altText = 'IMG_' . $dateEvent . '_' . $mediaIdPadded . '_mini';
                                ?>

                                <?php if (strpos($media['type_media'], 'image/') === 0) : ?>
                                    <div class="photo-wrapper">
                                        <a href="javascript:void(0)" onclick="openLightbox(event, <?= (int)$imageIndex ?>, <?= (int)$event['id'] ?>)">
                                            <img class="photo_galerie" src="<?= $thumbPath ?>" alt="<?= $altText ?>" title="Cliquez pour agrandir">
                                        </a>
                                    </div>
                                    <?php $imageIndex++; // Incrémenter seulement pour les images ?>
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
                <a href="<?= $currentPage > 1 ? '?page=' . ($currentPage - 1) : 'javascript:void(0)' ?>" class="btn_fleche btnPopup-primary <?= $currentPage <= 1 ? 'disabled' : '' ?>">◄</a>
                <span class="pagination-info"><?= $currentPage ?> / <?= $totalPages ?></span>
                <a href="<?= $currentPage < $totalPages ? '?page=' . ($currentPage + 1) : 'javascript:void(0)' ?>" class="btn_fleche btnPopup-primary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">►</a>
            </div>
        <?php endif; ?>

    </div>

    <!-- Structure de la Lightbox -->
    <div id="lightbox" class="lightbox-overlay" style="display:none;" onclick="closeLightboxOnOverlay(event)">
        <button class="lightbox-close" onclick="closeLightbox()" aria-label="Fermer">&times;</button>
        <div class="lightbox-content">
            <a class="lightbox-prev" onclick="changeImage(-1); event.stopPropagation();">&#10094;</a>
            <img id="lightboxImage" src="" alt="Image en plein écran" onclick="event.stopPropagation()">
            <a class="lightbox-next" onclick="changeImage(1); event.stopPropagation();">&#10095;</a>
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
        
        // Empêcher le scroll du body quand la lightbox est ouverte
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        document.getElementById('lightbox').style.display = 'none';
        // Réactiver le scroll
        document.body.style.overflow = '';
    }

    function closeLightboxOnOverlay(event) {
        // Fermer seulement si on clique sur l'overlay (pas sur le contenu)
        if (event.target.id === 'lightbox') {
            closeLightbox();
        }
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
            const imgElement = document.getElementById('lightboxImage');
            imgElement.src = image.full;
            document.getElementById('lightboxCounter').innerText = `${currentImageIndex + 1} / ${currentImageList.length}`;
            
            // Précharger l'image suivante pour une meilleure UX
            if (currentImageIndex + 1 < currentImageList.length) {
                const preloadNext = new Image();
                preloadNext.src = currentImageList[currentImageIndex + 1].full;
            }
        }
    }

    // Navigation au clavier pour la lightbox
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('lightbox').style.display === 'flex') {
            if (e.key === 'ArrowRight') {
                changeImage(1);
                e.preventDefault(); // Empêcher le scroll de la page
            } else if (e.key === 'ArrowLeft') {
                changeImage(-1);
                e.preventDefault();
            } else if (e.key === 'Escape') {
                closeLightbox();
            }
        }
    });

    // Scroll automatique vers l'événement cible s'il y a une recherche
    document.addEventListener('DOMContentLoaded', function() {
        const targetEventId = <?= json_encode($targetEventId) ?>;
        
        if (targetEventId) {
            // Attendre légèrement que le DOM soit totalement rendu
            setTimeout(function() {
                const targetElement = document.querySelector('[data-event-id="' + targetEventId + '"]');
                if (targetElement) {
                    // Scroll fluide vers l'élément
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    
                    // Ajouter un surlignage temporaire pour attirer l'attention
                    targetElement.style.transition = 'background-color 0.3s ease';
                    targetElement.style.backgroundColor = '#fff8dc';
                    
                    // Retirer le surlignage après 2 secondes
                    setTimeout(function() {
                        targetElement.style.backgroundColor = '';
                    }, 2000);
                }
            }, 100);
        }
    });
</script>

<?php include('./html_partials/footer.php') ?>