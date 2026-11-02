<?php

declare(strict_types=1);

namespace Admin\ClassPages\Users;

// Définition des rôles autorisés (accès superAdmin strict - [H-01])
$requiredRoles = ['superAdmin'];
require_once dirname(__DIR__, 3) . '/includes/security.php';


// Défense en profondeur : vérification stricte du rôle superAdmin
if ($role !== 'superAdmin') {
    http_response_code(403);
    header('Location: /cosmtt/admin/pages/home.php?unauthorized=1');
    exit;
}

/**
 * Vue en charge du rendu HTML pour la modification de l'utilisateur.
 */
class ModifyUserView
{
    /**
     * Génère l'interface utilisateur.
     */
    public function render(array $userData, string $errorMessage, ?int $userId, bool $userExists = true, string $currentUserRole = 'superAdmin'): void
    {
        // Sécurisation des données pour l'affichage (XSS)
        $firstNameValue = htmlspecialchars($userData['firstName'] ?? '');
        $lastNameValue  = htmlspecialchars($userData['lastName'] ?? '');
        $emailValue     = htmlspecialchars($userData['email'] ?? '');
        $roleValue      = htmlspecialchars($userData['role'] ?? '');
        $csrfToken      = htmlspecialchars($_SESSION['csrf_token'] ?? '');

        ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un utilisateur</title>
    <link rel="stylesheet" href="../../css/style_admin.css">
    <link rel="stylesheet" href="../../css/style_utilisateurs.css">
</head>

<body>
    <?php if ($userExists && $userId !== null): ?>
    <div class="title-bar">Modifier un utilisateur</div>
    <main<?= !empty($errorMessage) ? ' data-alert="' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        <form action="" method="POST" class="form encad">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="form_input">
                <label for="firstName">Prénom:</label>
                <input type="text" name="firstName" id="firstName" placeholder="Prénom" value="<?= $firstNameValue ?>"
                    autocomplete="given-name" required>

                <label for="lastName">Nom:</label>
                <input type="text" name="lastName" id="lastName" placeholder="Nom de famille"
                    value="<?= $lastNameValue ?>" autocomplete="family-name" required>

                <label for="email">Email:</label>
                <input type="email" name="email" id="email" placeholder="Email" value="<?= $emailValue ?>"
                    autocomplete="email" required>

                <label for="role">Rôle:</label>
                <?php if ($currentUserRole === 'superAdmin'): ?>
                <select name="role" id="role" required>
                    <option value="admin" <?= $roleValue === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="superAdmin" <?= $roleValue === 'superAdmin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
                <?php else: ?>
                <input type="text" id="role" value="<?= $roleValue ?>" disabled style="background-color: #f0f0f0; cursor: not-allowed;">
                <input type="hidden" name="role" value="<?= $roleValue ?>">
                <?php endif; ?>

                <input type="submit" value="Modifier" name="send">
                <input type="button" value="Annuler" id="cancelModifyUser">
            </div>
        </form>
    </main>
    <?php elseif ($userId !== null): ?>
    <p>Utilisateur non trouvé.</p>
    <?php else: ?>
    <p>ID de l'utilisateur non spécifié.</p>
    <?php endif; ?>

    <?php
        require_once dirname(__DIR__, 2) . '/parts/footer.php';
    ?>

</body>

</html>
<?php
    }
}