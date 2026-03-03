<?php
/**
 * =============================================================================
 * GALERIE CONTROLLER - GESTION DES REQUETES
 * =============================================================================
 * 
 * Contrôleur pour gérer les requêtes AJAX et le traitement des formulaires
 * de la galerie photos.
 * 
 * @author Admin
 * @version 1.0
 * =============================================================================
 */

require_once dirname(__DIR__, 1) . '/galerie/GalerieManager.php';
require_once dirname(__DIR__, 1) . '/galerie/ImageProcessor.php';

class GalerieController
{
    private PDO $conn;
    private GalerieManager $galerieManager;
    private ImageProcessor $imageProcessor;
    private string $csrfToken;

    /**
     * Constructeur
     * 
     * @param PDO $connection Connexion à la base de données
     * @param string $csrfToken Token CSRF
     */
    public function __construct(PDO $connection, string $csrfToken)
    {
        $this->conn = $connection;
        $this->csrfToken = $csrfToken;
        $this->galerieManager = new GalerieManager($connection);
        $this->imageProcessor = new ImageProcessor(
            $this->galerieManager->getUploadDir(),
            $this->galerieManager->getThumbDir()
        );
    }

    /**
     * Point d'entrée principal - traite les requêtes
     */
    public function handleRequest(): void
    {
        // Vérifier la méthode et l'action
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
            return;
        }

        $action = $_POST['action'];

        // Vérifier le token CSRF pour toutes les actions AJAX
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->verifyCsrfToken($token)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Token CSRF invalide.'], 403);
            return;
        }

        // Router les actions
        switch ($action) {
            case 'get_event_images':
                $this->handleGetEventImages();
                break;
            case 'get_page_content':
                $this->handleGetPageContent();
                break;
            case 'check_event':
                $this->handleCheckEvent();
                break;
            case 'edit_event':
                $this->handleEditEvent();
                break;
            case 'delete_event':
                $this->handleDeleteEvent();
                break;
            case 'delete_media':
                $this->handleDeleteMedia();
                break;
            case 'reorder_media':
                $this->handleReorderMedia();
                break;
        }
    }

    /**
     * Traite le formulaire principal d'ajout/modification d'événement
     * 
     * @return array Résultat du traitement
     */
    public function handleFormSubmission(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'redirect' => true
        ];

        // Vérifier le token CSRF
        $formCsrfToken = $_POST['csrf_token'] ?? '';
        if (!$this->verifyCsrfToken($formCsrfToken)) {
            $result['message'] = "Erreur de sécurité : Token CSRF invalide.";
            return $result;
        }

        // Vérifier la disponibilité de GD
        if (!ImageProcessor::isGdAvailable()) {
            $result['message'] = "❌ Erreur de configuration serveur : La librairie d'images GD avec le support WebP est requise mais n'est pas activée.";
            return $result;
        }

        try {
            // Récupérer les données du formulaire
            $eventMode = $_POST['event_mode'] ?? 'add';
            $titreEvenement = isset($_POST['titre_evenement']) ? trim($_POST['titre_evenement']) : '';
            $dateEvenement = isset($_POST['date_evenement']) ? trim($_POST['date_evenement']) : '';

            // Validation de base
            if (empty($titreEvenement) || empty($dateEvenement)) {
                throw new Exception("Le titre et la date sont obligatoires.");
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEvenement)) {
                throw new Exception("Le format de la date est invalide.");
            }

            $galerieId = 0;
            
            // Mode édition : mise à jour de l'événement existant
            if ($eventMode === 'edit') {
                $galerieId = (int)($_POST['event_id'] ?? 0);
                
                if ($galerieId > 0) {
                    // Supprimer les images marquées pour suppression
                    $this->handleMediaDeletionInEdit();

                    // Vérifier que l'événement existe
                    if (!$this->galerieManager->eventExists($galerieId)) {
                        throw new Exception("Événement introuvable.");
                    }

                    // Mettre à jour l'événement
                    $this->galerieManager->updateEvent($galerieId, $titreEvenement, $dateEvenement);
                }
            }

            // Mode ajout : gérer les conflits
            if ($eventMode === 'add' || $galerieId === 0) {
                $existingId = $this->handleEventConflict($titreEvenement, $dateEvenement, $galerieId);
                
                if ($existingId > 0) {
                    $galerieId = $existingId;
                } else {
                    // Créer le nouvel événement
                    $userId = $this->getCurrentUserId();
                    $galerieId = $this->galerieManager->createEvent($titreEvenement, $dateEvenement, $userId);
                }
            }

            if ($galerieId <= 0) {
                throw new Exception("La création ou la récupération de l'événement a échoué.");
            }

            // Traiter les images
            $imageData = $this->processImages($galerieId);
            $processResult = $imageData['result'];
            $nameToIdMap = $imageData['nameToIdMap'];

            // Appliquer l'ordre final des médias
            if (isset($_POST['final_order'])) {
                $this->applyFinalOrder($nameToIdMap);
            }

            // Préparer le message de résultat
            $result = $this->prepareResultMessage($eventMode, $processResult);

        } catch (Exception $e) {
            $result['message'] = "❌ Erreur : " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Gère la suppression des médias en mode édition
     */
    private function handleMediaDeletionInEdit(): void
    {
        $mediaIdsToDelete = isset($_POST['media_ids_to_delete']) ? $_POST['media_ids_to_delete'] : '';
        $deleteIds = array_filter(array_map('intval', explode(',', $mediaIdsToDelete)));

        if (!empty($deleteIds)) {
            foreach ($deleteIds as $mediaId) {
                if ($mediaId <= 0) continue;
                $this->galerieManager->deleteMedia($mediaId);
            }
        }
    }

    /**
     * Gère les conflits d'événements
     * 
     * @return int ID existant ou 0
     */
    private function handleEventConflict(string $titre, string $date, int $currentGalerieId): int
    {
        $forceAdd = isset($_POST['force_add']) && $_POST['force_add'] === '1';
        $existingId = isset($_POST['existing_event_id']) ? trim($_POST['existing_event_id']) : '';

        if (!$forceAdd && empty($existingId)) {
            $excludeId = $currentGalerieId > 0 ? $currentGalerieId : null;
            $checkResult = $this->galerieManager->checkEventExists($titre, $date, $excludeId);
            
            if ($checkResult['same_title_and_date']) {
                return $checkResult['id'];
            } elseif ($checkResult['same_title_same_season']) {
                throw new Exception("❌ AJOUT BLOQUÉ : " . $checkResult['conflicts'][0]['message'] . " Vous ne pouvez pas créer deux événements avec le même titre dans la même saison.");
            } elseif ($checkResult['same_date']) {
                throw new Exception("❌ AJOUT BLOQUÉ : " . $checkResult['conflicts'][0]['message'] . " Vous ne pouvez pas créer deux événements à la même date.");
            }
        }

        return !empty($existingId) ? (int)$existingId : 0;
    }

    /**
     * Traite les images uploadées
     *
     * @return array Résultat
     */
    private function processImages(int $galerieId): array
    {
        $result = [
            'count' => 0,
            'errors' => []
        ];

        $nameToIdMap = [];
        $filesDataJson = $_POST['compressed_files_data'] ?? '[]';
        $filesData = json_decode($filesDataJson, true);

        if (!is_array($filesData) || empty($filesData)) {
            return ['result' => $result, 'nameToIdMap' => $nameToIdMap];
        }

        $processedHashes = [];

        foreach ($filesData as $file) {
            $originalName = $file['name'] ?? 'image_inconnue';
            $base64Data = $file['data'] ?? '';

            if (empty($base64Data)) {
                $result['errors'][] = "Fichier '$originalName' : Données manquantes.";
                continue;
            }

            try {
                // Traiter l'image
                $processResult = $this->imageProcessor->processImage($base64Data, $originalName, $galerieId);

                if (!$processResult['success']) {
                    throw new Exception($processResult['error']);
                }

                // Vérifier les doublons
                $duplicateCheck = $this->galerieManager->checkDuplicateMedia(
                    $galerieId,
                    $processResult['hash'],
                    $processResult['filename']
                );

                if ($duplicateCheck) {
                    // Supprimer le fichier créé
                    $this->imageProcessor->deleteImageFiles(
                        $processResult['filename'],
                        $processResult['thumb_path']
                    );

                    $duplicateReason = !empty($duplicateCheck['hash_fichier'])
                        ? 'Contenu identique (hash)'
                        : 'Image identique détectée';

                    throw new Exception("Cette image existe déjà dans cet événement. ($duplicateReason)");
                }

                // Enregistrer en base de données
                $mediaData = [
                    'id_galerie' => $galerieId,
                    'nom_fichier' => $processResult['filename'],
                    'chemin_fichier' => $processResult['filepath'],
                    'type_media' => 'image/webp',
                    'taille_fichier' => $processResult['filesize'],
                    'largeur' => $processResult['width'],
                    'hauteur' => $processResult['height'],
                    'ordre_affichage' => 999,
                    'hash_fichier' => $processResult['hash']
                ];

                $mediaId = $this->galerieManager->addMedia($mediaData);

                // Mapper le nom original du fichier à son nouvel ID de média
                $nameToIdMap[$originalName] = $mediaId;

                // Enregistrer la miniature
                if ($processResult['thumb_created']) {
                    $this->galerieManager->addThumbnail(
                        $mediaId,
                        $processResult['thumb_path'],
                        $processResult['thumb_width'],
                        $processResult['thumb_height']
                    );
                }

                $processedHashes[] = $processResult['hash'];
                $result['count']++;

            } catch (Exception $e) {
                $result['errors'][] = "Fichier '$originalName' : " . $e->getMessage();
            }
        }

        return ['result' => $result, 'nameToIdMap' => $nameToIdMap];
    }

    /**
     * Applique l'ordre final des médias
     * @param array $nameToIdMap Map des noms de fichiers originaux vers les nouveaux ID de médias
     */
    private function applyFinalOrder(array $nameToIdMap): void
    {
        $finalOrder = json_decode($_POST['final_order'] ?? '[]', true);
        
        if (!is_array($finalOrder) || empty($finalOrder)) {
            return;
        }

        // Mettre à jour l'ordre de tous les médias
        foreach ($finalOrder as $order => $item) {
            $mediaIdToUpdate = null;

            if ($item['type'] === 'existing' && isset($item['id'])) {
                // Médias existants - mettre à jour leur ordre
                $mediaIdToUpdate = (int)$item['id'];
            } elseif ($item['type'] === 'new' && isset($item['name'])) {
                // Nouveaux médias - utiliser le map pour trouver l'ID
                if (isset($nameToIdMap[$item['name']])) {
                    $mediaIdToUpdate = $nameToIdMap[$item['name']];
                }
            }

            if ($mediaIdToUpdate) {
                $this->galerieManager->updateMediaOrder($mediaIdToUpdate, $order);
            }
        }
    }

    /**
     * Prépare le message de résultat
     */
    private function prepareResultMessage(string $eventMode, array $processResult): array
    {
        $countFiles = $processResult['count'];
        $errors = $processResult['errors'];
        
        if ($eventMode === 'edit') {
            if ($countFiles === 0 && empty($errors)) {
                $message = "✅ Événement modifié !";
            } elseif ($countFiles > 0 && empty($errors)) {
                $message = "✅ Événement modifié avec $countFiles image(s) ajoutée(s) !";
            } elseif ($countFiles > 0 && !empty($errors)) {
                $message = "✅ Événement modifié avec $countFiles image(s), mais des erreurs sont survenues :\n\n" . implode("\n", $errors);
            } else {
                $message = "❌ Erreurs lors de la modification :\n\n" . implode("\n", $errors);
            }
        } else {
            $actionText = !empty($_POST['existing_event_id']) ? "mis à jour" : "ajouté";
            
            if ($countFiles > 0 && empty($errors)) {
                $message = "✅ Événement $actionText avec $countFiles image(s) !";
            } elseif ($countFiles > 0 && !empty($errors)) {
                $message = "✅ Événement $actionText avec $countFiles image(s), mais des erreurs sont survenues :\n\n" . implode("\n", $errors);
            } elseif ($countFiles === 0 && !empty($errors)) {
                $message = "❌ Aucune image n'a pu être ajoutée. Erreurs :\n\n" . implode("\n", $errors);
            } else {
                $message = "Aucune image n'a été traitée.";
            }
        }

        return [
            'success' => empty($errors) || $countFiles > 0,
            'message' => $message,
            'redirect' => true
        ];
    }

    /**
     * Récupère l'ID de l'utilisateur courant
     */
    private function getCurrentUserId(): int
    {
        return $_SESSION['user_id'] ?? 
               ($_SESSION['super-admin'][0] ?? 
               ($_SESSION['admin'][0] ?? 1));
    }

    // =========================================================================
    // HANDLERS AJAX
    // =========================================================================

    /**
     * Récupère les images d'un événement
     */
    private function handleGetEventImages(): void
    {
        $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
        
        if ($eventId <= 0) {
            $this->sendJsonResponse(['success' => false, 'images' => []]);
            return;
        }

        try {
            $images = $this->galerieManager->getEventImages($eventId);
            $this->sendJsonResponse(['success' => true, 'images' => $images]);
        } catch (Exception $e) {
            error_log("Erreur get_event_images: " . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la récupération des images.']);
        }
    }

    /**
     * Récupère le contenu paginé
     */
    private function handleGetPageContent(): void
    {
        $eventsPerPage = GalerieManager::EVENTS_PER_PAGE;
        $currentPage = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;

        try {
            $data = $this->galerieManager->getEventsPaginated($currentPage);
            
            // Générer le HTML
            $html = $this->generateEventsHtml($data['events'], $data['media']);
            
            $this->sendHtmlResponse($html);
        } catch (Exception $e) {
            error_log("Erreur get_page_content: " . $e->getMessage());
            $this->sendHtmlResponse('<p style="color: red; text-align: center;">Erreur lors du chargement de la page.</p>');
        }
    }

    /**
     * Vérifie l'existence d'un événement
     */
    private function handleCheckEvent(): void
    {
        $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
        $date = isset($_POST['date']) ? trim($_POST['date']) : '';
        $excludeId = isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : null;

        if (empty($titre) || empty($date)) {
            $this->sendJsonResponse(['error' => 'Paramètres manquants.']);
            return;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->sendJsonResponse(['error' => 'Format de date invalide.']);
            return;
        }

        $checkResult = $this->galerieManager->checkEventExists($titre, $date, $excludeId);
        $this->sendJsonResponse($checkResult);
    }

    /**
     * Modifie un événement
     */
    private function handleEditEvent(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
        $date = isset($_POST['date']) ? trim($_POST['date']) : '';

        if ($id <= 0) {
            $this->sendJsonResponse(['success' => false, 'message' => 'ID invalide.']);
            return;
        }

        if (empty($titre)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Le titre est obligatoire.']);
            return;
        }

        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'La date est obligatoire et doit être au format YYYY-MM-DD.']);
            return;
        }

        try {
            if (!$this->galerieManager->eventExists($id)) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Événement introuvable.']);
                return;
            }

            $this->galerieManager->updateEvent($id, $titre, $date);
            $this->sendJsonResponse(['success' => true]);
        } catch (Exception $e) {
            error_log("Erreur edit_event: " . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la modification.']);
        }
    }

    /**
     * Supprime un événement
     */
    private function handleDeleteEvent(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $this->sendJsonResponse(['success' => false, 'message' => 'ID invalide.']);
            return;
        }

        try {
            $result = $this->galerieManager->deleteEvent($id);
            $this->sendJsonResponse($result);
        } catch (Exception $e) {
            error_log("Erreur delete_event: " . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la suppression.']);
        }
    }

    /**
     * Supprime un média
     */
    private function handleDeleteMedia(): void
    {
        $mediaId = isset($_POST['media_id']) ? (int)$_POST['media_id'] : 0;

        if ($mediaId <= 0) {
            $this->sendJsonResponse(['success' => false, 'message' => 'ID média invalide.']);
            return;
        }

        try {
            $success = $this->galerieManager->deleteMedia($mediaId);
            
            if ($success) {
                $this->sendJsonResponse(['success' => true]);
            } else {
                $this->sendJsonResponse(['success' => false, 'message' => 'Média introuvable.']);
            }
        } catch (Exception $e) {
            error_log("Erreur delete_media: " . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la suppression.']);
        }
    }

    /**
     * Réordonne les médias
     */
    private function handleReorderMedia(): void
    {
        $orderedIds = isset($_POST['ordered_ids']) ? $_POST['ordered_ids'] : '';
        $ids = array_filter(array_map('intval', explode(',', $orderedIds)));

        if (empty($ids)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Liste d\'IDs vide.']);
            return;
        }

        try {
            $success = $this->galerieManager->reorderMedia($ids);
            $this->sendJsonResponse(['success' => $success]);
        } catch (Exception $e) {
            error_log("Erreur reorder_media: " . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Erreur lors du réordonnement.']);
        }
    }

    // =========================================================================
    // MÉTHODES UTILITAIRES
    // =========================================================================

    /**
     * Vérifie le token CSRF
     */
    private function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    /**
     * Envoie une réponse JSON
     */
    private function sendJsonResponse($data, int $statusCode = 200): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        
        http_response_code($statusCode);
        echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        exit;
    }

    /**
     * Envoie une réponse HTML
     */
    private function sendHtmlResponse(string $html, int $statusCode = 200): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        
        http_response_code($statusCode);
        echo $html;
        exit;
    }

    /**
     * Génère le HTML pour les événements
     */
    public function generateEventsHtml(array $events, array $mediaByEvent): string
    {
        if (empty($events)) {
            return '<p style="text-align: center; margin-top: 30px;">Aucun événement n\'a été trouvé dans la galerie.</p>';
        }

        $html = '';
        
        foreach ($events as $event) {
            $html .= $this->generateEventHtml($event, $mediaByEvent[$event['id']] ?? []);
        }

        return $html;
    }

    /**
     * Génère le HTML pour un événement
     */
    public function generateEventHtml(array $event, array $media): string
    {
        $formattedDate = $this->galerieManager->formatDateFrench($event['date_evenement']);
        
        $html = '
        <section class="galerie" data-event-id="' . (int)$event['id'] . '">
            <hr>

            <div class="section-header">
                <h3 class="titre_photos">
                    <span class="event-titre-display">' . htmlspecialchars($event['titre'], ENT_QUOTES, 'UTF-8') . '</span>
                    &nbsp;–&nbsp;
                    <span class="event-date-display">' . htmlspecialchars($formattedDate) . '</span>
                </h3>
                <div class="btn-icons-events">
                    <button type="button" class="btn-icon btn-icon-edit" title="Modifier cet événement" 
                            onclick="startEditEvent(this)" 
                            data-id="' . (int)$event['id'] . '" 
                            data-titre="' . htmlspecialchars($event['titre'], ENT_QUOTES | JSON_HEX_APOS) . '" 
                            data-date="' . htmlspecialchars($event['date_evenement'], ENT_QUOTES) . '">
                        <img src="/cosmtt/img/icones/write.png" alt="Modifier" width="18">
                    </button>

                    <button type="button" class="btn-icon btn-icon-delete" title="Supprimer cet événement" 
                            onclick="deleteEvent(' . (int)$event['id'] . ', \'' . htmlspecialchars($event['titre'], ENT_QUOTES | JSON_HEX_APOS) . '\')">
                        <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" width="18">
                    </button>
                </div>
            </div>

            <div class="photo" data-event-id="' . (int)$event['id'] . '">';

        if (!empty($media)) {
            foreach ($media as $index => $mediaItem) {
                $fullPath = htmlspecialchars($mediaItem['chemin_fichier'], ENT_QUOTES, 'UTF-8');
                $thumbPath = !empty($mediaItem['chemin_miniature']) 
                    ? htmlspecialchars($mediaItem['chemin_miniature'], ENT_QUOTES, 'UTF-8') 
                    : $fullPath;
                
                if (strpos($mediaItem['type_media'] ?? '', 'image/') === 0) {
                    $html .= '
                <div class="photo-edit-wrapper" data-media-id="' . (int)$mediaItem['id'] . '" draggable="true">
                    <a class="photos" href="javascript:void(0)" onclick="openLightbox(event, ' . $index . ', ' . (int)$event['id'] . ')">
                        <img class="photo_galerie" src="' . $thumbPath . '" alt="Photo de l\'événement ' . htmlspecialchars($event['titre'], ENT_QUOTES, 'UTF-8') . '" title="Cliquez pour agrandir">
                    </a>
                </div>';
                }
            }
        } else {
            $html .= '
                <p>Aucune photo pour cet événement.</p>';
        }

        $html .= '
            </div>
        </section>';

        return $html;
    }

    /**
     * Getter pour le manager
     */
    public function getGalerieManager(): GalerieManager
    {
        return $this->galerieManager;
    }
}
