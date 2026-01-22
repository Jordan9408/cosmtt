<?php
include_once('../parts/header.php');
require_once('../connect_ddb.php');
require_once('../class/User.php');

$firstNameValue = "";
$lastNameValue = "";
$emailValue = "";
$roleValue = "";
$alertMessage = ""; // Variable pour stocker le message d'alerte

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    // Récupération et assainissement des données
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confPassword = $_POST['confPassword'] ?? '';
    $role = $_POST['role'] ?? '';

    // Conserver les valeurs pour réaffichage en cas d'erreur
    $firstNameValue = htmlspecialchars($firstName);
    $lastNameValue = htmlspecialchars($lastName);
    $emailValue = htmlspecialchars($email);
    $roleValue = htmlspecialchars($role);

    // Vérification de la correspondance des mots de passe
    if ($password !== $confPassword) {
        $alertMessage = "Le mot de passe doit être identique à la confirmation.";
    } else {
        try {
            // Formatage du nom et prénom
            $firstName = ucwords(strtolower($firstName), " -'"); // Première lettre en majuscule après espaces, tirets et apostrophes 
            $lastName = strtoupper($lastName); // Tout en majuscule
            
            $user = new User($conn);
            if ($user->addUser($firstName, $lastName, $email, $password, $role)) {
                // Redirection avec succès
                header("Location: ./showUser.php?message=AddSuccess");
                exit();
            } else {
                $alertMessage = "Échec de l'ajout de l'utilisateur.";
            }
        } catch (InvalidArgumentException $e) {
            $alertMessage = $e->getMessage();
        } catch (Exception $e) {
            $alertMessage = "Une erreur est survenue : " . $e->getMessage();
        }
    }
}
?>

    <h2>Ajouter un utilisateur</h2>
    <main>
        <form action="" method="POST" class="form encad">
            <div>
                <label for="firstName">Prénom:</label>
                <input type="text" name="firstName" id="firstName" placeholder="First Name" value="<?= $firstNameValue ?>" autocomplete="given-name" required>
                <label for="lastName">Nom:</label>
                <input type="text" name="lastName" placeholder="Last Name" id="lastName" value="<?= $lastNameValue ?>" autocomplete="family-name" required>
                <label for="email">Email:</label>
                <input type="email" name="email" placeholder="Email" id="email" value="<?= $emailValue ?>" autocomplete="email" required>
                <label for="password">Mot de passe :</label>
                <input type="password" name="password" id="password" placeholder="Password" autocomplete="new-password" required>
                <label for="confPassword">Confirmer le mot de passe :</label>
                <input type="password" name="confPassword" id="confPassword" placeholder="Confirm Password" autocomplete="new-password" required>
                <label for="role">Rôle:</label>
                <select name="role" id="role" required>
                    <option value="admin" <?= $roleValue === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="superAdmin" <?= $roleValue === 'superAdmin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
                <input type="submit" value="Ajouter" name="send">
            </div>
        </form>
    </main>

    <?php if (!empty($alertMessage)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            alert(<?= json_encode($alertMessage) ?>);
        });
    </script>
    <?php endif; ?>
</body>
</html>