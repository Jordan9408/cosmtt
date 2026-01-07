<?php
class GestionPhotos {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function ajouterCategoriePhoto($titre, $date) {
        $sql = "INSERT INTO Categories_photos (section_title, section_date) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$titre, $date]);
    }

    public function ajouterPhoto($section_id, $file_path) {
        $sql = "INSERT INTO Photos (section_id, file_path) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$section_id, $file_path]);
    }

    public function recupererToutesCategories() {
        $sql = "SELECT * FROM Categories_photos";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recupererPhotosParCategorie($section_id) {
        $sql = "SELECT * FROM Photos WHERE section_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$section_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ajoutez d'autres méthodes pour la mise à jour et la suppression si nécessaire
}
?>
