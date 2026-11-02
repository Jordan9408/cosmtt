<?php
declare(strict_types=1);

namespace Admin\ClassPages\Club;

use PDO;
use PDOException;

/**
 * Classe InfosClubManager
 * Gère toutes les opérations de base de données pour les infos du club
 */
class InfosClubManager
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Récupère les informations actuelles du club
     * 
     * @return array
     */
    public function getInfosClub(): array
    {
        try {
            $stmt = $this->conn->query("SELECT * FROM infos_club WHERE id = 1");
            $infos = $stmt->fetch(PDO::FETCH_ASSOC);

            // Si la table est vide, initialiser avec des valeurs vides
            if (!$infos) {
                $infos = [
                    'id' => 1,
                    'nom_club' => '',
                    'horaires' => '',
                    'tarif_adulte' => '',
                    'tarif_mineurs' => '',
                    'map_url' => '',
                    'adresse' => ''
                ];
            }

            return $infos;
        } catch (PDOException $e) {
            throw new \Exception("Erreur de lecture : " . $e->getMessage());
        }
    }

    /**
     * Enregistre les informations du club
     * 
     * @param array $data Les données à enregistrer
     * @return bool
     * @throws Exception
     */
    public function updateInfosClub(array $data): bool
    {
        try {
            $sql = "INSERT INTO infos_club (id, nom_club, horaires, tarif_adulte, tarif_mineurs, map_url, adresse) 
                    VALUES (1, :nom_club, :horaires, :tarif_adulte, :tarif_mineurs, :map_url, :adresse)
                    ON DUPLICATE KEY UPDATE 
                    nom_club = VALUES(nom_club),
                    horaires = VALUES(horaires), 
                    tarif_adulte = VALUES(tarif_adulte), 
                    tarif_mineurs = VALUES(tarif_mineurs), 
                    map_url = VALUES(map_url), 
                    adresse = VALUES(adresse)";

            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':nom_club'      => $data['nom_club'] ?? '',
                ':horaires'      => $data['horaires'] ?? '',
                ':tarif_adulte'  => $data['tarif_adulte'] ?? '',
                ':tarif_mineurs' => $data['tarif_mineurs'] ?? '',
                ':map_url'       => $data['map_url'] ?? '',
                ':adresse'       => $data['adresse'] ?? '',
            ]);
        } catch (PDOException $e) {
            throw new \Exception("Erreur lors de la mise à jour : " . $e->getMessage());
        }
    }
}
?>
