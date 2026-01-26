<?php
/**
 * Contrôleur pour l'ajout d'un utilisateur
 */
class AddUserController
{
    private $user;
    private $firstNameValue;
    private $lastNameValue;
    private $emailValue;
    private $roleValue;
    private $alertMessage;

    public function __construct($conn)
    {
        $this->user = new User($conn);
        $this->firstNameValue = "";
        $this->lastNameValue = "";
        $this->emailValue = "";
        $this->roleValue = "";
        $this->alertMessage = "";
    }

    /**
     * Traite la soumission du formulaire
     */
    public function handlePost()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
            // Récupération et assainissement des données
            $firstName = trim($_POST['firstName'] ?? '');
            $lastName = trim($_POST['lastName'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confPassword = $_POST['confPassword'] ?? '';
            $role = $_POST['role'] ?? '';

            // Conserver les valeurs pour réaffichage en cas d'erreur
            $this->firstNameValue = htmlspecialchars($firstName);
            $this->lastNameValue = htmlspecialchars($lastName);
            $this->emailValue = htmlspecialchars($email);
            $this->roleValue = htmlspecialchars($role);

            // Vérification de la correspondance des mots de passe
            if ($password !== $confPassword) {
                $this->alertMessage = "Le mot de passe doit être identique à la confirmation.";
            } else {
                try {
                    // Formatage du nom et prénom
                    $firstName = ucwords(strtolower($firstName), " -'"); // Première lettre en majuscule après espaces, tirets et apostrophes
                    $lastName = strtoupper($lastName); // Tout en majuscule

                    if ($this->user->addUser($firstName, $lastName, $email, $password, $role)) {
                        // Redirection avec succès
                        header("Location: ./showUser.php?message=AddSuccess");
                        exit();
                    } else {
                        $this->alertMessage = "Échec de l'ajout de l'utilisateur.";
                    }
                } catch (InvalidArgumentException $e) {
                    $this->alertMessage = $e->getMessage();
                } catch (Exception $e) {
                    $this->alertMessage = "Une erreur est survenue : " . $e->getMessage();
                }
            }
        }
    }

    public function getFirstNameValue() { return $this->firstNameValue; }
    public function getLastNameValue() { return $this->lastNameValue; }
    public function getEmailValue() { return $this->emailValue; }
    public function getRoleValue() { return $this->roleValue; }
    public function getAlertMessage() { return $this->alertMessage; }
}
?>
