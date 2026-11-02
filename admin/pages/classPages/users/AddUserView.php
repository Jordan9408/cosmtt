<?php
declare(strict_types=1);

namespace Admin\ClassPages\Users;

// Définition des rôles autorisés (accès superAdmin strict)
$requiredRoles = ['superAdmin'];
require_once dirname(__DIR__, 3) . '/includes/security.php';

// Défense en profondeur : vérification stricte du rôle superAdmin
if ($role !== 'superAdmin') {
    http_response_code(403);
    header('Location: /cosmtt/admin/pages/home.php?unauthorized=1');
    exit;
}
/**
 * Vue en charge du rendu HTML pour l'ajout d'un utilisateur.
 */
class AddUserView
{
    /**
     * Génère l'interface utilisateur.
     */
    public function render(array $formData, string $alertMessage, string $currentUserRole = 'superAdmin'): void
    {
        // Inclusion de l'en-tête (calcul des chemins depuis classPages/users)
        require_once dirname(__DIR__, 2) . '/parts/header.php';

        // Échappement sécurisé des données pour l'affichage (XSS)
        $firstName = htmlspecialchars($formData['firstName'] ?? '', ENT_QUOTES, 'UTF-8');
        $lastName  = htmlspecialchars($formData['lastName'] ?? '', ENT_QUOTES, 'UTF-8');
        $email     = htmlspecialchars($formData['email'] ?? '', ENT_QUOTES, 'UTF-8');
        $role      = htmlspecialchars($formData['role'] ?? '', ENT_QUOTES, 'UTF-8');
        $csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
        ?>

<!-- Feuille de style responsive spécifique aux formulaires Ajouter/Modifier utilisateur -->
<link rel="stylesheet" href="/cosmtt/admin/css/style_utilisateurs.css">

<div class="title-bar">Ajouter un utilisateur</div>

<main<?= $alertMessage !== '' ? ' data-alert="' . htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    <form action="" method="POST" class="form encad" style="margin-bottom: 10px;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="form_input">
            <label for="firstName">Prénom:</label>
            <input type="text" name="firstName" id="firstName" placeholder="Prénom" value="<?= $firstName ?>"
                autocomplete="given-name" required>

            <label for="lastName">Nom:</label>
            <input type="text" name="lastName" placeholder="Nom de famille" id="lastName" value="<?= $lastName ?>"
                autocomplete="family-name" required>

            <label for="email">Email:</label>
            <input type="email" name="email" placeholder="Email" id="email" value="<?= $email ?>" autocomplete="email"
                required>

            <div class="password-field">
                <label for="password">
                    Mot de passe :
                    <i class="fa-solid fa-circle-info info-tooltip-icon"
                       title="Minimum 12 caractères, 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial"></i>
                </label>
                <div class="password-input-wrapper">
                    <div class="input-with-icon">
                        <input type="password" name="password" id="password" placeholder="Mot de Passe"
                            autocomplete="new-password" minlength="12" required>
                        <button type="button" class="toggle-password" data-target="password"
                            aria-label="Afficher/Masquer le mot de passe" title="Afficher/Masquer">
                            <i class="fa-solid fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="password-field">
                <label for="confPassword">
                    Confirmation :
                    <!-- <i class="fa-solid fa-circle-info info-tooltip-icon"
                       title="Confirmation identique au mot de passe saisi."></i> -->
                </label>
                <div class="password-input-wrapper">
                    <div class="input-with-icon">
                        <input type="password" name="confPassword" id="confPassword" placeholder="Confirme Mot de Passe"
                            autocomplete="new-password" minlength="12" required>
                        <button type="button" class="toggle-password" data-target="confPassword"
                            aria-label="Afficher/Masquer la confirmation du mot de passe" title="Afficher/Masquer">
                            <i class="fa-solid fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
            </div>

            <label for="role">Rôle:</label>
            <?php if ($currentUserRole === 'superAdmin'): ?>
            <select name="role" id="role" required>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="superAdmin" <?= $role === 'superAdmin' ? 'selected' : '' ?>>Super Admin</option>
            </select>
            <?php else: ?>
            <input type="text" id="role" value="Admin" disabled style="background-color: #f0f0f0; cursor: not-allowed;">
            <input type="hidden" name="role" value="admin">
            <?php endif; ?>

            <input type="submit" value="Ajouter" name="send">
        </div>
    </form>
</main>

<?php
    require_once dirname(__DIR__, 2) . '/parts/footer.php';
?>

</body>

</html>
<?php
    }
}