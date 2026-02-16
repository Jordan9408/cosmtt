<?php

// Inclusion des fichiers nécessaires
require_once dirname(__DIR__, 2) . '/connect_ddb.php';
require_once dirname(__DIR__, 2) . '/parts/header.php';
require_once dirname(__DIR__, 3) . '/includes/security.php';
require_once dirname(__DIR__, 2) . '/classPages/security/FileSecurityValidator.php';


// Traitement du formulaire d'ajout
$msg = '';
$msgClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    // 1. Sécurité et Validation des entrées
    $titre = isset($_POST['titre_evenement']) ? trim($_POST['titre_evenement']) : '';
    $date = isset($_POST['date_evenement']) ? $_POST['date_evenement'] : '';
    
    if (empty($titre) || empty($date)) {
        $msg = "Le titre et la date sont obligatoires.";
        $msgClass = "error";
    } else {
        try {
            // On suppose que $pdo est disponible via connect_ddb.php
            if (!isset($pdo)) throw new Exception("Erreur de connexion BDD.");

            $pdo->beginTransaction();

            // 2. Insertion Section
            $stmtSection = $pdo->prepare("INSERT INTO section_galerie (titre, date_evenement, date_creation) VALUES (:titre, :date_evt, NOW())");
            $stmtSection->execute([':titre' => $titre, ':date_evt' => $date]);
            $idSection = $pdo->lastInsertId();

            // Chemins des dossiers (racine cosmtt/galerie_photos)
            $rootPath = dirname(__DIR__, 4); 
            $uploadDir = $rootPath . '/galerie_photos';
            $miniDir = $uploadDir . '/miniatures';

            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (!is_dir($miniDir)) mkdir($miniDir, 0755, true);

            // 3. Traitement des fichiers
            if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                $files = $_FILES['files'];
                $count = count($files['name']);

                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $files['tmp_name'][$i];
                        $name = $files['name'][$i];
                        $mime = mime_content_type($tmpName);
                        
                        // Nettoyage nom fichier (garde lettres, chiffres, tirets)
                        $pathInfo = pathinfo($name);
                        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '', $pathInfo['filename']);
                        if (empty($baseName)) $baseName = 'img_' . uniqid();
                        
                        // Vérification Image
                        if (strpos($mime, 'image/') === 0) {
                            // Nom final : IMG_2198_scaled.webp
                            $finalName = $baseName . '_scaled.webp';
                            $destPath = $uploadDir . '/' . $finalName;
                            
                            // Chargement image source
                            $sourceImg = null;
                            switch ($mime) {
                                case 'image/jpeg': $sourceImg = imagecreatefromjpeg($tmpName); break;
                                case 'image/png': $sourceImg = imagecreatefrompng($tmpName); break;
                                case 'image/webp': $sourceImg = imagecreatefromwebp($tmpName); break;
                            }

                            if ($sourceImg) {
                                // Conversion WebP (Qualité 80)
                                imagewebp($sourceImg, $destPath, 80);
                                
                                // Insertion Media
                                $stmtMedia = $pdo->prepare("INSERT INTO media (id_section, nom_fichier, type) VALUES (:id_sec, :nom, 'photo')");
                                $stmtMedia->execute([':id_sec' => $idSection, ':nom' => $finalName]);
                                $idMedia = $pdo->lastInsertId();

                                // Création Miniature (300x200)
                                // Nom : img698b..._1770..._460_mini.webp
                                $miniName = uniqid('img') . '_' . time() . '_460_mini.webp';
                                $miniPath = $miniDir . '/' . $miniName;

                                // Redimensionnement (Crop centré)
                                $thumbW = 300; $thumbH = 200;
                                $width = imagesx($sourceImg); $height = imagesy($sourceImg);
                                $srcRatio = $width / $height; $thumbRatio = $thumbW / $thumbH;

                                if ($srcRatio >= $thumbRatio) {
                                    $newH = $thumbH; $newW = $width / ($height / $thumbH);
                                } else {
                                    $newW = $thumbW; $newH = $height / ($width / $thumbW);
                                }

                                $tempImg = imagecreatetruecolor($newW, $newH);
                                imagealphablending($tempImg, false); imagesavealpha($tempImg, true); // Transparence
                                imagecopyresampled($tempImg, $sourceImg, 0, 0, 0, 0, $newW, $newH, $width, $height);

                                $finalThumb = imagecreatetruecolor($thumbW, $thumbH);
                                imagealphablending($finalThumb, false); imagesavealpha($finalThumb, true);
                                imagecopy($finalThumb, $tempImg, 0, 0, ($newW - $thumbW) / 2, ($newH - $thumbH) / 2, $thumbW, $thumbH);

                                imagewebp($finalThumb, $miniPath, 80);

                                // Insertion Miniature
                                $stmtMini = $pdo->prepare("INSERT INTO miniatures (id_media, nom_miniature) VALUES (:id_med, :nom)");
                                $stmtMini->execute([':id_med' => $idMedia, ':nom' => $miniName]);

                                imagedestroy($sourceImg); imagedestroy($tempImg); imagedestroy($finalThumb);
                            }
                        } elseif ($mime === 'video/mp4') {
                            // Vidéo (pas de conversion demandée, juste upload sécurisé)
                            $finalName = $baseName . '.mp4';
                            move_uploaded_file($tmpName, $uploadDir . '/' . $finalName);
                            $stmtMedia = $pdo->prepare("INSERT INTO media (id_section, nom_fichier, type) VALUES (:id_sec, :nom, 'video')");
                            $stmtMedia->execute([':id_sec' => $idSection, ':nom' => $finalName]);
                        }
                    }
                }
            }
            $pdo->commit();
            $msg = "Événement ajouté avec succès !"; $msgClass = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Erreur : " . $e->getMessage(); $msgClass = "error";
        }
    }
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
        }
        
        .media-card img, .media-card video {
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
        /* Styles pour les messages */
        .alert { padding: 15px; margin: 20px 0; border-radius: 4px; font-weight: bold; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .preview-item { position: relative; width: 100px; height: 100px; overflow: hidden; border-radius: 4px; border: 1px solid #ddd; }
    </style>
</head>

<body>

    <div class="title-bar">GESTION DE LA GALERIE PHOTOS/VIDÉOS</div>

    <main>
        
        <?php if (!empty($msg)): ?>
            <div class="alert <?php echo $msgClass; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        
        <a href="javascript:void(0);" class="lienPopup" id="showPopup">Ajouter un événement</a>

        <!-- Popup d'ajout d'événement -->
        <div class="popup" id="addPopup">
            <div class="popup-card">
                <div class="popup-content">
                    <h3>Ajouter un événement</h3>
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

                        <!-- Info sur la réorganisation -->
                        <!-- <div style="background-color: var(--fourth-color); border-left: 3px solid var(--primary-color); padding: 10px 15px; margin: 15px 0; border-radius: 4px; font-size: 13px; color: var(--fifty-color);">
                            💡 <strong>Astuce :</strong> Vous pouvez réorganiser les fichiers en les glissant et en les déposant à la position souhaitée.
                        </div> -->

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

        <!-- Overlay de chargement -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-content">
                <div class="spinner"></div>
                <p>Compression et téléchargement en cours...</p>
                <p id="progressText">0%</p>
            </div>
        </div>

    </main>

    <script src="/cosmtt/admin/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');
            const previewGrid = document.getElementById('previewGrid');
            const eventForm = document.getElementById('eventForm');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            // Gestion Drag & Drop
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = 'var(--primary-color)';
                dropZone.style.backgroundColor = 'rgba(0,0,0,0.05)';
            });

            dropZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#ccc';
                dropZone.style.backgroundColor = 'transparent';
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#ccc';
                dropZone.style.backgroundColor = 'transparent';
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    updatePreview(fileInput.files);
                }
            });

            fileInput.addEventListener('change', () => updatePreview(fileInput.files));

            function updatePreview(files) {
                previewGrid.innerHTML = '';
                Array.from(files).forEach(file => {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        div.innerHTML = file.type.startsWith('image/') 
                            ? `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">`
                            : `<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f0f0f0; font-size:24px;">🎬</div>`;
                        previewGrid.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            }

            // Afficher le chargement lors de la soumission
            eventForm.addEventListener('submit', () => loadingOverlay.style.display = 'flex');
        });
    </script>
    
</body>

</html>