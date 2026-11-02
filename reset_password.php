<?php

declare(strict_types=1);

// Le token de reset circule en GET : on évite sa fuite via l'en-tête Referer
header('Referrer-Policy: no-referrer');

require_once __DIR__ . '/include/session_boot.php';
require_once __DIR__ . '/include/RateLimiter.php';
require_once __DIR__ . '/admin/pages/connect_ddb.php';
require_once __DIR__ . '/admin/pages/class/Auth.php';
require_once __DIR__ . '/admin/pages/class/User.php';
require_once __DIR__ . '/admin/includes/ErrorMessages.php';

use Admin\Includes\ErrorMessages;

class ResetPasswordPage
{
    private array $errors = [];
    private bool $success = false;
    private string $token = '';
    private string $csrfToken = '';
    private RateLimiter $limiter;

    public function __construct(
        private readonly PDO $conn,
        private readonly Auth $auth,
    ) {
        $this->limiter = new RateLimiter(
            storageDir: __DIR__ . '/storage/rate_limit',
            maxAttempts: 5,
            windowSeconds: 900,
        );
    }

    public function handle(): void
    {
        // 1. Si le token arrive dans l'URL (clic depuis l'email) :
        // Si le token arrive dans l'URL (clic depuis l'e-mail) :
        if (isset($_GET['token']) && is_string($_GET['token']) && $_GET['token'] !== '') {
            $_SESSION['password_reset_token'] = $_GET['token'];
            header('Location: reset_password.php');
            exit();
        }

        // 2. On récupère le token depuis la session sécurisée
        $this->token = is_string($_SESSION['password_reset_token'] ?? null) ? $_SESSION['password_reset_token'] : '';

        if ($this->token === '') {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => ErrorMessages::get('AUTH_TOKEN_MISSING'),
            ];
            header('Location: connexion.php');
            exit();
        }

        $resetKey = 'reset-form:' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        if ($this->limiter->isLocked($resetKey)) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => ErrorMessages::format(
                    'AUTH_TOO_MANY_ATTEMPTS',
                    [$this->limiter->remainingMinutes($resetKey)]
                ),
            ];
            header('Location: connexion.php');
            exit();
        }

        try {
            $request = $this->auth->findValidPasswordResetRequest($this->token);

            if ($request === null) {
                unset($_SESSION['password_reset_token']);
                $this->limiter->hit($resetKey);
                $_SESSION['flash_message'] = [
                    'type' => 'error',
                    'message' => ErrorMessages::get('AUTH_TOKEN_INVALID'),
                ];
                header('Location: connexion.php');
                exit();
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$this->verifyCsrfToken($_POST['csrf_token'] ?? null)) {
                    $this->errors[] = ErrorMessages::get('CSRF_INVALID');
                    $this->csrfToken = $this->generateCsrfToken();
                    return;
                }

                if ($this->limiter->isLocked($resetKey)) {
                    $this->errors[] = ErrorMessages::format(
                        'AUTH_TOO_MANY_ATTEMPTS',
                        [$this->limiter->remainingMinutes($resetKey)]
                    );
                    $this->csrfToken = $this->generateCsrfToken();
                    return;
                }

                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if ($newPassword === '' || $confirmPassword === '') {
                    $this->limiter->hit($resetKey);
                    $this->errors[] = ErrorMessages::get('AUTH_FIELDS_REQUIRED');
                } elseif ($newPassword !== $confirmPassword) {
                    $this->limiter->hit($resetKey);
                    $this->errors[] = ErrorMessages::get('AUTH_PASSWORD_MISMATCH');
                } else {
                    $userId = (int) $request['user_id'];
                    $userClass = new User($this->conn);
                    $userData = $userClass->getUser($userId);

                    if (!$userData) {
                        $this->errors[] = ErrorMessages::get('AUTH_USER_NOT_FOUND');
                    } else {
                        try {
                            $userClass->updateUser(
                                $userId,
                                $userData['firstName'],
                                $userData['lastName'],
                                $userData['email'],
                                $newPassword,
                                $userData['role']
                            );

                            $this->auth->consumePasswordResetRequest($this->token);
                            unset($_SESSION['password_reset_token']);
                            $this->auth->invalidateSessionsAfterReset($userId);
                            $this->limiter->clear($resetKey);
                            $this->success = true;
                        } catch (InvalidArgumentException $e) {
                            $this->limiter->hit($resetKey);
                            $this->errors[] = $e->getMessage();
                        } catch (Exception $e) {
                            error_log('[ResetPassword] ' . $e->getMessage());
                            $this->errors[] = ErrorMessages::get('AUTH_UPDATE_ERROR');
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('[ResetPassword] PDO: ' . $e->getMessage());
            $this->errors[] = ErrorMessages::get('AUTH_INTERNAL_ERROR');
        }

        $this->csrfToken = $this->generateCsrfToken();
    }

    private function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function verifyCsrfToken(?string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getCsrfToken(): string
    {
        return $this->csrfToken;
    }
}

$auth = new Auth();
$page = new ResetPasswordPage($conn, $auth);
$page->handle();

require_once __DIR__ . '/html_partials/header_reset.php';

$resetAlert = !empty($page->getErrors()) ? implode("\n", $page->getErrors()) : ($page->isSuccess() ? ErrorMessages::get('AUTH_PASSWORD_UPDATED') : '');
$resetRedirect = $page->isSuccess() ? 'connexion.php' : '';
?>

<main<?= !empty($resetAlert) ? ' data-alert="' . htmlspecialchars($resetAlert, ENT_QUOTES, 'UTF-8') . '"' : '' ?><?= !empty($resetRedirect) ? ' data-redirect="' . htmlspecialchars($resetRedirect, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    <div class="main-connect">
        <h2 class="title-bar">Réinitialisation du mot de passe</h2>
        <div class="connect">
            <?php if ($page->isSuccess()): ?>
                <p>Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.</p>
                <p><a href="connexion.php">Page de connexion</a></p>
            <?php else: ?>
                <form action="reset_password.php" method="POST" id="login-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($page->getCsrfToken()) ?>">
                    <div class="encad form-recup">
                        <label for="new_password">
                            Nouveau mot de passe :
                            <i class="fa-solid fa-circle-info info-tooltip-icon"
                               title="Minimum 12 caractères, 1 majuscule, 1 chiffre, 1 caractère spécial"></i>
                        </label>
                        <div class="mdp">
                            <input type="password" id="new_password" class="connect-input" name="new_password" required minlength="12" autocomplete="new-password">
                            <i class="fa fa-eye" aria-hidden="true" style="cursor:pointer;"></i>
                        </div>
                        <label for="confirm_password">
                            Confirmer le mot de passe :
                            <!-- <i class="fa-solid fa-circle-info info-tooltip-icon"
                               title="Confirmation :&#10;Le mot de passe doit être strictement identique au mot de passe saisi ci-dessus."></i> -->
                        </label>
                        <div class="mdp">
                            <input type="password" id="confirm_password" class="connect-input" name="confirm_password" required minlength="12" autocomplete="new-password">
                            <i class="fa fa-eye" aria-hidden="true" style="cursor:pointer;"></i>
                        </div>
                        <input type="submit" id="reset-submit" class="connect-submit" value="Réinitialiser le mot de passe" name="valid-button">
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include('./html_partials/footer.php'); ?>
