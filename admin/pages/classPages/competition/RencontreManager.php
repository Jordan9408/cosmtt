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
        // Ajout de j.type_journee au SELECT
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
        $journeesEquipes = [];

        $equipesAssoc = array_column($equipes, 'NomEquipe', 'NomEquipe');
        $mapMatchIdToJourneeId = array_column($rencontres, 'journee_id', 'id');
        $journeesFromDb = $this->getJourneesByPoule($poule);
        $mapJourneeIdToJourneeName = array_column($journeesFromDb, 'journee_id', 'id');

        // 1. Identifier les correspondances à mettre à jour
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

        // 2. Validation individuelle de chaque correspondance et collecte de données
        foreach (array_keys($matchIds) as $id) {
            $current = array_filter($rencontres, fn($r) => $r['id'] == $id);
            $current = reset($current);

            if (!$current) {
                $errors['Général'][] = "Match avec ID $id non trouvé.";
                continue;
            }

            $journeeId = $current['journee_id'];
            $journeeName = $mapJourneeIdToJourneeName[$journeeId] ?? "Inconnue";

            $update = [
                'id' => $id,
                'new_equipe1' => $current['equipe1'],
                'new_equipe2' => $current['equipe2'],
                'new_score1'  => $current['score1'],
                'new_score2'  => $current['score2'],
            ];

            // Gestion d'équipe (superAdmin)
            if ($role === 'superAdmin') {
                $e1 = trim($postData['equipe1'][$id] ?? '');
                $e2 = trim($postData['equipe2'][$id] ?? '');
                $update['new_equipe1'] = ($e1 === '' || $e1 === 'Aucune équipe') ? null : $e1;
                $update['new_equipe2'] = ($e2 === '' || $e2 === 'Aucune équipe') ? null : $e2;

                if ($update['new_equipe1'] && !isset($equipesAssoc[$update['new_equipe1']])) {
                    $errors[$journeeName][] = "Équipe domicile invalide pour le match " . $id;
                }
                if ($update['new_equipe2'] && !isset($equipesAssoc[$update['new_equipe2']])) {
                    $errors[$journeeName][] = "Équipe extérieure invalide pour le match " . $id;
                }
                if ($update['new_equipe1'] && $update['new_equipe1'] === $update['new_equipe2']) {
                    $errors[$journeeName][] = "Pour un match, l'équipe domicile et l'équipe extérieure ne peuvent pas être identiques.";
                }
            }
            
            $equipe1ForCheck = $update['new_equipe1'];
            $equipe2ForCheck = $update['new_equipe2'];

            // Gestion des scores (admin + superAdmin)
            if (in_array($role, ['admin', 'superAdmin'])) {
                $score1_val_str = trim($postData['score1'][$id] ?? '');
                $score2_val_str = trim($postData['score2'][$id] ?? '');

                $e1_name = $equipe1ForCheck ?? 'Equipe1';
                $e2_name = $equipe2ForCheck ?? 'Equipe2';

                if (($score1_val_str !== '' || $score2_val_str !== '') && ($equipe1ForCheck === null || $equipe2ForCheck === null)) {
                    $errors[$journeeName][] = "Les scores ne peuvent pas être saisis si une équipe est manquante pour le match entre $e1_name et $e2_name.";
                    $score1_ok = false;
                    $score2_ok = false;
                } else {
                    $score1_ok = $score1_val_str === '' || filter_var($score1_val_str, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 14]]) !== false;
                    $score2_ok = $score2_val_str === '' || filter_var($score2_val_str, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 14]]) !== false;

                    if (!$score1_ok && !$score2_ok) {
                        $errors[$journeeName][] = "Score1 et Score2 sont invalides pour le match entre $e1_name et $e2_name (entre 0 à 14).";
                    } else if (!$score1_ok) {
                        $errors[$journeeName][] = "Score1 invalide pour le match entre $e1_name et $e2_name (entre 0 à 14).";
                    } else if (!$score2_ok) {
                        $errors[$journeeName][] = "Score2 invalide pour le match entre $e1_name et $e2_name (entre 0 à 14).";
                    }

                    if ($score1_ok && $score2_ok && $score1_val_str !== '' && $score2_val_str !== '') {
                        $score1_int = (int)$score1_val_str;
                        $score2_int = (int)$score2_val_str;
                        if (($score1_int + $score2_int) > 14) {
                            $errors[$journeeName][] = "La somme des scores pour le match entre $e1_name et $e2_name ne peut pas dépasser 14.";
                        }
                    }
                }
                
                if($score1_ok) $update['new_score1'] = ($score1_val_str === '') ? null : (int)$score1_val_str;
                if($score2_ok) $update['new_score2'] = ($score1_val_str === '') ? null : (int)$score2_val_str;
            }

            if ($equipe1ForCheck && $equipe1ForCheck !== 'Aucune équipe') {
                $journeesEquipes[$journeeId][] = $equipe1ForCheck;
            }
            if ($equipe2ForCheck && $equipe2ForCheck !== 'Aucune équipe') {
                $journeesEquipes[$journeeId][] = $equipe2ForCheck;
            }

            $matchUpdates[$id] = $update;
        }

        // 3. Validation des équipes en double par journée
        foreach ($journeesEquipes as $journeeId => $equipesInJournee) {
            $counts = array_count_values($equipesInJournee);
            $duplicates = [];
            foreach ($counts as $equipe => $count) {
                if ($count > 1) {
                    $duplicates[] = $equipe;
                }
            }
            if (!empty($duplicates)) {
                $journeeName = $mapJourneeIdToJourneeName[$journeeId] ?? "Inconnue";
                $equipesStr = implode(', ', $duplicates);
                $verb = count($duplicates) > 1 ? 'jouent' : 'joue';
                $errorMessage = "$equipesStr $verb plusieurs matchs. Une équipe ne peut disputer qu'un seul match par journée.";

                $errors[$journeeName][] = $errorMessage;
            }
        }
        
        // 4. Décision finale : valider ou renvoyer avec des erreurs
        if (!empty($errors)) {
            foreach ($errors as &$journeeErrors) {
                $journeeErrors = array_unique($journeeErrors);
            }
            return ['errors' => $errors, 'success' => null];
        }

        // 5. Exécuter dans la transaction s'il n'y a pas d'erreurs
        try {
            $this->conn->beginTransaction();
            $sql = "UPDATE rencontres SET 
                    equipe1 = :equipe1, 
                    score1 = :score1, 
                    score2 = :score2, 
                    equipe2 = :equipe2 
                WHERE id = :id";
            $stmt = $this->conn->prepare($sql);

            foreach ($matchUpdates as $id => $update) {
                if (isset($matchUpdates[$id])) {
                    $stmt->bindValue(':equipe1', $update['new_equipe1'], $update['new_equipe1'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $stmt->bindValue(':score1', $update['new_score1'], $update['new_score1'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $stmt->bindValue(':score2', $update['new_score2'], $update['new_score2'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $stmt->bindValue(':equipe2', $update['new_equipe2'], $update['new_equipe2'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $stmt->bindValue(':id', $update['id'], PDO::PARAM_INT);
                    $stmt->execute();
                }
            }

            $this->conn->commit();
            return ['errors' => [], 'success' => ''];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return ['errors' => ['Général' => ["Erreur base de données : " . $e->getMessage()]], 'success' => null];
        }
    }
}
// var_dump('RencontreManager.php - Ok');
?>