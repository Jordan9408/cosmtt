<?php
/**
 * =============================================================================
 * ARTICLE MANAGER - GESTION DES ARTICLES (CRUD + PAGINATION)
 * =============================================================================
 * 
 * Classe permettant de gérer les articles et leurs fichiers associés.
 * Encapsule toutes les opérations de base de données.
 * 
 * TABLE : articles
 *   - id (INT UNSIGNED AUTO_INCREMENT PK)
 *   - title_article (VARCHAR 255)
 *   - date_evmt_article (DATE)
 *   - date_creation_article (TIMESTAMP)
 *   - img_article (VARCHAR 255)
 *   - contenu_article (TEXT)
 *   - pdf_article (VARCHAR 255 NULL)
 * 
 * @version 1.0
 * =============================================================================
 */

class ArticleManager
{
    private PDO $conn;
    private string $imageDir;
    private string $pdfDir;

    /**
     * Constantes de configuration
     */
    public const ARTICLES_PER_PAGE = 4;
    public const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10 Mo
    public const MAX_PDF_SIZE   = 10 * 1024 * 1024;  // 10 Mo

    /**
     * Constructeur
     */
    public function __construct(PDO $connection)
    {
        $this->conn = $connection;
        $this->imageDir = dirname(__DIR__, 4) . '/docs/articles/articlesPhotos/';
        $this->pdfDir   = dirname(__DIR__, 4) . '/docs/articles/resultats/';

        $this->createDirectories();
    }

    /**
     * Crée les répertoires s'ils n'existent pas
     */
    private function createDirectories(): void
    {
        if (!is_dir($this->imageDir)) {
            mkdir($this->imageDir, 0755, true);
        }
        if (!is_dir($this->pdfDir)) {
            mkdir($this->pdfDir, 0755, true);
        }
    }

    // =========================================================================
    // GETTERS
    // =========================================================================

    public function getImageDir(): string
    {
        return $this->imageDir;
    }

    public function getPdfDir(): string
    {
        return $this->pdfDir;
    }

    // =========================================================================
    // OPÉRATIONS DE LECTURE
    // =========================================================================

    /**
     * Récupère les articles avec pagination
     */
    public function getArticlesPaginated(int $page = 1): array
    {
        $perPage     = self::ARTICLES_PER_PAGE;
        $currentPage = max(1, $page);

        // Compter le total
        $countStmt   = $this->conn->query("SELECT COUNT(*) FROM articles");
        $totalCount  = (int)$countStmt->fetchColumn();
        $totalPages  = max(1, (int)ceil($totalCount / $perPage));

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

        return [
            'articles'     => $articles,
            'total'        => $totalCount,
            'current_page' => $currentPage,
            'total_pages'  => $totalPages
        ];
    }

    /**
     * Récupère un article par son ID
     */
    public function getArticleById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM articles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);
        return $article ?: null;
    }

    /**
     * Vérifie si un article existe par son ID
     */
    public function articleExists(int $id): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM articles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return (bool)$stmt->fetch();
    }

    /**
     * Vérifie si un article existe selon différents critères
     * (titre + date dans la même saison sportive sept-août)
     */
    public function checkArticleExists(string $title, string $date, ?int $excludeId = null): array
    {
        $result = [
            'exists'               => false,
            'id'                   => null,
            'same_title_and_date'  => false,
            'same_title_same_season' => false,
            'same_date'            => false,
            'conflicts'            => []
        ];

        // Calcul de la saison (septembre à août)
        $month = (int)date('n', strtotime($date));
        $year  = (int)date('Y', strtotime($date));

        if ($month >= 9) {
            $seasonStart = $year;
            $seasonEnd   = $year + 1;
        } else {
            $seasonStart = $year - 1;
            $seasonEnd   = $year;
        }
        $seasonStartDate = $seasonStart . '-09-01';
        $seasonEndDate   = $seasonEnd   . '-08-31';

        // Vérification 1 : Même titre ET même date
        $sql = "SELECT id, title_article, date_evmt_article FROM articles 
                WHERE title_article = :title AND date_evmt_article = :date";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $sql .= " LIMIT 1";

        $params = [':title' => $title, ':date' => $date];
        if ($excludeId !== null) {
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $exact = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exact) {
            $result['exists']              = true;
            $result['id']                  = (int)$exact['id'];
            $result['same_title_and_date'] = true;
            $result['conflicts'][] = [
                'type'    => 'same_title_and_date',
                'message' => 'Un article avec ce titre et cette date existe déjà.'
            ];
            return $result;
        }

        // Vérification 2 : Même titre dans la même saison
        $sql = "SELECT id, title_article, date_evmt_article FROM articles 
                WHERE title_article = :title 
                AND date_evmt_article BETWEEN :season_start AND :season_end 
                AND date_evmt_article != :date";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $sql .= " LIMIT 1";

        $params = [
            ':title'        => $title,
            ':season_start' => $seasonStartDate,
            ':season_end'   => $seasonEndDate,
            ':date'         => $date
        ];
        if ($excludeId !== null) {
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $season = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($season) {
            $result['same_title_same_season'] = true;
            $result['conflicts'][] = [
                'type'    => 'same_title_same_season',
                'message' => "Un article avec ce titre existe déjà dans la saison {$seasonStart}-{$seasonEnd} (" .
                             $this->formatDateFrench($season['date_evmt_article']) . ")."
            ];
        }

        // Vérification 3 : Une autre article à cette date (indépendamment du titre)
        $sql = "SELECT id, title_article FROM articles 
                WHERE date_evmt_article = :date";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $sql .= " LIMIT 1";

        $params = [':date' => $date];
        if ($excludeId !== null) {
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $anyAtDate = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($anyAtDate) {
            $result['same_date'] = true;
            $result['id']        = (int)$anyAtDate['id'];
            $result['conflicts'][] = [
                'type'    => 'same_date',
                'message' => 'Un autre article est déjà programmé pour cette date (Titre : ' . htmlspecialchars($anyAtDate['title_article']) . ').'
            ];
        }

        return $result;
    }

    // =========================================================================
    // OPÉRATIONS D'ÉCRITURE
    // =========================================================================

    /**
     * Crée un nouvel article
     *
     * @param array $data Clés: title_article, date_evmt_article, img_article, contenu_article, pdf_article
     * @return int ID de l'article créé
     */
    public function createArticle(array $data): int
    {
        // 1. Gérer le PDF
        $pdfFilename = null;
        if (!empty($data['pdf_article'])) {
            $pdfFilename = $this->generatePdfFilename($data['title_article'], $data['date_evmt_article']);
            $sourcePath = $this->pdfDir . $data['pdf_article'];
            $destPath   = $this->pdfDir . $pdfFilename;
            
            if (file_exists($sourcePath) && $sourcePath !== $destPath) {
                if (file_exists($destPath)) {
                    $pdfFilename = str_replace('.pdf', '_' . uniqid() . '.pdf', $pdfFilename);
                    $destPath = $this->pdfDir . $pdfFilename;
                }
                rename($sourcePath, $destPath);
            }
        }

        // 2. Gérer l'image
        $imageFilename = $data['img_article'] ?? '';
        if (!empty($imageFilename)) {
            $finalImageName = $this->generateImageFilename($data['title_article'], $data['date_evmt_article']);
            $sourceImgPath = $this->imageDir . $imageFilename;
            $destImgPath   = $this->imageDir . $finalImageName;

            if (file_exists($sourceImgPath) && $sourceImgPath !== $destImgPath) {
                if (file_exists($destImgPath)) {
                    $finalImageName = str_replace('.webp', '_' . uniqid() . '.webp', $finalImageName);
                    $destImgPath = $this->imageDir . $finalImageName;
                }
                rename($sourceImgPath, $destImgPath);
            }
            $imageFilename = $finalImageName;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO articles (title_article, date_evmt_article, img_article, contenu_article, pdf_article)
            VALUES (:title, :date_evmt, :img, :contenu, :pdf)
        ");

        $stmt->execute([
            ':title'     => $data['title_article'],
            ':date_evmt' => $data['date_evmt_article'],
            ':img'       => $imageFilename,
            ':contenu'   => $data['contenu_article'],
            ':pdf'       => $pdfFilename
        ]);

        return (int)$this->conn->lastInsertId();
    }

    /**
     * Met à jour un article existant
     */
    public function updateArticle(int $id, array $data): bool
    {
        $oldArticle = $this->getArticleById($id);
        if (!$oldArticle) {
            return false;
        }

        // 1. Détecter si un changement de titre ou de date nécessite le renommage du PDF ou de l'image
        $newTitle = $data['title_article'] ?? $oldArticle['title_article'];
        $newDate  = $data['date_evmt_article'] ?? $oldArticle['date_evmt_article'];
        $titleChanged = isset($data['title_article']) && $data['title_article'] !== $oldArticle['title_article'];
        $dateChanged = isset($data['date_evmt_article']) && $data['date_evmt_article'] !== $oldArticle['date_evmt_article'];

        // --- GESTION DU PDF ---
        if (!empty($data['pdf_article'])) {
            $finalName = $this->generatePdfFilename($newTitle, $newDate);
            $sourcePath = $this->pdfDir . $data['pdf_article'];
            $destPath   = $this->pdfDir . $finalName;

            if (file_exists($sourcePath) && $sourcePath !== $destPath) {
                if (file_exists($destPath)) {
                    $finalName = str_replace('.pdf', '_' . uniqid() . '.pdf', $finalName);
                    $destPath = $this->pdfDir . $finalName;
                }
                rename($sourcePath, $destPath);
            }
            $data['pdf_article'] = $finalName;
        } 
        elseif (($titleChanged || $dateChanged) && !empty($oldArticle['pdf_article'])) {
            $oldFilename = $oldArticle['pdf_article'];
            $newFilename = $this->generatePdfFilename($newTitle, $newDate);
            
            if ($oldFilename !== $newFilename) {
                $oldPath = $this->pdfDir . $oldFilename;
                $newPath = $this->pdfDir . $newFilename;
                
                if (file_exists($oldPath)) {
                    if (file_exists($newPath)) {
                        $newFilename = str_replace('.pdf', '_' . uniqid() . '.pdf', $newFilename);
                        $newPath = $this->pdfDir . $newFilename;
                    }
                    if (rename($oldPath, $newPath)) {
                        $data['pdf_article'] = $newFilename;
                    }
                }
            }
        }

        // --- GESTION DE L'IMAGE ---
        if (!empty($data['img_article'])) {
            // Nouvelle image
            $finalImgName = $this->generateImageFilename($newTitle, $newDate);
            $sourceImgPath = $this->imageDir . $data['img_article'];
            $destImgPath   = $this->imageDir . $finalImgName;

            if (file_exists($sourceImgPath) && $sourceImgPath !== $destImgPath) {
                if (file_exists($destImgPath)) {
                    $finalImgName = str_replace('.webp', '_' . uniqid() . '.webp', $finalImgName);
                    $destImgPath = $this->imageDir . $finalImgName;
                }
                rename($sourceImgPath, $destImgPath);
            }
            $data['img_article'] = $finalImgName;
        } 
        elseif (($titleChanged || $dateChanged) && !empty($oldArticle['img_article'])) {
            // Pas de nouvelle image, mais changement de titre/date -> renommer l'image actuelle
            $oldImgFilename = $oldArticle['img_article'];
            $newImgFilename = $this->generateImageFilename($newTitle, $newDate);
            
            if ($oldImgFilename !== $newImgFilename) {
                $oldImgPath = $this->imageDir . $oldImgFilename;
                $newImgPath = $this->imageDir . $newImgFilename;
                
                if (file_exists($oldImgPath)) {
                    if (file_exists($newImgPath)) {
                        $newImgFilename = str_replace('.webp', '_' . uniqid() . '.webp', $newImgFilename);
                        $newImgPath = $this->imageDir . $newImgFilename;
                    }
                    if (rename($oldImgPath, $newImgPath)) {
                        $data['img_article'] = $newImgFilename;
                    }
                }
            }
        }

        // Construire dynamiquement les champs à mettre à jour
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['title_article'])) {
            $fields[] = "title_article = :title";
            $params[':title'] = $data['title_article'];
        }
        if (isset($data['date_evmt_article'])) {
            $fields[] = "date_evmt_article = :date_evmt";
            $params[':date_evmt'] = $data['date_evmt_article'];
        }
        if (isset($data['img_article'])) {
            $fields[] = "img_article = :img";
            $params[':img'] = $data['img_article'];
        }
        if (isset($data['contenu_article'])) {
            $fields[] = "contenu_article = :contenu";
            $params[':contenu'] = $data['contenu_article'];
        }
        if (array_key_exists('pdf_article', $data)) {
            $fields[] = "pdf_article = :pdf";
            $params[':pdf'] = $data['pdf_article'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE articles SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Supprime un article et ses fichiers physiques
     */
    public function deleteArticle(int $id): array
    {
        $result = ['success' => false, 'total_articles' => 0, 'total_pages' => 1];

        try {
            // Récupérer les infos de l'article pour supprimer les fichiers
            $article = $this->getArticleById($id);
            if (!$article) {
                return $result;
            }

            $this->conn->beginTransaction();

            // Supprimer les fichiers physiques
            if (!empty($article['img_article'])) {
                $imgFile = $this->imageDir . $article['img_article'];
                if (file_exists($imgFile) && is_file($imgFile)) {
                    unlink($imgFile);
                }
            }

            if (!empty($article['pdf_article'])) {
                $pdfFile = $this->pdfDir . $article['pdf_article'];
                if (file_exists($pdfFile) && is_file($pdfFile)) {
                    unlink($pdfFile);
                }
            }

            // Supprimer l'enregistrement BD
            $stmt = $this->conn->prepare("DELETE FROM articles WHERE id = :id");
            $stmt->execute([':id' => $id]);

            $this->conn->commit();

            // Calculer la pagination après suppression
            $countStmt = $this->conn->query("SELECT COUNT(*) FROM articles");
            $totalAfter = (int)$countStmt->fetchColumn();
            $totalPages = max(1, (int)ceil($totalAfter / self::ARTICLES_PER_PAGE));

            $result = [
                'success'        => true,
                'total_articles' => $totalAfter,
                'total_pages'    => $totalPages
            ];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Erreur deleteArticle: " . $e->getMessage());
        }

        return $result;
    }

    // =========================================================================
    // MÉTHODES UTILITAIRES
    // =========================================================================

    /**
     * Génère un nom de fichier image basé sur la convention de nommage
     * Format : IMG_titre_article_YYYYMMDD.webp
     */
    public function generateImageFilename(string $title, string $date): string
    {
        $titleWithoutAccents = $this->removeAccents($title);
        $sanitizedTitle = preg_replace('/[^\p{L}\p{N}_-]/u', '_', $titleWithoutAccents);
        $sanitizedTitle = preg_replace('/_+/', '_', $sanitizedTitle);
        $sanitizedTitle = trim($sanitizedTitle, '_');
        $formattedDate = str_replace('-', '', $date);
        
        return 'IMG_' . $sanitizedTitle . '_' . $formattedDate . '.webp';
    }

    /**
     * Génère un nom de fichier PDF basé sur la convention de nommage
     * Format : Titre_Article_YYYYMMDD_resultat.pdf
     */
    public function generatePdfFilename(string $title, string $date): string
    {
        $titleWithoutAccents = $this->removeAccents($title);
        $sanitizedTitle = preg_replace('/[^\p{L}\p{N}_-]/u', '_', $titleWithoutAccents);
        $sanitizedTitle = preg_replace('/_+/', '_', $sanitizedTitle);
        $sanitizedTitle = trim($sanitizedTitle, '_');
        $formattedDate = str_replace('-', '', $date);
        
        return $sanitizedTitle . '_' . $formattedDate . '_resultats.pdf';
    }

    /**
     * Supprime les accents d'une chaîne de caractères
     */
    private function removeAccents(string $string): string
    {
        $accents = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 
            'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'TH', 'ß' => 'ss', 
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 
            'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y'
        ];
        return strtr($string, $accents);
    }

    /**
     * Formate une date en français
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

        $day   = (int)date('d', $timestamp);
        $month = $months[(int)date('m', $timestamp) - 1];
        $year  = date('Y', $timestamp);

        return sprintf('%02d %s %d', $day, $month, $year);
    }
}
