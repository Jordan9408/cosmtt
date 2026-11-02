<?php
namespace Admin\ClassPages\Users;

use Admin\ClassPages\Club\Validations;
use Admin\Includes\ErrorMessages;
use Exception;
use InvalidArgumentException;

/**
 * Contrôleur traitant la logique de validation et le flux de données pour l'utilisateur.
 */
class ModifyUserController
{
    public function __construct(
        private readonly ModifyUserManager $manager,
        private readonly ModifyUserView $view,
        private readonly string $currentUserRole = '',
        private readonly int $currentUserId = 0
    ) {}

    /**
     * Point d'entrée principal pour traiter la requête courante.
     */
    public function handleRequest(): void
    {
        $userId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        if ($userId === null || $userId <= 0) {
            $this->view->render([], ErrorMessages::get('USER_ID_INVALID') ?? 'ID invalide', $userId, false, $this->currentUserRole);
            return;
        }

        $user = $this->manager->getUserById($userId);
        if (!$user) {
            $this->view->render([], '', $userId, false, $this->currentUserRole);
            return;
        }

        // Contrôle d'accès anti-IDOR :
        // Un superAdmin peut modifier n'importe quel compte.
        // Un admin standard ne peut modifier QUE son propre compte (currentUserId === userId).
        if ($this->currentUserRole !== 'superAdmin') {
            if ($this->currentUserId <= 0 || $this->currentUserId !== $userId) {
                http_response_code(403);
                die('Accès interdit : vous ne pouvez modifier que votre propre compte.');
            }
            if (($user['role'] ?? '') === 'superAdmin') {
                http_response_code(403);
                die('Accès interdit : privilèges superAdmin requis pour modifier ce compte.');
            }
        }

        $errorMessage = '';
        $userData = [
            'firstName' => $user['firstName'],
            'lastName'  => $user['lastName'],
            'email'     => $user['email'],
            'role'      => $user['role']
        ];

        // Traitement du formulaire POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
            $errorMessage = $this->handlePostSubmission($userId, $userData, $user);
        }

        // Appel de la vue pour l'affichage HTML
        $this->view->render($userData, $errorMessage, $userId, true, $this->currentUserRole);
    }

    /**
     * Gère la soumission du formulaire POST.
     */
    private function handlePostSubmission(int $userId, array &$userData, array $targetUser): string
    {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            return ErrorMessages::get('CSRF_INVALID');
        }
        // Régénération du jeton CSRF après usage (Single-Use Token)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Contrôle d'accès anti-IDOR lors de la soumission POST :
        if ($this->currentUserRole !== 'superAdmin') {
            if ($this->currentUserId <= 0 || $this->currentUserId !== $userId) {
                return 'Action interdite : vous ne pouvez modifier que votre propre compte.';
            }
            if (($targetUser['role'] ?? '') === 'superAdmin') {
                return 'Action interdite : vous ne pouvez pas modifier un superAdmin.';
            }
        }

        $firstNameRaw = trim($_POST['firstName'] ?? '');
        $lastNameRaw  = trim($_POST['lastName'] ?? '');
        $emailRaw     = trim($_POST['email'] ?? '');
        $password     = $_POST['password'] ?? null;
        $confPassword = $_POST['confPassword'] ?? null;

        // Seul un superAdmin peut modifier les rôles
        if ($this->currentUserRole === 'superAdmin') {
            $role = $_POST['role'] ?? $targetUser['role'];
        } else {
            // Un admin ordinaire ne peut ni promouvoir ni changer le rôle
            $role = $targetUser['role'];
        }

        // Mise à jour de la variable référence pour pré-remplir le formulaire en cas d'erreur
        $userData = [
            'firstName' => $firstNameRaw,
            'lastName'  => $lastNameRaw,
            'email'     => $emailRaw,
            'role'      => $role
        ];

        // 1. Formatage STRICT incluant les accents avec l'extension multibyte (mb_)
        $firstName = ucwords(mb_strtolower($firstNameRaw, 'UTF-8'), " -' ");
        $lastName  = mb_strtoupper($lastNameRaw, 'UTF-8'); // <--- TOUTES les lettres en majuscules (avec accents)

        // 2. Validation des données formatées
        $errors = $this->validateInput($firstName, $lastName, $emailRaw, $password, $confPassword, $role);

        if (!empty($errors)) {
            return implode("\n\n", $errors);
        }

        try {
            if ($this->manager->updateUser($userId, $firstName, $lastName, $emailRaw, $password, $role)) {
                header("Location: showUser.php");
                exit();
            }
            return ErrorMessages::get('USER_UPDATE_FAILED');
        } catch (InvalidArgumentException | Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Centralise la validation des données entrantes.
     */
    private function validateInput(string $firstName, string $lastName, string $email, ?string $password, ?string $confPassword, string $role): array
    {
        $errors = [];

        $checkFirst = Validations::validateCleanContent($firstName, "Prénom");
        if (!$checkFirst['valid']) $errors[] = $checkFirst['error'];

        $checkLast = Validations::validateCleanContent($lastName, "Nom");
        if (!$checkLast['valid']) $errors[] = $checkLast['error'];

        $checkEmail = Validations::validateCleanContent($email, "Email");
        if (!$checkEmail['valid']) $errors[] = $checkEmail['error'];

        // Le pattern de votre Regex (\p{L}) est excellent car il supporte nativement les accents majuscules
        $namePattern = "/^[\\p{L}][\\p{L}\\s\\-']*$/u";
        if (!preg_match($namePattern, $firstName)) {
            $errors[] = ErrorMessages::get('FIRSTNAME_INVALID');
        }
        if (!preg_match($namePattern, $lastName)) {
            $errors[] = ErrorMessages::get('LASTNAME_INVALID');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ErrorMessages::get('EMAIL_INVALID');
        }

        if (!in_array($role, ['admin', 'superAdmin'], true)) {
            $errors[] = ErrorMessages::get('ROLE_INVALID');
        }

        if ($this->currentUserRole !== 'superAdmin' && $role === 'superAdmin') {
            $errors[] = 'Attribution du rôle superAdmin non autorisée.';
        }

        if ($password !== null && $password !== '') {
            if (mb_strlen($password) < 12) {
                $errors[] = ErrorMessages::get('PASSWORD_TOO_SHORT');
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = ErrorMessages::get('PASSWORD_NO_CAPITAL');
            }
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = ErrorMessages::get('PASSWORD_NO_LOWERCASE');
            }
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = ErrorMessages::get('PASSWORD_NO_NUMBER');
            }
            if (!preg_match('/[\W_]/', $password)) {
                $errors[] = ErrorMessages::get('PASSWORD_NO_SPECIAL');
            }
            if ($password !== $confPassword) {
                $errors[] = ErrorMessages::get('PASSWORDS_MISMATCH');
            }
        }

        return $errors;
    }
}