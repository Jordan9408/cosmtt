<?php
function replace_special_caract(string $string): string
{
    $transliterator = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC;');
    $string = $transliterator->transliterate($string);
    return $string;
}

//  ------LES ÉQUIPES -------//
include ('../../../config/config.php'); // Assurez-vous d'inclure les fichiers nécessaires, notamment pour la connexion à la base de données.

// Fonction pour créer une équipe
function addEquipe($nom) {
    global $conn;
    $sql = "INSERT INTO equipes (nomEquipe) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nomEquipe);
    return $stmt->execute();
}

// Fonction pour lire toutes les équipes
function readEquipes() {
    global $conn;
    $sql = "SELECT * FROM equipes";
    $result = $conn->query($sql);
    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    } else {
        return array(); // Retourne un tableau vide en cas d'erreur de requête.
    }
}

// Fonction pour mettre à jour une équipe
function updateEquipe($id, $nom) {
    global $conn;
    $sql = "UPDATE equipes SET nomEquipe = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $nomEquipe, $id);
    return $stmt->execute();
}

// Fonction pour supprimer une équipe
function deleteEquipe($id) {
    global $conn;
    $sql = "DELETE FROM equipes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}
?>