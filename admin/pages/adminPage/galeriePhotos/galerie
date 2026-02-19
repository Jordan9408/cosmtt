<?php
/**
 * Galerie Photos - Page d'administration
 * 
 * Ce fichier permet de gérer les événements photo et l'upload d'images.
 * Les images sont converties en WebP et des miniatures sont générées.
 * 
 * Fonctionnalités :
 * - AJAX: Vérification d'existence d'événement
 * - AJAX: Modification titre/date d'événement (edit_event)
 * - AJAX: Suppression d'événement (delete_event)
 * - AJAX: Suppression d'un média (delete_media)
 * - AJAX: Réordonnement des médias (reorder_media)
 * - Upload: Compression client, conversion WebP serveur, miniatures
 * - Sécurité: Validation magic bytes, scan malware, ClamAV optionnel
 * 
 * CORRECTION: Ajout de vérifications serveur renforcées pour empêcher les doublons
 */

// =============================================================================
// SECTION 1: INCLUDES ET CONFIGURATION
// =============================================================================

require_once dirname(__DIR__, 2) . '/connect_ddb.php';
require_once dirname(__DIR__, 2) . '/parts/header.php';
require_once dirname(__DIR__, 3) . '/includes/security.php';
require_once dirname(__DIR__, 3) . '/includes/session_check.php';
require_once dirname(__DIR__, 2) . '/classPages/security/FileSecurityValidator.php';

// Gestion des messages flash après redirection (Pattern PRG)
$flashMessage = null;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Configuration des répertoires
define('UPLOAD_DIR', dirname(__DIR__, 4) . '/photos/');
define('UPLOAD_THUMB_DIR', dirname(__DIR__, 4) . '/photos/miniatures/');

// =============================================================================
// SECTION 2: CRÉATION DES RÉPERTOIRES
// =============================================================================

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(UPLOAD_THUMB_DIR)) {
    mkdir(UPLOAD_THUMB_DIR, 0755, true);
}

// =============================================================================
// SECTION 3: FONCTIONS UTILITAIRES
// =============================================================================

/**
 * Crée une miniature et convertit en WebP
 */
function createThumbnail(string $sourcePath, string $destPath, int $fixedHeight = 400) {
    $imageInfo = getimagesize($sourcePath);
    
    if (!$imageInfo) {
        return false;
    }

    [$width, $height, $type] = $imageInfo;

    $sourceRatio = $width / $height;
    $newHeight = $fixedHeight;
    $newWidth = (int)($newHeight * $sourceRatio);
    
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
    }
    
    imagecopyresampled(
        $newImage, 
        $source, 
        0, 0, 
        0, 0, 
        $newWidth, $newHeight, 
        $width, $height
    );
    
    $success = imagewebp($newImage, $destPath, 100);
    
    imagedestroy($source);
    imagedestroy($newImage);
    
    if ($success && file_exists($destPath)) {
        return ['width' => $newWidth, 'height' => $newHeight];
    }
    
    return false;
}

/**
 * Formate une date en français
 */
function formatDateFrench(string $dateString): string {
    if (!class_exists('IntlDateFormatter')) {
        setlocale(LC_TIME, 'fr_FR.UTF-8', 'fra');
        return strftime('%d %B %Y', strtotime($dateString));
    }
    
    try {
        $date = new DateTime($dateString);
        $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        return $formatter->format($date);
    } catch (Exception $e) {
        return $dateString;
    }
}

/**
 * Vérifie si un événement existe selon différents critères
 */
function checkEventExists(PDO $conn, string $titre, string $date): array {
    $result = [
        'exists' => false,
        'id' => null,
        'same_title_and_date' => false,
        'same_title_same_season' => false,
        'same_date' => false,
        'conflicts' => []
    ];
    
    $year = date('Y', strtotime($date));
    $month = date('n', strtotime($date));
    if ($month >= 9) {
        $seasonStart = $year;
        $seasonEnd = $year + 1;
    } else {
        $seasonStart = $year - 1;
        $seasonEnd = $year;
    }
    $seasonStartDate = $seasonStart . '-09-01';
    $seasonEndDate = $seasonEnd . '-08-31';
    
    $stmt = $conn->prepare("
        SELECT id, titre, date_evenement 
        FROM section_Galerie 
        WHERE titre = :titre AND date_evenement = :date 
        LIMIT 1
    ");
    $stmt->execute([':titre' => $titre, ':date' => $date]);
    $eventExact = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($eventExact) {
        $result['exists'] = true;
        $result['id'] = $eventExact['id'];
        $result['same_title_and_date'] = true;
        $result['conflicts'][] = [
            'type' => 'same_title_and_date',
            'message' => 'Un événement avec ce titre et cette date existe déjà.'
        ];
    }
    
    if (!$result['same_title_and_date']) {
        $stmt = $conn->prepare("
            SELECT id, titre, date_evenement 
            FROM section_Galerie 
            WHERE titre = :titre 
            AND date_evenement BETWEEN :season_start AND :season_end
            AND date_evenement != :date
            LIMIT 1
        ");
        $stmt->execute([
            ':titre' => $titre,
            ':season_start' => $seasonStartDate,
            ':season_end' => $seasonEndDate,
            ':date' => $date
        ]);
        $eventSeason = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($eventSeason) {
            $result['same_title_same_season'] = true;
            $result['conflicts'][] = [
                'type' => 'same_title_same_season',
                'message' => 'Un événement avec ce titre existe déjà dans la saison ' . $seasonStart . '-' . $seasonEnd . ' (' . date('d/m/Y', strtotime($eventSeason['date_evenement'])) . ').',
                'existing_date' => $eventSeason['date_evenement']
            ];
        }
    }
    
    if (!$result['same_title_and_date']) {
        $stmt = $conn->prepare("
            SELECT id, titre, date_evenement 
            FROM section_Galerie 
            WHERE date_evenement = :date
            AND titre != :titre
            LIMIT 1
        ");
        $stmt->execute([':date' => $date, ':titre' => $titre]);
        $eventDate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($eventDate) {
            $result['same_date'] = true;
            $result['conflicts'][] = [
                'type' => 'same_date',
                'message' => 'Un événement existe déjà à cette date : "' . htmlspecialchars($eventDate['titre']) . '".',
                'existing_title' => $eventDate['titre']
            ];
        }
    }
    
    return $result;
}

// =============================================================================
// SECTION 4: HANDLERS AJAX
// =============================================================================

// Nettoyage du buffer pour toutes les réponses AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- Vérification d'existence d'événement ---
    if ($action === 'check_event') {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        
        $titre = $_POST['titre'] ?? '';
        $date  = $_POST['date'] ?? '';
        
        echo json_encode(checkEventExists($conn, $titre, $date));
        exit;
    }

    // -------------------------------------------------------------------------
    // NOUVEAU : Modification du titre et/ou de la date d'un événement
    // -------------------------------------------------------------------------
    if ($action === 'edit_event') {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

        $id    = (int)($_POST['id'] ?? 0);
        $titre = trim($_POST['titre'] ?? '');
        $date  = trim($_POST['date']  ?? '');

        if (!$id || !$titre || !$date) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
            exit;
        }

        try {
            $stmt = $conn->prepare("UPDATE section_Galerie SET titre = :titre, date_evenement = :date WHERE id = :id");
            $stmt->execute([':titre' => $titre, ':date' => $date, ':id' => $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // NOUVEAU : Suppression d'un événement et de tous ses médias
    // -------------------------------------------------------------------------
    if ($action === 'delete_event') {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID invalide.']);
            exit;
        }

        try {
            $conn->beginTransaction();

            // Récupérer tous les médias liés pour supprimer les fichiers physiques
            $stmtM = $conn->prepare("SELECT m.id, m.nom_fichier, mi.chemin_miniature FROM media m LEFT JOIN miniatures mi ON m.id = mi.id_media WHERE m.id_galerie = :id");
            $stmtM->execute([':id' => $id]);
            $medias = $stmtM->fetchAll(PDO::FETCH_ASSOC);

            foreach ($medias as $media) {
                // Supprimer fichier principal
                $mainFile = UPLOAD_DIR . $media['nom_fichier'];
                if (file_exists($mainFile)) {
                    unlink($mainFile);
                }
                // Supprimer miniature
                if (!empty($media['chemin_miniature'])) {
                    $thumbFile = UPLOAD_THUMB_DIR . basename($media['chemin_miniature']);
                    if (file_exists($thumbFile)) {
                        unlink($thumbFile);
                    }
                }
            }

            // Supprimer miniatures en BDD
            $conn->prepare("DELETE mi FROM miniatures mi INNER JOIN media m ON mi.id_media = m.id WHERE m.id_galerie = :id")->execute([':id' => $id]);
            // Supprimer médias en BDD
            $conn->prepare("DELETE FROM media WHERE id_galerie = :id")->execute([':id' => $id]);
            // Supprimer l'événement
            $conn->prepare("DELETE FROM section_Galerie WHERE id = :id")->execute([':id' => $id]);

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // NOUVEAU : Suppression d'un média individuel
    // -------------------------------------------------------------------------
    if ($action === 'delete_media') {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

        $mediaId = (int)($_POST['media_id'] ?? 0);

        if (!$mediaId) {
            echo json_encode(['success' => false, 'message' => 'ID média invalide.']);
            exit;
        }

        try {
            $conn->beginTransaction();

            // Récupérer le média
            $stmtM = $conn->prepare("SELECT m.nom_fichier, mi.chemin_miniature FROM media m LEFT JOIN miniatures mi ON m.id = mi.id_media WHERE m.id = :id");
            $stmtM->execute([':id' => $mediaId]);
            $media = $stmtM->fetch(PDO::FETCH_ASSOC);

            if (!$media) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Média introuvable.']);
                exit;
            }

            // Supprimer fichier principal
            $mainFile = UPLOAD_DIR . $media['nom_fichier'];
            if (file_exists($mainFile)) {
                unlink($mainFile);
            }

            // Supprimer miniature
            if (!empty($media['chemin_miniature'])) {
                $thumbFile = UPLOAD_THUMB_DIR . basename($media['chemin_miniature']);
                if (file_exists($thumbFile)) {
                    unlink($thumbFile);
                }
            }

            // Supprimer en BDD
            $conn->prepare("DELETE FROM miniatures WHERE id_media = :id")->execute([':id' => $mediaId]);
            $conn->prepare("DELETE FROM media WHERE id = :id")->execute([':id' => $mediaId]);

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // NOUVEAU : Réordonnement des médias d'un événement
    // -------------------------------------------------------------------------
    if ($action === 'reorder_media') {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

        $orderedIds = $_POST['ordered_ids'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', $orderedIds)));

        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Liste d\'IDs vide.']);
            exit;
        }

        try {
            $stmt = $conn->prepare("UPDATE media SET ordre_affichage = :ordre WHERE id = :id");
            foreach ($ids as $index => $id) {
                $stmt->execute([':ordre' => $index, ':id' => $id]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// =============================================================================
// SECTION 5: TRAITEMENT DU FORMULAIRE
// =============================================================================

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        $message = "❌ Erreur de configuration serveur : La librairie d'images GD avec le support WebP est requise mais n'est pas activée. Impossible de traiter les images. Veuillez contacter l'administrateur du site.";
    } else {
        try {
            $titreEvenement = htmlspecialchars($_POST['titre_evenement'] ?? '', ENT_QUOTES, 'UTF-8');
            $dateEvenement  = htmlspecialchars($_POST['date_evenement'] ?? '', ENT_QUOTES, 'UTF-8');
            
            if (empty($titreEvenement) || empty($dateEvenement)) {
                throw new Exception("Le titre et la date sont obligatoires.");
            }

            $forceAdd   = isset($_POST['force_add']) && $_POST['force_add'] === '1';
            $existingId = $_POST['existing_event_id'] ?? '';
            
            if (!$forceAdd && empty($existingId)) {
                $checkResult = checkEventExists($conn, $titreEvenement, $dateEvenement);
                
                if ($checkResult['same_title_and_date']) {
                    $existingId = $checkResult['id'];
                } elseif ($checkResult['same_title_same_season']) {
                    $conflictMsg = $checkResult['conflicts'][0]['message'];
                    throw new Exception("❌ AJOUT BLOQUÉ : " . $conflictMsg . " Vous ne pouvez pas créer deux événements avec le même titre dans la même saison.");
                } elseif ($checkResult['same_date']) {
                    $conflictMsg = $checkResult['conflicts'][0]['message'];
                    throw new Exception("❌ AJOUT BLOQUÉ : " . $conflictMsg . " Vous ne pouvez pas créer deux événements à la même date.");
                }
            }

            $userId = $_SESSION['user_id'] ?? ($_SESSION['super-admin'][0] ?? ($_SESSION['admin'][0] ?? 1));

            $conn->beginTransaction();
            
            $galerieId = 0;

            if (!empty($existingId)) {
                $galerieId = (int)$existingId;
            } else {
                $stmt = $conn->prepare("INSERT INTO section_Galerie (titre, date_evenement, id_user) VALUES (:titre, :date, :user)");
                $stmt->execute([
                    ':titre' => $titreEvenement,
                    ':date'  => $dateEvenement,
                    ':user'  => $userId
                ]);
                $galerieId = (int)$conn->lastInsertId();
            }

            if (empty($galerieId)) {
                throw new Exception("La création ou la récupération de l'événement a échoué. ID de galerie invalide.");
            }

            $filesDataJson = $_POST['compressed_files_data'] ?? '[]';
            $filesData     = json_decode($filesDataJson, true);

            if (!is_array($filesData)) {
                throw new Exception("Erreur lors de la réception des fichiers.");
            }

            $countFiles      = 0;
            $errors          = [];
            $processedHashes = [];

            foreach ($filesData as $index => $file) {
                $originalName = htmlspecialchars(basename($file['name']), ENT_QUOTES, 'UTF-8');
                $base64Data   = $file['data'];
                
                try {
                    if (strpos($base64Data, 'base64,') !== false) {
                        $base64Data = explode('base64,', $base64Data)[1];
                    }
                    $fileContent = base64_decode($base64Data, true);
                    
                    if ($fileContent === false) {
                        throw new Exception("Décodage base64 échoué");
                    }

                    $tempScanFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scan_' . uniqid() . '.tmp';
                    if (file_put_contents($tempScanFile, $fileContent) === false) {
                        throw new Exception("Erreur système lors de l'analyse du fichier.");
                    }

                    try {
                        $finfo          = finfo_open(FILEINFO_MIME_TYPE);
                        $serverMimeType = finfo_buffer($finfo, $fileContent);
                        finfo_close($finfo);

                        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
                        if (!in_array($serverMimeType, $allowedImageTypes, true)) {
                            throw new Exception("Type de fichier non autorisé : " . htmlspecialchars($serverMimeType));
                        }

                        FileSecurityValidator::validateMagicBytes($tempScanFile, $serverMimeType);
                        FileSecurityValidator::scanForMalware($tempScanFile, $serverMimeType);
                        
                        $clamResult = FileSecurityValidator::scanWithClamAV($tempScanFile);
                        if ($clamResult['available'] && !$clamResult['clean']) {
                            throw new Exception("Menace détectée par l'antivirus serveur.");
                        }

                        $isImage = true;

                    } finally {
                        if (file_exists($tempScanFile)) {
                            unlink($tempScanFile);
                        }
                    }

                    $info         = pathinfo($file['name']);
                    $filenameBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $info['filename']);
                    $filePath     = null;
                    $finalFileHash = null;
                    $finalName    = '';

                    if ($isImage) {
                        $im = @imagecreatefromstring($fileContent);
                        if ($im !== false) {
                            $ext       = 'webp';
                            $finalName = 'IMG_' . $filenameBase . '_scaled.' . $ext;
                            $filePath  = UPLOAD_DIR . $finalName;
                            $serverMimeType = 'image/webp';

                            if (imageistruecolor($im) === false) {
                                imagepalettetotruecolor($im);
                            }
                            imagealphablending($im, true);
                            imagesavealpha($im, true);
                            
                            if (!imagewebp($im, $filePath, 100)) {
                                imagedestroy($im);
                                throw new Exception("La conversion de l'image en WebP a échoué.");
                            }
                            imagedestroy($im);
                            
                            if (!file_exists($filePath)) {
                                throw new Exception("Erreur lors de l'écriture du fichier final.");
                            }

                            $finalFileHash = hash_file('sha256', $filePath);
                            
                            $isDuplicate    = false;
                            $duplicateReason = '';
                            
                            if (in_array($finalFileHash, $processedHashes, true)) {
                                $isDuplicate    = true;
                                $duplicateReason = 'doublon dans le lot actuel';
                            } else {
                                $stmtCheck = $conn->prepare("
                                    SELECT id, nom_fichier, hash_fichier 
                                    FROM media 
                                    WHERE id_galerie = :id_gal 
                                    AND (hash_fichier = :hash OR nom_fichier = :nom_fichier)
                                    LIMIT 1
                                ");
                                $stmtCheck->execute([
                                    ':id_gal'     => $galerieId, 
                                    ':hash'       => $finalFileHash,
                                    ':nom_fichier' => $finalName
                                ]);
                                $existingMedia = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                                $stmtCheck->closeCursor();
                                
                                if ($existingMedia) {
                                    $isDuplicate = true;
                                    if (!empty($existingMedia['hash_fichier']) && $existingMedia['hash_fichier'] === $finalFileHash) {
                                        $duplicateReason = 'Contenu identique (hash)';
                                    } elseif ($existingMedia['nom_fichier'] === $finalName) {
                                        $duplicateReason = 'Nom de fichier identique';
                                    } else {
                                        $duplicateReason = 'Image identique détectée';
                                    }
                                }
                            }

                            if ($isDuplicate) {
                                if (file_exists($filePath)) {
                                    unlink($filePath); 
                                }
                                throw new Exception("Cette image existe déjà dans cet événement. ($duplicateReason)");
                            }

                            $processedHashes[] = $finalFileHash;
                            $processedNames[]  = $finalName;

                        } else {
                            throw new Exception("Format d'image non reconnu ou corrompu.");
                        }
                    }

                    if (!$filePath || !file_exists($filePath)) {
                        throw new Exception("Le traitement de l'image a échoué et aucun fichier n'a été produit.");
                    }
                    
                    $fileSize = filesize($filePath);
                    $width    = 0;
                    $height   = 0;
                    if ($isImage) {
                        $size = getimagesize($filePath);
                        if ($size) {
                            $width  = $size[0];
                            $height = $size[1];
                        }
                    }

                    $webPath = '/cosmtt/photos/' . $finalName;
                    
                    $stmtMedia = $conn->prepare("
                        INSERT INTO media (
                            id_galerie, nom_fichier, chemin_fichier, type_media, 
                            taille_fichier, largeur, hauteur, ordre_affichage, hash_fichier
                        ) VALUES (
                            :id_gal, :nom, :chemin, :type, 
                            :taille, :w, :h, :ordre, :hash
                        )
                    ");
                    $stmtMedia->execute([
                        ':id_gal' => $galerieId,
                        ':nom'    => $finalName,
                        ':chemin' => $webPath,
                        ':type'   => $serverMimeType,
                        ':taille' => $fileSize,
                        ':w'      => $width,
                        ':h'      => $height,
                        ':ordre'  => $index,
                        ':hash'   => $finalFileHash
                    ]);
                    $mediaId = (int)$conn->lastInsertId();

                    $thumbCreated = false;
                    $webThumbPath = '';
                    $thumbDims    = null;

                    if ($isImage) {
                        $thumbName = 'IMG_' . $filenameBase . '_mini.webp';
                        $thumbPath = UPLOAD_THUMB_DIR . $thumbName;
                        
                        $thumbDims = createThumbnail($filePath, $thumbPath, 400);
                        
                        if ($thumbDims) {
                            $webThumbPath = '/cosmtt/photos/miniatures/' . $thumbName;
                            $thumbCreated = true;
                        } else {
                            $errors[] = "Fichier '$originalName' : L'image a été sauvegardée, mais la création de sa miniature a échoué.";
                            error_log("Galerie - Échec createThumbnail pour: $filePath");
                        }
                    }

                    if ($thumbCreated) {
                        $stmtMini = $conn->prepare("
                            INSERT INTO miniatures (id_media, chemin_miniature, largeur, hauteur) 
                            VALUES (:id_media, :chemin, :w, :h)
                        ");
                        $stmtMini->execute([
                            ':id_media' => $mediaId,
                            ':chemin'   => $webThumbPath,
                            ':w'        => $thumbDims['width'],
                            ':h'        => $thumbDims['height']
                        ]);
                    }

                    $countFiles++;

                } catch (Exception $e) {
                    $errors[] = "Fichier '$originalName' : " . $e->getMessage();
                    error_log("Erreur upload galerie : " . $e->getMessage());
                }
            }

            $conn->commit();
            
            $actionText = !empty($existingId) ? "mis à jour" : "ajouté";
            
            if ($countFiles > 0 && empty($errors)) {
                $message = "✅ Événement $actionText avec $countFiles image(s) !";
            } elseif ($countFiles > 0 && !empty($errors)) {
                $message = "✅ Événement $actionText avec $countFiles image(s), mais des erreurs sont survenues :\n\n" . implode("\n", $errors);
            } elseif ($countFiles === 0 && !empty($errors)) {
                $message = "❌ Aucune image n'a pu être ajoutée. Erreurs :\n\n" . implode("\n", $errors);
            } else {
                $message = "Aucune image n'a été traité.";
            }

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $message = "❌ Erreur : " . $e->getMessage();
        }
    }

    if ($countFiles === 0 || !empty($errors)) {
        $_SESSION['flash_message'] = $message;
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// =============================================================================
// SECTION 6: RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $displayError = "❌ Erreur : Le volume total des fichiers envoyés dépasse la limite du serveur (" . ini_get('post_max_size') . "). Veuillez envoyer moins de photos à la fois.";
}

try {
    $stmtEvents = $conn->query("SELECT * FROM section_Galerie ORDER BY date_evenement DESC");
    $events     = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

    $stmtMedia = $conn->query("
        SELECT 
            m.id, 
            m.id_galerie, 
            m.chemin_fichier, 
            m.type_media,
            mi.chemin_miniature
        FROM media m
        LEFT JOIN miniatures mi ON m.id = mi.id_media
        ORDER BY m.id_galerie, m.ordre_affichage ASC
    ");
    $allMedia = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);

    $mediaByEvent = [];
    foreach ($allMedia as $media) {
        $mediaByEvent[$media['id_galerie']][] = $media;
    }

} catch (Exception $e) {
    $displayError = "Erreur lors de la récupération de la galerie : " . $e->getMessage();
    $events       = [];
    $mediaByEvent = [];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la Galerie Photos</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/colors_group.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/popup.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie_popup.css">
    
    <style>
        /* ---- Styles existants ---- */
        .sections-container,
        .medias-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
        }
        
        .section-card,
        .media-card {
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
        
        /* Boutons déplacement dans la popup d'ajout */
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
            margin-bottom: 40px;
        }
        
        .titre_photos {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .photo {
            display: flex;
            /* justify-content: center; */
            flex-wrap: wrap;
            gap: 10px;
            margin: 1em  10em;
            /* margin-right: 10px; */
            /* margin-bottom: 20px; */
            /* display: flex;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 10px;
            margin: 1em 9em; */
            /* overflow: hidden; */
        
        }
        
        .photos {
            height: 133px;
            width: fit-content;
        }
        
        .photo_galerie {
            width: auto;
            height: 100%;
        }

        /* ================================================================
           NOUVEAUX STYLES : En-tête de section avec icônes modifier/supprimer
           ================================================================ */

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


        /* Icônes modifier / supprimer */
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
            /* height: 32px; */
            width: 32px;
            margin-top: 2px;
        }

        .btn-icon img {
            display: block;
            width: 24px;
        }


        .btn-icon:hover {
            /* transform: scale(1.15); */
        }

        .btn-icon-edit {
            /* color: #1565c0; */
            /* background: #e3f0ff; */
        }

        .btn-icon-edit:hover {
            /* background: #bbdefb; */
        }

        .btn-icon-delete {
            /* color: #c62828; */
            /* background: #ffeaea; */
        }

        .btn-icon-delete:hover {
            /* background: #ffcdd2; */
        }


        /* ================================================================
           MODE ÉDITION de la section
           ================================================================ */

        /* Champs d'édition inline (cachés par défaut) */
        .edit-fields {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .edit-fields.visible {
            display: flex;
        }

        .edit-fields input[type="text"],
        .edit-fields input[type="date"] {
            padding: 6px 10px;
            border: 1px solid #90bce8;
            border-radius: 6px;
            font-size: 16px;
            outline: none;
        }

        .edit-fields input[type="text"] {
            min-width: 220px;
        }

        .btn-save-edit,
        .btn-cancel-edit {
            padding: 5px 14px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-save-edit {
            background: #1565c0;
            color: #fff;
        }

        .btn-save-edit:hover {
            background: #0d47a1;
        }

        .btn-cancel-edit {
            background: #e0e0e0;
            color: #333;
        }

        .btn-cancel-edit:hover {
            background: #bdbdbd;
        }

        /* ================================================================
           PHOTOS en mode édition : boutons déplacement + suppression
           ================================================================ */

        /* Wrapper autour de chaque photo en mode édition */
        .photo-edit-wrapper {
            position: relative;
            display: inline-flex;
            height: 133px;
            /* width: 133px; */
            overflow: hidden;
        }

        /* Bouton supprimer photo (×) – masqué par défaut, visible en mode édition */
        .btn-delete-photo {
            display: none;
            position: absolute;
            top: 8px;
            right: 4px;
            background: #e53935;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 14px;
            line-height: 24px;
            text-align: center;
            cursor: pointer;
            z-index: 20;
            padding: 0;
            font-weight: bold;
            transition: background 0.2s;
        }

        .btn-delete-photo:hover {
            background: #b71c1c;
        }

        /* Boutons déplacement photo (‹ ›) – masqués par défaut, visibles en mode édition */
        .btn-move-photo {
            display: none;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.85);
            color: #333;
            border: 1px solid #aaa;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            font-size: 15px;
            line-height: 26px;
            text-align: center;
            cursor: pointer;
            z-index: 20;
            padding: 0;
            transition: background 0.2s;
        }

        .btn-move-photo:hover {
            background: rgba(200,200,200,0.95);
        }

        .btn-move-left  { left: 4px; }
        .btn-move-right { right: 4px; }

        /* En mode édition, on affiche les boutons */
        .galerie.editing .btn-delete-photo,
        .galerie.editing .btn-move-photo {
            display: block;
        }

        /* En mode édition, masquer l'en-tête normal et afficher les champs */
        .galerie.editing .section-header {
            display: none;
        }

        .galerie.editing .edit-fields {
            display: flex;
        }
    </style>
</head>

<body>

    <div class="title-bar">GESTION DE LA GALERIE PHOTOS</div>

    <main>
        <!-- Lien pour ouvrir la popup -->
        <a href="javascript:void(0);" class="lienPopup" id="showPopup">Ajouter un événement</a>

        <!-- Popup d'ajout d'événement -->
        <div class="popup" id="addPopup">
            <div class="popup-card">
                <div class="popup-content">
                    <div class="title-bar">Ajouter un événement</div>
                    
                    <form method="POST" action="" class="form-ajout" id="eventForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_event">
                        
                        <div class="form-group-inline">
                            <input type="text" 
                                   name="titre_evenement" 
                                   id="titre_evenement"
                                   class="input-popup" 
                                   placeholder="Titre de l'événement"
                                   required>
                            <input type="date"
                                   name="date_evenement"
                                   id="date_evenement"
                                   class="input-popup"
                                   required>
                        </div>

                        <div class="file-upload-zone" id="dropZone">
                            <div style="font-size: 48px; margin-bottom: 10px;">📁</div>
                            <p><strong>Glissez et déposez vos photos ici</strong></p>
                            <p>ou</p>
                            <div class="upload-btn" onclick="document.getElementById('fileInput').click()">
                                ➕ Choisir les photos
                            </div>
                            <input type="file" 
                                   id="fileInput" 
                                   name="files[]" 
                                   class="file-input-hidden" 
                                   multiple 
                                   accept=".jpg,.jpeg,.png">
                            <p class="file-size-info">
                                Images : JPG, JPEG, PNG
                            </p>
                        </div>

                        <div id="previewGrid" class="preview-grid"></div>

                        <input type="hidden" name="compressed_files_data" id="compressedFilesData">
                        <input type="hidden" name="existing_event_id" id="existingEventId">
                        <input type="hidden" name="force_add" id="forceAdd" value="0">

                        <div class="popup-buttons">
                            <button type="button" class="btn btnPopup-primary" id="cancelPopup">Annuler</button>
                            <button type="submit" class="btn btnPopup-primary" id="submitBtn">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Overlay de chargement -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-content">
                <div class="spinner"></div>
                <p>Compression et téléchargement en cours...</p>
                <p id="progressText">0%</p>
            </div>
        </div>

        <!-- Galerie photos -->
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

                        <!-- En-tête normal avec icônes -->
                        <div class="section-header">
                            <h3 class="titre_photos">
                                <span class="event-titre-display"><?= htmlspecialchars($event['titre']) ?></span>
                                &nbsp;–&nbsp;
                                <span class="event-date-display"><?= formatDateFrench($event['date_evenement']) ?></span>
                            </h3>

                            <!-- Icône modifier -->
                            <button type="button"
                                    class="btn-icon btn-icon-edit"
                                    title="Modifier cet événement"
                                    onclick="startEditEvent(this)"
                                    data-id="<?= (int)$event['id'] ?>"
                                    data-titre="<?= htmlspecialchars($event['titre'], ENT_QUOTES) ?>"
                                    data-date="<?= htmlspecialchars($event['date_evenement'], ENT_QUOTES) ?>">
                                <img src="/cosmtt/img/icones/write.png" alt="Modifier" width="18">
                            </button>

                            <!-- Icône supprimer -->
                            <button type="button"
                                    class="btn-icon btn-icon-delete"
                                    title="Supprimer cet événement"
                                    onclick="deleteEvent(<?= (int)$event['id'] ?>, '<?= htmlspecialchars($event['titre'], ENT_QUOTES) ?>')">
                                <img src="/cosmtt/img/icones/remove.png" alt="Supprimer" width="18">
                            </button>


                        </div>

                        <!-- Champs d'édition (cachés par défaut) -->
                        <div class="edit-fields" data-event-id="<?= (int)$event['id'] ?>">
                            <input type="text"
                                   class="edit-titre"
                                   value="<?= htmlspecialchars($event['titre'], ENT_QUOTES) ?>"
                                   placeholder="Titre de l'événement">
                            <input type="date"
                                   class="edit-date"
                                   value="<?= htmlspecialchars($event['date_evenement'], ENT_QUOTES) ?>">
                            <button type="button" class="btn-save-edit" onclick="saveEditEvent(this)">✔ Enregistrer</button>
                            <button type="button" class="btn-cancel-edit" onclick="cancelEditEvent(this)">✖ Annuler</button>

                        </div>

                        <!-- Photos -->
                        <div class="photo" data-event-id="<?= (int)$event['id'] ?>">
                            <?php if (!empty($mediaByEvent[$event['id']])): ?>
                                <?php foreach ($mediaByEvent[$event['id']] as $media): ?>
                                    <?php
                                    $fullPath  = htmlspecialchars($media['chemin_fichier']);
                                    $thumbPath = !empty($media['chemin_miniature']) 
                                        ? htmlspecialchars($media['chemin_miniature']) 
                                        : $fullPath;
                                    ?>
                                    <?php if (strpos($media['type_media'], 'image/') === 0): ?>
                                        <div class="photo-edit-wrapper" data-media-id="<?= (int)$media['id'] ?>">

                                            <!-- Bouton supprimer la photo -->
                                            <button type="button"
                                                    class="btn-delete-photo"
                                                    title="Supprimer cette photo"
                                                    onclick="deleteMedia(<?= (int)$media['id'] ?>, this)">×</button>


                                            <!-- Bouton déplacer à gauche -->
                                            <button type="button"
                                                    class="btn-move-photo btn-move-left"
                                                    title="Déplacer à gauche"
                                                    onclick="moveMedia(this, -1)">❮</button>

                                            <a class="photos" href="<?= $fullPath ?>" target="_blank">
                                                <img class="photo_galerie" 
                                                     src="<?= $thumbPath ?>" 
                                                     alt="Photo de l'événement <?= htmlspecialchars($event['titre']) ?>"
                                                     title="Cliquez pour agrandir">
                                            </a>

                                            <!-- Bouton déplacer à droite -->
                                            <button type="button"
                                                    class="btn-move-photo btn-move-right"
                                                    title="Déplacer à droite"
                                                    onclick="moveMedia(this, 1)">❯</button>

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
        </div>
    </main>

    <!-- JavaScript existant -->
    <script src="/cosmtt/admin/js/app.js"></script>
    <script>
        // =====================================================================
        // CODE JAVASCRIPT EXISTANT (inchangé)
        // =====================================================================

        let selectedFiles = [];
        const MAX_IMAGE_SIZE = 4 * 1024 * 1024;
        let conflictCheckResult = null;

        document.getElementById('showPopup')?.addEventListener('click', () => {
            document.getElementById('addPopup').style.display = 'block';
        });

        document.getElementById('cancelPopup')?.addEventListener('click', () => {
            if (selectedFiles.length === 0 || confirm('Êtes-vous sûr de vouloir annuler ? Les images sélectionnées seront perdues.')) {
                document.getElementById('addPopup').style.display = 'none';
                resetForm();
            }
        });

        function resetForm() {
            document.getElementById('eventForm').reset();
            selectedFiles = [];
            document.getElementById('previewGrid').innerHTML = '';
            document.getElementById('existingEventId').value = '';
            document.getElementById('forceAdd').value = '0';
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
                div.className = 'preview-item';
                div.dataset.name = file.name;
                document.getElementById('previewGrid').appendChild(div);
                
                createPreview(file, div);
            });
        }

        function createPreview(file, div) {
            const reader = new FileReader();
            reader.onload = (e) => {
                div.innerHTML = `
                    <img src="${e.target.result}">
                    <button type="button" class="remove-btn" onclick="removeFile('${file.name}', this)">×</button>
                    <button type="button" class="move-btn move-left" onclick="moveFile(this, -1)">❮</button>
                    <button type="button" class="move-btn move-right" onclick="moveFile(this, 1)">❯</button>
                    <div class="file-info">${(file.size/1024/1024).toFixed(2)} Mo</div>
                `;
            };
            reader.readAsDataURL(file);
        }

        window.removeFile = function(name, btn) {
            selectedFiles = selectedFiles.filter(f => f.name !== name);
            btn.parentElement.remove();
        };

        window.moveFile = function(btn, direction) {
            const item = btn.closest('.preview-item');
            const parent = item.parentNode;
            
            if (direction === -1) {
                const prev = item.previousElementSibling;
                if (prev) parent.insertBefore(item, prev);
            } else {
                const next = item.nextElementSibling;
                if (next) parent.insertBefore(next, item);
            }
            
            const newOrder = [];
            document.querySelectorAll('.preview-item').forEach(div => {
                const file = selectedFiles.find(f => f.name === div.dataset.name);
                if (file) newOrder.push(file);
            });
            selectedFiles = newOrder;
        };

        async function checkForConflicts(titre, date) {
            try {
                const formData = new FormData();
                formData.append('action', 'check_event');
                formData.append('titre', titre);
                formData.append('date', date);

                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                if (!response.ok) throw new Error('Erreur réseau lors de la vérification');
                return await response.json();
            } catch (err) {
                console.error("Erreur vérification événement:", err);
                return null;
            }
        }

        document.getElementById('eventForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Ajouter';
            
            if (selectedFiles.length === 0) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Ajouter';
                return alert('Ajoutez au moins une image avant de soumettre.');
            }
            
            const titre = document.getElementById('titre_evenement').value.trim();
            const date  = document.getElementById('date_evenement').value;

            if (!titre || !date) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Ajouter';
                return alert('Veuillez renseigner le titre et la date de l\'événement.');
            }

            const checkResult = await checkForConflicts(titre, date);
            
            if (!checkResult) {
                console.warn('Impossible de vérifier les conflits, soumission au serveur');
            } else {
                if (checkResult.same_title_and_date) {
                    const msg = `Un événement "${titre}" existe déjà à cette date.\n\nVoulez-vous ajouter ces images à cet événement existant ?`;
                    if (!confirm(msg)) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Ajouter';
                        return;
                    }
                    document.getElementById('existingEventId').value = checkResult.id;
                    document.getElementById('forceAdd').value = '0';
                } else if (checkResult.same_title_same_season) {
                    const conflict = checkResult.conflicts.find(c => c.type === 'same_title_same_season');
                    alert('❌ AJOUT IMPOSSIBLE\n\n' + conflict.message + '\n\nVous ne pouvez pas créer deux événements avec le même titre dans la même saison.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Ajouter';
                    return;
                } else if (checkResult.same_date) {
                    const conflict = checkResult.conflicts.find(c => c.type === 'same_date');
                    alert('❌ AJOUT IMPOSSIBLE\n\n' + conflict.message + '\n\nVous ne pouvez pas créer deux événements à la même date.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Ajouter';
                    return;
                } else {
                    document.getElementById('existingEventId').value = '';
                    document.getElementById('forceAdd').value = '0';
                }
            }

            const loading  = document.getElementById('loadingOverlay');
            const progress = document.getElementById('progressText');
            loading.classList.add('show');
            submitBtn.textContent = 'Ajouter';
            
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
                submitBtn.textContent = 'Ajouter';
            }
        });

        function compressImage(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        const ctx    = canvas.getContext('2d');
                        let w = img.width;
                        let h = img.height;
                        const max = 1920;
                        if (w > max || h > max) {
                            const ratio = Math.min(max / w, max / h);
                            w *= ratio;
                            h *= ratio;
                        }
                        canvas.width  = Math.floor(w);
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
        // NOUVEAU CODE JAVASCRIPT : gestion édition / suppression sections
        // =====================================================================

        /**
         * Active le mode édition d'une section
         */
        function startEditEvent(btn) {
            const section = btn.closest('.galerie');
            section.classList.add('editing');
        }

        /**
         * Annule le mode édition sans sauvegarder
         */
        function cancelEditEvent(btn) {
            const section = btn.closest('.galerie');
            // Restaurer les valeurs d'origine
            const editFields = btn.closest('.edit-fields');
            const sectionHeader = section.querySelector('.section-header');
            const editBtn = sectionHeader.querySelector('.btn-icon-edit');
            
            editFields.querySelector('.edit-titre').value = editBtn.dataset.titre;
            editFields.querySelector('.edit-date').value  = editBtn.dataset.date;

            section.classList.remove('editing');
        }

        /**
         * Sauvegarde les modifications titre/date via AJAX
         */
        async function saveEditEvent(btn) {
            const section    = btn.closest('.galerie');
            const editFields = btn.closest('.edit-fields');
            const eventId    = parseInt(section.dataset.eventId);
            const newTitre   = editFields.querySelector('.edit-titre').value.trim();
            const newDate    = editFields.querySelector('.edit-date').value;

            if (!newTitre || !newDate) {
                alert('Le titre et la date sont obligatoires.');
                return;
            }

            btn.disabled = true;
            btn.textContent = '⏳ Enregistrement…';

            try {
                const formData = new FormData();
                formData.append('action', 'edit_event');
                formData.append('id', eventId);
                formData.append('titre', newTitre);
                formData.append('date', newDate);

                const resp = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.success) {
                    // Mettre à jour l'affichage du titre
                    const sectionHeader = section.querySelector('.section-header');
                    const editBtn = sectionHeader.querySelector('.btn-icon-edit');

                    // Mise à jour du data-* pour le bouton modifier
                    editBtn.dataset.titre = newTitre;
                    editBtn.dataset.date  = newDate;

                    // Mise à jour du texte affiché
                    sectionHeader.querySelector('.event-titre-display').textContent = newTitre;

                    // Reformater la date côté client (format lisible simple)
                    sectionHeader.querySelector('.event-date-display').textContent = formatDateClient(newDate);

                    section.classList.remove('editing');
                } else {
                    alert('❌ Erreur lors de la sauvegarde : ' + (data.message || 'Erreur inconnue'));
                }
            } catch (err) {
                console.error(err);
                alert('❌ Erreur réseau lors de la sauvegarde.');
            }

            btn.disabled = false;
            btn.textContent = '✔ Enregistrer';
        }

        /**
         * Formate une date YYYY-MM-DD en format lisible français (côté client)
         */
        function formatDateClient(dateStr) {
            const [y, m, d] = dateStr.split('-');
            const mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
            return `${parseInt(d)} ${mois[parseInt(m)-1]} ${y}`;
        }

        /**
         * Supprime un événement complet après confirmation
         */
        async function deleteEvent(eventId, titre) {
            if (!confirm(`⚠️ Êtes-vous sûr de vouloir supprimer l'événement "${titre}" et toutes ses photos ?\n\nCette action est irréversible.`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete_event');
                formData.append('id', eventId);

                const resp = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.success) {
                    // Supprimer la section du DOM
                    const section = document.querySelector(`.galerie[data-event-id="${eventId}"]`);
                    if (section) {
                        // Supprimer aussi le <hr> précédent si présent
                        section.remove();
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
         * Supprime une photo individuelle après confirmation
         */
        async function deleteMedia(mediaId, btn) {
            if (!confirm('Voulez-vous vraiment supprimer cette photo ?')) {
                return;
            }

            btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'delete_media');
                formData.append('media_id', mediaId);

                const resp = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.success) {
                    // Supprimer le wrapper de la photo du DOM
                    const wrapper = btn.closest('.photo-edit-wrapper');
                    if (wrapper) wrapper.remove();
                } else {
                    alert('❌ Erreur lors de la suppression : ' + (data.message || 'Erreur inconnue'));
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                alert('❌ Erreur réseau lors de la suppression.');
                btn.disabled = false;
            }
        }

        /**
         * Déplace une photo dans la galerie (gauche ou droite) et sauvegarde l'ordre
         */
        async function moveMedia(btn, direction) {
            const wrapper  = btn.closest('.photo-edit-wrapper');
            const photoDiv = wrapper.closest('.photo');
            const siblings = Array.from(photoDiv.querySelectorAll('.photo-edit-wrapper'));
            const idx      = siblings.indexOf(wrapper);

            if (direction === -1 && idx > 0) {
                // Déplacer à gauche
                photoDiv.insertBefore(wrapper, siblings[idx - 1]);
            } else if (direction === 1 && idx < siblings.length - 1) {
                // Déplacer à droite
                photoDiv.insertBefore(siblings[idx + 1], wrapper);
            } else {
                return; // Pas de déplacement possible
            }

            // Récupérer le nouvel ordre
            const newOrder = Array.from(photoDiv.querySelectorAll('.photo-edit-wrapper'))
                                  .map(w => w.dataset.mediaId)
                                  .join(',');

            // Sauvegarder le nouvel ordre en AJAX
            try {
                const formData = new FormData();
                formData.append('action', 'reorder_media');
                formData.append('ordered_ids', newOrder);

                const resp = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await resp.json();

                if (!data.success) {
                    console.warn('Erreur sauvegarde ordre:', data.message);
                }
            } catch (err) {
                console.error('Erreur réseau réordonnement:', err);
            }
        }
    </script>

    <?php if (isset($flashMessage) && !empty($flashMessage)): ?>
    <script>
        const flashMessageText = <?php echo json_encode($flashMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        if (flashMessageText) {
            alert(flashMessageText);
        }
    </script>
    <?php endif; ?>

</body>
</html>
