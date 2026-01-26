
<?php
// Inclusion du fichier de connexion à la base de données
include_once('../connect_ddb.php');
require_once('../class/User.php');
require_once('../classPages/users/DeleteUserController.php');

$controller = new DeleteUserController($conn);
$controller->handleRequest();
?>
