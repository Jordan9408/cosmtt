
 <?php
    // Inclusion du fichier de connexion à la base de données
    include_once('../connect_ddb.php');

    class Database
    {
        private $conn;

        public function __construct($conn)
        {
            $this->conn = $conn;
        }

        // Méthode pour supprimer un utilisateur
        public function deleteUser($user_id)
        {
            // Requête SQL pour supprimer l'utilisateur
            $sql = "DELETE FROM users WHERE user_id = ?";
            $stmt = $this->conn->prepare($sql);

            // Exécution de la requête avec le paramètre
            return $stmt->execute([$user_id]);
        }
    }

    // Vérification de l'ID de l'utilisateur
    if (isset($_GET['id'])) {
        $user_id = $_GET['id'];
        $database = new Database($conn); // Création d'une instance de la classe Database

        // Suppression de l'utilisateur
        if ($database->deleteUser($user_id)) {
            header("Location:./showUser.php?message=DeleteSuccess");
            exit();
        } else {
            header("Location:./showUser.php?message=DeleteFail");
            exit();
        }
    } else {
        header("Location:./showUser.php?message=InvalidRequest");
        exit();
    }
    ?>
