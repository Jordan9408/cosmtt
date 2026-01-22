<?php include_once('../parts/header.php') ?>
<?php include_once('../../../config/config.php') ?>

<?php
// Classe pour la gestion des utilisateurs
class UserManager
{
    private $conn;

    // Constructeur pour établir la connexion à la base de données
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Méthode pour récupérer la liste des utilisateurs
    public function getUsers()
    {
        $users = array();
        $sql = "SELECT * FROM users";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    // Méthode pour afficher la liste des utilisateurs dans le tableau
    public function displayUsers()
    {
        $users = $this->getUsers();

        if (!empty($users)) {
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['firstName']) . "</td>";
                echo "<td>" . htmlspecialchars($user['lastName']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>********</td>"; // Ne pas afficher le mot de passe
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td><a class='image' href='modifyUser.php?id=" . htmlspecialchars($user['user_id']) . "'><img src='../../../img/icones/write.png' alt='Modifier'></a></td>";
                echo "<td><a class='image' href='deleteUser.php?id=" . htmlspecialchars($user['user_id']) . "' onclick=\"return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');\"><img src='../../../img/icones/remove.png' alt='Supprimer'></a></td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='7' class='message'>Aucun utilisateur présent !</td></tr>";
        }
    }
}

include_once "../connect_ddb.php"; // Assurez-vous que ce fichier utilise PDO

// Création d'une instance de la classe UserManager
$userManager = new UserManager($conn);
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
                        $userManager->displayUsers();
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
<!-- <?php include_once('../parts/footer.php') ?> -->
