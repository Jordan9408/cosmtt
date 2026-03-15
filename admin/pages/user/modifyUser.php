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
            // Formatage du nom et prénom
            $firstName = ucwords(strtolower($firstName), " -'"); // Première lettre en majuscule après espaces, tirets
            $lastName = strtoupper($lastName); // Tout en majuscule

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
        <div class="title-bar">Modifier un utilisateur</div>
        <main>
            <form action="" method="POST" class="form encad">
                <div>
                    <label for="firstName">Prénom:</label>
                    <input type="text" name="firstName" id="firstName" placeholder="First Name" value="<?= htmlspecialchars($user['firstName']) ?>" autocomplete="given-name" required>
                    <label for="lastName">Nom:</label>
                    <input type="text" name="lastName" id="lastName" placeholder="Last Name" value="<?= htmlspecialchars($user['lastName']) ?>" autocomplete="family-name" required>
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" placeholder="Email" value="<?= htmlspecialchars($user['email']) ?>" autocomplete="email" required>                   
                    <label for="role">Rôle:</label>
                    <select name="role" id="role" required>
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
    <?php
        // Inclusion du footer
        include_once dirname(__DIR__, 3) . '/html_partials/footer.php';
    ?>

    <?php if (!empty($errorMessage)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            alert(<?= json_encode($errorMessage) ?>);
        });
    </script>
    <?php endif; ?>
</body>

</html>
