<?php
/**
 * Classe contrôleur pour gérer l'insertion d'une journée et de ses rencontres.
 */
class JourneeController
{
    private JourneeValidator $validator;
    private JourneeManager $journeeManager;
    private RencontreManager $rencontreManager;

    public function __construct(PDO $conn)
    {
        $this->validator = new JourneeValidator();
        $this->journeeManager = new JourneeManager($conn);
        $this->rencontreManager = new RencontreManager($conn);
    }

    /**
     * Gère la requête POST pour insérer une journée et ses rencontres.
     */
    public function handlePost(array $postData): array
    {
        $errors = array_merge(
            $this->validator->validerJournee($postData),
            $this->validator->validerRencontres($postData)
        );

        if (!empty($errors)) {
            return ['errors' => $errors, 'success' => null];
        }

        try {
            $journeeId = $this->journeeManager->obtenirOuCreerJournee(
                $postData['journée'],
                $postData['dateDebut'],
                $postData['dateFin'],
                $postData['poule'],
                $postData['type_journee'] // <- NOUVELLE DONNÉE
            );

            $rencontres = $this->preparerRencontres($postData, $journeeId);
            $this->rencontreManager->insererRencontres($rencontres);

            $numMatches = count($rencontres);
            return ['errors' => [], 'success' => ""];
        } catch (PDOException $e) {
            return ['errors' => ["Erreur lors de l'enregistrement : " . $e->getMessage()], 'success' => null];
        }
    }

    /**
     * Prépare les données des rencontres pour l'insertion.
     */
    private function preparerRencontres(array $postData, int $journeeId): array
    {
        $rencontres = [];
        $equipe1s = (array) ($postData['equipe1'] ?? []);
        $score1s = (array) ($postData['score1'] ?? []);
        $score2s = (array) ($postData['score2'] ?? []);
        $equipe2s = (array) ($postData['equipe2'] ?? []);

        for ($i = 0; $i < count($equipe1s); $i++) {
            $score1 = !empty($score1s[$i]) ? (int) $score1s[$i] : null;
            $score2 = !empty($score2s[$i]) ? (int) $score2s[$i] : null;
            $equipe1 = $equipe1s[$i] === "Aucune équipe" ? null : $equipe1s[$i];
            $equipe2 = $equipe2s[$i] === "Aucune équipe" ? null : $equipe2s[$i];
            $rencontres[] = [
                'journee_id' => $journeeId,
                'equipe1' => $equipe1,
                'score1' => $score1,
                'score2' => $score2,
                'equipe2' => $equipe2,
            ];
        }
        return $rencontres;
    }
}
// var_dump('JourneeController.php - Ok');
?>