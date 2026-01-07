<?php require_once("./../../parts/header.php"); ?>
<?php require_once("./../../../../config/config.php"); ?>
<?php
require_once("./../../class/imgClass.php");

$error = '';

if (!empty($_FILES)) {
    $img = $_FILES['img'];
    $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
    $allow_ext = array("jpg", "jpeg", "png");

    if (in_array($ext, $allow_ext)) {
        $imgName = uniqid() . '.' . $ext; // Générer un nom unique pour l'image
        $uploadDir = "images/"; // Répertoire pour stocker les images
        $thumbnailDir = "images/miniatures/"; // Répertoire pour stocker les miniatures

        if (move_uploaded_file($img['tmp_name'], $uploadDir . $imgName)) {
            // Créer une miniature
            $thumbnailName = str_replace(".{$ext}", "_mini.{$ext}", $imgName);
            Img::createMin($uploadDir . $imgName, $thumbnailDir, $thumbnailName, 200, 133);
            Img::convertJPG($uploadDir . $imgName);

            // Enregistrer les informations de l'image dans la base de données
            $imagePath = $uploadDir . $imgName;
            $thumbnailPath = $thumbnailDir . $thumbnailName;
            // Insérer les informations de l'image dans votre table d'images
            // Exemple de requête (à adapter selon votre structure de table)
            $sql = "INSERT INTO photos (photo_title, photo_url) VALUES (:photo_title, :photo_url)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':photo_title', $thumbnailPath);
            $stmt->bindParam(':photo_url', $imagePath);
            
            $stmt->execute();
        } else {
            $error = "Erreur lors du téléchargement de l'image.";
        }
    } else {
        $error = "Votre fichier n'est pas une image au format \"JPG\", \"JPEG\" ou \"PNG\".";
    }
}
?>

<h2>Ajouter des images</h2>
<?php 
    if (!empty($error)) {
        echo $error;
    }
?>
<form action="addPhoto.php" method="POST" enctype="multipart/form-data">
    <input type="file" name="img">
    <input type="submit" name="Envoyer">
</form>

<?php
// Affichage des images stockées dans le répertoire de miniatures
$thumbnailDir = "images/miniatures/";
$dir = opendir($thumbnailDir);
while ($file = readdir($dir)) {
    $allow_ext = array("jpg", "jpeg");
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, $allow_ext)) {
        $fileForDisplay = str_replace("_mini", "", $file);
?>
        <div id="galerie_photos">
            <div class="photo">
                <a href="<?php echo $thumbnailDir . $fileForDisplay; ?>">
                    <img src="<?php echo $thumbnailDir . $file; ?>" alt="images miniatures">
                </a>
            </div>
<?php
    }
}
?>

