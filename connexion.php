<?php
require_once('./admin/pages/class/Auth.php');
require_once('./html_partials/header.php');
require_once('./admin/pages/create_tab.php');

session_start();

$auth = new Auth();
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nettoyage et validation des entrées
    if (isset($_POST['valid-button'])) {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email) {
            $error = "Adresse email invalide.";
        } elseif (empty($password)) {
            $error = "Le mot de passe est requis.";
        } else {
            // Tentative de connexion
            if ($auth->login($email, $password)) {
                header("Location: ./admin/pages/home.php");
                exit();
            } else {
                $error = "Adresse Mail ou Mot de passe incorrect !";
            }
        }
    } elseif (isset($_POST['recup-submit'])) {
        $recup_email = filter_var($_POST['recup_mail'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$recup_email) {
            $error = "Adresse email invalide pour la réinitialisation.";
        } else {
            $user = $auth->findUserByEmail($recup_email);
            if ($user) {
                try {
                    $token = $auth->generateResetToken();
                    if ($auth->savePasswordResetRequest($user['user_id'], $token)) {
                        if ($auth->sendResetEmail($recup_email, $token)) {
                            $error = "";
                        } else {
                            $error = "Erreur lors de l'envoi de l'email de réinitialisation.";
                        }
                    } else {
                        $error = "Erreur lors de la sauvegarde de la demande de réinitialisation.";
                    }
                } catch (Exception $e) {
                    $error = "Erreur interne : " . $e->getMessage();
                }
            } else {
                $error = "Aucun utilisateur trouvé avec cet email.";
            }
        }
    }
}
?>

<main>
    <div class="main-connect">
        <h2 id="connexion">Connexion</h2>
        <div class="connect">
            <?php if (!empty($error)) : ?>
                <p class="Error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form action="" method="POST" enctype="multipart/form-data" class="login-form">
                <div class="encad form-recup">
                    <label>Email :</label>
                    <input type="email" class="connect-input email" name="email" required>

                    <label for="password">Mot de passe :</label>
                    <div class="mdp">
                        <input type="password" id="password" class="connect-input" name="password" required>
                        <i class="fa-solid fa-eye"></i>
                    </div>
                    <a href="#" id="forgot" class="mdp_oublie" title="Oublie ou modification du mot de passe.">Mot de passe oublié</a>
                    <input type="submit" class="connect-submit" value="Connectez-vous" name="valid-button">
                </div>
            </form>
        </div>
    </div>

    <div class="main-change-password" style="display:none;">
        <h2 id="connexion">Mot de passe oublié</h2>
        <div class="connect">
            <?php if (!empty($error)) : ?>
                <p class="Error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form action="" method="POST" enctype="multipart/form-data" class="login-form">
                <div class="encad form-recup">
                    <label>Email :</label>
                    <input type="email" class="connect-input email" name="recup_mail" placeholder="Votre adresse e-mail" required>

                    <input type="submit" class="connect-submit" name="recup-submit" value="Réinitialiser">
                    <input type="submit" class="connect-submit" name="annuler" value="Annuler">
                </div>
            </form>
        </div>
    </div>
</main>
<?php include('./html_partials/footer.php'); ?>
