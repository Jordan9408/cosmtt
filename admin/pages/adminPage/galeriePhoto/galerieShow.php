<?php
// Inclusion des fichiers nécessaires
require_once dirname(__DIR__, 2) . '/connect_ddb.php';
require_once dirname(__DIR__, 2) . '/parts/header.php';
require_once dirname(__DIR__, 3) . '/includes/security.php';
require_once dirname(__DIR__, 2) . '/classPages/security/FileSecurityValidator.php';

// Configuration des répertoires d'upload
$projectRoot = dirname(__DIR__, 4);
$uploadDir = $projectRoot . '/photos/images/';
$thumbDir = $projectRoot . '/photos/miniatures/';
$publicUploadDir = '/cosmtt/photos/images/';
$publicThumbDir = '/cosmtt/photos/miniatures/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if (!is_dir($thumbDir)) {
    mkdir($thumbDir, 0755, true);
}

$errors = [];
$success = null;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function isValidDate(string $date): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function publicToFilesystem(string $publicPath, string $projectRoot): ?string
{
    $prefix = '/cosmtt/';
    if (strpos($publicPath, $prefix) !== 0) {
        return null;
    }
    $relative = substr($publicPath, strlen($prefix));
    return $projectRoot . '/' . $relative;
}

function createThumbnailImage(string $sourcePath, string $destPath, int $targetWidth = 300, int $targetHeight = 300): bool
{
    if (!function_exists('imagecreatefromwebp')) {
        return false;
    }

    $src = @imagecreatefromwebp($sourcePath);
    if (!$src) {
        return false;
    }

    $srcWidth = imagesx($src);
    $srcHeight = imagesy($src);

    if ($srcWidth <= 0 || $srcHeight <= 0) {
        imagedestroy($src);
        return false;
    }

    $ratio = min($targetWidth / $srcWidth, $targetHeight / $srcHeight);
    $newWidth = (int) floor($srcWidth * $ratio);
    $newHeight = (int) floor($srcHeight * $ratio);

    $dst = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    $dstX = (int) floor(($targetWidth - $newWidth) / 2);
    $dstY = (int) floor(($targetHeight - $newHeight) / 2);

    imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

    $result = imagewebp($dst, $destPath, 85);
    imagedestroy($src);
    imagedestroy($dst);

    return $result;
}

function convertImageToWebp(string $sourcePath, string $destPath, string $mimeType, array &$sizeInfo = []): bool
{
    if (!function_exists('imagewebp')) {
        return false;
    }

    if ($mimeType === 'image/jpeg') {
        $img = @imagecreatefromjpeg($sourcePath);
    } elseif ($mimeType === 'image/png') {
        $img = @imagecreatefrompng($sourcePath);
    } else {
        return false;
    }

    if (!$img) {
        return false;
    }

    $width = imagesx($img);
    $height = imagesy($img);
    $sizeInfo = ['width' => $width, 'height' => $height];

    if ($mimeType === 'image/png') {
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
    }

    $result = imagewebp($img, $destPath, 85);
    imagedestroy($img);

    return $result;
}

function runCommand(string $command, array &$output = []): int
{
    $output = [];
    $exitCode = 0;
    @exec($command . ' 2>&1', $output, $exitCode);
    return $exitCode;
}

function ffmpegAvailable(): bool
{
    $output = [];
    return runCommand('ffmpeg -version', $output) === 0;
}

function convertVideoToMp4(string $sourcePath, string $destPath): bool
{
    $cmd = 'ffmpeg -y -i ' . escapeshellarg($sourcePath) .
        ' -c:v libx264 -preset medium -crf 23 -pix_fmt yuv420p -movflags +faststart -c:a aac -b:a 128k ' .
        escapeshellarg($destPath);

    return runCommand($cmd) === 0 && file_exists($destPath);
}

function createVideoThumbnail(string $sourcePath, string $destPath, int $size = 300): bool
{
    $filter = 'scale=' . $size . ':' . $size . ':force_original_aspect_ratio=decrease,pad=' . $size . ':' . $size . ':(ow-iw)/2:(oh-ih)/2';
    $cmd = 'ffmpeg -y -ss 00:00:01 -i ' . escapeshellarg($sourcePath) .
        ' -frames:v 1 -vf ' . escapeshellarg($filter) .
        ' ' . escapeshellarg($destPath);

    return runCommand($cmd) === 0 && file_exists($destPath);
}

function getVideoDimensions(string $sourcePath): array
{
    $cmd = 'ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0:s=x ' . escapeshellarg($sourcePath);
    $output = [];
    if (runCommand($cmd, $output) !== 0 || empty($output)) {
        return ['width' => null, 'height' => null];
    }

    $parts = explode('x', trim($output[0]));
    if (count($parts) !== 2) {
        return ['width' => null, 'height' => null];
    }

    return ['width' => (int) $parts[0], 'height' => (int) $parts[1]];
}

$csrfToken = $_SESSION['csrf_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
        $errors[] = 'Jeton CSRF invalide. Merci de recharger la page.';
    }

    if (empty($errors) && $action === 'add_event') {
        $titre = trim((string) ($_POST['titre_evenement'] ?? ''));
        $dateEvenement = (string) ($_POST['date_evenement'] ?? '');
        $userId = $_SESSION['user_id'] ?? null;

        if ($titre === '' || mb_strlen($titre) > 255) {
            $errors[] = 'Le titre est obligatoire (max 255 caractères).';
        }
        if (!isValidDate($dateEvenement)) {
            $errors[] = 'Date d\'événement invalide.';
        }
        if (!$userId) {
            $errors[] = 'Utilisateur non authentifié.';
        }

        if (!ffmpegAvailable()) {
            $errors[] = 'FFmpeg est requis pour traiter les vidéos.';
        }

        $allowedImages = ['image/jpeg', 'image/png'];
        $allowedVideos = ['video/mp4'];

        $files = $_FILES['files'] ?? null;
        if (!$files || empty($files['name'])) {
            $errors[] = 'Aucun fichier sélectionné.';
        }

        if (empty($errors)) {
            $uploadedFiles = [];
            $conn->beginTransaction();

            try {
                $stmtSection = $conn->prepare('INSERT INTO section_Galerie (titre, date_evenement, id_user) VALUES (:titre, :date_evenement, :id_user)');
                $stmtSection->execute([
                    ':titre' => $titre,
                    ':date_evenement' => $dateEvenement,
                    ':id_user' => $userId,
                ]);
                $idGalerie = (int) $conn->lastInsertId();

                $stmtMedia = $conn->prepare('INSERT INTO media (id_galerie, nom_fichier, chemin_fichier, type_media, taille_fichier, largeur, hauteur, ordre_affichage, hash_fichier) VALUES (:id_galerie, :nom_fichier, :chemin_fichier, :type_media, :taille_fichier, :largeur, :hauteur, :ordre_affichage, :hash_fichier)');
                $stmtMiniature = $conn->prepare('INSERT INTO miniature (id_media, chemin_fichier, largeur, hauteur) VALUES (:id_media, :chemin_fichier, :largeur, :hauteur)');

                $count = count($files['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Erreur upload fichier.');
                    }

                    $tmpPath = $files['tmp_name'][$i];
                    $originalName = $files['name'][$i];
                    $size = (int) $files['size'][$i];

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($tmpPath) ?: '';

                    $isImage = in_array($mime, $allowedImages, true);
                    $isVideo = in_array($mime, $allowedVideos, true);

                    if (!$isImage && !$isVideo) {
                        throw new RuntimeException('Type de fichier non autorisé.');
                    }

                    if ($isImage && $size > 4 * 1024 * 1024) {
                        throw new RuntimeException('Image trop lourde (4 Mo max).');
                    }

                    if ($isVideo && $size > 500 * 1024 * 1024) {
                        throw new RuntimeException('Vidéo trop lourde (500 Mo max).');
                    }

                    FileSecurityValidator::performFullValidation($tmpPath, $mime, $originalName);

                    if ($isImage) {
                        $imgInfo = @getimagesize($tmpPath);
                        if ($imgInfo === false) {
                            throw new RuntimeException('Image corrompue.');
                        }

                        $baseName = bin2hex(random_bytes(16));
                        $outputName = $baseName . '.webp';
                        $outputPath = $uploadDir . $outputName;

                        $sizeInfo = [];
                        if (!convertImageToWebp($tmpPath, $outputPath, $mime, $sizeInfo)) {
                            throw new RuntimeException('Conversion WebP échouée.');
                        }

                        $thumbName = $baseName . '_thumb.webp';
                        $thumbPath = $thumbDir . $thumbName;
                        if (!createThumbnailImage($outputPath, $thumbPath)) {
                            throw new RuntimeException('Création de miniature échouée.');
                        }

                        $hash = hash_file('sha256', $outputPath);
                        $fileSize = filesize($outputPath) ?: 0;

                        $stmtMedia->execute([
                            ':id_galerie' => $idGalerie,
                            ':nom_fichier' => $originalName,
                            ':chemin_fichier' => $publicUploadDir . $outputName,
                            ':type_media' => 'image',
                            ':taille_fichier' => $fileSize,
                            ':largeur' => $sizeInfo['width'] ?? null,
                            ':hauteur' => $sizeInfo['height'] ?? null,
                            ':ordre_affichage' => $i,
                            ':hash_fichier' => $hash,
                        ]);

                        $idMedia = (int) $conn->lastInsertId();

                        $stmtMiniature->execute([
                            ':id_media' => $idMedia,
                            ':chemin_fichier' => $publicThumbDir . $thumbName,
                            ':largeur' => 300,
                            ':hauteur' => 300,
                        ]);

                        $uploadedFiles[] = [$outputPath, $thumbPath];
                    }

                    if ($isVideo) {
                        $baseName = bin2hex(random_bytes(16));
                        $outputName = $baseName . '.mp4';
                        $outputPath = $uploadDir . $outputName;

                        if (!convertVideoToMp4($tmpPath, $outputPath)) {
                            throw new RuntimeException('Conversion MP4 échouée.');
                        }

                        $thumbName = $baseName . '_thumb.webp';
                        $thumbPath = $thumbDir . $thumbName;
                        if (!createVideoThumbnail($outputPath, $thumbPath, 300)) {
                            throw new RuntimeException('Création de miniature vidéo échouée.');
                        }

                        $dimensions = getVideoDimensions($outputPath);
                        $hash = hash_file('sha256', $outputPath);
                        $fileSize = filesize($outputPath) ?: 0;

                        $stmtMedia->execute([
                            ':id_galerie' => $idGalerie,
                            ':nom_fichier' => $originalName,
                            ':chemin_fichier' => $publicUploadDir . $outputName,
                            ':type_media' => 'video',
                            ':taille_fichier' => $fileSize,
                            ':largeur' => $dimensions['width'],
                            ':hauteur' => $dimensions['height'],
                            ':ordre_affichage' => $i,
                            ':hash_fichier' => $hash,
                        ]);

                        $idMedia = (int) $conn->lastInsertId();

                        $stmtMiniature->execute([
                            ':id_media' => $idMedia,
                            ':chemin_fichier' => $publicThumbDir . $thumbName,
                            ':largeur' => 300,
                            ':hauteur' => 300,
                        ]);

                        $uploadedFiles[] = [$outputPath, $thumbPath];
                    }
                }

                $conn->commit();
                $success = 'Événement ajouté avec succès.';
            } catch (Throwable $e) {
                $conn->rollBack();

                if (!empty($uploadedFiles)) {
                    foreach ($uploadedFiles as $paths) {
                        foreach ($paths as $path) {
                            if (is_string($path) && file_exists($path)) {
                                @unlink($path);
                            }
                        }
                    }
                }

                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors) && $action === 'delete_event') {
        $idGalerie = (int) ($_POST['id_galerie'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($idGalerie <= 0) {
            $errors[] = 'Événement invalide.';
        } else {
            $conn->beginTransaction();
            try {
                $stmtCheck = $conn->prepare('SELECT id FROM section_Galerie WHERE id = :id AND id_user = :id_user');
                $stmtCheck->execute([':id' => $idGalerie, ':id_user' => $userId]);
                if (!$stmtCheck->fetch()) {
                    throw new RuntimeException('Accès non autorisé.');
                }

                $stmtFiles = $conn->prepare('SELECT chemin_fichier FROM media WHERE id_galerie = :id');
                $stmtFiles->execute([':id' => $idGalerie]);
                $mediaPaths = $stmtFiles->fetchAll(PDO::FETCH_COLUMN);

                $stmtThumbs = $conn->prepare('SELECT miniature.chemin_fichier FROM miniature INNER JOIN media ON media.id = miniature.id_media WHERE media.id_galerie = :id');
                $stmtThumbs->execute([':id' => $idGalerie]);
                $thumbPaths = $stmtThumbs->fetchAll(PDO::FETCH_COLUMN);

                $stmtDelete = $conn->prepare('DELETE FROM section_Galerie WHERE id = :id');
                $stmtDelete->execute([':id' => $idGalerie]);

                $conn->commit();

                foreach (array_merge($mediaPaths, $thumbPaths) as $publicPath) {
                    $fsPath = publicToFilesystem((string) $publicPath, $projectRoot);
                    if ($fsPath && file_exists($fsPath)) {
                        @unlink($fsPath);
                    }
                }

                $success = 'Événement supprimé.';
            } catch (Throwable $e) {
                $conn->rollBack();
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors) && $action === 'delete_media') {
        $idMedia = (int) ($_POST['id_media'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($idMedia <= 0) {
            $errors[] = 'Média invalide.';
        } else {
            $conn->beginTransaction();
            try {
                $stmtCheck = $conn->prepare('SELECT media.id, media.chemin_fichier, section_Galerie.id_user FROM media INNER JOIN section_Galerie ON section_Galerie.id = media.id_galerie WHERE media.id = :id');
                $stmtCheck->execute([':id' => $idMedia]);
                $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                if (!$row || (int) $row['id_user'] !== $userId) {
                    throw new RuntimeException('Accès non autorisé.');
                }

                $stmtThumb = $conn->prepare('SELECT chemin_fichier FROM miniature WHERE id_media = :id');
                $stmtThumb->execute([':id' => $idMedia]);
                $thumbPath = $stmtThumb->fetchColumn();

                $stmtDelete = $conn->prepare('DELETE FROM media WHERE id = :id');
                $stmtDelete->execute([':id' => $idMedia]);

                $conn->commit();

                $paths = [(string) $row['chemin_fichier']];
                if ($thumbPath) {
                    $paths[] = (string) $thumbPath;
                }

                foreach ($paths as $publicPath) {
                    $fsPath = publicToFilesystem($publicPath, $projectRoot);
                    if ($fsPath && file_exists($fsPath)) {
                        @unlink($fsPath);
                    }
                }

                $success = 'Média supprimé.';
            } catch (Throwable $e) {
                $conn->rollBack();
                $errors[] = $e->getMessage();
            }
        }
    }

    if (empty($errors) && $action === 'update_order') {
        $idGalerie = (int) ($_POST['id_galerie'] ?? 0);
        $order = $_POST['order'] ?? [];
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($idGalerie <= 0 || !is_array($order)) {
            $errors[] = 'Données invalides.';
        } else {
            $stmtCheck = $conn->prepare('SELECT id FROM section_Galerie WHERE id = :id AND id_user = :id_user');
            $stmtCheck->execute([':id' => $idGalerie, ':id_user' => $userId]);
            if (!$stmtCheck->fetch()) {
                $errors[] = 'Accès non autorisé.';
            } else {
                $conn->beginTransaction();
                try {
                    $stmtUpdate = $conn->prepare('UPDATE media SET ordre_affichage = :ordre WHERE id = :id AND id_galerie = :id_galerie');
                    $index = 0;
                    foreach ($order as $idMedia) {
                        $idMedia = (int) $idMedia;
                        if ($idMedia <= 0) {
                            continue;
                        }
                        $stmtUpdate->execute([
                            ':ordre' => $index,
                            ':id' => $idMedia,
                            ':id_galerie' => $idGalerie,
                        ]);
                        $index++;
                    }
                    $conn->commit();
                    $success = 'Ordre mis à jour.';
                } catch (Throwable $e) {
                    $conn->rollBack();
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

// Chargement des sections + médias
$sections = [];
$mediaBySection = [];

try {
    $stmtSections = $conn->query('SELECT id, titre, date_evenement, date_creation FROM section_Galerie ORDER BY date_evenement DESC, id DESC');
    $sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($sections)) {
        $ids = array_map(static function ($s) { return (int) $s['id']; }, $sections);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtMedia = $conn->prepare(
            'SELECT media.*, miniature.chemin_fichier AS thumb_path '
            . 'FROM media '
            . 'LEFT JOIN miniature ON miniature.id_media = media.id '
            . 'WHERE media.id_galerie IN (' . $placeholders . ') '
            . 'ORDER BY media.ordre_affichage ASC, media.id ASC'
        );
        $stmtMedia->execute($ids);
        $rows = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $mediaBySection[(int) $row['id_galerie']][] = $row;
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Erreur lors du chargement des événements.';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la Galerie Photos/Vidéos</title>
    <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/colors_group.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/popup.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_galerie_popup.css">
    <style>
        .sections-container, .medias-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
        }

        .section-card, .media-card {
            border: 1px solid var(--fifth-color);
            padding: 15px;
            border-radius: 8px;
            width: 300px;
            position: relative;
            background: #fff;
        }

        .media-card img, .media-card video {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .media-card.is-dragging {
            opacity: 0.5;
        }

        .actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .actions form {
            margin: 0;
        }

        .actions button {
            border: none;
            background: var(--primary-color);
            color: #fff;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        .actions button.danger {
            background: #b91c1c;
        }

        .move-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(255, 255, 255, 0.8);
            color: #333;
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
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
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

        .alert {
            padding: 10px 12px;
            border-radius: 6px;
            margin: 10px 0;
        }

        .alert-error {
            background: #ffe9e9;
            border: 1px solid #f5b5b5;
            color: #7f1d1d;
        }

        .alert-success {
            background: #ecfdf3;
            border: 1px solid #bfe8ce;
            color: #14532d;
        }

        .media-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }

        .media-card-header {
            font-size: 12px;
            color: #444;
            margin-bottom: 8px;
        }

        .drag-handle {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
            cursor: grab;
        }
    </style>
</head>

<body>

    <div class="title-bar">GESTION DE LA GALERIE PHOTOS/VIDÉOS</div>

    <main>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>

        <a href="javascript:void(0);" class="lienPopup" id="showPopup">Ajouter un événement</a>

        <!-- Popup d'ajout d'événement -->
        <div class="popup" id="addPopup">
            <div class="popup-card">
                <div class="popup-content">
                    <h3>Ajouter un événement</h3>
                    <form method="POST" action="" class="form-ajout" id="eventForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_event">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

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
                            <p><strong>Glissez et déposez vos fichiers ici</strong></p>
                            <p>ou</p>
                            <div class="upload-btn" onclick="document.getElementById('fileInput').click()">
                                ➕ Choisir des fichiers
                            </div>
                            <input type="file"
                                   id="fileInput"
                                   name="files[]"
                                   class="file-input-hidden"
                                   multiple
                                   accept=".jpg,.jpeg,.png,.mp4">
                            <p class="file-size-info">
                                Images : JPG, JPEG, PNG (Max 4 Mo)<br>
                                Vidéos : MP4 (Max 500 Mo)
                            </p>
                        </div>

                        <!-- Grille de prévisualisation -->
                        <div id="previewGrid" class="preview-grid"></div>

                        <!-- Champ caché pour stocker les données des fichiers compressés -->
                        <input type="hidden" name="compressed_files_data" id="compressedFilesData">

                        <!-- Boutons d'action -->
                        <div class="popup-buttons">
                            <button type="button" class="btn btnPopup-primary" id="cancelPopup">Annuler</button>
                            <button type="submit" class="btn btnPopup-primary" id="submitBtn">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (!empty($sections)): ?>
            <div class="sections-container">
                <?php foreach ($sections as $section): ?>
                    <?php $sectionId = (int) $section['id']; ?>
                    <div class="section-card">
                        <div class="media-card-header">
                            <strong><?= h($section['titre']) ?></strong><br>
                            Événement: <?= h($section['date_evenement']) ?>
                        </div>

                        <div class="actions">
                            <form method="POST" action="" onsubmit="return confirm('Supprimer cet événement ?');">
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="id_galerie" value="<?= $sectionId ?>">
                                <button type="submit" class="danger">Supprimer l'événement</button>
                            </form>
                        </div>

                        <div class="media-grid" data-gallery-id="<?= $sectionId ?>">
                            <?php foreach (($mediaBySection[$sectionId] ?? []) as $media): ?>
                                <?php
                                    $mediaId = (int) $media['id'];
                                    $thumbPath = $media['thumb_path'] ?? '';
                                    $mediaPath = $media['chemin_fichier'] ?? '';
                                    $type = $media['type_media'] ?? '';
                                ?>
                                <div class="media-card" draggable="true" data-media-id="<?= $mediaId ?>">
                                    <div class="media-card-header">
                                        <?= h($media['nom_fichier'] ?? '') ?>
                                    </div>
                                    <?php if ($type === 'image'): ?>
                                        <img src="<?= h($mediaPath) ?>" alt="Image">
                                    <?php else: ?>
                                        <?php if ($thumbPath): ?>
                                            <img src="<?= h($thumbPath) ?>" alt="Miniature">
                                        <?php endif; ?>
                                        <video src="<?= h($mediaPath) ?>" controls></video>
                                    <?php endif; ?>
                                    <div class="drag-handle">Glisser pour réordonner</div>
                                    <div class="actions">
                                        <form method="POST" action="" onsubmit="return confirm('Supprimer ce média ?');">
                                            <input type="hidden" name="action" value="delete_media">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="id_media" value="<?= $mediaId ?>">
                                            <button type="submit" class="danger">Supprimer</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Overlay de chargement -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-content">
                <div class="spinner"></div>
                <p>Compression et téléchargement en cours...</p>
                <p id="progressText">0%</p>
            </div>
        </div>

    </main>

    <script>
        (function () {
            const csrfToken = '<?= h($csrfToken) ?>';
            let dragging = null;

            function sendOrderUpdate(container) {
                const galleryId = container.getAttribute('data-gallery-id');
                const ids = Array.from(container.querySelectorAll('[data-media-id]'))
                    .map((el) => el.getAttribute('data-media-id'));

                const data = new FormData();
                data.append('action', 'update_order');
                data.append('csrf_token', csrfToken);
                data.append('id_galerie', galleryId);
                ids.forEach((id) => data.append('order[]', id));

                fetch('', {
                    method: 'POST',
                    body: data
                }).catch(() => {});
            }

            document.querySelectorAll('.media-grid').forEach((grid) => {
                grid.addEventListener('dragstart', (e) => {
                    const target = e.target.closest('.media-card');
                    if (!target) return;
                    dragging = target;
                    target.classList.add('is-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });

                grid.addEventListener('dragend', () => {
                    if (dragging) {
                        dragging.classList.remove('is-dragging');
                    }
                    dragging = null;
                });

                grid.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    const target = e.target.closest('.media-card');
                    if (!target || !dragging || target === dragging) return;

                    const rect = target.getBoundingClientRect();
                    const shouldInsertBefore = e.clientY < rect.top + rect.height / 2;
                    if (shouldInsertBefore) {
                        grid.insertBefore(dragging, target);
                    } else {
                        grid.insertBefore(dragging, target.nextSibling);
                    }
                });

                grid.addEventListener('drop', (e) => {
                    e.preventDefault();
                    sendOrderUpdate(grid);
                });
            });
        })();
    </script>
    <script src="/cosmtt/admin/js/app.js"></script>
</body>
</html>
