<?php
/**
 * Classe pour gérer les rencontres
 */
class RencontreManager
{
    private $conn;

    public function __construct(PDO $connection)
    {
        $this->conn = $connection;
    }

    public function getRencontresByPoule(string $poule): array
    {
        // Ajout de j.type_journee au SELEC
        $sql = "SELECT r.id, r.journee_id, r.equipe1, r.score1, r.score2, r.equipe2, j.dateDebut, j.dateFin, j.type_journee
                FROM rencontres r
                INNER JOIN journeesChampionnat j ON r.journee_id = j.id
                WHERE j.poule = :poule
                  AND (r.equipe1 IN (SELECT NomEquipe FROM Equipes WHERE poules = :poule) OR r.equipe2 IN (SELECT NomEquipe FROM Equipes WHERE poules = :poule) OR r.equipe1 IS NULL OR r.equipe2 IS NULL)
                ORDER BY j.journee_id ASC, r.id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getJourneesByPoule(string $poule): array
    {
        $sql = "SELECT DISTINCT j.id, j.journee_id, j.dateDebut, j.dateFin, j.type_journee
                FROM journeesChampionnat j
                INNER JOIN rencontres r ON r.journee_id = j.id
                WHERE j.poule = :poule
                  AND ((r.equipe1 IN (SELECT NomEquipe FROM Equipes WHERE poules = :poule) OR r.equipe1 IS NULL)
                   OR (r.equipe2 IN (SELECT NomEquipe FROM Equipes WHERE poules = :poule) OR r.equipe2 IS NULL))
                ORDER BY j.dateDebut ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insererRencontres(array $rencontres): void
    {
        try {
            $this->conn->beginTransaction();
            foreach ($rencontres as $rencontre) {
                $sql = "INSERT INTO rencontres (journee_id, equipe1, score1, score2, equipe2) VALUES (:journee_id, :equipe1, :score1, :score2, :equipe2)";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(':journee_id', $rencontre['journee_id'], PDO::PARAM_INT);
                $stmt->bindValue(':equipe1', $rencontre['equipe1'], $rencontre['equipe1'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(':score1', $rencontre['score1'], $rencontre['score1'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':score2', $rencontre['score2'], $rencontre['score2'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':equipe2', $rencontre['equipe2'], $rencontre['equipe2'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->execute();
            }
            $this->conn->commit();
        } catch (PDOException $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Supprime une journée et toutes ses rencontres associées.
     */
    public function deleteJournee(int $journeeId): bool
    {
        try {
            $this->conn->beginTransaction();

            // Supprimer les rencontres associées à la journée
            $stmt = $this->conn->prepare("DELETE FROM rencontres WHERE journee_id = :journee_id");
            $stmt->bindValue(':journee_id', $journeeId, PDO::PARAM_INT);
            $stmt->execute();

            // Supprimer la journée
            $stmt = $this->conn->prepare("DELETE FROM journeesChampionnat WHERE id = :id");
            $stmt->bindValue(':id', $journeeId, PDO::PARAM_INT);
            $stmt->execute();

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Met à jour les rencontres basées sur les données POST, avec validation et sécurité.
     */
    public function mettreAJourRencontres(array $postData, array $equipes, string $poule, string $role): array
    {
        $errors = [];
        $rencontres = $this->getRencontresByPoule($poule);
        $matchUpdates = [];

        // Associer les équipes valides
        $equipesAssoc = array_column($equipes, 'NomEquipe', 'NomEquipe');

        // Identifier les matchs à mettre à jour
        $matchIds = [];
        $arraysToCheck = ['score1', 'score2'];
        if ($role === 'superAdmin') {
            $arraysToCheck = array_merge($arraysToCheck, ['equipe1', 'equipe2']);
        }
        foreach ($arraysToCheck as $field) {
            if (isset($postData[$field]) && is_array($postData[$field])) {
                foreach (array_keys($postData[$field]) as $id) {
                    $matchId = intval($id);
                    if ($matchId > 0) {
                        $matchIds[$matchId] = true;
                    }
                }
            }
        }

        // Préparer les mises à jour
        foreach (array_keys($matchIds) as $id) {
            $current = array_filter($rencontres, fn($r) => $r['id'] == $id);
            $current = reset($current);

            if (!$current) {
                $errors[] = "Match avec ID $id non trouvé.";
                continue;
            }

            $update = [
                'id' => $id,
                'journee_id' => $current['journee_id'],
                'new_equipe1' => $current['equipe1'],
                'new_equipe2' => $current['equipe2'],
                'new_score1'  => $current['score1'],
                'new_score2'  => $current['score2'],
            ];

            // 🔹 Gestion des équipes (superAdmin uniquement)
            if ($role === 'superAdmin') {
                $e1 = trim($postData['equipe1'][$id] ?? '');
                $e2 = trim($postData['equipe2'][$id] ?? '');

                $update['new_equipe1'] = ($e1 === '' || $e1 === 'Aucune équipe') ? null : $e1;
                $update['new_equipe2'] = ($e2 === '' || $e2 === 'Aucune équipe') ? null : $e2;

                if ($update['new_equipe1'] && !isset($equipesAssoc[$update['new_equipe1']])) {
                    $errors[] = "Équipe domicile invalide pour le match $id.";
                }
                if ($update['new_equipe2'] && !isset($equipesAssoc[$update['new_equipe2']])) {
                    $errors[] = "Équipe extérieure invalide pour le match $id.";
                }
                if ($update['new_equipe1'] && $update['new_equipe1'] === $update['new_equipe2']) {
                    $errors[] = "Les équipes du match $id doivent être différentes.";
                }
            }

            // 🔹 Gestion des scores (admin + superAdmin)
            if (in_array($role, ['admin', 'superAdmin'])) {
                foreach (['score1', 'score2'] as $s) {

                    // 1. Récupération de la valeur (trim pour gérer les espaces blancs)
                    $val = trim($postData[$s][$id] ?? '');

                    // 2. LOGIQUE MODIFIÉE : Si la valeur est vide après nettoyage
                    if ($val === '') {
                        // L'utilisateur a vidé le champ => on force la valeur à NULL pour la BDD
                        $update["new_$s"] = null;
                    } else {
                        // 3. LOGIQUE EXISTANTE : Si une valeur est présente, on la valide
                        $filtered = filter_var($val, FILTER_VALIDATE_INT, [
                            'options' => ['min_range' => 0, 'max_range' => 14]
                        ]);

                        if ($filtered === false) {
                            $errors[] = ucfirst($s) . " invalide pour le match $id (0 à 14).";
                        } else {
                            $update["new_$s"] = $filtered;
                        }
                    }
                }
            }

            $matchUpdates[$id] = $update;
        }

        if (!empty($errors)) {
            return ['errors' => $errors, 'success' => null];
        }

        // 🔹 Exécution en transaction
        try {
            $this->conn->beginTransaction();
            $sql = "UPDATE rencontres SET 
                    equipe1 = :equipe1, 
                    score1 = :score1, 
                    score2 = :score2, 
                    equipe2 = :equipe2 
                WHERE id = :id";
            $stmt = $this->conn->prepare($sql);

            foreach ($matchUpdates as $update) {
                $stmt->bindValue(':equipe1', $update['new_equipe1'], $update['new_equipe1'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(':score1', $update['new_score1'], $update['new_score1'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':score2', $update['new_score2'], $update['new_score2'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':equipe2', $update['new_equipe2'], $update['new_equipe2'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(':id', $update['id'], PDO::PARAM_INT);
                $stmt->execute();
            }

            $this->conn->commit();
            return ['errors' => [], 'success'=>''];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return ['errors' => ["Erreur base de données : " . $e->getMessage()], 'success' => null];
        }
    }
}
// var_dump('RencontreManager.php - Ok');
?>