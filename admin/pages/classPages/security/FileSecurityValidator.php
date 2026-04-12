<?php
/**
 * Validateur de sécurité des fichiers uploadés
 * Support : Images (JPG, PNG, WebP), Vidéos (MP4), Documents (PDF)
 */
class FileSecurityValidator {
    
    // Signatures magic bytes pour validation
    private static $MAGIC_BYTES = [
        'image/jpeg' => [0xFF, 0xD8, 0xFF],
        'image/jpg' => [0xFF, 0xD8, 0xFF], // Alias pour jpg
        'image/png' => [0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A], // PNG complet
        'image/webp' => [0x52, 0x49, 0x46, 0x46, 0x00, 0x00, 0x00, 0x00, 0x57, 0x45, 0x42, 0x50], // RIFF + WEBP
        'application/pdf' => [0x25, 0x50, 0x44, 0x46] // %PDF
    ];

    /**
     * Valide les magic bytes du fichier (en-tête binaire)
     * @param string $filePath Chemin du fichier
     * @param string $expectedType Type MIME attendu
     * @return bool
     * @throws Exception
     */
    public static function validateMagicBytes($filePath, $expectedType) {
        if (!file_exists($filePath)) {
            throw new Exception("Fichier non trouvé : $filePath");
        }

        // Lecture des premiers bytes du fichier
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new Exception("Impossible de lire le fichier");
        }

        $header = fread($handle, 16); // Lire 16 premiers bytes
        fclose($handle);

        // Vérifier les magic bytes
        if (!isset(self::$MAGIC_BYTES[$expectedType])) {
            throw new Exception("Type non autorisé : $expectedType");
        }

        $expectedSignature = self::$MAGIC_BYTES[$expectedType];
        $headerBytes = array_values(unpack('C*', substr($header, 0, count($expectedSignature))));

        // Comparaison des signatures (avec flexibilité pour MP4)
        $isValid = true;
        if ($expectedType === 'video/mp4') {
            // MP4 : vérifier 'ftyp' à partir de byte 4
            $ftyp = substr($header, 4, 4);
            $isValid = strpos($ftyp, 'ftyp') !== false;
        } else {
            // Autres formats : vérifier au début
            for ($i = 0; $i < count($expectedSignature); $i++) {
                if (!isset($headerBytes[$i]) || $headerBytes[$i] !== $expectedSignature[$i]) {
                    $isValid = false;
                    break;
                }
            }
        }

        if (!$isValid) {
            throw new Exception("Magic bytes invalides. Type attendu: $expectedType, signature: " . bin2hex(substr($header, 0, 4)));
        }

        return true;
    }

    /**
     * Scanne les fichiers pour détecter du code malveillant
     * @param string $filePath Chemin du fichier
     * @param string $fileType Type MIME
     * @return bool
     * @throws Exception
     */
    public static function scanForMalware($filePath, $fileType) {
        // Signatures dangereuses
        $dangerousPatterns = [
            '/<?php/i',
            '/<\?=/i',
            '/<\?/i',
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/passthru\s*\(/i',
            '/shell_exec\s*\(/i',
            '/popen\s*\(/i',
            '/proc_open\s*\(/i',
            '/assert\s*\(/i',
            '/create_function\s*\(/i',
            '/__halt_compiler/i',
            '/javascript:/i',
            '/onerror\s*=/i',
            '/onload\s*=/i'
        ];

        // Pour les images : vérifier les 2 premiers KB
        if (strpos($fileType, 'image') !== false) {
            $content = file_get_contents($filePath, false, null, 0, 2048);
            
            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    throw new Exception("⚠️ Code malveillant détecté dans l'image");
                }
            }
        }

        // Pour les vidéos : valider avec FFprobe
        if (strpos($fileType, 'video') !== false) {
            self::validateVideoWithFFprobe($filePath);
        }

        // Pour les PDF : scanner le contenu
        if (strpos($fileType, 'pdf') !== false) {
            self::validatePDFSafety($filePath);
        }

        return true;
    }

    /**
     * Valide une vidéo avec FFprobe
     * @param string $filePath Chemin du fichier
     * @return bool
     * @throws Exception
     */
    private static function validateVideoWithFFprobe($filePath) {
        if (!function_exists('exec')) {
            return true; // Pas de vérification si exec n'existe pas
        }

        // Vérifier si ffprobe est disponible
        exec('which ffprobe 2>/dev/null', $output, $returnVar);
        if ($returnVar !== 0) {
            error_log("FFprobe non disponible pour validation vidéo");
            return true;
        }

        // Valider la structure vidéo
        $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=codec_type -of csv=p=0 " . escapeshellarg($filePath) . " 2>&1";
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Exception("Vidéo corrompue ou invalide");
        }

        return true;
    }

    /**
     * Valide la sécurité des fichiers PDF
     * NOTE: Ne lance pas d'exception car les menaces seront nettoyées par PDFSecurityProcessor
     * Cette méthode vérifie juste la structure PDF valide
     * 
     * @param string $filePath Chemin du fichier
     * @return bool
     * @throws Exception
     */
    private static function validatePDFSafety($filePath) {
        // Lire les 5KB du début du PDF
        $content = file_get_contents($filePath, false, null, 0, 5120);

        // Vérifier l'en-tête PDF valide
        if (strpos($content, '%PDF') !== 0) {
            throw new Exception("PDF invalide : en-tête manquant");
        }

        // Les patterns dangereux ne lancent pas d'exception ici
        // car ils seront nettoyés par PDFSecurityProcessor
        // Cette méthode effectue juste une validation structurelle basique

        return true;
    }

    /**
     * Utilise ClamAV si disponible (meilleure option)
     * @param string $filePath Chemin du fichier
     * @return array ['available' => bool, 'clean' => bool]
     * @throws Exception
     */
    public static function scanWithClamAV($filePath) {
        if (!function_exists('exec')) {
            return ['available' => false];
        }

        // Vérifier si clamscan est disponible
        exec('which clamscan 2>/dev/null', $output, $returnVar);

        if ($returnVar !== 0) {
            return ['available' => false];
        }

        // Exécuter le scan
        $cmd = "clamscan --quiet " . escapeshellarg($filePath) . " 2>&1";
        exec($cmd, $output, $returnVar);

        // $returnVar = 0 : fichier propre
        // $returnVar = 1 : virus trouvé
        // $returnVar = 2 : erreur
        
        if ($returnVar === 1) {
            throw new Exception("⚠️ VIRUS DÉTECTÉ par ClamAV : " . implode(' ', $output));
        }

        if ($returnVar === 2) {
            error_log("Erreur ClamAV lors du scan de " . basename($filePath));
        }

        return ['available' => true, 'clean' => $returnVar === 0];
    }

    /**
     * Supprime les données EXIF des images
     * @param string $filePath Chemin du fichier
     * @param string $fileType Type MIME
     * @return bool
     */
    public static function removeExifData($filePath, $fileType) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'jpg' || $ext === 'jpeg') {
            $image = @imagecreatefromjpeg($filePath);
            if ($image) {
                imagejpeg($image, $filePath, 90);
                imagedestroy($image);
                return true;
            }
        } elseif ($ext === 'png') {
            $image = @imagecreatefrompng($filePath);
            if ($image) {
                imagepng($image, $filePath);
                imagedestroy($image);
                return true;
            }
        } elseif ($ext === 'webp') {
            $image = @imagecreatefromwebp($filePath);
            if ($image) {
                imagewebp($image, $filePath, 80);
                imagedestroy($image);
                return true;
            }
        }

        return false;
    }

    /**
     * Valide l'intégrité des fichiers avec hash SHA256
     * @param string $fileContent Contenu du fichier
     * @param string $expectedHash Hash attendu (optionnel)
     * @return string Le hash SHA256
     * @throws Exception
     */
    public static function validateFileIntegrity($fileContent, $expectedHash = null) {
        $hash = hash('sha256', $fileContent);

        if ($expectedHash && $hash !== $expectedHash) {
            throw new Exception("Intégrité du fichier compromise (hash mismatch)");
        }

        return $hash;
    }

    /**
     * Exécute la validation complète d'un fichier
     * @param string $filePath Chemin du fichier temporaire
     * @param string $fileType Type MIME
     * @param string $originalName Nom original du fichier
     * @return array Résultats de la validation
     * @throws Exception
     */
    public static function performFullValidation($filePath, $fileType, $originalName) {
        $results = [
            'magic_bytes' => false,
            'malware_scan' => false,
            'clamav_scan' => null,
            'exif_removed' => false,
            'hash' => null
        ];

        try {
            // 1. Valider les magic bytes
            self::validateMagicBytes($filePath, $fileType);
            $results['magic_bytes'] = true;

            // 2. Scan anti-malware basique
            self::scanForMalware($filePath, $fileType);
            $results['malware_scan'] = true;

            // 3. Scan ClamAV si disponible
            $clamResult = self::scanWithClamAV($filePath);
            $results['clamav_scan'] = $clamResult;

            // 4. Supprimer les données EXIF pour les images
            if (strpos($fileType, 'image') !== false) {
                self::removeExifData($filePath, $fileType);
                $results['exif_removed'] = true;
            }

            // 5. Calculer le hash
            $results['hash'] = hash_file('sha256', $filePath);

            return $results;

        } catch (Exception $e) {
            error_log("Validation échouée pour $originalName : " . $e->getMessage());
            throw $e;
        }
    }
}