<?php
/**
 * Gestion des journées (CRUD côté DB) et des rencontres associées.
 *
 * Objectifs :
 * - POO lisible
 * - PHP 8+ : strict types + typage
 */

declare(strict_types=1);

final class JourneeManager
{
    public function __construct(private PDO $conn) {}

    /**
     * Vérifie si une journée existe, sinon l'insère et retourne l'ID.
     */
    public function obtenirOuCreerJournee(
        string $journeeId,
        string $dateDebut,
        string $dateFin,
        string $poule,
        string $typeJournee
    ): int {
        $journeeId = ucfirst($journeeId);

        $stmt = $this->conn->prepare(
            "SELECT id
             FROM journeesChampionnat
             WHERE journee_id = :journee
               AND poule = :poule
               AND type_journee = :type_journee
             LIMIT 1"
        );
        $stmt->execute([
            ':journee'      => $journeeId,
            ':poule'        => $poule,
            ':type_journee' => $typeJournee,
        ]);

        $journeeExist = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($journeeExist) {
            return (int) $journeeExist['id'];
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO journeesChampionnat
                (journee_id, dateDebut, dateFin, poule, type_journee)
             VALUES
                (:journee, :dateDebut, :dateFin, :poule, :type_journee)"
        );
        $stmt->execute([
            ':journee'      => $journeeId,
            ':dateDebut'    => $dateDebut,
            ':dateFin'      => $dateFin,
            ':poule'        => $poule,
            ':type_journee' => $typeJournee,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Supprime une journée et toutes les rencontres associées dans une transaction.
     *
     * @throws PDOException
     */
    public function supprimerJourneeEtRencontres(int $journeeId): bool
    {
        $this->conn->beginTransaction();

        try {
            $stmtRencontres = $this->conn->prepare(
                "DELETE FROM rencontres WHERE journee_id = :journee_id"
            );
            $stmtRencontres->execute([':journee_id' => $journeeId]);

            $stmtJournee = $this->conn->prepare(
                "DELETE FROM journeesChampionnat WHERE id = :journee_id"
            );
            $stmtJournee->execute([':journee_id' => $journeeId]);

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}

