<?php
/**
 * =============================================================================
 * ARTICLE CONTROLLER - GESTION DES REQUÊTES ARTICLES
 * =============================================================================
 * 
 * Contrôleur pour gérer les requêtes AJAX et le traitement des formulaires
 * de gestion des articles.
 * 
 * SÉCURITÉ :
 * - Validation CSRF pour toutes les actions
 * - Image : validation MIME + magic bytes + scan malware + conversion WebP
 * - PDF : validation MIME + magic bytes + scan malware + compression Ghostscript
 * - Contenu : strip_tags + détection code malveillant
 * - Titre : vérification doublon dans la même saison
 * 
 * @version 1.0
 * =============================================================================
 */

require_once dirname(__DIR__, 1) . '/article/ArticleManager.php';
require_once dirname(__DIR__, 1) . '/security/FileSecurityValidator.php';

class ArticleController
{
    private PDO $conn;
    private ArticleManager $articleManager;
    private string $csrfToken;

    /** Types d'images autorisés */
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png'];
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    public function __construct(PDO $connection, string $csrfToken)
    {
        $this->conn = $connection;
        $this->csrfToken = $csrfToken;
        $this->articleManager = new ArticleManager($connection);
    }

    /**
     * Getter pour le manager
     */
    public function getArticleManager(): ArticleManager
    {
        return $this->articleManager;
    }

    // =========================================================================
    // POINT D'ENTRÉE PRINCIPAL
    // =========================================================================

    /**
     * Traite les requêtes AJAX
     */
    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
            return;
        }

        $action = $_POST['action'];

        // Vérifier le token CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->verifyCsrfToken($token)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Token CSRF invalide.'], 403);
            return;
        }

        switch ($action) {
            case 'delete_article':
                $this->handleDeleteArticle();
                break;
            case 'get_article':
                $this->handleGetArticle();
                break;
            case 'check_article':
                $this->handleCheckArticle();
                break;
        }
    }

    // =========================================================================
    // TRAITEMENT DU FORMULAIRE PRINCIPAL
    // =========================================================================

    /**
     * Traite le formulaire d'ajout / modification d'article
     */
    public function handleFormSubmission(): array
    {
        $result = [
            'success'  => false,
            'message'  => '',
            'redirect' => true
        ];

        // 1. Vérifier CSRF
        $formToken = $_POST['csrf_token'] ?? '';
        if (!$this->verifyCsrfToken($formToken)) {
            $result['message'] = "❌ Erreur de sécurité : Token CSRF invalide.";
            return $result;
        }

        // 2. Vérifier GD pour les images
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $result['message'] = "❌ Erreur serveur : La librairie GD avec support WebP est requise.";
            return $result;
        }

        try {
            // Récupérer les données du formulaire
            $mode       = $_POST['article_mode'] ?? 'add';
            $articleId  = (int)($_POST['article_id'] ?? 0);
            $title      = isset($_POST['title_article']) ? trim($_POST['title_article']) : '';
            $dateEvmt   = isset($_POST['date_evmt_article']) ? trim($_POST['date_evmt_article']) : '';
            $contenu    = isset($_POST['contenu_article']) ? $_POST['contenu_article'] : '';

            // --- Validation de base ---
            if (empty($title) || empty($dateEvmt)) {
                throw new Exception("Le titre et la date sont obligatoires.");
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEvmt)) {
                throw new Exception("Le format de la date est invalide.");
            }

            // --- Validation du titre (doublon dans la même saison) ---
            $excludeId = ($mode === 'edit' && $articleId > 0) ? $articleId : null;
            $checkResult = $this->articleManager->checkArticleExists($title, $dateEvmt, $excludeId);

            if ($checkResult['same_title_and_date']) {
                throw new Exception("❌ AJOUT BLOQUÉ : " . $checkResult['conflicts'][0]['message']);
            }
            if ($checkResult['same_title_same_season']) {
                throw new Exception("❌ AJOUT BLOQUÉ : " . $checkResult['conflicts'][0]['message']);
            }
            if ($checkResult['same_date']) {
                // On cherche le conflit de type 'same_date'
                $msg = "Un article est déjà programmé pour cette date.";
                foreach ($checkResult['conflicts'] as $conflict) {
                    if ($conflict['type'] === 'same_date') {
                        $msg = $conflict['message'];
                        break;
                    }
                }
                throw new Exception("❌ AJOUT BLOQUÉ : " . $msg);
            }

            // --- Validation et nettoyage du contenu ---
            $cleanContent = $this->sanitizeContent($contenu);

            // --- Traitement de l'image ---
            $imageFilename = '';
            if (isset($_FILES['image_article']) && $_FILES['image_article']['error'] === UPLOAD_ERR_OK) {
                $imageFilename = $this->processArticleImage($_FILES['image_article']);
            }

            // --- Traitement du PDF (optionnel) ---
            $pdfFilename = null;
            $pdfDeleteFlag = (int)($_POST['pdf_delete_flag'] ?? 0); // Récupérer le flag de suppression
            
            if (isset($_FILES['pdf_article']) && $_FILES['pdf_article']['error'] === UPLOAD_ERR_OK) {
                $pdfFilename = $this->processArticlePdf($_FILES['pdf_article'], $title, $dateEvmt);
            }

            // --- Insertion / Mise à jour en BDD ---

            if ($mode === 'edit' && $articleId > 0) {
                // Mode édition
                if (!$this->articleManager->articleExists($articleId)) {
                    throw new Exception("Article introuvable.");
                }

                $updateData = [
                    'title_article'      => $title,
                    'date_evmt_article'  => $dateEvmt,
                    'contenu_article'    => $cleanContent
                ];

                // Nouvelle image ?
                if (!empty($imageFilename)) {
                    // Supprimer l'ancienne image
                    $oldArticle = $this->articleManager->getArticleById($articleId);
                    if ($oldArticle && !empty($oldArticle['img_article'])) {
                        $oldImg = $this->articleManager->getImageDir() . $oldArticle['img_article'];
                        if (file_exists($oldImg) && is_file($oldImg)) {
                            unlink($oldImg);
                        }
                    }
                    $updateData['img_article'] = $imageFilename;
                }

                // Nouveau PDF ?
                if ($pdfFilename !== null) {
                    // Supprimer l'ancien PDF
                    $oldArticle = $oldArticle ?? $this->articleManager->getArticleById($articleId);
                    if ($oldArticle && !empty($oldArticle['pdf_article'])) {
                        $oldPdf = $this->articleManager->getPdfDir() . $oldArticle['pdf_article'];
                        if (file_exists($oldPdf) && is_file($oldPdf)) {
                            unlink($oldPdf);
                        }
                    }
                    $updateData['pdf_article'] = $pdfFilename;
                }
                // Suppression du PDF existant (flag à 1 et pas de nouveau PDF) ?
                elseif ($pdfDeleteFlag === 1) {
                    $oldArticle = $oldArticle ?? $this->articleManager->getArticleById($articleId);
                    if ($oldArticle && !empty($oldArticle['pdf_article'])) {
                        $oldPdf = $this->articleManager->getPdfDir() . $oldArticle['pdf_article'];
                        if (file_exists($oldPdf) && is_file($oldPdf)) {
                            unlink($oldPdf);
                        }
                        $updateData['pdf_article'] = null; // Supprimer la référence en BDD
                    }
                }

                $this->articleManager->updateArticle($articleId, $updateData);

                $result['success'] = true;
                $result['message'] = "";

            } else {
                // Mode ajout
                $articleData = [
                    'title_article'      => $title,
                    'date_evmt_article'  => $dateEvmt,
                    'img_article'        => $imageFilename,
                    'contenu_article'    => $cleanContent,
                    'pdf_article'        => $pdfFilename
                ];

                $newId = $this->articleManager->createArticle($articleData);
                $result['success'] = true;
                $result['message'] = "";
            }

        } catch (Exception $e) {
            $result['message'] = "Erreur : " . $e->getMessage();
        }

        return $result;
    }

    // =========================================================================
    // TRAITEMENT DES IMAGES
    // =========================================================================

    /**
     * Traite l'image uploadée : validation, scan sécurité, conversion WebP
     *
     * @param array $file Données $_FILES
     * @return string Nom du fichier WebP créé
     * @throws Exception
     */
    private function processArticleImage(array $file): string
    {
        // 1. Vérifier la taille
        if ($file['size'] > ArticleManager::MAX_IMAGE_SIZE) {
            throw new Exception("L'image est trop volumineuse (max " . (ArticleManager::MAX_IMAGE_SIZE / 1024 / 1024) . " Mo).");
        }

        // 2. Vérifier l'extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
            throw new Exception("Format d'image non autorisé. Formats acceptés : JPG, JPEG, PNG.");
        }

        // 3. Vérifier le type MIME côté serveur
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES, true)) {
            throw new Exception("Type MIME de l'image non autorisé : " . htmlspecialchars($mimeType));
        }

        // 4. Valider les magic bytes
        FileSecurityValidator::validateMagicBytes($file['tmp_name'], $mimeType);

        // 5. Scanner pour malware
        FileSecurityValidator::scanForMalware($file['tmp_name'], $mimeType);

        // 6. Scanner avec ClamAV si disponible
        $clamResult = FileSecurityValidator::scanWithClamAV($file['tmp_name']);
        if ($clamResult['available'] && !$clamResult['clean']) {
            throw new Exception("⚠️ Menace détectée par l'antivirus serveur dans l'image.");
        }

        // 7. Charger l'image et appliquer l'orientation EXIF
        $source = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if ($source === false) {
            throw new Exception("Format d'image non reconnu ou fichier corrompu.");
        }

        // Appliquer l'orientation EXIF si présente
        $source = $this->applyExifOrientation($file['tmp_name'], $source);

        // Truecolor + alpha
        if (imageistruecolor($source) === false) {
            imagepalettetotruecolor($source);
        }
        imagealphablending($source, true);
        imagesavealpha($source, true);

        // Générer un nom de fichier temporaire
        // Le nom final sera généré par le Manager lors de l'insertion/mise à jour en BDD
        $webpFilename = 'TEMP_IMG_' . uniqid() . '.webp';
        $destPath = $this->articleManager->getImageDir() . $webpFilename;

        if (!imagewebp($source, $destPath, 85)) {
            imagedestroy($source);
            throw new Exception("Erreur lors de la conversion de l'image en WebP.");
        }

        imagedestroy($source);

        // Vérifier que le fichier a bien été créé
        if (!file_exists($destPath)) {
            throw new Exception("Erreur lors de l'écriture du fichier image.");
        }

        // Supprimer les EXIF après conversion (pour la sécurité)
        FileSecurityValidator::removeExifData($destPath, 'image/webp');

        return $webpFilename;
    }

    // =========================================================================
    // ORIENTATION EXIF
    // =========================================================================

    /**
     * Applique la rotation EXIF à l'image GD
     * 
     * @param string $filePath Chemin du fichier original
     * @param resource $image Resource GD
     * @return resource Image GD avec orientation appliquée
     */
    private function applyExifOrientation($filePath, $image) {
        // Vérifier que exif_read_data est disponible
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($filePath);
            if ($exif === false || !isset($exif['Orientation'])) {
                return $image;
            }

            $orientation = (int)$exif['Orientation'];

            // Appliquer la rotation appropriée selon l'orientation EXIF
            // https://en.wikipedia.org/wiki/Exif#Orientation
            switch ($orientation) {
                case 2: // Horizontal flip
                    imageflip($image, IMG_FLIP_HORIZONTAL);
                    break;

                case 3: // Rotate 180
                    $image = imagerotate($image, 180, 0);
                    break;

                case 4: // Vertical flip
                    imageflip($image, IMG_FLIP_VERTICAL);
                    break;

                case 5: // Rotate 90 CCW + Horizontal flip
                    $image = imagerotate($image, 90, 0);
                    imageflip($image, IMG_FLIP_HORIZONTAL);
                    break;

                case 6: // Rotate 90 CW (portrait pris en landscape)
                    $image = imagerotate($image, -90, 0);
                    break;

                case 7: // Rotate 90 CW + Horizontal flip
                    $image = imagerotate($image, -90, 0);
                    imageflip($image, IMG_FLIP_HORIZONTAL);
                    break;

                case 8: // Rotate 270 CW
                    $image = imagerotate($image, 90, 0);
                    break;
            }

            return $image;
        } catch (Exception $e) {
            // En cas d'erreur EXIF, retourner l'image inchangée
            return $image;
        }
    }

    // =========================================================================
    // TRAITEMENT DES PDF
    // =========================================================================

    /**
     * Traite le PDF uploadé : validation, scan sécurité, compression
     *
     * @param array $file Données $_FILES
     * @return string Nom du fichier PDF traité
     * @throws Exception
     */
    private function processArticlePdf(array $file, string $title, string $dateEvmt): string
    {
        // 1. Vérifier la taille
        if ($file['size'] > ArticleManager::MAX_PDF_SIZE) {
            throw new Exception("Le PDF est trop volumineux (max " . (ArticleManager::MAX_PDF_SIZE / 1024 / 1024) . " Mo).");
        }

        // 2. Vérifier l'extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception("Le document doit être au format PDF.");
        }

        // 3. Vérifier le type MIME côté serveur
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($mimeType !== 'application/pdf') {
            throw new Exception("Type MIME du PDF non valide : " . htmlspecialchars($mimeType));
        }

        // 4. Valider les magic bytes (%PDF)
        FileSecurityValidator::validateMagicBytes($file['tmp_name'], 'application/pdf');

        // 5. Scanner pour malware
        FileSecurityValidator::scanForMalware($file['tmp_name'], $mimeType);

        // 6. Scanner avec ClamAV si disponible
        $clamResult = FileSecurityValidator::scanWithClamAV($file['tmp_name']);
        if ($clamResult['available'] && !$clamResult['clean']) {
            throw new Exception("⚠️ Menace détectée par l'antivirus serveur dans le PDF.");
        }

        // 7. Générer un nom de fichier temporaire sécurisé pour l'upload
        $tempFilename = 'TEMP_PDF_' . uniqid() . '.pdf';
        $tempDestPath = $this->articleManager->getPdfDir() . $tempFilename;

        // 8. Copier le fichier uploadé vers la destination temporaire
        if (!move_uploaded_file($file['tmp_name'], $tempDestPath)) {
            throw new Exception("Erreur lors de l'enregistrement du PDF temporaire.");
        }

        return $tempFilename;
    }

    /**
     * Compresse un PDF avec Ghostscript
     *
     * @param string $sourcePath Chemin du PDF source
     * @param string $destPath Chemin du PDF compressé
     * @return bool true si la compression a réussi, false sinon
     */
    private function compressPdfWithGhostscript(string $sourcePath, string $destPath): bool
    {
        // Vérifier que exec() est disponible
        if (!function_exists('exec')) {
            return false;
        }

        // Déterminer la commande Ghostscript selon la plateforme
        $gsCmd = (PHP_OS_FAMILY === 'Windows') ? 'gswin64c' : 'gs';

        // Commande de compression Ghostscript
        $cmd = sprintf(
            '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
            escapeshellcmd($gsCmd),
            escapeshellarg($destPath),
            escapeshellarg($sourcePath)
        );

        // Exécuter la compression
        exec($cmd, $output, $returnCode);

        // Vérifier le succès
        return ($returnCode === 0 && file_exists($destPath) && filesize($destPath) > 0);
    }

    // =========================================================================
    // VALIDATION DU CONTENU
    // =========================================================================

    /**
     * Nettoie et valide le contenu de l'article
     * Seuls le texte, les nombres et la ponctuation sont autorisés.
     * Aucun code HTML, JavaScript ou balisage n'est permis.
     *
     * @param string $content Contenu brut
     * @return string Contenu nettoyé
     * @throws Exception Si du code malveillant est détecté
     */
    private function sanitizeContent(string $content): string
    {
        if (empty(trim($content))) {
            throw new Exception("Le contenu de l'article est obligatoire.");
        }

        // =========================================================================
        // ÉTAPE 1 : Détection des patterns malveillants
        // =========================================================================
        
        $dangerousPatterns = [
            // Code exécutable
            '/<\s*script/i'               => 'Balise script détectée',
            '/<\s*iframe/i'               => 'Balise iframe détectée',  
            '/<\s*object/i'               => 'Balise object détectée',
            '/<\s*embed/i'                => 'Balise embed détectée',
            '/<\s*applet/i'               => 'Balise applet détectée',
            '/<\s*link/i'                 => 'Balise link détectée',
            '/<\s*meta/i'                 => 'Balise meta détectée',
            '/<\s*form/i'                 => 'Balise form détectée',
            '/<\s*input/i'                => 'Balise input détectée',
            '/<\s*button/i'               => 'Balise button détectée',
            '/<\s*textarea/i'             => 'Balise textarea détectée',
            '/<\s*select/i'               => 'Balise select détectée',
            
            // Protocoles dangereux
            '/javascript\s*:/i'           => 'Protocole javascript détecté',
            '/data\s*:/i'                 => 'Protocole data détecté',
            '/vbscript\s*:/i'             => 'Protocole vbscript détecté',
            
            // Attributs événements
            '/\bon\w+\s*=/i'              => 'Attribut événement JS détecté',
            '/\bonfocus\s*=/i'            => 'Attribut onfocus détecté',
            '/\bonload\s*=/i'             => 'Attribut onload détecté',
            '/\bonchange\s*=/i'           => 'Attribut onchange détecté',
            '/\bonclick\s*=/i'            => 'Attribut onclick détecté',
            '/\bonmouseover\s*=/i'        => 'Attribut onmouseover détecté',
            
            // Code serveur/système
            '/<\?php/i'                   => 'Code PHP détecté',
            '/<\?=/i'                     => 'Code PHP court détecté',
            '/<\?/i'                      => 'Tag ouverture PHP détecté',
            '/eval\s*\(/i'                => 'Appel eval() détecté',
            '/assert\s*\(/i'              => 'Appel assert() détecté',
            '/exec\s*\(/i'                => 'Appel exec() détecté',
            '/system\s*\(/i'              => 'Appel system() détecté',
            '/passthru\s*\(/i'            => 'Appel passthru() détecté',
            '/shell_exec\s*\(/i'          => 'Appel shell_exec() détecté',
            '/proc_open\s*\(/i'           => 'Appel proc_open() détecté',
            
            // Manipulation DOM/Cookies
            '/document\s*\.\s*cookie/i'   => 'Accès aux cookies détecté',
            '/window\s*\.\s*location/i'   => 'Redirection JS détectée',
            '/document\s*\.\s*write/i'    => 'Modification DOM détectée',
            '/innerHTML|innerText/i'      => 'Modification innerHTML détectée',
            
            // Patterns supplémentaires
            '/<\s*style[^>]*>/i'          => 'Balise style détectée (pas autorisée)',
            '/expression\s*\(/i'          => 'Expression CSS IE détectée',
            '/@import/i'                  => 'Directive @import détectée',
            '/<\s*base/i'                 => 'Balise base détectée',
            '/<!--.*-->/s'                => 'Commentaires HTML détectés',
            '/__proto__|constructor/i'    => 'Manipulation prototype détectée',
        ];

        foreach ($dangerousPatterns as $pattern => $description) {
            if (preg_match($pattern, $content)) {
                error_log("Contenu malveillant détecté: $description - Pattern: $pattern");
                throw new Exception("Contenu interdit : $description. Seuls le texte et les nombres sont autorisés.");
            }
        }

        // =========================================================================
        // ÉTAPE 2 : Vérifier la présence de balises HTML quelconques
        // =========================================================================
        
        if ($content !== strip_tags($content)) {
            throw new Exception("Le contenu ne doit contenir que du texte et des nombres. Les balises HTML ne sont pas autorisées.");
        }

        // =========================================================================
        // ÉTAPE 3 : Nettoyer le contenu
        // =========================================================================
        
        $clean = strip_tags($content);  // Supprimer toutes les balises
        $clean = htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');  // Échapper les caractères spéciaux
        $clean = html_entity_decode($clean, ENT_QUOTES, 'UTF-8');  // Décoder pour le stockage
        $clean = trim($clean);  // Supprimer les espaces inutiles
        
        // Vérifier que le contenu n'a pas disparu après nettoyage
        if (empty($clean)) {
            throw new Exception("Le contenu de l'article est vide après nettoyage.");
        }

        // =========================================================================
        // ÉTAPE 4 : Vérifications supplémentaires
        // =========================================================================
        
        // Vérifier la longueur raisonnable
        if (strlen($clean) > 100000) {
            throw new Exception("Le contenu est trop volumineux (max 100 000 caractères).");
        }

        // Vérifier qu'il n'y a pas de chaînes de caractères répétées suspectes
        if (preg_match('/(.{3})\1{50,}/', $clean)) {
            throw new Exception("Contenu suspect détecté (caractères répétés anormalement).");
        }

        // =========================================================================
        // ÉTAPE 5 : Retourner le contenu nettoyé
        // =========================================================================
        
        error_log("Contenu article nettoyé avec succès (" . strlen($clean) . " caractères)");
        return $clean;
    }

    // =========================================================================
    // HANDLERS AJAX
    // =========================================================================

    /**
     * Supprime un article via AJAX
     */
    private function handleDeleteArticle(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $this->sendJsonResponse(['success' => false, 'message' => 'ID invalide.']);
            return;
        }

        try {
            $result = $this->articleManager->deleteArticle($id);
            $this->sendJsonResponse($result);
        } catch (Exception $e) {
            error_log("Erreur delete_article: " . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la suppression.']);
        }
    }

    /**
     * Récupère un article pour édition via AJAX
     */
    private function handleGetArticle(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $this->sendJsonResponse(['success' => false, 'message' => 'ID invalide.']);
            return;
        }

        try {
            $article = $this->articleManager->getArticleById($id);
            if (!$article) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Article introuvable.']);
                return;
            }
            $this->sendJsonResponse(['success' => true, 'article' => $article]);
        } catch (Exception $e) {
            error_log("Erreur get_article: " . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la récupération.']);
        }
    }

    /**
     * Vérifie l'existence d'un article (titre + date)
     */
    private function handleCheckArticle(): void
    {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $date  = isset($_POST['date'])  ? trim($_POST['date'])  : '';
        $excludeId = isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : null;

        if (empty($title) || empty($date)) {
            $this->sendJsonResponse(['error' => 'Paramètres manquants.']);
            return;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->sendJsonResponse(['error' => 'Format de date invalide.']);
            return;
        }

        $result = $this->articleManager->checkArticleExists($title, $date, $excludeId);
        $this->sendJsonResponse($result);
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
     * Envoie une réponse JSON et arrête l'exécution
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
}
