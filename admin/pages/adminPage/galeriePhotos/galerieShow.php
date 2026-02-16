<?php
/**
 * Galerie Photos - Page d'administration
 * 
 * Ce fichier permet de gérer les événements photo et l'upload d'images.
 * Les images sont converties en WebP et des miniatures sont générées.
 * 
 * Fonctionnalités :
 * - AJAX: Vérification d'existence d'événement
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
 * 
 * @param string $sourcePath Chemin du fichier source
 * @param string $destPath Chemin de destination
 * @param int $fixedHeight Hauteur fixe pour la miniature
 * @return array|false Dimensions de la miniature ou false en cas d'échec
 */
function createThumbnail(string $sourcePath, string $destPath, int $fixedHeight = 400) {
    $imageInfo = getimagesize($sourcePath);
    
    if (!$imageInfo) {
        return false;
    }

    [$width, $height, $type] = $imageInfo;

    // Calcul des nouvelles dimensions en conservant les proportions (Hauteur fixe)
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

    // Gestion de la transparence
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
    
    // Sauvegarde en WebP
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
 * 
 * @param string $dateString Date au format MySQL
 * @return string Date formatée en français
 */
function formatDateFrench(string $dateString): string {
    if (!class_exists('IntlDateFormatter')) {
        // Fallback si l'extension intl n'est pas activée
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
 * 
 * @param PDO $conn Connexion à la base de données
 * @param string $titre Titre de l'événement
 * @param string $date Date de l'événement
 * @return array Résultat de la vérification avec détails des conflits
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
    
    // Extraire l'année de la date pour déterminer la saison
    $year = date('Y', strtotime($date));
    // Pour une saison sportive (septembre à août), déterminer la saison
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
    
    // 1. Vérification: même titre ET même date (exact)
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
    
    // 2. Vérification: même titre dans la même saison (année)
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
    
    // 3. Vérification: même date (indépendamment du titre)
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

/**
 * Vérifie si un événement existe déjà (appel AJAX)
 * Retourne des informations détaillées sur les conflits possibles:
 * - same_title_and_date: même titre et même date
 * - same_title_same_season: même titre dans la même saison (année)
 * - same_date: même date (indépendamment du titre)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_event') {
    // Nettoyer le buffer de sortie pour éviter toute pollution HTML
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    
    $titre = $_POST['titre'] ?? '';
    $date = $_POST['date'] ?? '';
    
    $result = checkEventExists($conn, $titre, $date);
    
    echo json_encode($result);
    exit;
}

// =============================================================================
// SECTION 5: TRAITEMENT DU FORMULAIRE
// =============================================================================

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    // Vérification de la librairie GD
    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        $message = "❌ Erreur de configuration serveur : La librairie d'images GD avec le support WebP est requise mais n'est pas activée. Impossible de traiter les images. Veuillez contacter l'administrateur du site.";
    } else {
        try {
            // Sécurisation des entrées
            $titreEvenement = htmlspecialchars($_POST['titre_evenement'] ?? '', ENT_QUOTES, 'UTF-8');
            $dateEvenement = htmlspecialchars($_POST['date_evenement'] ?? '', ENT_QUOTES, 'UTF-8');
            
            if (empty($titreEvenement) || empty($dateEvenement)) {
                throw new Exception("Le titre et la date sont obligatoires.");
            }

            // =========================================================================
            // VÉRIFICATION SERVEUR RENFORCÉE - NOUVELLE LOGIQUE
            // =========================================================================
            
            $forceAdd = isset($_POST['force_add']) && $_POST['force_add'] === '1';
            $existingId = $_POST['existing_event_id'] ?? '';
            
            // Si on n'a pas explicitement forcé l'ajout, on vérifie les conflits
            if (!$forceAdd && empty($existingId)) {
                $checkResult = checkEventExists($conn, $titreEvenement, $dateEvenement);
                
                // Si même titre ET même date → on utilise cet événement existant
                if ($checkResult['same_title_and_date']) {
                    $existingId = $checkResult['id'];
                }
                // Si même titre dans la saison → BLOQUER l'insertion
                elseif ($checkResult['same_title_same_season']) {
                    $conflictMsg = $checkResult['conflicts'][0]['message'];
                    throw new Exception("❌ AJOUT BLOQUÉ : " . $conflictMsg . " Vous ne pouvez pas créer deux événements avec le même titre dans la même saison.");
                }
                // Si même date (titre différent) → BLOQUER l'insertion
                elseif ($checkResult['same_date']) {
                    $conflictMsg = $checkResult['conflicts'][0]['message'];
                    throw new Exception("❌ AJOUT BLOQUÉ : " . $conflictMsg . " Vous ne pouvez pas créer deux événements à la même date.");
                }
            }

            // Récupération de l'ID utilisateur
            $userId = $_SESSION['user_id'] ?? ($_SESSION['super-admin'][0] ?? ($_SESSION['admin'][0] ?? 1));

            $conn->beginTransaction();
            
            $galerieId = 0;

            // Utilisation de l'événement existant ou création d'un nouveau
            if (!empty($existingId)) {
                $galerieId = (int)$existingId;
            } else {
                // Insertion du nouvel événement (seulement si toutes les vérifications sont passées)
                $stmt = $conn->prepare("INSERT INTO section_Galerie (titre, date_evenement, id_user) VALUES (:titre, :date, :user)");
                $stmt->execute([
                    ':titre' => $titreEvenement,
                    ':date' => $dateEvenement,
                    ':user' => $userId
                ]);
                $galerieId = (int)$conn->lastInsertId();
            }

            // Traitement des fichiers reçus (JSON compressé depuis le JS)
            $filesDataJson = $_POST['compressed_files_data'] ?? '[]';
            $filesData = json_decode($filesDataJson, true);

            if (!is_array($filesData)) {
                throw new Exception("Erreur lors de la réception des fichiers.");
            }

            $countFiles = 0;
            $errors = [];

            // Traitement de chaque fichier
            foreach ($filesData as $index => $file) {
                $originalName = htmlspecialchars(basename($file['name']), ENT_QUOTES, 'UTF-8');
                $base64Data = $file['data'];
                
                try {
                    // Nettoyage du header base64
                    if (strpos($base64Data, 'base64,') !== false) {
                        $base64Data = explode('base64,', $base64Data)[1];
                    }
                    $fileContent = base64_decode($base64Data, true);
                    
                    if ($fileContent === false) {
                        throw new Exception("Décodage base64 échoué");
                    }

                    // -----------------------------------------------------------------------------
                    // VALIDATION SÉCURISÉE AVANCÉE
                    // -----------------------------------------------------------------------------
                    
                    // Création d'un fichier temporaire pour l'analyse
                    $tempScanFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scan_' . uniqid() . '.tmp';
                    if (file_put_contents($tempScanFile, $fileContent) === false) {
                        throw new Exception("Erreur système lors de l'analyse du fichier.");
                    }

                    try {
                        // 1. Détection du type MIME via finfo (côté serveur)
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $serverMimeType = finfo_buffer($finfo, $fileContent);
                        finfo_close($finfo);

                        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
                        if (!in_array($serverMimeType, $allowedImageTypes, true)) {
                            throw new Exception("Type de fichier non autorisé : " . htmlspecialchars($serverMimeType));
                        }

                        // 2. Validation des Magic Bytes
                        FileSecurityValidator::validateMagicBytes($tempScanFile, $serverMimeType);
                        
                        // 3. Scan Malware
                        FileSecurityValidator::scanForMalware($tempScanFile, $serverMimeType);
                        
                        // 4. Scan ClamAV (si disponible)
                        $clamResult = FileSecurityValidator::scanWithClamAV($tempScanFile);
                        if ($clamResult['available'] && !$clamResult['clean']) {
                            throw new Exception("Menace détectée par l'antivirus serveur.");
                        }

                        $isImage = true;

                    } finally {
                        // Nettoyage du fichier temporaire
                        if (file_exists($tempScanFile)) {
                            unlink($tempScanFile);
                        }
                    }

                    // -----------------------------------------------------------------------------
                    // VÉRIFICATION DES DOUBLONS (HASH)
                    // -----------------------------------------------------------------------------
                    
                    $fileHash = hash('sha256', $fileContent);
                    
                    $stmtCheck = $conn->prepare("SELECT id FROM media WHERE id_galerie = :id_gal AND hash_fichier = :hash LIMIT 1");
                    $stmtCheck->execute([':id_gal' => $galerieId, ':hash' => $fileHash]);
                    if ($stmtCheck->fetch()) {
                        throw new Exception("Cette image existe déjà dans cet événement.");
                    }
                    $stmtCheck->closeCursor();

                    // -----------------------------------------------------------------------------
                    // TRAITEMENT ET CONVERSION DE L'IMAGE
                    // -----------------------------------------------------------------------------
                    
                    // Nom de fichier de base
                    $info = pathinfo($file['name']);
                    $filenameBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $info['filename']);

                    if ($isImage) {
                        // Conversion en WebP
                        $im = @imagecreatefromstring($fileContent);
                        if ($im !== false) {
                            $ext = 'webp';
                            $finalName = 'IMG_' . $filenameBase . '_scaled.' . $ext;
                            $filePath = UPLOAD_DIR . $finalName;
                            $serverMimeType = 'image/webp';

                            // Gestion transparence
                            if (imageistruecolor($im) === false) {
                                imagepalettetotruecolor($im);
                            }
                            imagealphablending($im, true);
                            imagesavealpha($im, true);
                            
                            imagewebp($im, $filePath, 100);
                            imagedestroy($im);
                        } else {
                            throw new Exception("Format d'image non reconnu ou corrompu.");
                        }
                    }
                    
                    if (!file_exists($filePath)) {
                        throw new Exception("Erreur lors de l'écriture du fichier.");
                    }
                    
                    // Récupération des dimensions
                    $fileSize = filesize($filePath);
                    $width = 0;
                    $height = 0;
                    if ($isImage) {
                        $size = getimagesize($filePath);
                        if ($size) {
                            $width = $size[0];
                            $height = $size[1];
                        }
                    }

                    // -----------------------------------------------------------------------------
                    // INSERTION EN BASE DE DONNÉES
                    // -----------------------------------------------------------------------------
                    
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
                        ':nom' => $finalName,
                        ':chemin' => $webPath,
                        ':type' => $serverMimeType,
                        ':taille' => $fileSize,
                        ':w' => $width,
                        ':h' => $height,
                        ':ordre' => $index,
                        ':hash' => $fileHash
                    ]);
                    $mediaId = (int)$conn->lastInsertId();

                    // -----------------------------------------------------------------------------
                    // CRÉATION DE LA MINIATURE
                    // -----------------------------------------------------------------------------
                    
                    $thumbCreated = false;
                    $webThumbPath = '';
                    $thumbDims = null;

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

                    // Insertion miniature si créée
                    if ($thumbCreated) {
                        $stmtMini = $conn->prepare("
                            INSERT INTO miniatures (id_media, chemin_miniature, largeur, hauteur) 
                            VALUES (:id_media, :chemin, :w, :h)
                        ");
                        $stmtMini->execute([
                            ':id_media' => $mediaId,
                            ':chemin' => $webThumbPath,
                            ':w' => $thumbDims['width'],
                            ':h' => $thumbDims['height']
                        ]);
                    }

                    $countFiles++;

                } catch (Exception $e) {
                    $errors[] = "Fichier '$originalName' : " . $e->getMessage();
                    error_log("Erreur upload galerie : " . $e->getMessage());
                }
            }

            $conn->commit();
            
            // -----------------------------------------------------------------------------
            // MESSAGE DE RÉSULTAT
            // -----------------------------------------------------------------------------
            
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
}

// =============================================================================
// SECTION 6: RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE
// =============================================================================

// Détection dépassement post_max_size
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $displayError = "❌ Erreur : Le volume total des fichiers envoyés dépasse la limite du serveur (" . ini_get('post_max_size') . "). Veuillez envoyer moins de photos à la fois.";
}

// Récupération des événements et médias
try {
    // Tous les événements triés par date
    $stmtEvents = $conn->query("SELECT * FROM section_Galerie ORDER BY date_evenement DESC");
    $events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

    // Tous les médias avec leurs miniatures
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

    // Grouper les médias par ID d'événement
    $mediaByEvent = [];
    foreach ($allMedia as $media) {
        $mediaByEvent[$media['id_galerie']][] = $media;
    }

} catch (Exception $e) {
    $displayError = "Erreur lors de la récupération de la galerie : " . $e->getMessage();
    $events = [];
    $mediaByEvent = [];
}

/*
 * SÉCURITÉ : Il est fortement recommandé de placer un fichier .htaccess
 * à la racine des répertoires d'upload (ex: /photos/) pour empêcher 
 * l'exécution de scripts malveillants.
 *
 * Contenu pour .htaccess :
 * -------------------------------------------------
 * Options -Indexes
 * 
 * <FilesMatch "\.(php|pl|py|jsp|asp|sh|cgi)$">
 *   Require all denied
 * </FilesMatch>
 * -------------------------------------------------
 */
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
        /* Styles spécifiques à cette page */
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
        
        .move-left {
            left: 5px;
        }
        
        .move-right {
            right: 5px;
        }
        
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
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .photos {
            height: 133px;
            width: fit-content;
        }
        
        .photo_galerie {
            width: auto;
            height: 100%;
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
                        
                        <!-- Titre et Date sur la même ligne -->
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

                        <!-- Zone d'upload de fichiers -->
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
                                Images : JPG, JPEG, PNG (Max 4 Mo)
                            </p>
                        </div>

                        <!-- Grille de prévisualisation -->
                        <div id="previewGrid" class="preview-grid"></div>

                        <!-- Champs cachés pour les données -->
                        <input type="hidden" name="compressed_files_data" id="compressedFilesData">
                        <input type="hidden" name="existing_event_id" id="existingEventId">
                        <input type="hidden" name="force_add" id="forceAdd" value="0">

                        <!-- Boutons d'action -->
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
                    <section class="galerie">
                        <hr>
                        <h3 class="titre_photos">
                            <?= htmlspecialchars($event['titre']) ?> – <?= formatDateFrench($event['date_evenement']) ?>
                        </h3>
                        <div class="photo">
                            <?php if (!empty($mediaByEvent[$event['id']])): ?>
                                <?php foreach ($mediaByEvent[$event['id']] as $media): ?>
                                    <?php
                                    $fullPath = htmlspecialchars($media['chemin_fichier']);
                                    $thumbPath = !empty($media['chemin_miniature']) 
                                        ? htmlspecialchars($media['chemin_miniature']) 
                                        : $fullPath;
                                    ?>
                                    <?php if (strpos($media['type_media'], 'image/') === 0): ?>
                                        <a class="photos" href="<?= $fullPath ?>" target="_blank">
                                            <img class="photo_galerie" 
                                                 src="<?= $thumbPath ?>" 
                                                 alt="Photo de l'événement <?= htmlspecialchars($event['titre']) ?>"
                                                 title="Cliquez pour agrandir">
                                        </a>
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

    <!-- JavaScript -->
    <script src="/cosmtt/admin/js/app.js"></script>
    <script>
        /**
         * Gestion de l'upload des photos - Logique JavaScript
         * 
         * MODIFICATION: Ajout de la gestion des conflits côté client
         */
        
        // Variables globales
        let selectedFiles = [];
        const MAX_IMAGE_SIZE = 4 * 1024 * 1024; // 4 Mo
        let conflictCheckResult = null;

        // -----------------------------------------------------------------------------
        // OUVERTURE/FERMETURE POPUP
        // -----------------------------------------------------------------------------
        
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
        }

        // -----------------------------------------------------------------------------
        // DRAG & DROP
        // -----------------------------------------------------------------------------
        
        const dropZone = document.getElementById('dropZone');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'));
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'));
        });
        
        dropZone.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files));
        document.getElementById('fileInput').addEventListener('change', function() {
            handleFiles(this.files);
        });

        // -----------------------------------------------------------------------------
        // TRAITEMENT DES FICHIERS
        // -----------------------------------------------------------------------------
        
        function handleFiles(files) {
            Array.from(files).forEach(file => {
                const isImage = file.type.match('image.*');
                
                if (!isImage) {
                    return alert(`Format non supporté: ${file.name}`);
                }
                if (file.size > MAX_IMAGE_SIZE) {
                    return alert(`Image trop lourde (>4Mo): ${file.name}`);
                }
                if (selectedFiles.some(f => f.name === file.name)) {
                    return alert(`L'image est déjà ajoutée: ${file.name}`);
                }

                selectedFiles.push(file);
                
                // Création du placeholder
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
            
            // Mise à jour de l'ordre dans selectedFiles
            const newOrder = [];
            document.querySelectorAll('.preview-item').forEach(div => {
                const file = selectedFiles.find(f => f.name === div.dataset.name);
                if (file) newOrder.push(file);
            });
            selectedFiles = newOrder;
        };

        // -----------------------------------------------------------------------------
        // VÉRIFICATION DES CONFLITS (NOUVELLE FONCTION)
        // -----------------------------------------------------------------------------
        
        async function checkForConflicts(titre, date) {
            try {
                const formData = new FormData();
                formData.append('action', 'check_event');
                formData.append('titre', titre);
                formData.append('date', date);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Erreur réseau lors de la vérification');
                }
                
                const result = await response.json();
                return result;
                
            } catch (err) {
                console.error("Erreur vérification événement:", err);
                return null;
            }
        }

        // -----------------------------------------------------------------------------
        // COMPRESSION ET SOUMISSION
        // -----------------------------------------------------------------------------
        
        document.getElementById('eventForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (selectedFiles.length === 0) {
                return alert('Ajoutez au moins une image avant de soumettre.');
            }
            
            // Récupération des valeurs
            const titre = document.getElementById('titre_evenement').value.trim();
            const date = document.getElementById('date_evenement').value;

            if (!titre || !date) {
                return alert('Veuillez renseigner le titre et la date de l\'événement.');
            }

            // Vérification de l'existence de l'événement
            const checkResult = await checkForConflicts(titre, date);
            
            if (!checkResult) {
                // Si la vérification échoue, on laisse le serveur gérer
                console.warn('Impossible de vérifier les conflits, soumission au serveur');
            } else {
                // Cas 1: Même titre ET même date → Ajout à l'événement existant
                if (checkResult.same_title_and_date) {
                    const msg = `Un événement "${titre}" existe déjà à cette date.\n\nVoulez-vous ajouter ces images à cet événement existant ?`;
                    if (!confirm(msg)) {
                        return;
                    }
                    document.getElementById('existingEventId').value = checkResult.id;
                    document.getElementById('forceAdd').value = '0';
                }
                // Cas 2: Même titre dans la saison → BLOQUER
                else if (checkResult.same_title_same_season) {
                    const conflict = checkResult.conflicts.find(c => c.type === 'same_title_same_season');
                    alert('❌ AJOUT IMPOSSIBLE\n\n' + conflict.message + '\n\nVous ne pouvez pas créer deux événements avec le même titre dans la même saison.');
                    return; // BLOQUER la soumission
                }
                // Cas 3: Même date (titre différent) → BLOQUER
                else if (checkResult.same_date) {
                    const conflict = checkResult.conflicts.find(c => c.type === 'same_date');
                    alert('❌ AJOUT IMPOSSIBLE\n\n' + conflict.message + '\n\nVous ne pouvez pas créer deux événements à la même date.');
                    return; // BLOQUER la soumission
                }
                // Cas 4: Aucun conflit → Création d'un nouvel événement
                else {
                    document.getElementById('existingEventId').value = '';
                    document.getElementById('forceAdd').value = '0';
                }
            }

            // Affichage du chargement
            const loading = document.getElementById('loadingOverlay');
            const progress = document.getElementById('progressText');
            loading.classList.add('show');
            
            try {
                const processedFiles = [];
                
                for (let i = 0; i < selectedFiles.length; i++) {
                    const file = selectedFiles[i];
                    progress.textContent = `Traitement : ${Math.round(((i+1) / selectedFiles.length) * 100)}%`;
                    
                    const data = await compressImage(file);
                    
                    processedFiles.push({
                        name: file.name,
                        type: file.type,
                        data: data
                    });
                }
                
                document.getElementById('compressedFilesData').value = JSON.stringify(processedFiles);
                
                // Vider l'input file pour éviter l'envoi en double
                document.getElementById('fileInput').value = '';
                this.submit();
                
            } catch (err) {
                console.error(err);
                alert('Erreur lors du traitement des images.');
                loading.classList.remove('show');
            }
        });

        /**
         * Compression d'image côté client
         * @param {File} file Fichier à compresser
         * @returns {Promise<string>} Données Base64 compressées
         */
        function compressImage(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        // Redimensionnement max 1920px
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
                        
                        // Export en JPEG qualité 90%
                        resolve(canvas.toDataURL('image/jpeg', 0.9));
                    };
                    img.onerror = () => reject(new Error("Erreur chargement image"));
                    img.src = e.target.result;
                };
                reader.onerror = () => reject(new Error("Erreur lecture fichier"));
                reader.readAsDataURL(file);
            });
        }
    </script>

    <?php if ($message): ?>
        <script>
            // Injection sécurisée du message
            const message = <?php echo json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            alert(message);
            
            // Si le message contient le symbole de succès, on ferme la popup et on rafraîchit
            if (message.includes("✅")) {
                document.getElementById('addPopup').style.display = 'none';
                window.location.reload();
            }
        </script>
    <?php endif; ?>

</body>
</html>