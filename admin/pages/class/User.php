<?php

class User
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Ajoute un nouvel utilisateur.
     *
     * @throws InvalidArgumentException
     */
    public function addUser(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        string $role
    ): bool {
        $this->validateNames($firstName, $lastName);
        $this->validateEmail($email);
        $this->validatePassword($password);

        if ($this->existsEmail($email)) {
            throw new InvalidArgumentException("L'adresse email est déjà utilisée.");
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO users (firstName, lastName, email, password, role)
             VALUES (:firstName, :lastName, :email, :password, :role)"
        );

        return $stmt->execute([
            ':firstName' => $firstName,
            ':lastName'  => $lastName,
            ':email'     => $email,
            ':password'  => password_hash($password, PASSWORD_DEFAULT),
            ':role'      => $role,
        ]);
    }

    /**
     * Met à jour un utilisateur existant.
     * Le mot de passe est optionnel : null ou chaîne vide = inchangé.
     *
     * @throws InvalidArgumentException
     */
    public function updateUser(
        int $user_id,
        string $firstName,
        string $lastName,
        string $email,
        ?string $password,
        string $role
    ): bool {
        $this->validateNames($firstName, $lastName);
        $this->validateEmail($email);

        if ($this->existsEmailForOther($email, $user_id)) {
            throw new InvalidArgumentException("L'adresse email est déjà utilisée par un autre utilisateur.");
        }

        $hashedPassword = null;
        if ($password !== null && $password !== '') {
            $this->validatePassword($password);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql = "UPDATE users
                SET firstName = :firstName, lastName = :lastName, email = :email, role = :role"
             . ($hashedPassword !== null ? ", password = :password" : "")
             . " WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($sql);

        $params = [
            ':firstName' => $firstName,
            ':lastName'  => $lastName,
            ':email'     => $email,
            ':role'      => $role,
            ':user_id'   => $user_id,
        ];

        if ($hashedPassword !== null) {
            $params[':password'] = $hashedPassword;
        }

        return $stmt->execute($params);
    }

    /**
     * Retourne les données d'un utilisateur, ou null s'il est introuvable.
     */
    public function getUser(int $user_id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Supprime un utilisateur et ses données associées (droit à l'oubli).
     */
    public function deleteUser(int $user_id): bool
    {
        try {
            $this->conn->beginTransaction();

            $this->conn->prepare("DELETE FROM password_reset_request WHERE user_id = ?")
                       ->execute([$user_id]);

            $stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = ?");
            $result = $stmt->execute([$user_id]);

            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Erreur lors de la suppression de l'utilisateur $user_id : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si une adresse email est déjà utilisée.
     */
    public function existsEmail(string $email): bool
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // -------------------------------------------------------------------------
    // Méthodes privées de validation
    // -------------------------------------------------------------------------

    private function validateNames(string $firstName, string $lastName): void
    {
        if (empty($firstName) || empty($lastName)) {
            throw new InvalidArgumentException("Le prénom et le nom sont obligatoires.");
        }

        if (preg_match('/[0-9]/', $firstName) || preg_match('/[0-9]/', $lastName)) {
            throw new InvalidArgumentException("Le prénom et le nom ne doivent pas contenir de chiffres.");
        }
    }

    private function validateEmail(string $email): void
    {
        if (empty($email)) {
            throw new InvalidArgumentException("L'adresse email est obligatoire.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("L'adresse email n'est pas valide.");
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1));
        if ($domain === '' || (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A'))) {
            throw new InvalidArgumentException("Le domaine de l'adresse email semble inexistant.");
        }
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 12) {
            throw new InvalidArgumentException("Le mot de passe doit contenir au moins 12 caractères.");
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new InvalidArgumentException("Le mot de passe doit contenir au moins une majuscule.");
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw new InvalidArgumentException("Le mot de passe doit contenir au moins une minuscule.");
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException("Le mot de passe doit contenir au moins un chiffre.");
        }
        if (!preg_match('/[\W_]/', $password)) {
            throw new InvalidArgumentException("Le mot de passe doit contenir au moins un caractère spécial.");
        }
    }

    /**
     * Vérifie si une adresse email est utilisée par un utilisateur autre que $excludeId.
     */
    private function existsEmailForOther(string $email, int $excludeId): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id"
        );
        $stmt->execute([':email' => $email, ':user_id' => $excludeId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}