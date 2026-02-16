<?php
include_once('../parts/header.php');
require_once('../connect_ddb.php');
require_once('../class/User.php');
require_once('../classPages/users/AddUserController.php');

$controller = new AddUserController($conn);
$controller->handlePost();

$firstNameValue = $controller->getFirstNameValue();
$lastNameValue = $controller->getLastNameValue();
$emailValue = $controller->getEmailValue();
$roleValue = $controller->getRoleValue();
$alertMessage = $controller->getAlertMessage();
?>

    <div class="title-bar">Ajouter un utilisateur</div>
    
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