<?php

declare(strict_types=1);

require_once __DIR__ . '/include/security_connexion.php';
require_once __DIR__ . '/admin/includes/ErrorMessages.php';
// create_tab.php (outil d'admin de création de tables) n'est plus inclus sur la page publique.
// Les tables sont initialisées à la demande (ex: Auth::ensureResetTable) ou via un script CLI protégé.

use Admin\Includes\ErrorMessages;

/**
 * Gère toute la logique métier de la page de connexion.
 */
final class ConnexionPage
{
    private string $error          = '';
    private string $successMessage = '';
    private string $email          = '';
    private bool   $showResetForm  = false;
    private array  $flash          = [];

    public function __construct(
        private readonly object $auth,
        private readonly RateLimiter $loginLimiter,
    ) {
    }

    public function handle(): void
    {
        $this->loadFlashMessage();

        if ($this->loginLimiter->isLocked($this->loginKey())) {
            $this->error = ErrorMessages::format(
                'AUTH_TOO_MANY_ATTEMPTS',
                [$this->loginLimiter->remainingMinutes($this->loginKey())]
            );
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->error === '') {
            if (isset($_POST['valid-button'])) {
                $this->handleLogin();
            } elseif (isset($_POST['recup-submit'])) {
                $this->handlePasswordReset();
            }
        }
    }

    private function loadFlashMessage(): void
    {
        if (!isset($_SESSION['flash_message'])) {
            return;
        }

        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);

        if (is_array($flash) && !empty($flash['message'])) {
            $this->flash = $flash;
            if (($flash['type'] ?? '') === 'error') {
                $this->error = $flash['message'];
            }
        }
    }

    private function verifyCsrf(): bool
    {
        if (
            !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            $this->error = ErrorMessages::get('AUTH_CSRF_INVALID');
            error_log('SÉCURITÉ: Tentative CSRF détectée depuis ' . $this->clientIpHash());
            return false;
        }
        return true;
    }

    private function regenerateCsrfToken(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    private function handleLogin(): void
    {
        if (!$this->verifyCsrf()) {
            return;
        }

        if ($this->loginLimiter->isLocked($this->loginKey())) {
            $this->error = ErrorMessages::format(
                'AUTH_TOO_MANY_ATTEMPTS',
                [$this->loginLimiter->remainingMinutes($this->loginKey())]
            );
            return;
        }

        $this->email = $_POST['email'] ?? '';
        $password    = $_POST['password'] ?? '';

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->registerLoginFailure();
            $this->error = ErrorMessages::get('AUTH_EMAIL_INVALID');
            return;
        }

        // Verrou lié au compte ciblé (anti rotation d'IP / brute-force compte par compte)
        $acctKey = $this->accountKey($this->email);
        if ($this->loginLimiter->isLocked($acctKey)) {
            $this->error = ErrorMessages::format(
                'AUTH_TOO_MANY_ATTEMPTS',
                [$this->loginLimiter->remainingMinutes($acctKey)]
            );
            return;
        }

        if ($password === '') {
            $this->registerLoginFailure();
            $this->loginLimiter->hit($acctKey);
            $this->error = ErrorMessages::get('AUTH_PASSWORD_EMPTY');
            return;
        }

        try {
            if ($this->auth->login($this->email, $password)) {
                $this->loginLimiter->clear($this->loginKey());
                $this->loginLimiter->clear($acctKey);
                $_SESSION['auth_created'] = time();
                header('Location: ./admin/pages/home.php');
                exit();
            }
        } catch (Exception $e) {
            error_log('SÉCURITÉ: Erreur lors de la connexion: ' . $e->getMessage());
            $this->error = ErrorMessages::get('AUTH_INTERNAL_ERROR');
            $this->regenerateCsrfToken();
            $this->registerLoginFailure();
            $this->loginLimiter->hit($acctKey);
            return;
        }

        $ipLocked   = $this->registerLoginFailure();
        $acctLocked = $this->loginLimiter->hit($acctKey);

        if ($acctLocked) {
            error_log('SÉCURITÉ: Compte verrouillé suite à plusieurs échecs (anti-bruteforce) : ' . hash('sha256', strtolower(trim($this->email))));
        }

        if ($ipLocked || $acctLocked) {
            $remaining = max(
                $this->loginLimiter->remainingMinutes($this->loginKey()),
                $this->loginLimiter->remainingMinutes($acctKey)
            );
            $this->error = ErrorMessages::format('AUTH_TOO_MANY_ATTEMPTS', [$remaining]);
        } else {
            $this->error = ErrorMessages::get('AUTH_LOGIN_FAILED');
        }

        $this->regenerateCsrfToken();
        error_log('SÉCURITÉ: Tentative de connexion échouée depuis ' . $this->clientIpHash());
    }

    private function registerLoginFailure(): bool
    {
        $locked = $this->loginLimiter->hit($this->loginKey());
        if ($locked) {
            error_log('SÉCURITÉ: IP verrouillée (login) : ' . $this->clientIpHash());
        }
        return $locked;
    }

    private function handlePasswordReset(): void
    {
        if (!$this->verifyCsrf()) {
            $this->showResetForm = true;
            return;
        }

        $resetKey = $this->resetKey();

        if ($this->loginLimiter->isLocked($resetKey)) {
            $this->error = ErrorMessages::format(
                'AUTH_TOO_MANY_ATTEMPTS',
                [$this->loginLimiter->remainingMinutes($resetKey)]
            );
            $this->showResetForm = true;
            return;
        }

        $this->loginLimiter->hit($resetKey);

        if ($this->loginLimiter->isLocked($resetKey)) {
            $this->error = ErrorMessages::format(
                'AUTH_TOO_MANY_ATTEMPTS',
                [$this->loginLimiter->remainingMinutes($resetKey)]
            );
            $this->showResetForm = true;
            error_log('SÉCURITÉ: IP bloquée (reset) : ' . $this->clientIpHash());
            return;
        }

        $recupEmail = $_POST['recup_mail'] ?? '';

        if (!filter_var($recupEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error         = ErrorMessages::get('AUTH_RESET_EMAIL_INVALID');
            $this->showResetForm = true;
            return;
        }

        $user = $this->auth->findUserByEmail($recupEmail);

        if (!$user) {
            $this->regenerateCsrfToken();
            $this->successMessage = ErrorMessages::get('AUTH_RESET_MAIL_SENT');
            return;
        }

        try {
            $token = $this->auth->generateResetToken();

            if (!$this->auth->savePasswordResetRequest($user['user_id'], $token)) {
                $this->error         = ErrorMessages::get('AUTH_RESET_SAVE_ERROR');
                $this->showResetForm = true;
                error_log('SÉCURITÉ: Erreur lors de la sauvegarde du token de réinitialisation');
                return;
            }

            if (!$this->auth->sendResetEmail($recupEmail, $token)) {
                $this->error         = ErrorMessages::get('AUTH_RESET_SEND_ERROR');
                $this->showResetForm = true;
                error_log('SÉCURITÉ: Erreur lors de l\'envoi du mail de réinitialisation pour ' . substr($recupEmail, 0, 5) . '...');
                return;
            }

            $this->regenerateCsrfToken();
            $this->successMessage = ErrorMessages::get('AUTH_RESET_MAIL_SENT');
        } catch (Exception $e) {
            $this->error         = ErrorMessages::get('AUTH_INTERNAL_ERROR');
            $this->showResetForm = true;
            error_log('SÉCURITÉ: Exception lors de la réinitialisation: ' . $e->getMessage());
        }
    }

    private function loginKey(): string
    {
        return 'login:' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * Verrou lié au compte ciblé (résiste efficacement à la rotation d'IP [M-01]).
     * Basé uniquement sur l'identifiant normalisé du compte (email).
     * N'inclut PAS l'adresse IP cliente ni le User-Agent afin de bloquer
     * les attaques par force brute distribuée (multi-IP / proxies / botnets).
     */
    private function accountKey(string $email): string
    {
        return 'login-acct:' . hash('sha256', strtolower(trim($email)));
    }

    private function resetKey(): string
    {
        return 'reset:' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function clientIpHash(): string
    {
        return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getSuccessMessage(): string
    {
        return $this->successMessage;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isShowResetForm(): bool
    {
        return $this->showResetForm;
    }

    public function getFlash(): array
    {
        return $this->flash;
    }
}

$page = new ConnexionPage(
    auth: $auth,
    loginLimiter: $rateLimiter,
);
$page->handle();

require_once __DIR__ . '/html_partials/header.php';
$alertMessage = $page->getError() ?: ($page->getSuccessMessage() ?: ($page->getFlash()['message'] ?? ''));
?>

<main<?= !empty($alertMessage) ? ' data-alert="' . htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    <!-- Formulaire de connexion -->
    <div class="main-connect" style="<?= $page->isShowResetForm() ? 'display:none;' : 'display:block;' ?>">
        <h2 class="title-bar">Connexion</h2>
        <div class="connect">
            <form action="" method="POST" class="login-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="encad form-recup">
                    <label for="email">Email :</label>
                    <input type="email" id="email" class="connect-input email" name="email"
                           value="<?= htmlspecialchars($page->getEmail()) ?>"
                           autocomplete="email" required>

                    <label for="password">Mot de passe :</label>
                    <div class="mdp">
                        <input type="password" id="password" class="connect-input" name="password"
                               autocomplete="current-password" required>
                        <i class="fa-solid fa-eye"></i>
                    </div>

                    <a href="#" id="forgot" class="mdp_oublie"
                       title="Oublie ou modification du mot de passe.">Mot de passe oublié</a>
                    <input type="submit" class="connect-submit" value="Connectez-vous" name="valid-button">
                </div>
            </form>
        </div>
    </div>

    <!-- Formulaire de réinitialisation du mot de passe -->
    <div class="main-change-password" style="<?= $page->isShowResetForm() ? 'display:block;' : 'display:none;' ?>">
        <h2 class="title-bar">Mot de passe oublié</h2>
        <div class="connect">
            <form action="" method="POST" class="login-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="encad form-recup">
                    <label for="recup_mail">Email :</label>
                    <input type="email" id="recup_mail" class="connect-input email" name="recup_mail"
                           autocomplete="email" required>

                    <input type="submit" class="connect-submit" name="recup-submit" value="Réinitialiser">
                    <input type="submit" class="connect-submit" name="annuler"      value="Annuler">
                </div>
            </form>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/html_partials/footer.php'); ?>
