<?php
/**
 * Classe pour gérer le championnat et calculer le classement
 */
class ChampionnatManager
{
    private $conn;
    private $equipeManager;
    private $rencontreManager;

    public function __construct(PDO $connection)
    {
        $this->conn = $connection;
        $this->equipeManager = new EquipeManager($connection);
        $this->rencontreManager = new RencontreManager($connection);
    }

    /**
     * Calcule le classement des équipes pour une poule donnée.
     * Retourne un tableau associatif avec les stats par équipe.
     */
    public function calculerClassement(string $poule): array
    {
        $equipes = $this->equipeManager->getEquipesByPoule($poule);
        $rencontres = $this->rencontreManager->getRencontresByPoule($poule);

        // Initialiser le tableau des stats
        $classement = [];
        foreach ($equipes as $equipe) {
            $classement[ucfirst($equipe['NomEquipe'])] = [
                'points' => 0,
                'journees' => 0,
                'victoires' => 0,
                'defaites' => 0,
                'nuls' => 0,
            ];
        }

        // Calculer les stats
        foreach ($rencontres as $match) {
            $equipe1 = $match['equipe1'];
            $equipe2 = $match['equipe2'];
            $score1 = $match['score1'];
            $score2 = $match['score2'];

            // Ignorer les matchs sans score ou avec équipe NULL
            if ($score1 === null || $score2 === null || $equipe1 === null || $equipe2 === null) {
                continue;
            }

            // Journées jouées
            $keyEquipe1 = ucfirst($equipe1);
            $keyEquipe2 = ucfirst($equipe2);

            if (!isset($classement[$keyEquipe1])) {
                $classement[$keyEquipe1] = [
                    'points' => 0,
                    'journees' => 0,
                    'victoires' => 0,
                    'defaites' => 0,
                    'nuls' => 0,
                ];
            }
            if (!isset($classement[$keyEquipe2])) {
                $classement[$keyEquipe2] = [
                    'points' => 0,
                    'journees' => 0,
                    'victoires' => 0,
                    'defaites' => 0,
                    'nuls' => 0,
                ];
            }

            $classement[$keyEquipe1]['journees']++;
            $classement[$keyEquipe2]['journees']++;

            // Victoires, défaites, nuls et points
            if ($score1 > $score2) {
                $classement[$keyEquipe1]['victoires']++;
                $classement[$keyEquipe1]['points'] += 3;
                $classement[$keyEquipe2]['defaites']++;
                $classement[$keyEquipe2]['points'] += 1;
            } elseif ($score1 < $score2) {
                $classement[$keyEquipe2]['victoires']++;
                $classement[$keyEquipe2]['points'] += 3;
                $classement[$keyEquipe1]['defaites']++;
                $classement[$keyEquipe1]['points'] += 1;
            } else {
                $classement[$keyEquipe1]['nuls']++;
                $classement[$keyEquipe1]['points'] += 2;
                $classement[$keyEquipe2]['nuls']++;
                $classement[$keyEquipe2]['points'] += 2;
            }
        }

        // Trier par points décroissants, puis victoires, puis nom équipe
        uasort($classement, function ($a, $b) {
            if ($a['points'] === $b['points']) {
                if ($a['victoires'] === $b['victoires']) {
                    return 0;
                }
                return ($a['victoires'] > $b['victoires']) ? -1 : 1;
            }
            return ($a['points'] > $b['points']) ? -1 : 1;
        });

        return $classement;
    }
}
// var_dump('ChampionnatManager.php - Ok');
?>