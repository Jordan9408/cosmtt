<?php
include_once('../parts/header.php');
include_once('../../../config/config.php');
include_once ('../connect_ddb.php');
require_once('../class/User.php');

$user = null;
$errorMessage = '';

if (isset($_GET['id'])) {
    $user_id = (int) $_GET['id'];
    $userClass = new User($conn);

    // Traitement du formulaire de modification
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? null;
        $role = $_POST['role'] ?? '';

        try {
            if ($userClass->updateUser($user_id, $firstName, $lastName, $email, $password, $role)) {
                header("Location: showUser.php");
                exit();
            } else {
                $errorMessage = "Échec de la modification de l'utilisateur.";
            }
        } catch (InvalidArgumentException $e) {
            $errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $errorMessage = "Une erreur est survenue : " . $e->getMessage();
        }
    }

    // Récupération des informations de l'utilisateur à modifier
    $user = $userClass->getUser($user_id);
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un utilisateur</title>
    <link rel="stylesheet" href="../../css/style_admin.css">
</head>

<body>
    <?php if ($user): ?>
        <h2>Modifier un utilisateur</h2>
        <main>
            <?php if ($errorMessage): ?>
            <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
            <form action="" method="POST" class="form encad">
                <div>
                    <label for="firstName">Prénom:</label>
                    <input type="text" name="firstName" placeholder="First Name" value="<?= htmlspecialchars($user['firstName']) ?>" required>
                    <label for="lastName">Nom:</label>
                    <input type="text" name="lastName" placeholder="Last Name" value="<?= htmlspecialchars($user['lastName']) ?>" required>
                    <label for="email">Email:</label>
                    <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    
                    
                    <label for="role">Rôle:</label>
                    <select name="role" required>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="superAdmin" <?= $user['role'] === 'superAdmin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                    <input type="submit" value="Modifier" name="send">
                    <input type="button" value="Annuler" onclick="window.location.href='showUser.php'">
                </div>
            </form>
        </main>
    <?php elseif (isset($user_id)): ?>
        <p>Utilisateur non trouvé.</p>
    <?php else: ?>
        <p>ID de l'utilisateur non spécifié.</p>
    <?php endif; ?>
</body>

</html>
