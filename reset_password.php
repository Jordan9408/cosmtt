<?php
require_once('./html_partials/header_reset.php');
require_once('./admin/pages/connect_ddb.php');
require_once('./admin/pages/class/User.php');
// require_once('./css/style.css');
session_start();

/**
 * Génère un token CSRF et le stocke en session
 * @return string
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie la validité du token CSRF
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

$errors = [];
$success = false;
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $errors[] = "Token manquant.";
} else {
    try {
        // Vérification du token dans la base
        $stmt = $conn->prepare("SELECT * FROM password_reset_request WHERE token = :token");
        $stmt->execute(['token' => $token]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $errors[] = "Token invalide ou expiré.";
        } else {
            // Ici, on pourrait vérifier la date d'expiration du token si la colonne existe
            // Exemple : if (strtotime($request['expires_at']) < time()) { $errors[] = "Token expiré."; }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Vérification CSRF
                if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
                    $errors[] = "Requête invalide (CSRF).";
                }

                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                // Validation des mots de passe
                if (empty($new_password) || empty($confirm_password)) {
                    $errors[] = "Veuillez remplir tous les champs.";
                } elseif ($new_password !== $confirm_password) {
                    $errors[] = "Les mots de passe ne correspondent pas.";
                } else {
                    // Récupération des infos utilisateur
                    $user_id = (int)$request['user_id'];
                    $userClass = new User($conn);
                    $userData = $userClass->getUser($user_id);

                    if (!$userData) {
                        $errors[] = "Utilisateur introuvable.";
                    } else {
                        try {
                            // Mise à jour du mot de passe via la méthode updateUser
                            // On garde les autres infos inchangées
                            $userClass->updateUser(
                                $user_id,
                                $userData['firstName'],
                                $userData['lastName'],
                                $userData['email'],
                                $new_password,
                                $userData['role']
                            );

                            // Suppression de la demande de réinitialisation
                            $delete_request = $conn->prepare("DELETE FROM password_reset_request WHERE token = :token");
                            $delete_request->execute(['token' => $token]);

                            $success = true;
                            // On peut envisager une redirection vers la page de connexion ici
                        } catch (InvalidArgumentException $e) {
                            $errors[] = $e->getMessage();
                        } catch (Exception $e) {
                            $errors[] = "Erreur lors de la mise à jour du mot de passe.";
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Erreur lors de la réinitialisation : " . htmlspecialchars($e->getMessage());
    }
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Réinitialisation du mot de passe</title>
</head>

<body>
    <main>
        <div class="main-connect">
            <h2 id="connexion">Réinitialisation du mot de passe</h2>
            <div class="connect">
                <?php if ($success): ?>
                    <p>Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.</p>
                    <p><a href="connexion.php">Page de connexion</a></p>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <ul class="errors" style="color:red;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <form action="" method="POST" enctype="multipart/form-data" id="login-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div class="encad form-recup">
                            <label for="new_password">Nouveau mot de passe :</label>
                            <div class="mdp">
                                <input type="password" id="new_password" class="connect-input" name="new_password" required minlength="8" autocomplete="new-password">
                                <i class="fa fa-eye" aria-hidden="true" style="cursor:pointer;"></i>
                            </div>
                            <label for="confirm_password">Confirmer le mot de passe :</label>
                            <div class="mdp">
                                <input type="password" id="confirm_password" class="connect-input" name="confirm_password" required minlength="8" autocomplete="new-password">
                                <i class="fa fa-eye" aria-hidden="true" style="cursor:pointer;"></i>
                            </div>
                            <input type="submit" id="reset-submit" class="connect-submit" value="Réinitialiser le mot de passe" name="valid-button">
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>

<?php include('./html_partials/footer.php'); ?>

</html>
<script src="./js/app.js"></script>