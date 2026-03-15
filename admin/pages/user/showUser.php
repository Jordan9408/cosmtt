<?php include_once('../parts/header.php') ?>
<?php include_once('../../../config/config.php') ?>

<?php
include_once "../connect_ddb.php"; // Assurez-vous que ce fichier utilise PDO
require_once('../classPages/users/ShowUserController.php');

// Création d'une instance du contrôleur
$controller = new ShowUserController($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs</title>
    <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
</head>

<body>
        <div class="title-bar">Gestion des utilisateurs</div>
    <main>
        <div id="container">
            <div class="link_container">
                <a href="addUser.php" class="link">Ajouter un utilisateur</a>
            </div>
            <section class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Prénom</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Mot de passe</th>
                            <th>Rôle</th>
                            <th>Modifier</th>
                            <th>Supprimer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Appel de la méthode pour afficher les utilisateurs
                        $controller->displayUsers();
                        ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>
    <script>
        // Vérifier les paramètres GET pour afficher les messages
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        if (message === 'DeleteFail') {
            alert('Erreur lors de la suppression de l\'utilisateur.');
        } else if (message === 'InvalidRequest') {
            alert('Requête invalide.');
        }
    </script>
</body>

</html>

