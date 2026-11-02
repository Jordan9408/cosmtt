<?php
/**
 * =============================================================================
 * ARTICLE CONTROLLER - GESTION DES REQUÊTES ARTICLES
 * =============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/article/ArticleManager.php';
require_once dirname(__DIR__, 1) . '/security/FileSecurityValidator.php';
require_once dirname(__DIR__, 1) . '/security/PDFSecurityProcessor.php';
require_once dirname(__DIR__, 2) . '/classPages/club/Validations.php';
require_once dirname(__DIR__, 3) . '/includes/ErrorMessages.php';

use Admin\Includes\ErrorMessages;

class ArticleController
{
    private ArticleManager $articleManager;

    private const array ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png'];
    private const array ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    public function __construct(
        private readonly PDO $conn, 
        private readonly string $csrfToken
    ) {
        $this->articleManager = new ArticleManager($this->conn);
    }

    public function getArticleManager(): ArticleManager
    {
        return $this->articleManager;
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
            return;
        }

        $action = $_POST['action'];
        $token  = $_POST['csrf_token'] ?? '';
        
        if (!$this->verifyCsrfToken($token)) {
            $this->sendJsonResponse(['success' => false, 'message' => ErrorMessages::get('CSRF_INVALID')], 403);
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

    public function handleFormSubmission(): array
    {
        $result = [
            'success'  => false,
            'message'  => '',
            'redirect' => true
        ];

        try {
            $formToken = $_POST['csrf_token'] ?? '';
            if (!$this->verifyCsrfToken($formToken)) {
                $result['message'] = ErrorMessages::get('CSRF_INVALID');
                return $result;
            }

            if (!extension_loaded('gd') || !function_exists('imagewebp')) {
                $result['message'] = ErrorMessages::get('GD_SUPPORT_MISSING');
                return $result;
            }

            $uploadedFiles = [];
            $mode          = $_POST['article_mode'] ?? 'add';
            $articleId     = (int)($_POST['article_id'] ?? 0);
            $title         = isset($_POST['title_article']) ? trim($_POST['title_article']) : '';
            $dateEvmt      = isset($_POST['date_evmt_article']) ? trim($_POST['date_evmt_article']) : '';
            $contenu       = $_POST['contenu_article'] ?? '';

            if (empty($title) || empty($dateEvmt)) {
                throw new Exception(ErrorMessages::get('ARTICLE_TITLE_DATE_REQ'));
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEvmt)) {
                throw new Exception(ErrorMessages::get('ARTICLE_DATE_INVALID'));
            }

            if (mb_strlen($title) > 150) {
                throw new Exception(ErrorMessages::get('ARTICLE_TITLE_TOO_LONG'));
            }
            $title = strip_tags($title);
            if (!preg_match('/^[\p{L}0-9\s\-_,\.\'!\?\(\)]+$/u', $title)) {
                throw new Exception(ErrorMessages::get('ARTICLE_TITLE_INVALID_CHARS'));
            }
            $checkTitre = \Admin\ClassPages\Club\Validations::validateCleanContent($title, "Titre de l'article");
            if (!$checkTitre['valid']) {
                throw new Exception($checkTitre['error']);
            }

            $excludeId = ($mode === 'edit' && $articleId > 0) ? $articleId : null;
            $checkResult = $this->articleManager->checkArticleExists($title, $dateEvmt, $excludeId);

            if ($checkResult['same_title_and_date'] || $checkResult['same_title_same_season']) {
                throw new Exception("AJOUT BLOQUÉ : " . $checkResult['conflicts'][0]['message']);
            }
            if ($checkResult['same_date']) {
                $msg = ErrorMessages::get('GENERIC_ERROR');
                foreach ($checkResult['conflicts'] as $conflict) {
                    if ($conflict['type'] === 'same_date') {
                        $msg = $conflict['message'];
                        break;
                    }
                }
                throw new Exception("AJOUT BLOQUÉ : " . $msg);
            }

            $cleanContent = $this->sanitizeContent($contenu);
            $imageFilename = '';
            
            if (isset($_FILES['image_article']) && $_FILES['image_article']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['image_article']['error'] !== UPLOAD_ERR_OK) {
                    $uploadError = $_FILES['image_article']['error'];
                    throw new Exception("Erreur technique d'upload de l'image (Code $uploadError). Vérifiez les limites d'upload de votre serveur.");
                }

                $imageFilename = $this->processArticleImage($_FILES['image_article']);
                $uploadedFiles[] = $this->articleManager->getImageDir() . $imageFilename;
            }

            $pdfFilename = null;
            $pdfDeleteFlag = (int)($_POST['pdf_delete_flag'] ?? 0);
            
            if (isset($_FILES['pdf_article']) && $_FILES['pdf_article']['error'] === UPLOAD_ERR_OK) {
                $pdfFilename = $this->processArticlePdf($_FILES['pdf_article']);
                $uploadedFiles[] = $this->articleManager->getPdfDir() . $pdfFilename;
            }

            if ($mode === 'edit' && $articleId > 0) {
                if (!$this->articleManager->articleExists($articleId)) {
                    throw new Exception(ErrorMessages::get('ARTICLE_NOT_FOUND'));
                }

                $updateData = [
                    'title_article'     => $title,
                    'date_evmt_article' => $dateEvmt,
                    'contenu_article'   => $cleanContent
                ];

                $oldArticle = $this->articleManager->getArticleById($articleId);

                if (!empty($imageFilename)) {
                    $updateData['img_article'] = $imageFilename;
                }

                if ($pdfFilename !== null) {
                    if ($oldArticle && !empty($oldArticle['pdf_article'])) {
                        $oldPdf = $this->articleManager->getPdfDir() . $oldArticle['pdf_article'];
                        if (file_exists($oldPdf) && is_file($oldPdf)) {
                            @unlink($oldPdf);
                        }
                    }
                    $updateData['pdf_article'] = $pdfFilename;
                } elseif ($pdfDeleteFlag === 1 && $oldArticle && !empty($oldArticle['pdf_article'])) {
                    $oldPdf = $this->articleManager->getPdfDir() . $oldArticle['pdf_article'];
                    if (file_exists($oldPdf) && is_file($oldPdf)) {
                        @unlink($oldPdf);
                    }
                    $updateData['pdf_article'] = null;
                }

                $this->articleManager->updateArticle($articleId, $updateData);
                $result['success'] = true;

            } else {
                $articleData = [
                    'title_article'     => $title,
                    'date_evmt_article' => $dateEvmt,
                    'img_article'       => $imageFilename,
                    'contenu_article'   => $cleanContent,
                    'pdf_article'       => $pdfFilename
                ];

                $this->articleManager->createArticle($articleData);
                $result['success'] = true;
            }

        } catch (Throwable $e) {
            foreach ($uploadedFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
            $result['message'] = "Erreur : " . $e->getMessage();
            error_log('Article submission error: ' . $e->getMessage());
        }

        return $result;
    }

    private function processArticleImage(array $file): string
    {
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '256M');

        try {
            if ($file['size'] > ArticleManager::MAX_IMAGE_SIZE) {
                throw new Exception(ErrorMessages::get('IMAGE_SIZE_EXCEEDED'));
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
                throw new Exception(ErrorMessages::get('IMAGE_FORMAT_DENIED'));
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES, true)) {
                throw new Exception(ErrorMessages::get('IMAGE_MIME_DENIED'));
            }

            FileSecurityValidator::validateMagicBytes($file['tmp_name'], $mimeType);
            FileSecurityValidator::scanForMalware($file['tmp_name'], $mimeType);

            $clamResult = FileSecurityValidator::scanWithClamAV($file['tmp_name']);
            if ($clamResult['available'] && !$clamResult['clean']) {
                throw new Exception(ErrorMessages::get('IMAGE_MALWARE'));
            }

            $source = @imagecreatefromstring(file_get_contents($file['tmp_name']));
            if ($source === false) {
                throw new Exception(ErrorMessages::get('IMAGE_CORRUPT'));
            }

            $source = $this->applyExifOrientation($file['tmp_name'], $source);

            if (imageistruecolor($source) === false) {
                imagepalettetotruecolor($source);
            }
            imagealphablending($source, true);
            imagesavealpha($source, true);

            $webpFilename = 'TEMP_IMG_' . uniqid() . '.webp';
            $destPath = $this->articleManager->getImageDir() . $webpFilename;

            if (!imagewebp($source, $destPath, 85)) {
                throw new Exception(ErrorMessages::get('IMAGE_CONV_ERROR'));
            }

            if (!file_exists($destPath)) {
                throw new Exception(ErrorMessages::get('IMAGE_WRITE_ERROR'));
            }

            imagedestroy($source);
            $source = null;

            FileSecurityValidator::removeExifData($destPath, 'image/webp');
            return $webpFilename;
        } finally {
            if (isset($source) && $source instanceof \GdImage) {
                imagedestroy($source);
            }
            ini_set('memory_limit', $previousLimit);
        }
    }

    private function applyExifOrientation(string $filePath, \GdImage $image): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($filePath);
        if ($exif === false || !isset($exif['Orientation'])) {
            return $image;
        }

        return match ((int)$exif['Orientation']) {
            2 => imageflip($image, IMG_FLIP_HORIZONTAL) ? $image : $image,
            3 => imagerotate($image, 180, 0) ?: $image,
            4 => imageflip($image, IMG_FLIP_VERTICAL) ? $image : $image,
            5 => imageflip($image = imagerotate($image, 90, 0) ?: $image, IMG_FLIP_HORIZONTAL) ? $image : $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            7 => imageflip($image = imagerotate($image, -90, 0) ?: $image, IMG_FLIP_HORIZONTAL) ? $image : $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    private function processArticlePdf(array $file): string
    {
        if ($file['size'] > ArticleManager::MAX_PDF_SIZE) {
            throw new Exception(ErrorMessages::get('PDF_SIZE_EXCEEDED'));
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception(ErrorMessages::get('PDF_FORMAT_DENIED'));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($mimeType !== 'application/pdf') {
            throw new Exception(ErrorMessages::get('PDF_MIME_DENIED'));
        }

        FileSecurityValidator::validateMagicBytes($file['tmp_name'], 'application/pdf');
        FileSecurityValidator::scanForMalware($file['tmp_name'], $mimeType);

        $clamResult = FileSecurityValidator::scanWithClamAV($file['tmp_name']);
        if ($clamResult['available'] && !$clamResult['clean']) {
            throw new Exception(ErrorMessages::get('PDF_MALWARE'));
        }

        $tempFilename = 'TEMP_PDF_' . uniqid() . '.pdf';
        $tempDestPath = $this->articleManager->getPdfDir() . $tempFilename;

        $processing = PDFSecurityProcessor::processPDF($file['tmp_name'], $tempDestPath);
        if (!$processing['success']) {
            throw new Exception(
                ErrorMessages::get('PDF_SAVE_ERROR') . ' ' . ($processing['error'] ?? '')
            );
        }

        error_log(PDFSecurityProcessor::getProcessingDescription($processing));

        return $tempFilename;
    }

    private function sanitizeContent(string $content): string
    {
        $content = trim($content);
        if (empty($content)) {
            throw new Exception(ErrorMessages::get('CONTENT_REQUIRED'));
        }

        $validation = \Admin\ClassPages\Club\Validations::validateCleanContent($content, "Contenu de l'article");
        if (!$validation['valid']) {
            throw new Exception($validation['error']);
        }

        $stripped = strip_tags($content);
        if ($content !== $stripped) {
            throw new Exception(ErrorMessages::get('CONTENT_HTML_DENIED'));
        }

        if (strlen($stripped) > 100000) {
            throw new Exception(ErrorMessages::get('CONTENT_SIZE_EXCEEDED'));
        }

        if (preg_match('/(.{3})\1{50,}/', $stripped)) {
            throw new Exception(ErrorMessages::get('CONTENT_SUSPICIOUS'));
        }

        return $stripped;
    }

    private function handleDeleteArticle(): void
    {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->sendJsonResponse(['success' => false, 'message' => ErrorMessages::get('ID_INVALID')]);
            return;
        }

        try {
            $this->sendJsonResponse($this->articleManager->deleteArticle($id));
        } catch (Exception) {
            $this->sendJsonResponse(['success' => false, 'message' => ErrorMessages::get('GENERIC_ERROR')]);
        }
    }

    private function handleGetArticle(): void
    {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->sendJsonResponse(['success' => false, 'message' => ErrorMessages::get('ID_INVALID')]);
            return;
        }

        try {
            $article = $this->articleManager->getArticleById($id);
            if (!$article) {
                $this->sendJsonResponse(['success' => false, 'message' => ErrorMessages::get('ARTICLE_NOT_FOUND')]);
                return;
            }
            $this->sendJsonResponse(['success' => true, 'article' => $article]);
        } catch (Exception) {
            $this->sendJsonResponse(['success' => false, 'message' => ErrorMessages::get('GENERIC_ERROR')]);
        }
    }

    private function handleCheckArticle(): void
    {
        $title     = trim((string)($_POST['title'] ?? ''));
        $date      = trim((string)($_POST['date'] ?? ''));
        $excludeId = isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : null;

        if (empty($title) || empty($date)) {
            $this->sendJsonResponse(['error' => 'Paramètres manquants.']);
            return;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->sendJsonResponse(['error' => 'Format de date invalide.']);
            return;
        }

        $this->sendJsonResponse($this->articleManager->checkArticleExists($title, $date, $excludeId));
    }

    private function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    private function sendJsonResponse(array $data, int $statusCode = 200): void
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