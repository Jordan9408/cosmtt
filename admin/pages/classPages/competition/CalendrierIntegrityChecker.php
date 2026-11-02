<?php

declare(strict_types=1);

/**
 * Vérifications d'intégrité pour l'ajout d'une journée et de ses rencontres.
 */
class CalendrierIntegrityChecker
{
    private const EXCLUDED_TEAM_NAME = 'Aucune équipe';

    /**
     * Utilisation de la promotion de propriété de constructeur (PHP 8+)
     */
    public function __construct(private PDO $conn) {}

    /**
     * Vérifie si une journée existe déjà (même poule + type + libellé journee_id).
     */
    public function journeeExiste(string $poule, string $journeeId, string $typeJournee): ?int
    {
        $sql = "SELECT id 
                FROM journeesChampionnat 
                WHERE journee_id = :journee 
                  AND poule = :poule 
                  AND type_journee = :type_journee 
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':journee'      => ucfirst($journeeId),
            ':poule'        => $poule,
            ':type_journee' => $typeJournee,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? (int)$row['id'] : null;
    }

    /**
     * Vérifie si une nouvelle période chevauche une journée existante.
     * * @return array<array{id: int, journee_id: string, dateDebut: string, dateFin: string}>
     */
    public function journeesSurChevauchementPériode(
        string $poule,
        string $typeJournee,
        string $nouvelleDateDebut,
        string $nouvelleDateFin
    ): array {
        $sql = "SELECT id, journee_id, dateDebut, dateFin
                FROM journeesChampionnat
                WHERE poule = :poule
                  AND type_journee = :type_journee
                  AND (:nouvelleDebut <= dateFin AND :nouvelleFin >= dateDebut)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':poule'         => $poule,
            ':type_journee'  => $typeJournee,
            ':nouvelleDebut' => $nouvelleDateDebut,
            ':nouvelleFin'   => $nouvelleDateFin,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Vérifie collision d'engagements : une équipe ne peut pas jouer plus d'une rencontre dans la même journée.
     * * @param array<array{equipe1: string, equipe2: string}> $rencontresProposees
     * @return string[] Liste des équipes en collision.
     */
    public function equipesEnCollisionPourJournee(int $journeeDbId, array $rencontresProposees): array
    {
        $equipes = [];

        // 1. Récupération des rencontres existantes en base de données
        $stmt = $this->conn->prepare("SELECT equipe1, equipe2 FROM rencontres WHERE journee_id = :journee_id");
        $stmt->execute([':journee_id' => $journeeDbId]);
        $rencontresExistantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Extraction des équipes de la BDD et du POST via une méthode privée (DRY)
        $this->extraireEquipes($rencontresExistantes, $equipes);
        $this->extraireEquipes($rencontresProposees, $equipes);

        // 3. Filtrage pour ne garder que les doublons
        $counts = array_count_values($equipes);
        
        return array_keys(
            array_filter($counts, static fn(int $quantite): bool => $quantite > 1)
        );
    }

    /**
     * Extrait et filtre les équipes valides d'une liste de rencontres (Méthode utilitaire privée).
     *
     * @param array<array{equipe1?: string, equipe2?: string}> $rencontres
     * @param string[] $listeEquipes Référence du tableau à alimenter
     */
    private function extraireEquipes(array $rencontres, array &$listeEquipes): void
    {
        foreach ($rencontres as $r) {
            foreach (['equipe1', 'equipe2'] as $cle) {
                if (!empty($r[$cle]) && $r[$cle] !== self::EXCLUDED_TEAM_NAME) {
                    $listeEquipes[] = $r[$cle];
                }
            }
        }
    }
}