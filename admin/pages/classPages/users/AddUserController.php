<?php
declare(strict_types=1);

namespace Admin\ClassPages\Users;

use Admin\ClassPages\Club\Validations;
use Admin\Includes\ErrorMessages;
use Exception;
use InvalidArgumentException;

/**
 * Contrôleur traitant la validation et la logique métier pour l'ajout d'utilisateur.
 */
class AddUserController
{
    public function __construct(
        private readonly AddUserManager $manager,
        private readonly AddUserView $view,
        private readonly string $currentUserRole = ''
    ) {}

    /**
     * Point d'entrée pour traiter la requête courante.
     */
    public function handleRequest(): void
    {
        $alertMessage = '';
        $formData = [
            'firstName' => '',
            'lastName'  => '',
            'email'     => '',
            'role'      => ''
        ];

        // Traitement de la soumission POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
            $alertMessage = $this->handlePostSubmission($formData);
        }

        // Délégation de l'affichage à la Vue
        $this->view->render($formData, $alertMessage, $this->currentUserRole);
    }

    /**
     * Gère et valide les données issues du formulaire POST.
     */
    private function handlePostSubmission(array &$formData): string
    {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            return ErrorMessages::get('CSRF_INVALID');
        }
        // Régénération du jeton CSRF après usage (Single-Use Token)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $firstNameRaw = trim($_POST['firstName'] ?? '');
        $lastNameRaw  = trim($_POST['lastName'] ?? '');
        $emailRaw     = trim($_POST['email'] ?? '');
        $password     = $_POST['password'] ?? '';
        $confPassword = $_POST['confPassword'] ?? '';
        
        // Seul un superAdmin peut créer un compte superAdmin
        if ($this->currentUserRole === 'superAdmin') {
            $role = $_POST['role'] ?? 'admin';
        } else {
            $role = 'admin';
        }

        // Pré-remplissage en cas d'erreur pour ne pas frustrer l'utilisateur
        $formData = [
            'firstName' => $firstNameRaw,
            'lastName'  => $lastNameRaw,
            'email'     => $emailRaw,
            'role'      => $role
        ];

        $errors = $this->validateInput($firstNameRaw, $lastNameRaw, $emailRaw, $password, $confPassword, $role);

        if (!empty($errors)) {
            // Regroupe toutes les erreurs formatées
            return ErrorMessages::formatMany($errors);
        }

        // Formatage STRICT des noms (support de TOUTES les majuscules avec accents)
        $firstName = ucwords(mb_strtolower($firstNameRaw, 'UTF-8'), " -'");
        $lastName  = mb_strtoupper($lastNameRaw, 'UTF-8');

        try {
            if ($this->manager->addUser($firstName, $lastName, $emailRaw, $password, $role)) {
                // Redirection en cas de succès (Évite la re-soumission en F5)
                header("Location: ./showUser.php?message=AddSuccess");
                exit();
            }
            return ErrorMessages::get('USER_ADD_FAILED');
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        } catch (Exception $e) {
            error_log('Erreur ajout utilisateur : ' . $e->getMessage());
            return ErrorMessages::get('USER_ADD_FAILED');
        }
    }

    /**
     * Centralise les vérifications de sécurité et d'intégrité (Whitelisting, Regex, Tailles).
     */
    private function validateInput(string $firstName, string $lastName, string $email, string $password, string $confPassword, string $role): array
    {
        $errors = [];

        // Validation XSS / Scripts
        $checkFirst = Validations::validateCleanContent($firstName, "Prénom");
        if (!$checkFirst['valid']) $errors[] = $checkFirst['error'];

        $checkLast = Validations::validateCleanContent($lastName, "Nom");
        if (!$checkLast['valid']) $errors[] = $checkLast['error'];

        $checkEmail = Validations::validateCleanContent($email, "Email");
        if (!$checkEmail['valid']) $errors[] = $checkEmail['error'];

        $checkRole = Validations::validateCleanContent($role, "Rôle");
        if (!$checkRole['valid']) $errors[] = $checkRole['error'];

        // Validation du rôle (Whitelist)
        if (!in_array($role, ['admin', 'superAdmin'], true)) {
            $errors[] = ErrorMessages::get('ROLE_INVALID');
        }

        if ($this->currentUserRole !== 'superAdmin' && $role === 'superAdmin') {
            $errors[] = 'Attribution du rôle superAdmin non autorisée.';
        }

        // Tailles max
        if (mb_strlen($firstName) > 80 || mb_strlen($lastName) > 80) {
            $errors[] = ErrorMessages::get('NAME_TOO_LONG');
        }
        if (mb_strlen($email) > 120) {
            $errors[] = ErrorMessages::get('EMAIL_TOO_LONG');
        }

        // Validité Email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ErrorMessages::get('EMAIL_INVALID');
        }

        // Mots de passe
        if ($password !== $confPassword) {
            $errors[] = ErrorMessages::get('PASSWORDS_MISMATCH');
        }

        if ($password !== '') {

            $invalid =
                mb_strlen($password) < 12 ||
                !preg_match('/[A-Z]/', $password) ||
                !preg_match('/[a-z]/', $password) ||
                !preg_match('/[0-9]/', $password) ||
                !preg_match('/[\W_]/', $password);

            if ($invalid) {
                $errors[] = "Votre mot de passe doit contenir au minimum: \n12 caractères, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.";
            }
        }
        // Format de nom / prénom autorisé
        $namePattern = "/^[\\p{L}][\\p{L}\\s\\-']*$/u";
        if ($firstName !== '' && !preg_match($namePattern, $firstName)) {
            $errors[] = ErrorMessages::get('FIRSTNAME_INVALID');
        }
        if ($lastName !== '' && !preg_match($namePattern, $lastName)) {
            $errors[] = ErrorMessages::get('LASTNAME_INVALID');
        }

        return $errors;
    }
}