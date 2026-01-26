<?php
/**
 * Contrôleur pour l'affichage des utilisateurs
 */
class ShowUserController
{
    private $userManager;

    public function __construct($conn)
    {
        $this->userManager = new UserManager($conn);
    }

    /**
     * Affiche la liste des utilisateurs
     */
    public function displayUsers()
    {
        $this->userManager->displayUsers();
    }
}

/**
 * Classe pour la gestion des utilisateurs (déplacée depuis showUser.php)
 */
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
?>
