<?php
require_once('./admin/pages/class/Auth.php');
require_once('./html_partials/header.php');
require_once('./admin/pages/create_tab.php');

session_start();

$auth = new Auth();
$error = "";
$success_message = "";
$email = '';
$recup_email = '';
$show_reset_form = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nettoyage et validation des entrées
    if (isset($_POST['valid-button'])) {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "L'adresse email n'est pas valide.";
        } elseif (empty($password)) {
            $error = "Le mot de passe ne peut pas être vide.";
        } else {
            // Tentative de connexion
            if ($auth->login($email, $password)) {
                header("Location: ./admin/pages/home.php");
                exit();
            } else {
                $error = "Email ou mot de passe incorrect.";
            }
        }
    } elseif (isset($_POST['recup-submit'])) {
        $recup_email = $_POST['recup_mail'] ?? '';

        if (!filter_var($recup_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Adresse email invalide pour la réinitialisation.";
            $show_reset_form = true;
        } else {
            $user = $auth->findUserByEmail($recup_email);
            if ($user) {
                try {
                    $token = $auth->generateResetToken();
                    if ($auth->savePasswordResetRequest($user['user_id'], $token)) {
                        if ($auth->sendResetEmail($recup_email, $token)) {
                            $success_message = "Si un compte est associé à cet e-mail, un lien de réinitialisation a été envoyé.";
                        } else {
                            $error = "Erreur lors de l'envoi de l'email de réinitialisation.";
                            $show_reset_form = true;
                        }
                    } else {
                        $error = "Erreur lors de la sauvegarde de la demande de réinitialisation.";
                        $show_reset_form = true;
                    }
                } catch (Exception $e) {
                    $error = "Erreur interne : " . $e->getMessage();
                    $show_reset_form = true;
                }
            } else {
                // Pour des raisons de sécurité, on ne confirme pas si l'utilisateur existe.
                $success_message = "Si un compte est associé à cet e-mail, un lien de réinitialisation a été envoyé.";
            }
        }
    }
}
?>

<main>
    <?php if (!empty($error)) : ?>
        <script>window.errorToAlert = '<?= addslashes(htmlspecialchars($error)) ?>';</script>
    <?php endif; ?>
    <?php if (!empty($success_message)) : ?>
        <script>window.successToAlert = '<?= addslashes(htmlspecialchars($success_message)) ?>';</script>
    <?php endif; ?>
    <div class="main-connect" style="<?= $show_reset_form ? 'display:none;' : 'display:block;' ?>">
        <h2 class="title-bar">Connexion</h2>
        <div class="connect">
            <form action="" method="POST" enctype="multipart/form-data" class="login-form">
                <div class="encad form-recup">
                    <label for="email">Email :</label>
                    <input type="email" id="email" class="connect-input email" name="email" value="<?= htmlspecialchars($email) ?>" autocomplete="email" required>

                    <label for="password">Mot de passe :</label>
                    <div class="mdp">
                        <input type="password" id="password" class="connect-input" name="password" autocomplete="current-password" required>
                        <i class="fa-solid fa-eye"></i>
                    </div>
                    <a href="#" id="forgot" class="mdp_oublie" title="Oublie ou modification du mot de passe.">Mot de passe oublié</a>
                    <input type="submit" class="connect-submit" value="Connectez-vous" name="valid-button">
                </div>
            </form>
        </div>
    </div>

    <div class="main-change-password" style="<?= $show_reset_form ? 'display:block;' : 'display:none;' ?>">
        <h2 id="connexion">Mot de passe oublié</h2>
        <div class="connect">
            <form action="" method="POST" enctype="multipart/form-data" class="login-form">
                <div class="encad form-recup">
                    <label for="recup_mail">Email :</label>
                    <input type="email" id="recup_mail" class="connect-input email" name="recup_mail" autocomplete="email" required>

                    <input type="submit" class="connect-submit" name="recup-submit" value="Réinitialiser">
                    <input type="submit" class="connect-submit" name="annuler" value="Annuler">
                </div>
            </form>
        </div>
    </div>
</main>
<?php include('./html_partials/footer.php'); ?>
<script>
    document.getElementById('forgot').addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelector('.main-connect').style.display = 'none';
        document.querySelector('.main-change-password').style.display = 'block';
    });

    // Aussi gérer le bouton "Annuler"
    document.querySelector('input[name="annuler"]').addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelector('.main-connect').style.display = 'block';
        document.querySelector('.main-change-password').style.display = 'none';
    });
</script>