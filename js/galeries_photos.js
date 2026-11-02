/**
 * galeries_photos.js - Gestion de la lightbox et de la navigation des galeries photos
 */

(function () {
    let currentImageIndex = 0;
    let currentEventId = null;
    let currentImageList = [];

    function getEventImages() {
        if (window.EVENT_IMAGES && typeof window.EVENT_IMAGES === 'object') {
            return window.EVENT_IMAGES;
        }
        const dataScript = document.getElementById('eventImagesData');
        if (dataScript) {
            try {
                window.EVENT_IMAGES = JSON.parse(dataScript.textContent);
                return window.EVENT_IMAGES;
            } catch (e) {
                console.error('Erreur lecture données images:', e);
            }
        }
        return {};
    }

    function openLightbox(event, index, eventId) {
        if (event && event.preventDefault) {
            event.preventDefault();
        }
        currentEventId = eventId;
        const allImages = getEventImages();
        currentImageList = allImages[eventId] || [];

        if (currentImageList.length === 0) {
            console.error("Aucune image trouvée pour l'événement ID:", eventId);
            return;
        }

        currentImageIndex = index;
        const lightbox = document.getElementById('lightbox');
        if (lightbox) {
            lightbox.style.display = 'flex';
        }
        updateLightboxImage();
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        const lightbox = document.getElementById('lightbox');
        if (lightbox) {
            lightbox.style.display = 'none';
        }
        document.body.style.overflow = '';
    }

    function closeLightboxOnOverlay(event) {
        if (event && event.target && event.target.id === 'lightbox') {
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
            const counterElement = document.getElementById('lightboxCounter');
            if (imgElement && image) {
                imgElement.src = image.full;
            }
            if (counterElement) {
                counterElement.innerText = `${currentImageIndex + 1} / ${currentImageList.length}`;
            }

            if (currentImageIndex + 1 < currentImageList.length) {
                const preloadNext = new Image();
                preloadNext.src = currentImageList[currentImageIndex + 1].full;
            }
        }
    }

    // Export global pour les handlers de la page
    window.openLightbox = openLightbox;
    window.closeLightbox = closeLightbox;
    window.closeLightboxOnOverlay = closeLightboxOnOverlay;
    window.changeImage = changeImage;

    // Navigation au clavier
    document.addEventListener('keydown', function (e) {
        const lightbox = document.getElementById('lightbox');
        if (lightbox && lightbox.style.display === 'flex') {
            if (e.key === 'ArrowRight') {
                changeImage(1);
                e.preventDefault();
            } else if (e.key === 'ArrowLeft') {
                changeImage(-1);
                e.preventDefault();
            } else if (e.key === 'Escape') {
                closeLightbox();
            }
        }
    });

    // Scroll automatique vers l'événement cible si demandé
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.gallery-lightbox-trigger').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                openLightbox(
                    event,
                    Number(trigger.dataset.imageIndex),
                    Number(trigger.dataset.eventId)
                );
            });
        });

        const lightbox = document.getElementById('lightbox');
        const closeButton = lightbox ? lightbox.querySelector('.lightbox-close') : null;
        const previousButton = lightbox ? lightbox.querySelector('.lightbox-prev') : null;
        const nextButton = lightbox ? lightbox.querySelector('.lightbox-next') : null;

        if (lightbox) {
            lightbox.addEventListener('click', closeLightboxOnOverlay);
        }
        if (closeButton) {
            closeButton.addEventListener('click', closeLightbox);
        }
        if (previousButton) {
            previousButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                changeImage(-1);
            });
        }
        if (nextButton) {
            nextButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                changeImage(1);
            });
        }

        const dataScript = document.getElementById('eventImagesData');
        const targetEventId = dataScript ? dataScript.dataset.targetEventId : null;

        if (targetEventId) {
            setTimeout(function () {
                const targetElement = document.querySelector('[data-event-id="' + targetEventId + '"]');
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    targetElement.style.transition = 'background-color 0.3s ease';
                    targetElement.style.backgroundColor = '#cfcfcf80';
                    targetElement.style.borderRadius = '5px';
                    setTimeout(function () {
                        targetElement.style.backgroundColor = '';
                    }, 2000);
                }
            }, 100);
        }
    });
})();
