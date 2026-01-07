<?php
class EquipeManager
{
    private $conn;

    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    public function ajouterEquipe($nomEquipe, $poule)
    {
        $nomEquipe = ucfirst($nomEquipe);

        if (empty($nomEquipe) || empty($poule)) {
            header("Location: /cosmtt/admin/pages/adminPage/championnat/equipes/showEquipe.php?message=EmptyFields");
            exit;
        }

        $existingEquipe = $this->getEquipeByNameAndPoule($nomEquipe, $poule);

        if ($existingEquipe) {
            header("Location: /cosmtt/admin/pages/adminPage/championnat/equipes/showEquipe.php?message=EquipeExists");
            exit;
        }

        $query = "INSERT INTO Equipes (NomEquipe, Poules) VALUES (:nomEquipe, :poule)";
        $stmt = $this->conn->prepare($query);

        if ($stmt) {
            $stmt->bindValue(':nomEquipe', $nomEquipe, PDO::PARAM_STR);
            $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);
            if ($stmt->execute()) {
                header("Location: /cosmtt/admin/pages/adminPage/championnat/equipes/showEquipe.php");
                exit;
            } else {
                header("Location: /cosmtt/admin/pages/adminPage/championnat/equipes/showEquipe.php?message=Error");
                exit;
            }
        } else {
            header("Location: /cosmtt/admin/pages/adminPage/championnat/equipes/showEquipe.php?message=Error");
            exit;
        }
    }

    public function getEquipeByNameAndPoule($nomEquipe, $poule)
    {
        $sql = "SELECT * FROM Equipes WHERE NomEquipe = :nomEquipe AND Poules = :poule";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':nomEquipe', $nomEquipe, PDO::PARAM_STR);
        $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result;
        }

        return null;
    }

    public function getEquipesByPoule($poule)
    {
        $equipes = array();

        $sql = "SELECT * FROM Equipes WHERE Poules = :poule ORDER BY NomEquipe ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':poule', $poule, PDO::PARAM_STR);

        if ($stmt->execute()) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $equipes[] = $row;
            }
        }

        return $equipes;
    }

    public function displayEquipes($poule)
    {
        $equipes = $this->getEquipesByPoule($poule);

        if (!empty($equipes)) {
            echo "<table>";
            echo "<tr>";
            echo "<th colspan=2>" . $poule . "</th>";
            echo "</tr>";
            echo "<tr>";
            echo "<th>Equipes</th>";
            echo "<th>Supprimer</th>";
            echo "</tr>";

            foreach ($equipes as $equipe) {
                echo "<tr>";
                echo "<td>" . ucfirst($equipe['NomEquipe']) . "</td>";
                // MODIFICATION : Appel de la suppression via showEquipe.php avec l'ID
                echo "<td><a class='image' href='showEquipe.php?action=delete&id=" . $equipe['equipe_id'] . "' onclick=\"return confirm('Êtes-vous sûr de vouloir supprimer cette équipe ? \\nCette action mettra à jour toutes les rencontres associées.');\"><img src='/cosmtt/img/icones/remove.png' alt=''></a></td>";
                echo "</tr>";
            }

            echo "</table>";
        } else {
            echo "<p class='message'>Aucune équipe présente dans la poule $poule !</p>";
        }
    }

    public function supprimerEquipe($equipe_id)
    {
        try {
            // 1. Démarrer la transaction pour garantir l'atomicité de l'opération
            $this->conn->beginTransaction();

            // Récupérer le nom de l'équipe avant suppression
            $sql = "SELECT NomEquipe FROM Equipes WHERE equipe_id = :equipe_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':equipe_id', $equipe_id, PDO::PARAM_INT);
            $stmt->execute();
            $equipe = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$equipe) {
                $this->conn->rollBack();
                header("location:/cosmtt/admin/pages/adminPage/championnat/equipes/showEquipe.php?message=InvalidRequest");
                exit;
            }

            $nomEquipe = $equipe['NomEquipe'];

            // 2. Mettre à jour les rencontres : définir equipe1 ET score1 à NULL
            $updateQuery1 = "UPDATE rencontres SET equipe1 = NULL, score1 = NULL WHERE equipe1 = :nomEquipe";
            $updateStmt1 = $this->conn->prepare($updateQuery1);
            $updateStmt1->bindValue(':nomEquipe', $nomEquipe, PDO::PARAM_STR);
            $updateStmt1->execute();

            // 3. Mettre à jour les rencontres : définir equipe2 ET score2 à NULL
            $updateQuery2 = "UPDATE rencontres SET equipe2 = NULL, score2 = NULL WHERE equipe2 = :nomEquipe";
            $updateStmt2 = $this->conn->prepare($updateQuery2);
            $updateStmt2->bindValue(':nomEquipe', $nomEquipe, PDO::PARAM_STR);
            $updateStmt2->execute();

            // 4. Supprimer l'équipe de la table Equipes
            $query = "DELETE FROM Equipes WHERE equipe_id = :equipe_id";
            $stmt = $this->conn->prepare($query);

            if ($stmt) {
                $stmt->bindValue(':equipe_id', $equipe_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    // 5. Valider la transaction si toutes les étapes ont réussi
                    $this->conn->commit();
                    header("location:/cosmtt/admin/pages/adminPage/championnat/equipes/showEquipe.php?message=DeleteSuccess");
                    exit;
                } else {
                    $this->conn->rollBack();
                    header("location:/cosmtt/admin/pages/adminpage/championnat/equipes/showEquipe.php?message=DeleteFail");
                    exit;
                }
            } else {
                $this->conn->rollBack();
                // Utilisation d'un message d'erreur existant en cas de problème de préparation
                header("location:/cosmtt/admin/pages/adminPage/championnat/equipes/showEquipe.php?message=DeleteFail");
                exit;
            }
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur inattendue et rediriger
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            header("location:/cosmtt/admin/pages/adminPage/championnat/equipes/showEquipe.php?message=DeleteFail");
            // Il est recommandé d'enregistrer $e->getMessage() dans un journal (log)
            exit;
        }
    }
}
?>