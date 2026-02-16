<?php

/**
 * Classe pour gérer les joueurs avec la table classementJoueurs
 */
class JoueurManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Récupérer tous les joueurs
     */
    public function getAllJoueurs()
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    ID,
                    Nom,
                    Prenom,
                    Classement,
                    PointsDebutSaison,
                    PointsMensuels,
                    EvolutionMensuelle,
                    (PointsMensuels - PointsDebutSaison) as evolution_calculee
                FROM classementJoueurs
                ORDER BY Nom ASC, Prenom ASC
            ");
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des joueurs : " . $e->getMessage());
        }
    }

    /**
     * Récupérer un joueur par son ID
     */
    public function getJoueurById($id)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM classementJoueurs WHERE ID = :id
            ");
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération du joueur : " . $e->getMessage());
        }
    }

    /**
     * Ajouter un nouveau joueur
     */
    public function addJoueur($data)
    {
        $errors = [];
        
        // ICI : normalisation des données 
        // $nom = strtoupper(trim($data['nom'])); 
        // $prenom = ucwords(mb_strtolower(trim($data['prenom']), 'UTF-8'));

        // Validation basique uniquement
        if (empty($data['nom'])) {
            $errors[] = "Le nom est obligatoire.";
        }
        if (empty($data['prenom'])) {
            $errors[] = "Le prénom est obligatoire.";
        }
        if (!isset($data['classement']) || $data['classement'] < 5) {
            $errors[] = "Le classement doit être au moins 5.";
        }
        if (!isset($data['points_debut_saison']) || $data['points_debut_saison'] < 500) {
            $errors[] = "Les points de début de saison doivent être au minimun 500.";
        }
        if (!isset($data['points_mensuels']) || $data['points_mensuels'] < 0) {
            $errors[] = "Les points mensuels doivent être positifs.";
        }

        if (!empty($errors)) {
            return ['errors' => $errors, 'success' => ''];
        }

        try {
            
            // Calcul de l'évolution mensuelle
            $evolutionMensuelle = $data['points_mensuels'] - $data['points_debut_saison'];

            // Modification des données avant insertion (nom en majuscules, 1e lettre du prénom avec majuscule)
            $nom = strtoupper(trim($data['nom']));
            $prenom = mb_convert_case(trim($data['prenom']), MB_CASE_TITLE, "UTF-8");
            
            $stmt = $this->conn->prepare("
                INSERT INTO classementJoueurs (Nom, Prenom, Classement, PointsDebutSaison, PointsMensuels, EvolutionMensuelle)
                VALUES (:nom, :prenom, :classement, :points_debut_saison, :points_mensuels, :evolution_mensuelle)
            ");
            
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':prenom', $prenom);
            $stmt->bindParam(':classement', $data['classement'], PDO::PARAM_INT);
            $stmt->bindParam(':points_debut_saison', $data['points_debut_saison'], PDO::PARAM_INT);
            $stmt->bindParam(':points_mensuels', $data['points_mensuels'], PDO::PARAM_INT);
            $stmt->bindParam(':evolution_mensuelle', $evolutionMensuelle, PDO::PARAM_INT);
            
            $stmt->execute();
            
            return [
                'errors' => [],
                'success' => "AjoutJoueurOK"
            ];
        } catch (PDOException $e) {
            return [
                'errors' => ["Erreur lors de l'ajout du joueur : " . $e->getMessage()],
                'success' => ''
            ];
        }
    }

    /**
     * Mettre à jour les joueurs (lors de l'édition en masse)
     */
    public function updateJoueur($data)
    {
        $errors = [];
        $success = '';

        try {
            $this->conn->beginTransaction();

            if (isset($data['joueur_id']) && is_array($data['joueur_id'])) {
                foreach ($data['joueur_id'] as $id) {
                    // Validation basique pour chaque joueur
                    if (empty($data['nom'][$id]) || empty($data['prenom'][$id])) {
                        $errors[] = "Le nom et le prénom sont obligatoires pour tous les joueurs.";
                        continue;
                    }

                    // Calcul de l'évolution mensuelle
                    $evolutionMensuelle = $data['points_mensuels'][$id] - $data['points_debut_saison'][$id];
                    $nom = strtoupper(trim($data['nom'][$id]));
                    $prenom = mb_convert_case(trim($data['prenom'][$id]), MB_CASE_TITLE, "UTF-8");

                    $stmt = $this->conn->prepare("
                        UPDATE classementJoueurs 
                        SET Nom = :nom,
                            Prenom = :prenom,
                            Classement = :classement,
                            PointsDebutSaison = :points_debut_saison,
                            PointsMensuels = :points_mensuels,
                            EvolutionMensuelle = :evolution_mensuelle
                        WHERE ID = :id
                    ");

                    $stmt->bindParam(':nom', $nom);
                    $stmt->bindParam(':prenom', $prenom);
                    $stmt->bindParam(':classement', $data['classement'][$id], PDO::PARAM_INT);
                    $stmt->bindParam(':points_debut_saison', $data['points_debut_saison'][$id], PDO::PARAM_INT);
                    $stmt->bindParam(':points_mensuels', $data['points_mensuels'][$id], PDO::PARAM_INT);
                    $stmt->bindParam(':evolution_mensuelle', $evolutionMensuelle, PDO::PARAM_INT);
                    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

                    $stmt->execute();
                }
            }

            if (empty($errors)) {
                $this->conn->commit();
                $success = "ModificationsOK";
            } else {
                $this->conn->rollBack();
            }

            return ['errors' => $errors, 'success' => $success];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return [
                'errors' => ["Erreur lors de la mise à jour : " . $e->getMessage()],
                'success' => ''
            ];
        }
    }

    /**
     * Supprimer un joueur
     */
    public function deleteJoueur($id)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM classementJoueurs WHERE ID = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return true;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la suppression du joueur : " . $e->getMessage());
        }
    }

    /**
     * Récupérer les statistiques globales
     */
    public function getStatistiques()
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    COUNT(*) as total_joueurs,
                    AVG(PointsMensuels) as moyenne_points,
                    SUM(CASE WHEN PointsMensuels > PointsDebutSaison THEN 1 ELSE 0 END) as joueurs_progression,
                    SUM(CASE WHEN PointsMensuels < PointsDebutSaison THEN 1 ELSE 0 END) as joueurs_regression
                FROM classementJoueurs
            ");
            
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des statistiques : " . $e->getMessage());
        }
    }

    /**
     * Mettre à jour l'évolution mensuelle pour tous les joueurs
     * (Synchroniser la colonne EvolutionMensuelle)
     */
    public function updateEvolutionMensuelle()
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE classementJoueurs 
                SET EvolutionMensuelle = (PointsMensuels - PointsDebutSaison)
            ");
            
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la mise à jour de l'évolution mensuelle : " . $e->getMessage());
        }
    }
}