<?php
/**
 * =============================================================================
 * ARTICLE MANAGER - GESTION DES ARTICLES (CRUD + PAGINATION)
 * =============================================================================
 */

declare(strict_types=1);

class ArticleManager
{
    private string $imageDir;
    private string $pdfDir;

    public const int ARTICLES_PER_PAGE = 4;
    public const int MAX_IMAGE_SIZE    = 10485760; // 10 Mo
    public const int MAX_PDF_SIZE      = 10485760; // 10 Mo

    public function __construct(private readonly PDO $conn)
    {
        $this->imageDir = dirname(__DIR__, 4) . '/docs/articles/articlesPhotos/';
        $this->pdfDir   = dirname(__DIR__, 4) . '/docs/articles/resultats/';
        $this->createDirectories();
    }

    private function createDirectories(): void
    {
        if (!is_dir($this->imageDir)) {
            mkdir($this->imageDir, 0755, true);
        }
        if (!is_dir($this->pdfDir)) {
            mkdir($this->pdfDir, 0755, true);
        }
    }

    public function getImageDir(): string
    {
        return $this->imageDir;
    }

    public function getPdfDir(): string
    {
        return $this->pdfDir;
    }

    public function getArticlesPaginated(int $page = 1): array
    {
        $perPage     = self::ARTICLES_PER_PAGE;
        $currentPage = max(1, $page);

        try {
            $countStmt  = $this->conn->query("SELECT COUNT(*) FROM articles");
            $totalCount = (int)$countStmt->fetchColumn();
        } catch (PDOException $e) {
            throw new Exception('Erreur lecture articles : ' . $e->getMessage());
        }

        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $offset = ($currentPage - 1) * $perPage;

        $stmt = $this->conn->prepare(
            "SELECT * FROM articles ORDER BY date_evmt_article DESC, id DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($articles as &$art) {
            if (!empty($art['pdf_article'])) {
                $pdfPath = $this->pdfDir . $art['pdf_article'];
                if (!is_file($pdfPath)) {
                    $cleanStmt = $this->conn->prepare("UPDATE articles SET pdf_article = NULL WHERE id = :id");
                    $cleanStmt->execute([':id' => $art['id']]);
                    $art['pdf_article'] = null;
                }
            }
        }
        unset($art);

        return [
            'articles'     => $articles,
            'total'        => $totalCount,
            'current_page' => $currentPage,
            'total_pages'  => $totalPages
        ];
    }

    public function getArticleById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM articles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$article) {
            return null;
        }

        if (!empty($article['pdf_article'])) {
            $pdfPath = $this->pdfDir . $article['pdf_article'];
            if (!is_file($pdfPath)) {
                $cleanStmt = $this->conn->prepare("UPDATE articles SET pdf_article = NULL WHERE id = :id");
                $cleanStmt->execute([':id' => $id]);
                $article['pdf_article'] = null;
            }
        }

        return $article;
    }

    public function articleExists(int $id): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM articles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return (bool)$stmt->fetch();
    }

    public function checkArticleExists(string $title, string $date, ?int $excludeId = null): array
    {
        $result = [
            'exists'                 => false,
            'id'                     => null,
            'same_title_and_date'    => false,
            'same_title_same_season' => false,
            'same_date'              => false,
            'conflicts'              => []
        ];

        $time  = strtotime($date);
        $month = (int)date('n', $time);
        $year  = (int)date('Y', $time);

        $seasonStart     = ($month >= 9) ? $year : $year - 1;
        $seasonEnd       = $seasonStart + 1;
        $seasonStartDate = $seasonStart . '-09-01';
        $seasonEndDate   = $seasonEnd . '-08-31';

        // 1. Même titre ET même date
        $sql = "SELECT id FROM articles WHERE title_article = :title AND date_evmt_article = :date";
        if ($excludeId !== null) $sql .= " AND id != :exclude_id";
        
        $stmt = $this->conn->prepare($sql . " LIMIT 1");
        $params = [':title' => $title, ':date' => $date];
        if ($excludeId !== null) $params[':exclude_id'] = $excludeId;
        $stmt->execute($params);
        
        if ($exact = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result['exists']              = true;
            $result['id']                  = (int)$exact['id'];
            $result['same_title_and_date'] = true;
            $result['conflicts'][]         = ['type' => 'same_title_and_date', 'message' => 'Un article avec ce titre et cette date existe déjà.'];
            return $result;
        }

        // 2. Même titre dans la même saison
        $sql = "SELECT id, date_evmt_article FROM articles WHERE title_article = :title AND date_evmt_article BETWEEN :season_start AND :season_end AND date_evmt_article != :date";
        if ($excludeId !== null) $sql .= " AND id != :exclude_id";
        
        $stmt = $this->conn->prepare($sql . " LIMIT 1");
        $params = [':title' => $title, ':season_start' => $seasonStartDate, ':season_end' => $seasonEndDate, ':date' => $date];
        if ($excludeId !== null) $params[':exclude_id'] = $excludeId;
        $stmt->execute($params);

        if ($season = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result['same_title_same_season'] = true;
            $result['conflicts'][]            = [
                'type'    => 'same_title_same_season',
                'message' => "Un article avec ce titre existe déjà dans la saison {$seasonStart}-{$seasonEnd} (" . $this->formatDateFrench($season['date_evmt_article']) . ")."
            ];
        }

        // 3. Un autre article à cette date
        $sql = "SELECT id, title_article FROM articles WHERE date_evmt_article = :date";
        if ($excludeId !== null) $sql .= " AND id != :exclude_id";
        
        $stmt = $this->conn->prepare($sql . " LIMIT 1");
        $params = [':date' => $date];
        if ($excludeId !== null) $params[':exclude_id'] = $excludeId;
        $stmt->execute($params);

        if ($anyAtDate = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result['same_date'] = true;
            $result['id']        = (int)$anyAtDate['id'];
            $result['conflicts'][] = [
                'type'    => 'same_date',
                'message' => 'Un autre article est déjà programmé pour cette date (Titre : ' . htmlspecialchars($anyAtDate['title_article']) . ').'
            ];
        }

        return $result;
    }

    public function createArticle(array $data): int
    {
        $pdfFilename   = $this->renameUploadedFile($data['pdf_article'] ?? '', $this->pdfDir, $this->generatePdfFilename($data['title_article'], $data['date_evmt_article']));
        $imageFilename = $this->renameUploadedFile($data['img_article'] ?? '', $this->imageDir, $this->generateImageFilename($data['title_article'], $data['date_evmt_article'], true));

        $stmt = $this->conn->prepare("INSERT INTO articles (title_article, date_evmt_article, img_article, contenu_article, pdf_article) VALUES (:title, :date_evmt, :img, :contenu, :pdf)");
        $stmt->execute([
            ':title'     => $data['title_article'],
            ':date_evmt' => $data['date_evmt_article'],
            ':img'       => $imageFilename,
            ':contenu'   => $data['contenu_article'],
            ':pdf'       => $pdfFilename
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function updateArticle(int $id, array $data): bool
    {
        $oldArticle = $this->getArticleById($id);
        if (!$oldArticle) {
            return false;
        }

        $newTitle     = $data['title_article'] ?? $oldArticle['title_article'];
        $newDate      = $data['date_evmt_article'] ?? $oldArticle['date_evmt_article'];
        $titleChanged = isset($data['title_article']) && $data['title_article'] !== $oldArticle['title_article'];
        $dateChanged  = isset($data['date_evmt_article']) && $data['date_evmt_article'] !== $oldArticle['date_evmt_article'];

        if (!empty($data['pdf_article'])) {
            $data['pdf_article'] = $this->renameUploadedFile($data['pdf_article'], $this->pdfDir, $this->generatePdfFilename($newTitle, $newDate));
        } elseif (($titleChanged || $dateChanged) && !empty($oldArticle['pdf_article'])) {
            $data['pdf_article'] = $this->renameExistingFile($oldArticle['pdf_article'], $this->pdfDir, $this->generatePdfFilename($newTitle, $newDate));
        }

        if (!empty($data['img_article'])) {
            $newImageName = $this->renameUploadedFile(
                $data['img_article'],
                $this->imageDir,
                $this->generateImageFilename($newTitle, $newDate, true)
            );
            $oldImageName = (string)($oldArticle['img_article'] ?? '');
            if ($oldImageName !== '' && $oldImageName !== $newImageName) {
                $oldImagePath = $this->imageDir . $oldImageName;
                if (file_exists($oldImagePath) && is_file($oldImagePath)) {
                    @unlink($oldImagePath);
                }
            }
            $data['img_article'] = $newImageName;
        } elseif (($titleChanged || $dateChanged) && !empty($oldArticle['img_article'])) {
            $data['img_article'] = $this->renameExistingFile($oldArticle['img_article'], $this->imageDir, $this->generateImageFilename($newTitle, $newDate));
        }

        $fields = [];
        $params = [':id' => $id];

        $allowedFields = [
            'title_article'     => ':title',
            'date_evmt_article' => ':date_evmt',
            'img_article'       => ':img',
            'contenu_article'   => ':contenu',
            'pdf_article'       => ':pdf'
        ];

        foreach ($allowedFields as $dbField => $paramKey) {
            if (array_key_exists($dbField, $data)) {
                $fields[] = "$dbField = $paramKey";
                $params[$paramKey] = $data[$dbField];
            }
        }

        if (empty($fields)) {
            return false;
        }

        return $this->conn->prepare("UPDATE articles SET " . implode(', ', $fields) . " WHERE id = :id")->execute($params);
    }

    public function deleteArticle(int $id): array
    {
        $result = ['success' => false, 'total_articles' => 0, 'total_pages' => 1];

        try {
            $article = $this->getArticleById($id);
            if (!$article) return $result;

            $this->conn->beginTransaction();

            if (!empty($article['img_article']) && file_exists($f = $this->imageDir . $article['img_article'])) {
                @unlink($f);
            }
            if (!empty($article['pdf_article']) && file_exists($f = $this->pdfDir . $article['pdf_article'])) {
                @unlink($f);
            }

            $this->conn->prepare("DELETE FROM articles WHERE id = :id")->execute([':id' => $id]);
            $this->conn->commit();

            $totalAfter = (int)$this->conn->query("SELECT COUNT(*) FROM articles")->fetchColumn();
            return [
                'success'        => true,
                'total_articles' => $totalAfter,
                'total_pages'    => max(1, (int)ceil($totalAfter / self::ARTICLES_PER_PAGE))
            ];
        } catch (Exception) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return $result;
        }
    }

    private function renameUploadedFile(string $currentFilename, string $dir, string $newFilename): ?string
    {
        if (empty($currentFilename)) return null;

        $sourcePath = $dir . $currentFilename;
        $destPath   = $dir . $newFilename;

        if (!file_exists($sourcePath)) {
            return file_exists($destPath) ? $newFilename : $currentFilename;
        }

        if ($sourcePath === $destPath) {
            return $newFilename;
        }

        if (file_exists($destPath)) {
            @unlink($destPath);
        }

        if (!rename($sourcePath, $destPath)) {
            error_log("Échec du renommage : {$sourcePath} -> {$destPath}");
            return $currentFilename;
        }
        return $newFilename;
    }

    private function renameExistingFile(string $currentFilename, string $dir, string $newFilename): ?string
    {
        if ($currentFilename === $newFilename) return $currentFilename;

        $oldPath = $dir . $currentFilename;
        $newPath = $dir . $newFilename;

        if (!file_exists($oldPath)) return $currentFilename;

        if (file_exists($newPath)) {
            @unlink($newPath);
        }

        if (!rename($oldPath, $newPath)) {
            error_log("Échec du renommage fichier existant : {$oldPath} -> {$newPath}");
            return $currentFilename;
        }
        return $newFilename;
    }

    public function generateImageFilename(string $title, string $date, bool $unique = false): string
    {
        $sanitized = preg_replace('/[^\p{L}\p{N}_-]/u', '_', $this->removeAccents($title));
        $sanitized = preg_replace('/_+/', '_', trim($sanitized, '_'));
        $base = 'IMG_' . $sanitized . '_' . str_replace('-', '', $date);
        return $unique ? $base . '_' . bin2hex(random_bytes(4)) . '.webp' : $base . '.webp';
    }

    public function generatePdfFilename(string $title, string $date): string
    {
        $sanitized = preg_replace('/[^\p{L}\p{N}_-]/u', '_', $this->removeAccents($title));
        $sanitized = preg_replace('/_+/', '_', trim($sanitized, '_'));
        return $sanitized . '_' . str_replace('-', '', $date) . '_resultats.pdf';
    }

    private function removeAccents(string $string): string
    {
        return strtr($string, [
            'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'AE','Ç'=>'C','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','Ð'=>'D','Ñ'=>'N','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O',
            'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ý'=>'Y','Þ'=>'TH','ß'=>'ss','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
            'å'=>'a','æ'=>'ae','ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ð'=>'d',
            'ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y',
            'þ'=>'th','ÿ'=>'y'
        ]);
    }

    public function formatDateFrench(string $dateString): string
    {
        if (($timestamp = strtotime($dateString)) === false) {
            return $dateString;
        }

        if (class_exists('IntlDateFormatter')) {
            try {
                return (new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::NONE))->format(new DateTime($dateString));
            } catch (Exception) {}
        }

        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        return sprintf('%02d %s %d', (int)date('d', $timestamp), $months[(int)date('m', $timestamp) - 1], date('Y', $timestamp));
    }
}