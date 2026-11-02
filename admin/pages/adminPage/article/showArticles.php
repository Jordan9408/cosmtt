<?php
/**
 * Page d'administration des articles.
 *
 * Dépendances externes :
 *   connect_ddb.php, security.php, session_check.php, header.php
 *   ArticleController.php, ArticleView.php, footer.php
 */

declare(strict_types=1);

$isAjaxFetch = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch'
    && isset($_SERVER['REQUEST_METHOD'])
    && $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isAjaxFetch) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
}

class ShowArticlesPage
{
    private PDO $conn;
    private ArticleController $articleController;
    private ArticleManager $articleManager;

    private ?string $flashMessage = null;
    private ?string $displayError = null;
    private bool $isAjax = false;
    private int $currentPage = 1;
    private array $articles = [];
    private int $totalPages = 1;

    private string $pagesPath;
    private string $partsPath;
    private string $includesPath;
    private string $classPagesPath;

    public function __construct()
    {
        $this->pagesPath      = dirname(__DIR__, 2) . '/';
        $this->partsPath      = $this->pagesPath . 'parts/';
        $this->includesPath   = dirname(__DIR__, 3) . '/includes/';
        $this->classPagesPath = $this->pagesPath . 'classPages/article/';
    }

    public function run(): void
    {
        $this->loadDependencies();
        $this->detectAjax();

        try {
            $this->loadFlashMessage();
            $this->initController();
            $this->checkUploadSizeError();
            $this->handleAjaxSizeError();
            $this->dispatchRequest();
            $this->prepareViewData();
            $this->renderView();
        } catch (Throwable $e) {
            error_log('Erreur interne showArticles (AJAX) : ' . $e->getMessage());
            if ($this->isAjax) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Une erreur interne est survenue. Veuillez réessayer.'
                ], 500);
            }
            throw $e;
        }
    }

    private function loadDependencies(): void
    {
        require_once $this->pagesPath . 'connect_ddb.php';
        require_once $this->includesPath . 'security.php';
        require_once $this->includesPath . 'session_check.php';
        require_once $this->partsPath . 'header.php';
        require_once $this->classPagesPath . 'ArticleController.php';

        global $conn;
        $this->conn = $conn;
    }

    private function detectAjax(): void
    {
        global $isAjaxFetch;
        $this->isAjax = (bool)$isAjaxFetch;
    }

    private function loadFlashMessage(): void
    {
        if (isset($_SESSION['flash_message'])) {
            $this->flashMessage = (string)$_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
        }
    }

    private function initController(): void
    {
        $csrfTokenForJs          = (string)($_SESSION['csrf_token'] ?? '');
        $this->articleController = new ArticleController($this->conn, $csrfTokenForJs);
        $this->articleManager    = $this->articleController->getArticleManager();
    }

    private function checkUploadSizeError(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && empty($_POST)
            && empty($_FILES)
            && isset($_SERVER['CONTENT_LENGTH'])
            && (int)$_SERVER['CONTENT_LENGTH'] > 0
        ) {
            require_once $this->includesPath . 'ErrorMessages.php';
            $this->displayError = \Admin\Includes\ErrorMessages::get('UPLOAD_LIMIT_EXCEEDED')
                . ' Limite actuelle : ' . ini_get('post_max_size') . '.';
        }
    }

    private function handleAjaxSizeError(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && $this->isAjax
            && !empty($this->displayError)
        ) {
            $this->sendJsonResponse(['success' => false, 'message' => $this->displayError]);
        }
    }

    private function dispatchRequest(): void
    {
        $this->articleController->handleRequest();

        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'add_article'
        ) {
            if ($this->isAjax) {
                $this->handleAjaxFormSubmission();
                return;
            }

            $this->handleClassicFormSubmission();
            return;
        }

        if ($this->isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string)($_POST['action'] ?? '');
            if ($action !== 'add_article') {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Requête AJAX non supportée pour action="' . $action . '".'
                ], 400);
            }
        }
    }

    private function handleAjaxFormSubmission(): void
    {
        $formResult   = $this->articleController->handleFormSubmission();
        $redirectPage = max(1, (int)($_POST['current_page'] ?? 1));
        $redirectUrl  = $_SERVER['PHP_SELF'] . '?page=' . $redirectPage;

        $this->sendJsonResponse([
            'success'      => (bool)($formResult['success'] ?? false),
            'message'      => (string)($formResult['message'] ?? ''),
            'redirect_url' => ($formResult['success'] ?? false) ? $redirectUrl : null,
        ]);
    }

    private function handleClassicFormSubmission(): void
    {
        $formResult = $this->articleController->handleFormSubmission();

        if ($formResult['success']) {
            $_SESSION['flash_message'] = $formResult['message'];
            $currentPage = max(1, (int)($_POST['current_page'] ?? 1));
            header("Location: {$_SERVER['PHP_SELF']}?page=$currentPage");
            exit;
        }

        $this->flashMessage = $formResult['message'] ?? null;
    }

    private function prepareViewData(): void
    {
        $this->currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        try {
            $data             = $this->articleManager->getArticlesPaginated($this->currentPage);
            $this->articles   = $data['articles'];
            $this->totalPages = $data['total_pages'];
        } catch (Exception $e) {
            error_log('Erreur showArticles.php : ' . $e->getMessage());
            $this->displayError = "Impossible de récupérer les articles. Vérifiez que la table 'articles' existe, qu'elle contient des enregistrements et que la connexion à la BDD fonctionne.";
            $this->articles    = [];
            $this->totalPages  = 1;
            $this->currentPage = 1;
        }
    }

    private function renderView(): void
    {
        if ($this->isAjax) {
            $action = (string)($_POST['action'] ?? '');
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Réponse HTML générée alors que requête AJAX en cours. Action="' . $action . '"'
            ], 500);
        }

        if ($this->isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Réponse non conforme (HTML au lieu de JSON).'
            ], 500);
        }

        $viewData = [
            'flashMessage'   => $this->flashMessage,
            'displayError'   => $this->displayError,
            'articles'       => $this->articles,
            'totalPages'     => $this->totalPages,
            'currentPage'    => $this->currentPage,
            'csrfTokenForJs' => $_SESSION['csrf_token'] ?? '',
            'articleManager' => $this->articleManager,
        ];

        require_once $this->classPagesPath . 'ArticleView.php';
        (new ArticleView())->render($viewData);
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

(new ShowArticlesPage())->run();