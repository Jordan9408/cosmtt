<?php
include_once('../parts/header.php');
require_once('../connect_ddb.php');
require_once('../class/User.php');

$firstNameValue = "";
$lastNameValue = "";
$emailValue = "";
$roleValue = "";
$errorMessage = "";

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
        $errorMessage = "Le mot de passe doit être identique à la confirmation.";
    } else {
        try {
            $user = new User($conn);
            if ($user->addUser($firstName, $lastName, $email, $password, $role)) {
                header("Location: ./showUser.php");
                exit();
            } else {
                $errorMessage = "Échec de l'ajout de l'utilisateur.";
            }
        } catch (InvalidArgumentException $e) {
            $errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $errorMessage = "Une erreur est survenue : " . $e->getMessage();
        }
    }
}
?>

<body>
    <h2>Ajouter un utilisateur</h2>
    <main>
        <?php if ($errorMessage): ?>
            <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <form action="" method="POST" class="form encad">
            <div>
                <label for="firstName">Prénom:</label>
                <input type="text" name="firstName" placeholder="First Name" value="<?= $firstNameValue ?>">
                <label for="lastName">Nom:</label>
                <input type="text" name="lastName" placeholder="Last Name" value="<?= $lastNameValue ?>">
                <label for="email">Email:</label>
                <input type="email" name="email" placeholder="Email" value="<?= $emailValue ?>">
                <label for="password">Mot de passe :</label>
                <input type="password" name="password" placeholder="Password">
                <label for="confPassword">Confirmer le mot de passe :</label>
                <input type="password" name="confPassword" placeholder="Confirm Password">
                <label for="role">Rôle:</label>
                <select name="role">
                    <option value="admin" <?= $roleValue === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="superAdmin" <?= $roleValue === 'superAdmin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
                <input type="submit" value="Ajouter" name="send">
            </div>
        </form>
    </main>
</body>
</html>
