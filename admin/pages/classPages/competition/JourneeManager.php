<?php
/**
 * Classe pour gérer les opérations sur les journées.
 */
class JourneeManager
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Vérifie si une journée existe, sinon l'insère et retourne l'ID.
     */
    public function obtenirOuCreerJournee(string $journeeId, string $dateDebut, string $dateFin, string $poule, string $typeJournee): int
    {
        $journeeId = ucfirst($journeeId);

        // On vérifie maintenant avec le type de journée pour l'unicité
        $stmt = $this->conn->prepare("SELECT id FROM journeesChampionnat WHERE journee_id = :journee AND poule = :poule AND type_journee = :type_journee");
        $stmt->bindValue(':journee', $journeeId, PDO::PARAM_STR);
        $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);
        $stmt->bindValue(':type_journee', $typeJournee, PDO::PARAM_STR); // Ajout
        $stmt->execute();
        $journeeExist = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($journeeExist) {
            return $journeeExist['id'];
        }

        // Ajout de type_journee à l'insertion
        $stmt = $this->conn->prepare("INSERT INTO journeesChampionnat (journee_id, dateDebut, dateFin, poule, type_journee) VALUES (:journee, :dateDebut, :dateFin, :poule, :type_journee)");
        $stmt->bindValue(':journee', $journeeId, PDO::PARAM_STR);
        $stmt->bindValue(':dateDebut', $dateDebut, PDO::PARAM_STR);
        $stmt->bindValue(':dateFin', $dateFin, PDO::PARAM_STR);
        $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);
        $stmt->bindValue(':type_journee', $typeJournee, PDO::PARAM_STR); // Ajout
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    /**
     * Supprime une journée et toutes les rencontres associées dans une transaction.
     * @param int $journeeId L'ID de la journée à supprimer.
     * @return bool Vrai si la suppression a réussi.
     * @throws PDOException En cas d'erreur de base de données.
     */
    public function supprimerJourneeEtRencontres(int $journeeId): bool
    {
        try {
            $this->conn->beginTransaction();

            // 1. Suppression des rencontres associées à cette journée
            $stmtRencontres = $this->conn->prepare("DELETE FROM rencontres WHERE journee_id = :journee_id");
            $stmtRencontres->bindValue(':journee_id', $journeeId, PDO::PARAM_INT);
            $stmtRencontres->execute();

            // 2. Suppression de la journée elle-même
            $stmtJournee = $this->conn->prepare("DELETE FROM journeesChampionnat WHERE id = :journee_id");
            $stmtJournee->bindValue(':journee_id', $journeeId, PDO::PARAM_INT);
            $stmtJournee->execute();

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            // Relancer l'exception pour que le contrôleur puisse la gérer et afficher l'erreur
            throw $e;
        }
    }
}
// var_dump('JourneeManager.php - Ok');
?>