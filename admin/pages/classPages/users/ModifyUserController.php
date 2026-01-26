<?php
/**
 * Contrôleur pour la modification d'un utilisateur
 */
class ModifyUserController
{
    private $userClass;
    private $user;
    private $errorMessage;

    public function __construct($conn)
    {
        $this->userClass = new User($conn);
        $this->user = null;
        $this->errorMessage = '';
    }

    /**
     * Traite la soumission du formulaire et récupère l'utilisateur
     */
    public function handleRequest()
    {
        if (isset($_GET['id'])) {
            $user_id = (int) $_GET['id'];

            // Traitement du formulaire de modification
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
                $firstName = trim($_POST['firstName'] ?? '');
                $lastName = trim($_POST['lastName'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? null;
                $role = $_POST['role'] ?? '';

                try {
                    // Formatage du nom et prénom
                    $firstName = ucwords(strtolower($firstName), " -'"); // Première lettre en majuscule après espaces, tirets
                    $lastName = strtoupper($lastName); // Tout en majuscule

                    if ($this->userClass->updateUser($user_id, $firstName, $lastName, $email, $password, $role)) {
                        header("Location: showUser.php");
                        exit();
                    } else {
                        $this->errorMessage = "Échec de la modification de l'utilisateur.";
                    }
                } catch (InvalidArgumentException $e) {
                    $this->errorMessage = $e->getMessage();
                } catch (Exception $e) {
                    $this->errorMessage = "Une erreur est survenue : " . $e->getMessage();
                }
            }

            // Récupération des informations de l'utilisateur à modifier
            $this->user = $this->userClass->getUser($user_id);
        }
    }

    public function getUser() { return $this->user; }
    public function getErrorMessage() { return $this->errorMessage; }
}
?>
