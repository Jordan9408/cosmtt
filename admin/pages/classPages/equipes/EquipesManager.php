<?php

/**
 * Classe EquipeManager
 * Gère l'ensemble des opérations CRUD et la logique métier de la table 'Equipes'.
 */
class EquipeManager
{
    /**
     * Le constructeur utilise la promotion de propriété (PHP 8+)
     * pour déclarer et assigner automatiquement l'instance PDO.
     */
    public function __construct(private PDO $conn) {}

    /**
     * Ajoute une nouvelle équipe après validation des données
     */
    public function ajouterEquipe(string $nomEquipe, string $poule): string
    {
        $nomEquipe = ucfirst(trim($nomEquipe));

        if (empty($nomEquipe) || empty($poule)) {
            return "EmptyFields";
        }
        
        // Validation des caractères autorisés (lettres, chiffres, espaces et tirets)
        if (!preg_match('/^[\p{L}0-9\s-]*$/u', $nomEquipe)) {
            return "EQUIPE_NOM_INVALID_CHARS";
        }

        if ($this->getEquipeByNameAndPoule($nomEquipe, $poule)) {
            return "EquipeExists";
        }

        $query = "INSERT INTO Equipes (NomEquipe, Poules) VALUES (:nomEquipe, :poule)";
        $stmt = $this->conn->prepare($query);

        if ($stmt) {
            $stmt->bindValue(':nomEquipe', $nomEquipe, PDO::PARAM_STR);
            $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return "AddSuccess";
            }
        }
        
        return "GENERIC_ERROR";
    }

    /**
     * Recherche et récupère une équipe spécifique par son nom et sa poule
     */
    public function getEquipeByNameAndPoule(string $nomEquipe, string $poule): ?array
    {
        $sql = "SELECT * FROM Equipes WHERE NomEquipe = :nomEquipe AND Poules = :poule";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':nomEquipe', $nomEquipe, PDO::PARAM_STR);
        $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        }

        return null;
    }

    /**
     * Récupère toutes les équipes appartenant à une poule spécifique
     */
    public function getEquipesByPoule(string $poule): array
    {
        $sql = "SELECT * FROM Equipes WHERE Poules = :poule ORDER BY NomEquipe ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [];
    }

    /**
     * Génère et affiche le tableau HTML contenant les équipes d'une poule
     */
    public function displayEquipes(string $poule, string $csrfToken = ''): void
    {
        $equipes = $this->getEquipesByPoule($poule);

        if (empty($equipes)) {
            echo "<p class='message'>Aucune équipe présente dans la poule " . htmlspecialchars($poule, ENT_QUOTES, 'UTF-8') . " !</p>";
            return;
        }

        $pouleHtml = htmlspecialchars($poule, ENT_QUOTES, 'UTF-8');
        
        echo <<<HTML
        <table>
            <tr><th colspan="2">{$pouleHtml}</th></tr>
            <tr><th>Equipes</th><th>Supprimer</th></tr>
        HTML;

        foreach ($equipes as $equipe) {
            $nomFormatte = htmlspecialchars(ucfirst($equipe['NomEquipe']), ENT_QUOTES, 'UTF-8');
            $id = (int)$equipe['equipe_id'];
            $token = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
            
            echo <<<HTML
            <tr>
                <td>{$nomFormatte}</td>
                <td>
                    <form method="POST" action="showEquipe.php" class="js-confirm-delete-team" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="{$id}">
                        <input type="hidden" name="csrf_token" value="{$token}">
                        <button type="submit" class="image-button" title="Supprimer" style="background:none;border:none;cursor:pointer;padding:0;display:inline-flex;align-items:center;justify-content:center;">
                            <img src="/cosmtt/img/icones/remove.png" alt="Supprimer">
                        </button>
                    </form>
                </td>
            </tr>
            HTML;
        }

        echo "</table>";
    }

    /**
     * Supprime de manière sécurisée (avec Transaction SQL) une équipe et nettoie ses rencontres
     */
    public function supprimerEquipe(int $equipe_id): string
    {
        try {
            $this->conn->beginTransaction();

            // 1. Récupération du nom de l'équipe avant sa suppression
            $sql = "SELECT NomEquipe FROM Equipes WHERE equipe_id = :equipe_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':equipe_id', $equipe_id, PDO::PARAM_INT);
            $stmt->execute();
            $equipe = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$equipe) {
                $this->conn->rollBack();
                return "InvalidRequest";
            }

            $nomEquipe = $equipe['NomEquipe'];

            // 2. Désassociation propre de l'équipe dans la table des rencontres (colonnes equipe1 et equipe2)
            $updateQueries = [
                "UPDATE rencontres SET equipe1 = NULL, score1 = NULL WHERE equipe1 = :nomEquipe",
                "UPDATE rencontres SET equipe2 = NULL, score2 = NULL WHERE equipe2 = :nomEquipe"
            ];

            foreach ($updateQueries as $query) {
                $updateStmt = $this->conn->prepare($query);
                $updateStmt->bindValue(':nomEquipe', $nomEquipe, PDO::PARAM_STR);
                $updateStmt->execute();
            }

            // 3. Suppression de la ligne de l'équipe
            $deleteQuery = "DELETE FROM Equipes WHERE equipe_id = :equipe_id";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->bindValue(':equipe_id', $equipe_id, PDO::PARAM_INT);

            if ($deleteStmt && $deleteStmt->execute()) {
                $this->conn->commit();
                return "DeleteSuccess";
            }

            $this->conn->rollBack();
            return "DeleteFail";

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log($e->getMessage());
            return "DeleteFail";
        }
    }
}