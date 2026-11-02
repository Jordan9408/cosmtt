<?php
declare(strict_types=1);

// Définition des rôles autorisés (accès superAdmin strict - [H-01])
$requiredRoles = ['superAdmin'];
require_once dirname(__DIR__, 3) . '/includes/security.php';

// Défense en profondeur : vérification stricte du rôle superAdmin
if ($role !== 'superAdmin') {
    http_response_code(403);
    header('Location: /cosmtt/admin/pages/home.php?unauthorized=1');
    exit;
}

class ShowUserView
{
    /**
     * Génère les lignes du tableau HTML pour les utilisateurs
     */
    public function renderUserRows(array $users): void
    {
        if (empty($users)) {
            echo "<tr><td colspan='7' class='message'>Aucun utilisateur présent !</td></tr>";
            return;
}

        $csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $currentUserRole = (string)($_SESSION['role'] ?? '');

        foreach ($users as $user) {
            $userId = (int)($user['user_id'] ?? 0);
            $isSelf = ($userId === $currentUserId);
            $canDelete = ($currentUserRole === 'superAdmin' && !$isSelf);

            echo "<tr>";
            echo "<td data-label='Prénom'>" . htmlspecialchars((string)$user['firstName']) . "</td>";
            echo "<td data-label='Nom'>" . htmlspecialchars((string)$user['lastName']) . "</td>";
            echo "<td data-label='Email'>" . htmlspecialchars((string)$user['email']) . "</td>";
            echo "<td data-label='Mot de passe'>********</td>"; // Sécurité : Ne pas afficher le mot de passe
            echo "<td data-label='Rôle'>" . htmlspecialchars((string)$user['role']) . "</td>";
            echo "<td class='action-cell' data-label='Modifier'><a class='image' href='modifyUser.php?id=" . htmlspecialchars((string)$userId) . "'><img src='../../../img/icones/write.png' alt='Modifier'></a></td>";
            echo "<td class='action-cell' data-label='Supprimer'>";
            
            if ($canDelete) {
                echo "<form method='POST' action='deleteUser.php' class='delete-user-form' style='display:inline;'>";
                echo "<input type='hidden' name='csrf_token' value='" . $csrf . "'>";
                echo "<input type='hidden' name='id' value='" . htmlspecialchars((string)$userId) . "'>";
                echo "<button type='submit' class='image-button delete-user-btn' title='Supprimer' style='background:none;border:none;cursor:pointer;padding:0;display:inline-flex;align-items:center;justify-content:center;'>";
                echo "<img src='../../../img/icones/remove.png' alt='Supprimer'>";
                echo "</button>";
                echo "</form>";
            } elseif ($isSelf) {
                echo "<button type='button' class='image-button delete-user-btn' disabled title='Vous ne pouvez pas supprimer votre propre compte' style='background:none;border:none;cursor:not-allowed;padding:0;display:inline-flex;align-items:center;justify-content:center;opacity:0.35;'>";
                echo "<img src='../../../img/icones/remove.png' alt='Suppression désactivée' style='filter: grayscale(100%); pointer-events: none;'>";
                echo "</button>";
            } else {
                echo "<button type='button' class='image-button delete-user-btn' disabled title='Privilèges superAdmin requis pour supprimer un compte' style='background:none;border:none;cursor:not-allowed;padding:0;display:inline-flex;align-items:center;justify-content:center;opacity:0.35;'>";
                echo "<img src='../../../img/icones/remove.png' alt='Suppression désactivée' style='filter: grayscale(100%); pointer-events: none;'>";
                echo "</button>";
            }

            echo "</td>";
            echo "</tr>";
        }
    }
}