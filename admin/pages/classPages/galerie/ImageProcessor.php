<?php
/**
 * =============================================================================
 * IMAGE PROCESSOR - TRAITEMENT D'IMAGES
 * =============================================================================
 * 
 * Classe permettant de traiter les images : compression, conversion WebP,
 * création de miniatures et validation de sécurité.
 * 
 * @author Admin
 * @version 1.0
 * =============================================================================
 */

require_once dirname(__DIR__, 2). '/classPages/security/FileSecurityValidator.php';

class ImageProcessor
{
    private string $uploadDir;
    private string $thumbDir;
    private int $maxImageSize;
    private int $thumbnailHeight;
    private int $jpegQuality;
    private int $webpQuality;
    private array $allowedTypes;

    /**
     * Constructeur
     * 
     * @param string $uploadDir Répertoire d'upload des images
     * @param string $thumbDir Répertoire des miniatures
     */
    public function __construct(string $uploadDir, string $thumbDir)
    {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->thumbDir = rtrim($thumbDir, '/') . '/';
        $this->maxImageSize = 4 * 1024 * 1024; // 4 Mo
        $this->thumbnailHeight = 400;
        $this->jpegQuality = 90;
        $this->webpQuality = 100;
        $this->allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    }

    /**
     * Configure la taille maximale des images
     */
    public function setMaxImageSize(int $size): self
    {
        $this->maxImageSize = $size;
        return $this;
    }

    /**
     * Configure la hauteur des miniatures
     */
    public function setThumbnailHeight(int $height): self
    {
        $this->thumbnailHeight = $height;
        return $this;
    }

    /**
     * Configure la qualité JPEG
     */
    public function setJpegQuality(int $quality): self
    {
        $this->jpegQuality = min(100, max(1, $quality));
        return $this;
    }

    /**
     * Configure la qualité WebP
     */
    public function setWebpQuality(int $quality): self
    {
        $this->webpQuality = min(100, max(1, $quality));
        return $this;
    }

    /**
     * Vérifie si la librairie GD est disponible avec le support WebP
     * 
     * @return bool
     */
    public static function isGdAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    /**
     * Traite un fichier image depuis des données base64
     * 
     * @param string $base64Data Données base64 de l'image
     * @param string $originalName Nom original du fichier
     * @param int $galerieId ID de l'événement
     * @return array Résultat du traitement
     */
    public function processImage(string $base64Data, string $originalName, int $galerieId): array
    {
        $result = [
            'success' => false,
            'media_id' => null,
            'filename' => '',
            'error' => ''
        ];

        try {
            // Décoder les données base64
            if (strpos($base64Data, 'base64,') !== false) {
                $base64Data = explode('base64,', $base64Data)[1];
            }
            
            $fileContent = base64_decode($base64Data, true);
            
            if ($fileContent === false) {
                throw new Exception("Décodage base64 échoué");
            }

            // Vérifier la taille
            if (strlen($fileContent) > $this->maxImageSize) {
                throw new Exception("Image trop volumineuse (max " . ($this->maxImageSize / 1024 / 1024) . " Mo)");
            }

            // Créer un fichier temporaire pour le scan de sécurité
            $tempScanFile = $this->createTempFile($fileContent);
            
            try {
                // Validation de sécurité
                $this->validateSecurity($tempScanFile, $originalName);
            } finally {
                if (file_exists($tempScanFile)) {
                    unlink($tempScanFile);
                }
            }

            // Préparer le nom de fichier
            $filenameBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            
            // Créer l'image WebP
            $webpFilename = 'IMG_' . $filenameBase . '_scaled.webp';
            $webpPath = $this->uploadDir . $webpFilename;
            
            // Convertir en WebP
            $webpResult = $this->convertToWebP($fileContent, $webpPath);
            
            if (!$webpResult['success']) {
                throw new Exception($webpResult['error']);
            }

            // Vérifier que le fichier a été créé
            if (!file_exists($webpPath)) {
                throw new Exception("Erreur lors de l'écriture du fichier final.");
            }

            // Calculer le hash
            $fileHash = hash_file('sha256', $webpPath);

            // Obtenir les dimensions
            $dimensions = $this->getImageDimensions($webpPath);
            
            // Créer la miniature
            $thumbnailResult = $this->createThumbnail($webpPath, $filenameBase);

            // Préparer les données du résultat
            $result = [
                'success' => true,
                'filename' => $webpFilename,
                'filepath' => '/cosmtt/photos/' . $webpFilename,
                'filesize' => filesize($webpPath),
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'hash' => $fileHash,
                'thumb_created' => $thumbnailResult['success'],
                'thumb_path' => $thumbnailResult['path'] ?? '',
                'thumb_width' => $thumbnailResult['width'] ?? 0,
                'thumb_height' => $thumbnailResult['height'] ?? 0
            ];

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            error_log("Erreur traitement image : " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Valide la sécurité d'un fichier image
     * 
     * @param string $filePath Chemin du fichier
     * @param string $originalName Nom original
     * @throws Exception
     */
    private function validateSecurity(string $filePath, string $originalName): void
    {
        // Vérifier le type MIME côté serveur
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $serverMimeType = finfo_buffer($finfo, file_get_contents($filePath, false, null, 0, 1024));
        finfo_close($finfo);

        if (!in_array($serverMimeType, $this->allowedTypes, true)) {
            throw new Exception("Type de fichier non autorisé : " . htmlspecialchars($serverMimeType));
        }

        // Valider les magic bytes
        FileSecurityValidator::validateMagicBytes($filePath, $serverMimeType);
        
        // Scanner pour malware
        FileSecurityValidator::scanForMalware($filePath, $serverMimeType);
        
        // Scanner avec ClamAV si disponible
        $clamResult = FileSecurityValidator::scanWithClamAV($filePath);
        if ($clamResult['available'] && !$clamResult['clean']) {
            throw new Exception("Menace détectée par l'antivirus serveur.");
        }
    }

    /**
     * Convertit une image en WebP
     * 
     * @param string $sourceData Données source de l'image
     * @param string $destPath Chemin de destination
     * @return array Résultat
     */
    private function convertToWebP(string $sourceData, string $destPath): array
    {
        $result = ['success' => false, 'error' => ''];

        // Créer l'image depuis les données
        $source = @imagecreatefromstring($sourceData);
        
        if ($source === false) {
            $result['error'] = "Format d'image non reconnu ou corrompu.";
            return $result;
        }

        // Convertir en truecolor si nécessaire
        if (imageistruecolor($source) === false) {
            imagepalettetotruecolor($source);
        }

        // Gérer la transparence
        imagealphablending($source, true);
        imagesavealpha($source, true);

        // Sauvegarder en WebP
        if (!imagewebp($source, $destPath, $this->webpQuality)) {
            imagedestroy($source);
            $result['error'] = "La conversion de l'image en WebP a échoué.";
            return $result;
        }

        imagedestroy($source);
        $result['success'] = true;
        
        return $result;
    }

    /**
     * Crée une miniature de l'image
     * 
     * @param string $sourcePath Chemin du fichier source
     * @param string $filenameBase Nom de base pour la miniature
     * @return array Résultat
     */
    public function createThumbnail(string $sourcePath, string $filenameBase): array
    {
        $result = ['success' => false, 'path' => '', 'width' => 0, 'height' => 0];

        if (!file_exists($sourcePath)) {
            return $result;
        }

        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return $result;
        }

        [$width, $height, $type] = $imageInfo;

        // Calculer les nouvelles dimensions (hauteur fixe)
        $sourceRatio = $width / $height;
        $newHeight = $this->thumbnailHeight;
        $newWidth = (int)($newHeight * $sourceRatio);

        // Créer la nouvelle image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        if (!$newImage) {
            return $result;
        }

        // Charger l'image source selon son type
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
                imagedestroy($newImage);
                return $result;
        }

        if (!$source) {
            imagedestroy($newImage);
            return $result;
        }

        // Gérer la transparence pour PNG et WebP
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        // Redimensionner
        imagecopyresampled(
            $newImage, $source, 0, 0, 0, 0, 
            $newWidth, $newHeight, $width, $height
        );

        // Sauvegarder la miniature
        $thumbFilename = 'IMG_' . $filenameBase . '_mini.webp';
        $thumbPath = $this->thumbDir . $thumbFilename;

        if (imagewebp($newImage, $thumbPath, $this->webpQuality)) {
            $result = [
                'success' => true,
                'path' => '/cosmtt/photos/miniatures/' . $thumbFilename,
                'width' => $newWidth,
                'height' => $newHeight
            ];
        }

        imagedestroy($source);
        imagedestroy($newImage);

        return $result;
    }

    /**
     * Crée un fichier temporaire pour le scan
     * 
     * @param string $content Contenu du fichier
     * @return string Chemin du fichier temporaire
     */
    private function createTempFile(string $content): string
    {
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scan_' . uniqid() . '.tmp';
        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    /**
     * Obtient les dimensions d'une image
     * 
     * @param string $filePath Chemin du fichier
     * @return array ['width' => int, 'height' => int]
     */
    private function getImageDimensions(string $filePath): array
    {
        $size = getimagesize($filePath);
        if ($size) {
            return ['width' => $size[0], 'height' => $size[1]];
        }
        return ['width' => 0, 'height' => 0];
    }

    /**
     * Supprime un fichier image et sa miniature
     * 
     * @param string $filename Nom du fichier
     * @param string|null $thumbPath Chemin de la miniature
     * @return bool Succès
     */
    public function deleteImageFiles(string $filename, ?string $thumbPath = null): bool
    {
        $success = true;

        // Supprimer le fichier principal
        $mainFile = $this->uploadDir . $filename;
        if (file_exists($mainFile) && is_file($mainFile)) {
            if (!unlink($mainFile)) {
                $success = false;
            }
        }

        // Supprimer la miniature
        if ($thumbPath) {
            $thumbFile = $this->thumbDir . basename($thumbPath);
            if (file_exists($thumbFile) && is_file($thumbFile)) {
                if (!unlink($thumbFile)) {
                    $success = false;
                }
            }
        }

        return $success;
    }
}