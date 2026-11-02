/**
 * showUser.js - Gestion des messages et des confirmations de suppression des utilisateurs
 */
document.addEventListener('DOMContentLoaded', function () {
    const cancelModifyUser = document.getElementById('cancelModifyUser');
    if (cancelModifyUser) {
        cancelModifyUser.addEventListener('click', function () {
            window.location.href = 'showUser.php';
        });
    }

    // 1. Vérifier les paramètres GET pour afficher les messages
    const urlParams = new URLSearchParams(window.location.search);
    const message = urlParams.get('message');
    if (message === 'DeleteSuccess') {
        // alert('');
    } else if (message === 'CannotDeleteSelf') {
        alert('Action impossible : vous ne pouvez pas supprimer votre propre compte.');
    } else if (message === 'InvalidCsrf') {
        alert('Session expirée ou jeton de sécurité invalide. Veuillez réessayer.');
    } else if (message === 'DeleteFail') {
        alert('Erreur lors de la suppression de l\'utilisateur.');
    } else if (message === 'InvalidRequest') {
        alert('Requête invalide.');
    }

    // 2. Gestionnaire de confirmation de suppression
    document.querySelectorAll('.delete-user-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')) {
                e.preventDefault();
            }
        });
    });

    // 3. Nettoyer l'URL propre sans rechargement
    if (window.history.replaceState && window.location.search) {
        window.history.replaceState(null, null, window.location.pathname);
    }
});
