<?php

/**
 * Classe pour gérer les équipes
 */
class EquipeManager
{
    private $conn;

    public function __construct(PDO $connection)
    {
        $this->conn = $connection;
    }

    public function getEquipesByPoule(string $poule): array
    {
        $sql = "SELECT equipe_id, NomEquipe FROM Equipes WHERE poules = :poule ORDER BY NomEquipe ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
// var_dump('EquipeManager.php - Ok');
?>