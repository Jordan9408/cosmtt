
<?php
require_once('../../../config/config.php');
require_once('../create_tab.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre_crit = $_POST['title_crit'];
    $image_crit = $_FILES['image_crit'];
    $contenu_crit = $_POST['contenu-crit'];
    $pdf_crit = $_FILES['pdf_crit'];

    // Connexion à la base de données
    $conn = new Database();
    // $pdo = $conn->connect();

    // Requête d'insertion de l'article
    $stmt = $pdo->prepare("INSERT INTO criteriums (titre_crit, image_crit, contenu_crit, pdf_crit) VALUES (?, ?, ?, ?)");
    $stmt->execute([$titre_crit, $image_crit, $contenu_crit, $pdf_crit]);

    // Message de succès
    echo 'Article inséré avec succès!';
}
?>
        

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>insérer un article</title>
</head>
<body>
    <h1>Insérer un article</h1>
    <form action="insert.php" method="post" enctype="multipart/form-data">
        <label for="title_crit">Titre :</label><br><br>
        <input type="text" name="title_crit" id="title_crit" required><br><br>

        <label for="image_crit">Image :</label><br><br>
        <input type="file" name="image_crit" id="image_crit" required><br><br>

        <label for="contenu-crit">Contenu :</label><br>
        <textarea name="contenu-crit" id="contenu-crit" rows="4" cols="50" required></textarea><br><br>

        <label for="pdf_crit">Fichier PDF :</label><br><br>
        <input type="file" name="pdf_crit" id="pdf_crit" accept=".pdf" required><br><br>

        <input type="submit" value="Insérer">
    </form>
</body>
</html>






























