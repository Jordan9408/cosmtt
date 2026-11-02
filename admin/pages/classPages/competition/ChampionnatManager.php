<?php

declare(strict_types=1);

/**
 * Classe pour gérer le championnat et calculer le classement.
 */
class ChampionnatManager
{
    private EquipeManager $equipeManager;
    private RencontreManager $rencontreManager;

    /**
     * Utilisation du typage explicite et initialisation des managers requis.
     */
    public function __construct(private PDO $conn)
    {
        $this->equipeManager = new EquipeManager($this->conn);
        $this->rencontreManager = new RencontreManager($this->conn);
    }

    /**
     * Calcule le classement des équipes pour une poule donnée.
     * * @param string $poule
     * @return array<string, array{points: int, journees: int, victoires: int, defaites: int, nuls: int}>
     */
    public function calculerClassement(string $poule): array
    {
        $equipes = $this->equipeManager->getEquipesByPoule($poule);
        $rencontres = $this->rencontreManager->getRencontresByPoule($poule);

        // Initialisation du tableau des statistiques
        $classement = [];
        foreach ($equipes as $equipe) {
            $nomEquipe = ucfirst($equipe['NomEquipe']);
            $classement[$nomEquipe] = $this->genererStatsInitiales();
        }

        // Calcul des statistiques par match
        foreach ($rencontres as $match) {
            $score1 = $match['score1'] ?? null;
            $score2 = $match['score2'] ?? null;
            $equipe1 = $match['equipe1'] ?? null;
            $equipe2 = $match['equipe2'] ?? null;

            // Ignorer les matchs sans score ou si une équipe est manquante
            if ($score1 === null || $score2 === null || $equipe1 === null || $equipe2 === null) {
                continue;
            }

            $keyEquipe1 = ucfirst($equipe1);
            $keyEquipe2 = ucfirst($equipe2);

            // Sécurité si une équipe n'était pas initialisée via $equipes
            $classement[$keyEquipe1] ??= $this->genererStatsInitiales();
            $classement[$keyEquipe2] ??= $this->genererStatsInitiales();

            $classement[$keyEquipe1]['journees']++;
            $classement[$keyEquipe2]['journees']++;

            // Attribution des points selon le résultat
            $this->attribuerPoints($classement[$keyEquipe1], $classement[$keyEquipe2], (int)$score1, (int)$score2);
        }

        // Tri du classement (Points Desc, puis Victoires Desc)
        uasort($classement, static function (array $a, array $b): int {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            return $b['victoires'] <=> $a['victoires'];
        });

        return $classement;
    }

    /**
     * Structure de données par défaut pour les statistiques d'une équipe.
     * * @return array{points: int, journees: int, victoires: int, defaites: int, nuls: int}
     */
    private function genererStatsInitiales(): array
    {
        return [
            'points'    => 0,
            'journees'  => 0,
            'victoires' => 0,
            'defaites'  => 0,
            'nuls'      => 0,
        ];
    }

    /**
     * Applique les règles de calcul des points selon les scores du match.
     */
    private function attribuerPoints(array &$statsE1, array &$statsE2, int $score1, int $score2): void
    {
        if ($score1 > $score2) {
            // Victoire Équipe 1 (3 pts) | Défaite Équipe 2 (1 pt)
            $statsE1['victoires']++;
            $statsE1['points'] += 3;
            $statsE2['defaites']++;
            $statsE2['points'] += 1;
        } elseif ($score1 < $score2) {
            // Victoire Équipe 2 (3 pts) | Défaite Équipe 1 (1 pt)
            $statsE2['victoires']++;
            $statsE2['points'] += 3;
            $statsE1['defaites']++;
            $statsE1['points'] += 1;
        } else {
            // Match nul (2 pts chacun)
            $statsE1['nuls']++;
            $statsE1['points'] += 2;
            $statsE2['nuls']++;
            $statsE2['points'] += 2;
        }
    }
}