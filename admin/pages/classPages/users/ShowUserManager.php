<?php
declare(strict_types=1);

class ShowUserManager
{
    // PHP 8 : Promotion du constructeur pour initialiser la connexion PDO
    public function __construct(private PDO $conn)
    {
    }

    /**
     * Récupère la liste des utilisateurs depuis la base de données
     */
    public function getUsers(): array
    {
        $sql = "SELECT * FROM users";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        
        // Retourne un tableau vide si aucun résultat n'est trouvé
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
?>