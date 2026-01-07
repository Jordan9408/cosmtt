<?php 
    include_once("../../../html_partials/header.php");
?>

<?php
// if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["fileToUpload"])) {
//     $uploadDirectory = "chemin/vers/le/repertoire/de/stockage/";

//     $tempName = $_FILES["fileToUpload"]["tmp_name"];
//     $fileName = $_FILES["fileToUpload"]["name"];
//     $filePath = $uploadDirectory . $fileName;

//     if (move_uploaded_file($tempName, $filePath)) {
//         // Fichier téléchargé avec succès, insérer le chemin dans la base de données
//         $sql = "INSERT INTO table_files (file_name, file_path) VALUES ('$fileName', '$filePath')";
//         if ($conn->query($sql) === TRUE) {
//             echo "Fichier téléchargé avec succès et enregistré dans la base de données.";
//         } else {
//             echo "Erreur: " . $sql . "<br>" . $conn->error;
//         }
//     } else {
//         echo "Erreur lors du téléchargement du fichier.";
//     }
// } else {
//     // echo "Aucun fichier n'a été téléchargé ou la requête n'est pas une requête POST.";
// }

// $conn->close();
?>




<!DOCTYPE html>
<html>
<head>
    <title>Ajouter des photos et des vidéos</title>
</head>
<body>
    <form action="" method="post" enctype="multipart/form-data">
        
        <input type="file" name="fileToUpload" id="fileToUpload">
        <input type="submit" value="Télécharger le fichier" name="submit">
    </form>
</body>
</html>
