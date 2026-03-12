<?php
/**
 * =============================================================================
 * GALERIE MANAGER - GESTION DES ÉVÉNEMENTS ET MÉDIAS
 * =============================================================================
 * 
 * Classe permettant de gérer les événements photo et leurs médias.
 * Encapsule toutes les opérations de base de données.
 * 
 * @author Admin
 * @version 1.0
 * =============================================================================
 */

class GalerieManager
{
    private PDO $conn;
    private string $uploadDir;
    private string $thumbDir;

    /**
     * Constantes de configuration
     */
    public const EVENTS_PER_PAGE = 4;
    public const MAX_IMAGE_SIZE = 4 * 1024 * 1024; // 4 Mo

    /**
     * Constructeur
     * 
     * @param PDO $connection Connexion à la base de données
     */
    public function __construct(PDO $connection)
    {
        $this->conn = $connection;
        $this->uploadDir = dirname(__DIR__, 4) . '/photos/';
        $this->thumbDir = dirname(__DIR__, 4) . '/photos/miniatures/';
        
        $this->createDirectories();
    }

    /**
     * Crée les répertoires d'upload s'ils n'existent pas
     */
    private function createDirectories(): void
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbDir)) {
            mkdir($this->thumbDir, 0755, true);
        }
    }

    /**
     * Getter pour le répertoire d'upload
     */
    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }

    /**
     * Getter pour le répertoire des miniatures
     */
    public function getThumbDir(): string
    {
        return $this->thumbDir;
    }

    // =========================================================================
    // OPÉRATIONS SUR LES ÉVÉNEMENTS
    // =========================================================================

    /**
     * Récupère tous les événements avec pagination
     * 
     * @param int $page Numéro de page
     * @return array ['events' => [], 'total' => int, 'pages' => int]
     */
    public function getEventsPaginated(int $page = 1): array
    {
        $eventsPerPage = self::EVENTS_PER_PAGE;
        $currentPage = max(1, $page);
        
        // Compter le total des événements
        $countStmt = $this->conn->query("SELECT COUNT(*) FROM section_Galerie");
        $totalEvents = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalEvents / $eventsPerPage));
        
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }
        
        $offset = ($currentPage - 1) * $eventsPerPage;

        $stmt = $this->conn->prepare(
            "SELECT * FROM section_Galerie ORDER BY date_evenement DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $eventsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les médias associés
        $mediaByEvent = [];
        if (!empty($events)) {
            $eventIds = array_map(fn($e) => (int)$e['id'], $events);
            $mediaByEvent = $this->getMediaByEventIds($eventIds);
        }

        return [
            'events' => $events,
            'media' => $mediaByEvent,
            'total' => $totalEvents,
            'current_page' => $currentPage,
            'total_pages' => $totalPages
        ];
    }

    /**
     * Récupère les médias pour une liste d'IDs d'événements
     * 
     * @param array $eventIds Tableau d'IDs d'événements
     * @return array Médias indexés par ID d'événement
     */
    public function getMediaByEventIds(array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        
        $stmt = $this->conn->prepare("
            SELECT m.id, m.id_galerie, m.chemin_fichier, m.type_media, 
                   m.nom_fichier, m.ordre_affichage, mi.chemin_miniature
            FROM media m
            LEFT JOIN miniatures mi ON m.id = mi.id_media
            WHERE m.id_galerie IN ($placeholders)
            ORDER BY m.id_galerie, m.ordre_affichage ASC
        ");
        $stmt->execute($eventIds);
        
        $allMedia = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mediaByEvent = [];
        
        foreach ($allMedia as $media) {
            $mediaByEvent[$media['id_galerie']][] = $media;
        }
        
        return $mediaByEvent;
    }

    /**
     * Récupère les images d'un événement spécifique
     * 
     * @param int $eventId ID de l'événement
     * @return array Images de l'événement
     */
    public function getEventImages(int $eventId): array
    {
        if ($eventId <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT m.id, m.chemin_fichier, mi.chemin_miniature
            FROM media m
            LEFT JOIN miniatures mi ON m.id = mi.id_media
            WHERE m.id_galerie = :event_id
            ORDER BY m.ordre_affichage ASC
        ");
        $stmt->execute([':event_id' => $eventId]);
        $medias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $images = [];
        foreach ($medias as $media) {
            $images[] = [
                'id' => (int)$media['id'],
                'full' => htmlspecialchars($media['chemin_fichier'], ENT_QUOTES, 'UTF-8'),
                'thumb' => htmlspecialchars(
                    !empty($media['chemin_miniature']) 
                        ? $media['chemin_miniature'] 
                        : $media['chemin_fichier'], 
                    ENT_QUOTES, 'UTF-8'
                )
            ];
        }
        
        return $images;
    }

    /**
     * Vérifie si un événement existe selon différents critères
     * 
     * @param string $titre Titre de l'événement
     * @param string $date Date de l'événement (YYYY-MM-DD)
     * @param int|null $excludeId ID à exclure (mode édition)
     * @return array Résultats de la vérification
     */
    public function checkEventExists(string $titre, string $date, ?int $excludeId = null): array
    {
        $result = [
            'exists' => false,
            'id' => null,
            'same_title_and_date' => false,
            'same_title_same_season' => false,
            'same_date' => false,
            'conflicts' => []
        ];

        // Calcul de la saison (septembre à août)
        $month = (int)date('n', strtotime($date));
        $year = (int)date('Y', strtotime($date));
        
        if ($month >= 9) {
            $seasonStart = $year;
            $seasonEnd = $year + 1;
        } else {
            $seasonStart = $year - 1;
            $seasonEnd = $year;
        }
        $seasonStartDate = $seasonStart . '-09-01';
        $seasonEndDate = $seasonEnd . '-08-31';
        
        // Vérification 1: Même titre ET même date
        $sql = "SELECT id, titre, date_evenement FROM section_Galerie 
                WHERE titre = :titre AND date_evenement = :date";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $sql .= " LIMIT 1";
        
        $params = [':titre' => $titre, ':date' => $date];
        if ($excludeId !== null) {
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $eventExact = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($eventExact) {
            $result['exists'] = true;
            $result['id'] = (int)$eventExact['id'];
            $result['same_title_and_date'] = true;
            $result['conflicts'][] = [
                'type' => 'same_title_and_date',
                'message' => 'Un événement avec ce titre et cette date existe déjà.'
            ];
        }
        
        // Vérification 2: Même titre ET même saison
        if (!$result['same_title_and_date']) {
            $sql = "SELECT id, titre, date_evenement FROM section_Galerie 
                    WHERE titre = :titre AND date_evenement BETWEEN :season_start AND :season_end 
                    AND date_evenement != :date";
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
            }
            $sql .= " LIMIT 1";
            
            $params = [
                ':titre' => $titre, 
                ':season_start' => $seasonStartDate, 
                ':season_end' => $seasonEndDate, 
                ':date' => $date
            ];
            if ($excludeId !== null) {
                $params[':exclude_id'] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $eventSeason = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($eventSeason) {
                $result['same_title_same_season'] = true;
                $result['conflicts'][] = [
                    'type' => 'same_title_same_season',
                    'message' => "Un événement avec ce titre existe déjà dans la saison {$seasonStart}-{$seasonEnd} (" . 
                                 $this->formatDateFrench($eventSeason['date_evenement']) . ").",
                    'existing_date' => $eventSeason['date_evenement']
                ];
            }
        }
        
        // Vérification 3: Même date mais titre différent
        if (!$result['same_title_and_date']) {
            $sql = "SELECT id, titre, date_evenement FROM section_Galerie 
                    WHERE date_evenement = :date AND titre != :titre";
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
            }
            $sql .= " LIMIT 1";
            
            $params = [':date' => $date, ':titre' => $titre];
            if ($excludeId !== null) {
                $params[':exclude_id'] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $eventDate = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($eventDate) {
                $result['same_date'] = true;
                $result['conflicts'][] = [
                    'type' => 'same_date',
                    'message' => 'Un événement existe déjà à cette date : "' . 
                                 htmlspecialchars($eventDate['titre'], ENT_QUOTES, 'UTF-8') . '".',
                    'existing_title' => $eventDate['titre']
                ];
            }
        }
        
        return $result;
    }

    /**
     * Crée un nouvel événement
     * 
     * @param string $titre Titre de l'événement
     * @param string $date Date de l'événement
     * @param int $userId ID de l'utilisateur
     * @return int ID de l'événement créé
     */
    public function createEvent(string $titre, string $date, int $userId): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO section_Galerie (titre, date_evenement, id_user) VALUES (:titre, :date, :user)"
        );
        $stmt->execute([':titre' => $titre, ':date' => $date, ':user' => $userId]);
        
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Met à jour un événement existant
     * 
     * @param int $id ID de l'événement
     * @param string $titre Nouveau titre
     * @param string $date Nouvelle date
     * @return bool Succès de l'opération
     */
    public function updateEvent(int $id, string $titre, string $date): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE section_Galerie SET titre = :titre, date_evenement = :date WHERE id = :id"
        );
        return $stmt->execute([':titre' => $titre, ':date' => $date, ':id' => $id]);
    }

    /**
     * Supprime un événement et tous ses médias
     * 
     * @param int $id ID de l'événement
     * @return array Résultat de la suppression
     */
    public function deleteEvent(int $id): array
    {
        $result = ['success' => false, 'total_events' => 0, 'new_page' => 1, 'total_pages' => 1];
        
        try {
            $this->conn->beginTransaction();

            // Récupérer les médias de l'événement
            $stmtM = $this->conn->prepare(
                "SELECT m.id, m.nom_fichier, mi.chemin_miniature 
                 FROM media m 
                 LEFT JOIN miniatures mi ON m.id = mi.id_media 
                 WHERE m.id_galerie = :id"
            );
            $stmtM->execute([':id' => $id]);
            $medias = $stmtM->fetchAll(PDO::FETCH_ASSOC);

            // Supprimer les fichiers physiques
            foreach ($medias as $media) {
                $mainFile = $this->uploadDir . $media['nom_fichier'];
                if (file_exists($mainFile) && is_file($mainFile)) {
                    unlink($mainFile);
                }
                if (!empty($media['chemin_miniature'])) {
                    $thumbFile = $this->thumbDir . basename($media['chemin_miniature']);
                    if (file_exists($thumbFile) && is_file($thumbFile)) {
                        unlink($thumbFile);
                    }
                }
            }

            // Supprimer les enregistrements BD
            $this->conn->prepare("DELETE mi FROM miniatures mi INNER JOIN media m ON mi.id_media = m.id WHERE m.id_galerie = :id")
                ->execute([':id' => $id]);
            $this->conn->prepare("DELETE FROM media WHERE id_galerie = :id")->execute([':id' => $id]);
            $this->conn->prepare("DELETE FROM section_Galerie WHERE id = :id")->execute([':id' => $id]);

            $this->conn->commit();

            // Calculer la nouvelle pagination
            $countStmt = $this->conn->query("SELECT COUNT(*) FROM section_Galerie");
            $totalEventsAfter = (int)$countStmt->fetchColumn();

            $eventsPerPage = self::EVENTS_PER_PAGE;
            $newTotalPages = max(1, (int)ceil($totalEventsAfter / $eventsPerPage));

            $result = [
                'success' => true,
                'total_events' => $totalEventsAfter,
                'new_page' => $newTotalPages,
                'total_pages' => $newTotalPages
            ];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Erreur delete_event: " . $e->getMessage());
        }
        
        return $result;
    }

    /**
     * Vérifie si un événement existe par son ID
     * 
     * @param int $id ID de l'événement
     * @return bool
     */
    public function eventExists(int $id): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM section_Galerie WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return (bool)$stmt->fetch();
    }

    // =========================================================================
    // OPÉRATIONS SUR LES MÉDIAS
    // =========================================================================

    /**
     * Ajoute un média dans la base de données
     * 
     * @param array $mediaData Données du média
     * @return int ID du média créé
     */
    public function addMedia(array $mediaData): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO media (
                id_galerie, nom_fichier, chemin_fichier, type_media, 
                taille_fichier, largeur, hauteur, ordre_affichage, hash_fichier
            ) VALUES (
                :id_gal, :nom, :chemin, :type, 
                :taille, :w, :h, :ordre, :hash
            )
        ");
        
        $stmt->execute([
            ':id_gal' => $mediaData['id_galerie'],
            ':nom' => $mediaData['nom_fichier'],
            ':chemin' => $mediaData['chemin_fichier'],
            ':type' => $mediaData['type_media'],
            ':taille' => $mediaData['taille_fichier'],
            ':w' => $mediaData['largeur'],
            ':h' => $mediaData['hauteur'],
            ':ordre' => $mediaData['ordre_affichage'] ?? 999,
            ':hash' => $mediaData['hash_fichier']
        ]);
        
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Ajoute une miniature dans la base de données
     * 
     * @param int $mediaId ID du média parent
     * @param string $thumbPath Chemin de la miniature
     * @param int $width Largeur
     * @param int $height Hauteur
     * @return bool Succès
     */
    public function addThumbnail(int $mediaId, string $thumbPath, int $width, int $height): bool
    {
        $stmt = $this->conn->prepare("
            INSERT INTO miniatures (id_media, chemin_miniature, largeur, hauteur) 
            VALUES (:id_media, :chemin, :w, :h)
        ");
        
        return $stmt->execute([
            ':id_media' => $mediaId,
            ':chemin' => $thumbPath,
            ':w' => $width,
            ':h' => $height
        ]);
    }

    /**
     * Supprime un média et ses fichiers
     * 
     * @param int $mediaId ID du média
     * @return bool Succès
     */
    public function deleteMedia(int $mediaId): bool
    {
        try {
            $this->conn->beginTransaction();

            // Récupérer les infos du média
            $stmtM = $this->conn->prepare(
                "SELECT m.nom_fichier, mi.chemin_miniature 
                 FROM media m 
                 LEFT JOIN miniatures mi ON m.id = mi.id_media 
                 WHERE m.id = :id"
            );
            $stmtM->execute([':id' => $mediaId]);
            $media = $stmtM->fetch(PDO::FETCH_ASSOC);

            if (!$media) {
                $this->conn->rollBack();
                return false;
            }

            // Supprimer les fichiers physiques
            $mainFile = $this->uploadDir . $media['nom_fichier'];
            if (file_exists($mainFile) && is_file($mainFile)) {
                unlink($mainFile);
            }

            if (!empty($media['chemin_miniature'])) {
                $thumbFile = $this->thumbDir . basename($media['chemin_miniature']);
                if (file_exists($thumbFile) && is_file($thumbFile)) {
                    unlink($thumbFile);
                }
            }

            // Supprimer les enregistrements BD
            $this->conn->prepare("DELETE FROM miniatures WHERE id_media = :id")
                ->execute([':id' => $mediaId]);
            $this->conn->prepare("DELETE FROM media WHERE id = :id")
                ->execute([':id' => $mediaId]);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Erreur delete_media: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour l'ordre d'affichage des médias
     * 
     * @param array $ids Ordered array of media IDs
     * @return bool Succès
     */
    public function reorderMedia(array $ids): bool
    {
        try {
            $stmt = $this->conn->prepare("UPDATE media SET ordre_affichage = :ordre WHERE id = :id");
            
            foreach ($ids as $index => $id) {
                if ($id > 0) {
                    $stmt->execute([':ordre' => $index, ':id' => $id]);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("Erreur reorder_media: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour l'ordre d'affichage d'un média spécifique
     * 
     * @param int $mediaId ID du média
     * @param int $order Nouvel ordre
     * @return bool Succès
     */
    public function updateMediaOrder(int $mediaId, int $order): bool
    {
        try {
            $stmt = $this->conn->prepare("UPDATE media SET ordre_affichage = :ordre WHERE id = :id");
            return $stmt->execute([':ordre' => $order, ':id' => $mediaId]);
        } catch (Exception $e) {
            error_log("Erreur updateMediaOrder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les IDs des derniers médias ajoutés pour un événement
     * 
     * @param int $galerieId ID de l'événement
     * @param int $limit Nombre de médias à récupérer
     * @return array Tableau des IDs
     */
    public function getLatestMediaIds(int $galerieId, int $limit = 10): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT id FROM media 
                WHERE id_galerie = :id_gal 
                ORDER BY id DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':id_gal', $galerieId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return array_reverse($results); // Retourner dans l'ordre d'ajout (plus ancien en premier)
        } catch (Exception $e) {
            error_log("Erreur getLatestMediaIds: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Vérifie si un média existe par son hash ou nom de fichier
     * 
     * @param int $galerieId ID de l'événement
     * @param string $hash Hash du fichier
     * @param string $filename Nom du fichier
     * @return array|false Infos du média existant ou false
     */
    public function checkDuplicateMedia(int $galerieId, string $hash, string $filename)
    {
        $stmt = $this->conn->prepare("
            SELECT id, nom_fichier, hash_fichier 
            FROM media 
            WHERE id_galerie = :id_gal 
            AND (hash_fichier = :hash OR nom_fichier = :nom_fichier)
            LIMIT 1
        ");
        
        $stmt->execute([
            ':id_gal' => $galerieId, 
            ':hash' => $hash,
            ':nom_fichier' => $filename
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // MÉTHODES UTILITAIRES
    // =========================================================================

    /**
     * Formate une date en français
     * 
     * @param string $dateString Date au format MySQL
     * @return string Date formatée
     */
    public function formatDateFrench(string $dateString): string
    {
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return $dateString;
        }
        
        if (class_exists('IntlDateFormatter')) {
            try {
                $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
                return $formatter->format(new DateTime($dateString));
            } catch (Exception $e) {
                // Fallback
            }
        }
        
        $months = [
            'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'
        ];
        
        $day = (int)date('d', $timestamp);
        $month = $months[(int)date('m', $timestamp) - 1];
        $year = date('Y', $timestamp);
        
        return sprintf('%02d %s %d', $day, $month, $year);
    }
}