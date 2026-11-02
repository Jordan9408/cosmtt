<?php
declare(strict_types=1);

// 1. Sécurité et Session (obligatoire avant toute action de suppression)
require_once dirname(__DIR__, 2) . '/includes/security.php';
require_once dirname(__DIR__, 2) . '/includes/session_check.php';
require_once dirname(__DIR__, 1) . '/connect_ddb.php';
require_once dirname(__DIR__, 1) . '/class/User.php';

/**
 * Classe gérant la suppression d'un utilisateur
 */
class DeleteUser
{
    public function __construct(private User $user, private string $role)
    {
    }

    /**
     * Traite la requête de suppression
     */
    public function handleRequest(array $requestData): void
    {
        // Seul un superAdmin est autorisé à supprimer un utilisateur
        if ($this->role !== 'superAdmin') {
            http_response_code(403);
            die('Accès interdit : privilèges superAdmin requis.');
        }

        // Vérification CSRF
        $token = $requestData['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $this->redirect('showUser.php?message=InvalidCsrf');
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Vérification stricte de la présence et de la validité de l'ID
        if (!empty($requestData['id']) && is_numeric($requestData['id'])) {
            $userId = (int) $requestData['id'];

            // Protection anti auto-suppression du compte actif
            if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
                $this->redirect('showUser.php?message=CannotDeleteSelf');
            }

            // Exécution de la suppression
            if ($this->user->deleteUser($userId)) {
                $this->redirect('showUser.php?message=DeleteSuccess');
            } else {
                $this->redirect('showUser.php?message=DeleteFail');
            }
        } else {
            $this->redirect('showUser.php?message=InvalidRequest');
        }
    }

    /**
     * Gère la redirection de manière isolée
     */
    private function redirect(string $url): never
    {
        header("Location: " . $url);
        exit();
    }
}

// 2. Point d'entrée d'exécution du script
if (isset($conn)) {
    $userModel = new User($conn);
    $deleteController = new DeleteUser($userModel, $role ?? '');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $deleteController->handleRequest($_POST);
    } else {
        header('Location: showUser.php?message=InvalidRequest');
        exit();
    }
}
?>