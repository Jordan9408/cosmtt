<?php
// 1. Sécurité et Session (avant tout rendu)
$requiredRoles = ['superAdmin'];
require_once dirname(__DIR__, 2) . '/includes/security.php';
require_once dirname(__DIR__, 2) . '/includes/session_check.php';

// Défense en profondeur : vérification stricte du rôle superAdmin
if (($role ?? '') !== 'superAdmin') {
    http_response_code(403);
    header('Location: /cosmtt/admin/pages/home.php?unauthorized=1');
    exit;
}
require_once dirname(__DIR__, 1) . '/connect_ddb.php';
require_once dirname(__DIR__, 3) . '/config/config.php';

// Appel du contrôleur depuis le nouveau dossier "users"
require_once dirname(__DIR__, 1) . '/classPages/users/ShowUserController.php';
require_once dirname(__DIR__, 1) . '/parts/header.php';

// Création d'une instance du contrôleur
$controller = new ShowUserController($conn);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs</title>
    <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
    <link rel="stylesheet" href="/cosmtt/admin/css/style_utilisateurs.css">
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
    <?php
        require_once dirname(__DIR__, 1) . '/parts/footer.php';
    ?>
    <script src="/cosmtt/admin/js/showUser.js"></script>
</body>

</html>