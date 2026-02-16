<?php
/**
 * Classe User
 * Gère les opérations liées aux utilisateurs, notamment l'ajout d'un nouvel utilisateur.
 */
class User
{
    /**
     * Instance PDO pour la base de données
     * @var PDO
     */
    private PDO $conn;

    /**
     * Constructeur
     * @param PDO $conn Connexion PDO à la base de données
     */
    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Ajoute un nouvel utilisateur à la base de données après validation des données.
     * @param string $firstName Prénom de l'utilisateur
     * @param string $lastName Nom de l'utilisateur
     * @param string $email Email de l'utilisateur
     * @param string $password Mot de passe en clair
     * @param string $role Rôle de l'utilisateur
     * @return bool true si ajout réussi, false sinon
     * @throws InvalidArgumentException en cas de données invalides
     */
    public function addUser(string $firstName, string $lastName, string $email, string $password, string $role): bool
    {
        // Validation des données
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($role)) {
            throw new InvalidArgumentException("Tous les champs sont obligatoires.");
        }

        if (preg_match('/[0-9]/', $firstName) || preg_match('/[0-9]/', $lastName)) {
            throw new InvalidArgumentException("Le prénom et le nom ne doivent pas contenir de chiffres.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("L'adresse email n'est pas valide.");
        }

        // Vérification si l'email existe déjà
        if ($this->existsEmail($email)) {
            throw new InvalidArgumentException("L'adresse email est déjà utilisée.");
        }

        // Vérification de la longueur minimale du mot de passe
        if (strlen($password) < 8) {
            throw new InvalidArgumentException("Le mot de passe doit contenir au moins 8 caractères.");
        }
        // On peut ajouter d'autres règles de complexité ici si besoin

        // Liste des domaines autorisés
        $allowedDomains = [
            'gmail.com', 'gmail.fr', 'hotmail.fr', 'hotmail.com', 'outlook.fr', 'outlook.com', 'live.com', 'live.fr', 'windowslive.com', 'msn.com', 'orange.fr', 'orange.com', 'wanadoo.fr', 'wanadoo.com', 'sfr.fr',  'neuf.fr', 'bbox.fr', 'free.fr', 'aliceadsl.fr', 'mailo.com', 'laposte.net', 'yahoo.com', 'yahoo.fr', 'proton.me', 'protonmail.com'
        ];
        $domain = substr(strrchr($email, '@'), 1);
        if (!in_array(strtolower($domain), $allowedDomains, true)) {
            throw new InvalidArgumentException("Le domaine de l'email n'est pas autorisé.");
        }

        // Hachage du mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Préparation de la requête SQL
        $sql = "INSERT INTO users (firstName, lastName, email, password, role) VALUES (:firstName, :lastName, :email, :password, :role)";
        $stmt = $this->conn->prepare($sql);

        // Liaison des paramètres
        $stmt->bindValue(':firstName', $firstName);
        $stmt->bindValue(':lastName', $lastName);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':role', $role);

        // Exécution de la requête
        return $stmt->execute();
    }

    /**
     * Vérifie si une adresse email est déjà utilisée par un autre utilisateur
     * @param string $email
     * @return bool true si l'email existe, false sinon
     */
    public function existsEmail(string $email): bool
    {
        $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['email' => $email]);
        $count = $stmt->fetchColumn();
        return $count > 0;
    }
    /**
     * Met à jour un utilisateur existant après validation des données.
     * @param int $user_id ID de l'utilisateur à modifier
     * @param string $firstName Prénom de l'utilisateur
     * @param string $lastName Nom de l'utilisateur
     * @param string $email Email de l'utilisateur
     * @param string|null $password Mot de passe en clair (optionnel)
     * @param string $role Rôle de l'utilisateur
     * @return bool true si mise à jour réussie, false sinon
     * @throws InvalidArgumentException en cas de données invalides
     */
    public function updateUser(int $user_id, string $firstName, string $lastName, string $email, ?string $password, string $role): bool
    {
        // Validation des données
        if (empty($firstName) || empty($lastName) || empty($email) || empty($role)) {
            throw new InvalidArgumentException("Tous les champs sauf le mot de passe sont obligatoires.");
        }

        if (preg_match('/[0-9]/', $firstName) || preg_match('/[0-9]/', $lastName)) {
            throw new InvalidArgumentException("Le prénom et le nom ne doivent pas contenir de chiffres.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("L'adresse email n'est pas valide.");
        }

        // Vérification si l'email existe déjà pour un autre utilisateur
        $sql = "SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['email' => $email, 'user_id' => $user_id]);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            throw new InvalidArgumentException("L'adresse email est déjà utilisée par un autre utilisateur.");
        }

        // Vérification de la longueur minimale du mot de passe si fourni
        if ($password !== null && $password !== '') {
            if (strlen($password) < 8) {
                throw new InvalidArgumentException("Le mot de passe doit contenir au moins 8 caractères.");
            }
            // On peut ajouter d'autres règles de complexité ici si besoin
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        } else {
            $hashedPassword = null;
        }

        // Liste des domaines autorisés
        $allowedDomains = [
            'gmail.com', 'googlemail.com', 'hotmail.fr', 'hotmail.com', 'outlook.fr', 'outlook.com', 'live.com', 'live.fr', 'windowslive.com', 'msn.com', 'orange.fr', 'orange.com', 'wanadoo.fr', 'wanadoo.com', 'sfr.fr', 'bbox.fr', 'free.fr', 'alicepro.fr', 'aliceadsl.fr', 'mailo.com', 'laposte.net', 'yahoo.com', 'yahoo.fr', 'proton.me', 'protonmail.com', 'pm.me', 'icloud.com', 'me.com', 'mac.com'
        ];
        $domain = substr(strrchr($email, '@'), 1);
        if (!in_array(strtolower($domain), $allowedDomains, true)) {
            throw new InvalidArgumentException("Le domaine de l'email n'est pas autorisé.");
        }

        // Préparation de la requête SQL
        $sql = "UPDATE users SET firstName = :firstName, lastName = :lastName, email = :email, role = :role";
        if ($hashedPassword !== null) {
            $sql .= ", password = :password";
        }
        $sql .= " WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($sql);

        // Liaison des paramètres
        $stmt->bindValue(':firstName', $firstName);
        $stmt->bindValue(':lastName', $lastName);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':role', $role);
        if ($hashedPassword !== null) {
            $stmt->bindValue(':password', $hashedPassword);
        }
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);

        // Exécution de la requête
        return $stmt->execute();
    }

    /**
     * Récupère les informations d'un utilisateur par son ID
     * @param int $user_id
     * @return array|null Tableau associatif des données utilisateur ou null si non trouvé
     */
    public function getUser(int $user_id): ?array
    {
        $sql = "SELECT * FROM users WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Supprime un utilisateur par son ID
     * @param int $user_id
     * @return bool true si suppression réussie, false sinon
     */
    public function deleteUser(int $user_id): bool
    {
        $sql = "DELETE FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$user_id]);
    }
}
?>
