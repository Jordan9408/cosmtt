<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once dirname(__DIR__, 3) . '/include/session_boot.php';
require_once dirname(__DIR__, 3) . '/config/load_env.php';

/**
 * Classe Auth
 * Gère l'authentification des utilisateurs, la gestion des sessions et la réinitialisation des mots de passe.
 */
class Auth
{
    /** Nombre d'octets pour le token CSRF (32 octets = 64 caractères hexadécimaux) */
    private const CSRF_TOKEN_BYTES = 32;

    /** Nombre d'octets pour le token de réinitialisation de mot de passe */
    private const RESET_TOKEN_BYTES = 50;

    /** Durée de validité (en minutes) d'une demande de réinitialisation de mot de passe */
    private const RESET_TOKEN_EXPIRY_MINUTES = 15;

    /** URL de base du formulaire de réinitialisation (surcharge possible via APP_URL dans .env) */

    /**
     * Instance PDO pour la base de données
     * @var PDO
     */
    private PDO $db;

    /**
     * Constructeur
     * Initialise la connexion à la base de données via la classe DatabaseConnection
     */
    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();

    }

    /**
     * Tente de connecter un utilisateur avec son email et mot de passe
     * @param string $email
     * @param string $password
     * @return bool true si connexion réussie, false sinon
     */
    public function login(string $email, string $password): bool
    {
        $user = $this->fetchUserRecordByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            // Régénérer l'ID de session pour prévenir la fixation de session
            session_regenerate_id(true);

            // Stocker les informations essentielles en session
            $_SESSION['email'] = $user['email'];
            $_SESSION['firstName'] = $user['firstName'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['auth_password_fingerprint'] = hash('sha256', $user['password']);

            // Générer un nouveau token CSRF
            $_SESSION['csrf_token'] = bin2hex(random_bytes(self::CSRF_TOKEN_BYTES));

            return true;
        }

        return false;
    }

    /**
     * Déconnecte l'utilisateur en détruisant la session
     * @return void
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Vérifie si un utilisateur est connecté
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['email']);
    }

    /**
     * Génère un token sécurisé pour la réinitialisation de mot de passe
     * @return string
     * @throws Exception
     */
    public function generateResetToken(): string
    {
        return bin2hex(random_bytes(self::RESET_TOKEN_BYTES));
    }

    public function hashResetToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function resetFingerprint(): string
    {
        return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    public function savePasswordResetRequest(int $userId, string $token): bool
    {
        $this->ensureResetTable();

        $tokenHash = $this->hashResetToken($token);

        $this->db->prepare('DELETE FROM password_reset_request WHERE user_id = :user_id OR expires_date < NOW()')
            ->execute(['user_id' => $userId]);

        // date_request et expires_date sont calculées par MySQL (NOW() / DATE_ADD), et non par PHP,
        // pour rester cohérentes avec le NOW() utilisé côté lecture (findValidPasswordResetRequest)
        // même en cas de décalage entre l'horloge PHP et l'horloge/fuseau de MySQL.
        $stmt = $this->db->prepare(
            'INSERT INTO password_reset_request (user_id, date_request, expires_date, token, fingerprint) 
             VALUES (:user_id, NOW(), DATE_ADD(NOW(), INTERVAL :expiry_minutes MINUTE), :token, :fingerprint)'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':expiry_minutes', self::RESET_TOKEN_EXPIRY_MINUTES, PDO::PARAM_INT);
        $stmt->bindValue(':token', $tokenHash, PDO::PARAM_STR);
        $stmt->bindValue(':fingerprint', $this->resetFingerprint(), PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Crée la table de réinitialisation si elle n'existe pas (idempotent).
     * Ajoute la colonne fingerprint pour lier le token à une empreinte (IP + User-Agent).
     */
    private function ensureResetTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS password_reset_request (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                date_request DATETIME NOT NULL,
                expires_date DATETIME NOT NULL,
                token VARCHAR(64) NOT NULL,
                fingerprint VARCHAR(64) NOT NULL DEFAULT ""
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $cols = $this->db->query('SHOW COLUMNS FROM password_reset_request')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('fingerprint', $cols, true)) {
            $this->db->exec('ALTER TABLE password_reset_request ADD COLUMN fingerprint VARCHAR(64) NOT NULL DEFAULT ""');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findValidPasswordResetRequest(string $plainToken): ?array
    {
        if ($plainToken === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM password_reset_request WHERE token = :token AND expires_date >= NOW() LIMIT 1'
        );
        $stmt->execute(['token' => $this->hashResetToken($plainToken)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        // L'empreinte (IP/User-Agent) n'est plus bloquante pour permettre à l'utilisateur
        // d'ouvrir son email et le lien depuis un autre appareil ou navigateur.
        // La sécurité repose sur le token cryptographique aléatoire à usage unique et sa durée de validité.

        return $row;
    }

    public function consumePasswordResetRequest(string $plainToken): void
    {
        $this->db->prepare('DELETE FROM password_reset_request WHERE token = :token')
            ->execute(['token' => $this->hashResetToken($plainToken)]);
    }

    /**
     * Invalide toutes les sessions actives de l'utilisateur après reset de mot de passe.
     * On force la régénération/clear de la session courante (l'utilisateur vient de prouver le contrôle du compte).
     */
    public function invalidateSessionsAfterReset(int $userId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['auth_created'] = time();
        }
    }

    /**
     * Envoie un email de réinitialisation de mot de passe
     * @param string $email
     * @param string $token
     * @return bool
     */
    public function sendResetEmail(string $email, string $token): bool
    {
        $subject = $this->buildResetEmailSubject();
        $body = $this->buildResetEmailBody($this->buildResetLink($token));

        // On évite mail() pour supprimer l'erreur localhost:25 et être déployable.
        // PHPMailer est déjà présent dans /phpmailer.
        // Charger la config SMTP (variables d'environnement)
        require_once dirname(__DIR__, 3) . '/config/email.php';
        require_once __DIR__ . '/EmailConfig.php';
        require_once __DIR__ . '/PasswordResetMailer.php';

        $fromName = \Admin\Pages\Class\EmailConfig::fromName();
        $fromAddress = \Admin\Pages\Class\EmailConfig::fromAddress();

        return \Admin\Pages\Class\PasswordResetMailer::send(
            $email,
            $subject,
            $body,
            $fromName,
            $fromAddress
        );
    }

    /**
     * Recherche un utilisateur par email
     * @param string $email
     * @return array|null
     */
    public function findUserByEmail(string $email): ?array
    {
        $user = $this->fetchUserRecordByEmail($email);

        return $user !== false ? $user : null;
    }

    /**
     * Récupère l'enregistrement complet d'un utilisateur via son email
     * (factorise la requête commune à login() et findUserByEmail())
     * @param string $email
     * @return array|false
     */
    private function fetchUserRecordByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Construit le lien de réinitialisation à partir du token
     * @param string $token
     * @return string
     */
    private function buildResetLink(string $token): string
    {
        return $this->resetPasswordUrl() . '?token=' . urlencode($token);
    }

    private function resetPasswordUrl(): string
    {
        $configured = getenv('APP_URL');
        $parts = is_string($configured) ? parse_url($configured) : false;

        if (
            !is_array($parts)
            || empty($parts['scheme'])
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || array_key_exists('query', $parts)
            || array_key_exists('fragment', $parts)
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            throw new RuntimeException('APP_URL doit contenir une URL canonique valide.');
        }

        $host = trim($parts['host'], '[]');
        $validHost = strtolower($host) === 'localhost'
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
        if (!$validHost) {
            throw new RuntimeException('APP_URL doit contenir un nom d’hôte valide.');
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme === 'http' && !in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            $scheme = 'https';
        }

        $baseUrl = rtrim($configured, '/');
        if (strtolower($parts['scheme']) !== $scheme) {
            $baseUrl = $scheme . substr($baseUrl, strlen($parts['scheme']));
        }

        return $baseUrl . '/reset_password.php';
    }

    /**
     * Construit le sujet (encodé UTF-8/Base64) de l'email de réinitialisation
     * @return string
     */
    private function buildResetEmailSubject(): string
    {
        return '=?UTF-8?B?' . base64_encode('Réinitialisation de votre mot de passe') . '?=';
    }

    /**
     * Construit le corps texte de l'email de réinitialisation
     * @param string $resetLink
     * @return string
     */
    private function buildResetEmailBody(string $resetLink): string
    {
        return sprintf(
            "Bonjour,\r\n\r\nCliquez sur ce lien pour réinitialiser votre mot de passe :\r\n%s\r\n\r\nCe lien expirera dans %d minutes.\r\n\r\nCordialement,\r\nL'équipe COSM",
            $resetLink,
            self::RESET_TOKEN_EXPIRY_MINUTES
        );
    }

    /**
     * Construit les en-têtes de l'email de réinitialisation
     * @return string
     */
    private function buildResetEmailHeaders(): string
    {
        return implode("\r\n", [
            'From: no-reply@cosmtt.fr',
            'Reply-To: no-reply@cosmtt.fr',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ]) . "\r\n";
    }
}