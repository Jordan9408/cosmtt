<?php
// Définir les paramètres de connexion
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_HOST', 'localhost');
define('DB_NAME', 'bd_cosmtt_php');
try {
    // Créer la connexion
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Connexion réussie à la base de données !"; // Message de succès (facultatif)
} catch (PDOException $e) {
    // Gestion des erreurs
    echo "Erreur de connexion : " . $e->getMessage();
}
// $conn = mysqli_connect($host, $user, $pass, $dbname);

// // Check(vérifier) connection
// if (!$conn) {
//     die("Connection failed: " . mysqli_connect_error());
// }
