<?php
require_once __DIR__ . '/Database.php';

/**
 * Classe Auth
 * Gère l'authentification des utilisateurs, la gestion des sessions et la réinitialisation des mots de passe.
 */
class Auth
{
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Tente de connecter un utilisateur avec son email et mot de passe
     * @param string $email
     * @param string $password
     * @return bool true si connexion réussie, false sinon
     */
    public function login(string $email, string $password): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Stocker les informations essentielles en session
            $_SESSION['email'] = $user['email'];
            $_SESSION['firstName'] = $user['firstName'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_id'] = $user['user_id'];
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
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
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
        return bin2hex(random_bytes(50));
    }

    /**
     * Enregistre une demande de réinitialisation de mot de passe
     * @param int $userId
     * @param string $token
     * @return bool
     */
    public function savePasswordResetRequest(int $userId, string $token): bool
    {
        $dateRequest = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("INSERT INTO password_reset_request (user_id, date_request, token) VALUES (:user_id, :date_request, :token)");
        return $stmt->execute([
            'user_id' => $userId,
            'date_request' => $dateRequest,
            'token' => $token
        ]);
    }

    /**
     * Envoie un email de réinitialisation de mot de passe
     * @param string $email
     * @param string $token
     * @return bool
     */
        
    public function sendResetEmail(string $email, string $token): bool
    {
        $resetLink = "http://localhost:8081/cosmtt/reset_password.php?token=$token";
        // Adapter selon domaine
        $subject = "=?UTF-8?B?" . base64_encode("Réinitialisation de votre mot de passe") . "?=";
        $message = "Cliquez sur ce lien pour réinitialiser votre mot de passe : 
        $resetLink\nCe lien expirera dans 15 minutes.";
        $headers = "From: no-reply@cosmtt.fr\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
        
        $result = mail($email, $subject, $message, $headers);
        
        // Ajouter un log pour déboguer
        error_log("Email reset - To: $email, Result: " . ($result ? "SUCCESS" : "FAILED"));
        
        return $result;
    }

    /**
     * Recherche un utilisateur par email
     * @param string $email
     * @return array|null
     */
    public function findUserByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}
?>

