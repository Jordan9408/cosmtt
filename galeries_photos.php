<?php
/**
 * =============================================================================
 * GALERIE PHOTOS - PAGE PUBLIQUE D'AFFICHAGE (Optimisé PHP 8+)
 * =============================================================================
 */

declare(strict_types=1);

include('./html_partials/header.php');
require_once __DIR__ . '/admin/pages/connect_ddb.php';
require_once __DIR__ . '/admin/pages/classPages/galerie/GalerieManager.php';

class GaleriePhotosPage
{
    private array $events = [];
    private array $mediaByEvent = [];
    private int $currentPage = 1;
    private int $totalPages = 1;
    private ?string $displayError = null;
    private ?int $targetEventId = null;
    private array $eventImagesForJs = [];
    private GalerieManager $galerieManager;

    /**
     * Le constructeur injecte la connexion PDO
     */
    public function __construct(private readonly PDO $conn)
    {
        $this->galerieManager = new GalerieManager($this->conn);
    }

    /**
     * Point d'entrée principal pour traiter la logique de la requête
     */
    public function handleRequest(): void
    {
        $this->currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        
        $this->processDeepLinking();
        $this->fetchData();
        $this->prepareLightboxData();
    }

    /**
     * Gère la recherche d'un événement spécifique via l'URL (Deep Linking)
     */
    private function processDeepLinking(): void
    {
        if (isset($_GET['title'], $_GET['date'])) {
            $searchTitle = trim(urldecode((string)$_GET['title']));
            $searchDate = trim(urldecode((string)$_GET['date']));

            if ($searchTitle !== '' && $searchDate !== '') {
                $foundEvent = $this->galerieManager->findEventByTitleAndDate($searchTitle, $searchDate);
                if ($foundEvent) {
                    $this->targetEventId = (int)$foundEvent['id'];
                    $this->currentPage = $this->galerieManager->getEventPageById($this->targetEventId);
                }
            }
        }
    }

    /**
     * Récupère les données paginées depuis le manager
     */
    private function fetchData(): void
    {
        try {
            $data = $this->galerieManager->getEventsPaginated($this->currentPage);
            $this->events = $data['events'] ?? [];
            $this->mediaByEvent = $data['media'] ?? [];
            $this->totalPages = (int)($data['total_pages'] ?? 1);
            $this->currentPage = (int)($data['current_page'] ?? 1);
        } catch (Exception $e) {
            error_log('[GaleriePhotosPage] ' . $e->getMessage());
            $this->displayError = "Une erreur est survenue lors de la récupération de la galerie. Veuillez réessayer plus tard.";
        }
    }

    /**
     * Prépare le tableau JSON optimisé pour la Lightbox JavaScript
     */
    private function prepareLightboxData(): void
    {
        foreach ($this->mediaByEvent as $eventId => $mediaItems) {
            $this->eventImagesForJs[$eventId] = [];

            foreach ($mediaItems as $media) {
                $typeMedia = (string)($media['type_media'] ?? '');
                
                // Modernisation PHP 8 : str_starts_with au lieu de strpos === 0
                if (!str_starts_with($typeMedia, 'image/')) {
                    continue;
                }

                $full = (string)($media['chemin_fichier'] ?? '');
                $thumb = !empty($media['chemin_miniature']) ? (string)$media['chemin_miniature'] : $full;

                $this->eventImagesForJs[$eventId][] = [
                    'full' => $full,
                    'thumb' => $thumb,
                ];
            }
        }
    }

    /**
     * Formate la date via le manager
     */
    public function formatDate(string $date): string
    {
        return $this->galerieManager->formatDateFrench($date);
    }

    /**
     * Génère et affiche le rendu HTML et JavaScript de la page
     */
    public function render(): void
    {
        ?>
<main>
    <h2 class="title-bar" id="galerie">Galerie</h2>
    <div id="galerie_photos">

        <?php if ($this->displayError !== null) : ?>
        <p style="color: red; text-align: center;"><?= htmlspecialchars($this->displayError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if (empty($this->events)) : ?>
        <p style="text-align: center; margin-top: 30px;">Aucun événement n'a été trouvé dans la galerie.</p>
        <?php else : ?>
        <?php foreach ($this->events as $event) : ?>
        <section class="galerie-event" data-event-id="<?= (int)$event['id'] ?>">
            <h3 class="titre_photos">
                <span class="event-title-text"><?= htmlspecialchars((string)$event['titre'], ENT_QUOTES, 'UTF-8') ?>
                    -</span>
                <span class="event-date-display"><?= $this->formatDate((string)$event['date_evenement']) ?></span>
            </h3>
            <div class="photo-grid">
                <?php if (!empty($this->mediaByEvent[$event['id']])) : ?>
                <?php 
                                    $imageIndex = 0; 
                                    foreach ($this->mediaByEvent[$event['id']] as $media) : 
                                        $typeMedia = (string)($media['type_media'] ?? '');
                                        if (!str_starts_with($typeMedia, 'image/')) {
                                            continue;
                                        }

                                        $fullPath = htmlspecialchars((string)($media['chemin_fichier'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $thumbPath = !empty($media['chemin_miniature']) ? htmlspecialchars((string)$media['chemin_miniature'], ENT_QUOTES, 'UTF-8') : $fullPath;
                                        $dateEvent = preg_replace('/[^0-9]/', '', (string)$event['date_evenement']);
$mediaId = $media['id'] ?? '';
                                        // str_pad() attend une string : on force la conversion.
                                        $mediaIdPadded = str_pad((string)(int)$mediaId, 4, '0', STR_PAD_LEFT);
                                        $altText = 'IMG_' . $dateEvent . '_' . $mediaIdPadded . '_mini';
                                    ?>
                <div class="photo-wrapper">
                    <a href="#" class="gallery-lightbox-trigger" data-image-index="<?= $imageIndex ?>"
                        data-event-id="<?= (int)$event['id'] ?>">
                        <img class="photo_galerie" src="<?= $thumbPath ?>"
                            alt="<?= htmlspecialchars($altText, ENT_QUOTES, 'UTF-8') ?>" title="Cliquez pour agrandir">
                    </a>
                </div>
                <?php $imageIndex++; ?>
                <?php endforeach; ?>
                <?php else : ?>
                <p>Aucun média pour cet événement.</p>
                <?php endif; ?>
            </div>
        </section>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($this->totalPages > 1) : ?>
        <div class="pagination">
            <a href="<?= $this->currentPage > 1 ? '?page=' . ($this->currentPage - 1) : '#' ?>"
                class="btn_fleche btnPopup-primary <?= $this->currentPage <= 1 ? 'disabled' : '' ?>">◄</a>
            <span class="pagination-info"><?= $this->currentPage ?> / <?= $this->totalPages ?></span>
            <a href="<?= $this->currentPage < $this->totalPages ? '?page=' . ($this->currentPage + 1) : '#' ?>"
                class="btn_fleche btnPopup-primary <?= $this->currentPage >= $this->totalPages ? 'disabled' : '' ?>">►</a>
        </div>
        <?php endif; ?>

    </div>

    <div id="lightbox" class="lightbox-overlay" style="display:none;">
        <button class="lightbox-close" aria-label="Fermer">&times;</button>
        <div class="lightbox-content">
            <a href="#" class="lightbox-prev">&#10094;</a>
            <img id="lightboxImage" src="" alt="Image en plein écran">
            <a href="#" class="lightbox-next">&#10095;</a>
        </div>
        <div class="lightbox-counter" id="lightboxCounter"></div>
    </div>
</main>

    <script type="application/json" id="eventImagesData" data-target-event-id="<?= (int)($this->targetEventId ?? 0) ?>">
    <?= json_encode($this->eventImagesForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    </script>
    <script src="/cosmtt/js/galeries_photos.js"></script>
<?php
    }

    // Getters publics (Optionnels, si requis par d'autres composants externes)
    public function getEvents(): array { return $this->events; }
    public function getMediaByEvent(): array { return $this->mediaByEvent; }
    public function getCurrentPage(): int { return $this->currentPage; }
    public function getTotalPages(): int { return $this->totalPages; }
    public function getDisplayError(): ?string { return $this->displayError; }
    public function getTargetEventId(): ?int { return $this->targetEventId; }
    public function getEventImagesForJs(): array { return $this->eventImagesForJs; }
}

// =========================================================================
// INTERFACE D'EXÉCUTION DU COMPOSANT
// =========================================================================
if (isset($conn) && $conn instanceof PDO) {
    $page = new GaleriePhotosPage($conn);
    $page->handleRequest();
    $page->render();
} else {
    error_log("Erreur d'initialisation : La connexion PDO est manquante.");
    echo "<p style='text-align:center;'>Une erreur système est survenue.</p>";
}

include('./html_partials/footer.php');