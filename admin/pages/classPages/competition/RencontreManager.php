<?php
/**
 * RencontreManager
 * Gestion des rencontres côté base de données.
 *
 * Objectifs :
 * - POO lisible
 * - PHP 8+ (strict types, typage)
 * - Nettoyage (suppression de bruit, factorisation)
 */

declare(strict_types=1);

final class RencontreManager
{
    public function __construct(private PDO $conn) {}

    /**
     * Renvoie les rencontres liées à une poule.
     * Inclut aussi les infos de la journée (dateDebut/dateFin/type_journee).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRencontresByPoule(string $poule): array
    {
        $sql = "SELECT 
                    r.id,
                    r.journee_id,
                    r.equipe1,
                    r.score1,
                    r.score2,
                    r.equipe2,
                    j.dateDebut,
                    j.dateFin,
                    j.type_journee
                FROM rencontres r
                INNER JOIN journeesChampionnat j ON r.journee_id = j.id
                WHERE j.poule = :poule
                  AND (
                        r.equipe1 IN (SELECT NomEquipe FROM Equipes WHERE poules = :poule2)
                     OR r.equipe2 IN (SELECT NomEquipe FROM Equipes WHERE poules = :poule3)
                     OR r.equipe1 IS NULL
                     OR r.equipe2 IS NULL
                  )
                ORDER BY j.journee_id ASC, r.id ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':poule' => $poule,
            ':poule2' => $poule,
            ':poule3' => $poule,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Renvoie la liste des journées d'une poule qui ont au moins une rencontre.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getJourneesByPoule(string $poule): array
    {
        $sql = "SELECT DISTINCT
                    j.id,
                    j.journee_id,
                    j.dateDebut,
                    j.dateFin,
                    j.type_journee,
                    j.poule
                FROM journeesChampionnat j
                INNER JOIN rencontres r ON r.journee_id = j.id
                WHERE j.poule = :poule
                  AND (
                        (r.equipe1 IN (SELECT NomEquipe FROM Equipes WHERE poules = :poule2) OR r.equipe1 IS NULL)
                     OR (r.equipe2 IN (SELECT NomEquipe FROM Equipes WHERE poules = :poule3) OR r.equipe2 IS NULL)
                  )
                ORDER BY j.dateDebut ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':poule' => $poule,
            ':poule2' => $poule,
            ':poule3' => $poule,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Insertion multiple de rencontres.
     *
     * @param array<int, array{journee_id:int, equipe1:?string, score1:?int, score2:?int, equipe2:?string}> $rencontres
     */
    public function insererRencontres(array $rencontres): void
    {
        $this->conn->beginTransaction();

        try {
            $sql = "INSERT INTO rencontres (journee_id, equipe1, score1, score2, equipe2)
                    VALUES (:journee_id, :equipe1, :score1, :score2, :equipe2)";

            $stmt = $this->conn->prepare($sql);

            foreach ($rencontres as $rencontre) {
                $stmt->bindValue(':journee_id', $rencontre['journee_id'], PDO::PARAM_INT);

                $stmt->bindValue(
                    ':equipe1',
                    $rencontre['equipe1'],
                    $rencontre['equipe1'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
                );
                $stmt->bindValue(
                    ':score1',
                    $rencontre['score1'],
                    $rencontre['score1'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT
                );
                $stmt->bindValue(
                    ':score2',
                    $rencontre['score2'],
                    $rencontre['score2'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT
                );
                $stmt->bindValue(
                    ':equipe2',
                    $rencontre['equipe2'],
                    $rencontre['equipe2'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
                );

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
        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare("DELETE FROM rencontres WHERE journee_id = :journee_id");
            $stmt->execute([':journee_id' => $journeeId]);

            $stmt = $this->conn->prepare("DELETE FROM journeesChampionnat WHERE id = :id");
            $stmt->execute([':id' => $journeeId]);

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Met à jour les rencontres basées sur les données POST, avec validation et sécurité.
     *
     * @param array<string, mixed> $postData
     * @param array<int, array<string, mixed>> $equipes
     * @return array{errors: array<string, array<int, string>>, success: ?string}
     */
    public function mettreAJourRencontres(array $postData, array $equipes, string $poule, string $role): array
    {
        $errors = [];

        $rencontres = $this->getRencontresByPoule($poule);
        $journeesFromDb = $this->getJourneesByPoule($poule);

        $equipesAssoc = array_column($equipes, 'NomEquipe', 'NomEquipe');
        $matchIds = $this->extraireMatchIdsAReviser($postData, $role);

        if (empty($matchIds)) {
            return ['errors' => [], 'success' => ''];
        }

        $mapMatchIdToRencontre = $this->indexRencontresParId($rencontres);
        $mapJourneeIdToName = array_column($journeesFromDb, 'journee_id', 'id');

        // Prépare les updates
        $matchUpdates = [];
        $journeesEquipes = []; // pour contrôle "une équipe max 1 match / journée"

        foreach (array_keys($matchIds) as $id) {
            $current = $mapMatchIdToRencontre[$id] ?? null;
            if ($current === null) {
                $errors['Général'][] = "Match avec ID $id non trouvé.";
                continue;
            }

            $journeeId = (int) $current['journee_id'];
            $journeeName = $mapJourneeIdToName[$journeeId] ?? 'Inconnue';

            $update = [
                'id' => $id,
                'new_equipe1' => $current['equipe1'],
                'new_equipe2' => $current['equipe2'],
                'new_score1' => $current['score1'],
                'new_score2' => $current['score2'],
            ];

            if ($role === 'superAdmin') {
                $update = $this->appliquerGestionEquipesSuperAdmin($postData, $id, $update, $equipesAssoc, $journeeName, $errors);
            }

            // Scores : admin + superAdmin
            if (in_array($role, ['admin', 'superAdmin'], true)) {
                $this->appliquerGestionScores($postData, $id, $update, $errors, $journeeName);
            }

            // Contrôle : construction des équipes réellement utilisées
            if ($update['new_equipe1'] && $update['new_equipe1'] !== 'Aucune équipe') {
                $journeesEquipes[$journeeId][] = (string) $update['new_equipe1'];
            }
            if ($update['new_equipe2'] && $update['new_equipe2'] !== 'Aucune équipe') {
                $journeesEquipes[$journeeId][] = (string) $update['new_equipe2'];
            }

            $matchUpdates[$id] = $update;
        }

        // Validation : équipes dupliquées dans une journée
        $this->validerDoublonsParJournee($journeesEquipes, $mapJourneeIdToName, $errors);

        // Décision finale
        if (!empty($errors)) {
            foreach ($errors as &$journeeErrors) {
                $journeeErrors = array_values(array_unique($journeeErrors));
            }
            return ['errors' => $errors, 'success' => null];
        }

        // Exécution transactionnelle
        $this->conn->beginTransaction();
        try {
            $sql = "UPDATE rencontres
                    SET equipe1 = :equipe1,
                        score1  = :score1,
                        score2  = :score2,
                        equipe2 = :equipe2
                    WHERE id = :id";

            $stmt = $this->conn->prepare($sql);

            foreach ($matchUpdates as $update) {
                $stmt->bindValue(':equipe1', $update['new_equipe1'], $update['new_equipe1'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(':score1', $update['new_score1'], $update['new_score1'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':score2', $update['new_score2'], $update['new_score2'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':equipe2', $update['new_equipe2'], $update['new_equipe2'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(':id', (int) $update['id'], PDO::PARAM_INT);
                $stmt->execute();
            }

            $this->conn->commit();
            return ['errors' => [], 'success' => ''];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return ['errors' => ['Général' => ['Erreur base de données : ' . $e->getMessage()]], 'success' => null];
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array<int, true>
     */
    private function extraireMatchIdsAReviser(array $postData, string $role): array
    {
        $matchIds = [];
        $fields = ['score1', 'score2'];
        if ($role === 'superAdmin') {
            $fields = array_merge($fields, ['equipe1', 'equipe2']);
        }

        foreach ($fields as $field) {
            $values = $postData[$field] ?? null;
            if (!is_array($values)) {
                continue;
            }

            foreach (array_keys($values) as $id) {
                $matchId = filter_var($id, FILTER_VALIDATE_INT);
                if ($matchId !== false && $matchId > 0) {
                    $matchIds[$matchId] = true;
                }
            }
        }

        return $matchIds;
    }

    /**
     * @param array<int, array<string, mixed>> $rencontres
     * @return array<int, array<string, mixed>>
     */
    private function indexRencontresParId(array $rencontres): array
    {
        $map = [];
        foreach ($rencontres as $r) {
            $id = (int) $r['id'];
            $map[$id] = $r;
        }
        return $map;
    }

    /**
     * @param array<string, mixed> $postData
     * @param array{ id:int, new_equipe1:mixed, new_equipe2:mixed, new_score1:mixed, new_score2:mixed } $update
     * @param array<string, mixed> $equipesAssoc
     * @param string $journeeName
     * @param array<string, array<int, string>> $errors
     * @return array{ id:int, new_equipe1:mixed, new_equipe2:mixed, new_score1:mixed, new_score2:mixed }
     */
    private function appliquerGestionEquipesSuperAdmin(
        array $postData,
        int $matchId,
        array $update,
        array $equipesAssoc,
        string $journeeName,
        array &$errors
    ): array {
        $e1 = trim((string) ($postData['equipe1'][$matchId] ?? ''));
        $e2 = trim((string) ($postData['equipe2'][$matchId] ?? ''));

        $update['new_equipe1'] = ($e1 === '' || $e1 === 'Aucune équipe') ? null : $e1;
        $update['new_equipe2'] = ($e2 === '' || $e2 === 'Aucune équipe') ? null : $e2;

        if ($update['new_equipe1'] && !isset($equipesAssoc[$update['new_equipe1']])) {
            $errors[$journeeName][] = 'Équipe domicile invalide pour le match ' . $matchId;
        }
        if ($update['new_equipe2'] && !isset($equipesAssoc[$update['new_equipe2']])) {
            $errors[$journeeName][] = 'Équipe extérieure invalide pour le match ' . $matchId;
        }
        if ($update['new_equipe1'] && $update['new_equipe1'] === $update['new_equipe2']) {
            $errors[$journeeName][] = 'Pour un match, l\'équipe domicile et l\'équipe extérieure ne peuvent pas être identiques.';
        }

        return $update;
    }

    /**
     * @param array<string, mixed> $postData
     * @param array{ id:int, new_equipe1:mixed, new_equipe2:mixed, new_score1:mixed, new_score2:mixed } $update
     * @param array<string, array<int, string>> $errors
     */
    private function appliquerGestionScores(array $postData, int $matchId, array &$update, array &$errors, string $journeeName): void
    {
        $score1ValStr = trim((string) ($postData['score1'][$matchId] ?? ''));
        $score2ValStr = trim((string) ($postData['score2'][$matchId] ?? ''));

        $e1Name = $update['new_equipe1'] ?? 'Equipe1';
        $e2Name = $update['new_equipe2'] ?? 'Equipe2';

        // Si scores fournis mais équipes manquantes
        if (($score1ValStr !== '' || $score2ValStr !== '') && ($update['new_equipe1'] === null || $update['new_equipe2'] === null)) {
            $errors[$journeeName][] = "Les scores ne peuvent pas être saisis si une équipe est manquante pour le match entre $e1Name et $e2Name.";
            return;
        }

        $score1Ok = $score1ValStr === '';
        $score2Ok = $score2ValStr === '';

        if ($score1ValStr !== '') {
            $score1Ok = filter_var($score1ValStr, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 14]]) !== false;
        }
        if ($score2ValStr !== '') {
            $score2Ok = filter_var($score2ValStr, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 14]]) !== false;
        }

        if (!$score1Ok && !$score2Ok) {
            $errors[$journeeName][] = "Score1 et Score2 sont invalides pour le match entre $e1Name et $e2Name (entre 0 à 14).";
        } elseif (!$score1Ok) {
            $errors[$journeeName][] = "Score1 invalide pour le match entre $e1Name et $e2Name (entre 0 à 14).";
        } elseif (!$score2Ok) {
            $errors[$journeeName][] = "Score2 invalide pour le match entre $e1Name et $e2Name (entre 0 à 14).";
        }

        if ($score1Ok && $score2Ok && $score1ValStr !== '' && $score2ValStr !== '') {
            $s1 = (int) $score1ValStr;
            $s2 = (int) $score2ValStr;
            if (($s1 + $s2) > 14) {
                $errors[$journeeName][] = "La somme des scores pour le match entre $e1Name et $e2Name ne peut pas dépasser 14.";
            } else {
                $update['new_score1'] = $score1ValStr === '' ? null : $s1;
                $update['new_score2'] = $score2ValStr === '' ? null : $s2;
            }
        } else {
            // Si score invalide, on ne modifie pas update
            if ($score1ValStr === '') {
                $update['new_score1'] = null;
            }
            if ($score2ValStr === '') {
                $update['new_score2'] = null;
            }
        }
    }

    /**
     * @param array<int, array<int, string>> $journeesEquipes
     * @param array<int, string> $mapJourneeIdToName
     * @param array<string, array<int, string>> $errors
     */
    private function validerDoublonsParJournee(array $journeesEquipes, array $mapJourneeIdToName, array &$errors): void
    {
        foreach ($journeesEquipes as $journeeId => $equipesInJournee) {
            if (empty($equipesInJournee)) {
                continue;
            }

            $counts = array_count_values($equipesInJournee);
            $duplicates = [];
            foreach ($counts as $equipe => $count) {
                if ($count > 1) {
                    $duplicates[] = (string) $equipe;
                }
            }

            if (empty($duplicates)) {
                continue;
            }

            $journeeName = $mapJourneeIdToName[$journeeId] ?? 'Inconnue';
            $equipesStr = implode(', ', $duplicates);
            $verb = count($duplicates) > 1 ? 'jouent' : 'joue';

            $errors[$journeeName][] = "{$equipesStr} $verb plusieurs matchs dans la même journée. Une équipe ne peut disputer qu'un seul match par journée.";
        }
    }
}