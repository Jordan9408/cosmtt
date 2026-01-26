<?php
/**
 * Contrôleur pour la suppression d'un utilisateur
 */
class DeleteUserController
{
    private $user;

    public function __construct($conn)
    {
        $this->user = new User($conn);
    }

    /**
     * Traite la suppression de l'utilisateur
     */
    public function handleRequest()
    {
        // Vérification de l'ID de l'utilisateur
        if (isset($_GET['id'])) {
            $user_id = $_GET['id'];

            // Suppression de l'utilisateur
            if ($this->user->deleteUser($user_id)) {
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
    }
}
?>
