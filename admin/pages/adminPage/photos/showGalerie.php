<?php require_once("./../../connect_ddb.php"); ?>
<?php


class Galerie
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Méthode pour créer une nouvelle catégorie avec titre et date
    public function createCategorie($titre, $date)
    {
        $query = "INSERT INTO categories_photos (section_titre, section_date) VALUES (?, ?)";
        $stmt = $this->conn->prepare($query);
        // Utilisation des placeholders pour lier les valeurs
        $stmt->bindParam(1, $titre);
        $stmt->bindParam(2, $date);

        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    // Méthode pour mettre à jour le titre et la date d'une catégorie existante
    public function updateCategorie($categorieId, $titre, $date)
    {
        $query = "UPDATE categories_photos SET section_titre = :titre, section_date = :date WHERE section_id = :categorie_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':titre', $titre);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':categorie_id', $categorieId);

        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    // Méthode pour supprimer une catégorie avec ses photos associées
    public function deleteCategorie($categorieId)
    {
        $query = "DELETE FROM categories_photos WHERE section_id = :categorie_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':categorie_id', $categorieId);

        if ($stmt->execute()) {
            // Supprimer également les photos associées à la catégorie
            $query = "DELETE FROM photos WHERE section_id = :categorie_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':categorie_id', $categorieId);
            $stmt->execute();

            return true;
        } else {
            return false;
        }
    }

    // Méthode pour récupérer toutes les catégories de la galerie
    public function getAllCategories()
    {
        $query = "SELECT * FROM categories_photos";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $categories;
    }
}









// ------------------------------------------//
// Liste des sections de photos
// $query_sections = $conn->query("SELECT * FROM Categories_photos");
// $sections = $query_sections->fetchAll(PDO::FETCH_ASSOC);

// // Affichage de la galerie
// if (isset($_GET['section_id'])) {
//     $section_id = $_GET['section_id'];
//     $query_photos = $conn->prepare("SELECT * FROM Photos WHERE section_id = :section_id");
//     $query_photos->bindParam(':section_id', $section_id);
//     $query_photos->execute();
//     $photos = $query_photos->fetchAll(PDO::FETCH_ASSOC);
//     // Afficher les photos de la section sélectionnée
//     foreach ($photos as $photo) {
//         echo "<img src='" . $photo['file_path'] . "' alt='Photo'>";
//         echo "<a href='?delete_photo=" . $photo['photo_id'] . "'>Supprimer</a>";
//         echo "<a href='?edit_photo=" . $photo['photo_id'] . "'>Modifier</a>";
//     }
// }

// // Suppression d'une photo
// if (isset($_GET['delete_photo'])) {
//     $photo_id = $_GET['delete_photo'];
//     $conn->query("DELETE FROM Photos WHERE photo_id = $photo_id");
//     // Redirection vers la même section après la suppression
//     header("Location: {$_SERVER['PHP_SELF']}?section_id=$section_id");
//     exit();
// }

// // Formulaire d'ajout ou de modification d'une photo
// // if (isset($_GET['edit_photo'])) {
// //     // Logique pour la modification de la photo
// //     // Vous pouvez utiliser un formulaire pour mettre à jour les détails de la photo
// //     // Utilisez le $_POST pour récupérer les données envoyées par le formulaire
// //     // Effectuez la mise à jour dans la base de données
// // }

// // Formulaire d'ajout d'une nouvelle photo
// echo "<form action='' method='post' enctype='multipart/form-data'>";
// echo "<input type='text' placeholder='Titre'>";
// echo "<input type='date' name='datetime'>";
// echo "<input type='file' name='image'>";
// // echo "<select name='section'>";
// // foreach ($sections as $section) {
// //     echo "<option value='" . $section['section_id'] . "'>" . $section['section_title'] . "</option>";
// // }
// // echo "</select>";
// echo "<input type='submit' name='submit' value='Ajouter'>";
// echo "</form>";

// // Traitement de l'ajout d'une nouvelle photo
// if (isset($_POST['submit'])) {
//     $section_id = $_POST['section'];
//     $file_path = "chemin/vers/votre/dossier/" . $_FILES['image']['name'];
//     move_uploaded_file($_FILES['image']['tmp_name'], $file_path);
//     $conn->query("INSERT INTO Photos (section_id, file_path) VALUES ($section_id, '$file_path')");
//     // Redirection vers la même section après l'ajout
//     header("Location: {$_SERVER['PHP_SELF']}?section_id=$section_id");
//     exit();
// }
// ------------------------------------------//


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter des nouvelles images</title>
</head>

<body>
    <div class="form">
        <form method="POST" action="" enctype="multipart/form-data">
            <label for="titre">Titre :</label>
            <input type="text" id="titre" name="section_titre" required><br><br>

            <label for="date">Date :</label>
            <input type="date" id="date" name="section_date" required><br><br>

            <input type="submit" value="Ajouter">
        </form>
        <!-- <h2>Ajouts d'images</h2>
        <form action="" method="POST" enctype="multipart/form-data">
            <label for="title">Titre de la catégorie :</label><br>
            <input type="text" id="title" placeholder="Titre" name="title"><br><br>
            <label for="datetime">Date :</label><br>
            <input type="date" id="datetime" name="datetime"><br><br>
            <input type="file" name="image[]" multiple><br/><br>
            <input type="submit" name="submit" value="Ajouter">
        </form> -->
    </div>
</body>

</html>