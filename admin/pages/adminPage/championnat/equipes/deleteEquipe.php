<?php
// NOUVEAU : Contrôleur pour la suppression d'équipe via GET
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {

    // Vérification de rôle : s'assurer que seul un superAdmin (ou un rôle autorisé) peut supprimer
    if ($role === 'superAdmin' || $role === 'admin') {
        $equipe_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

        if ($equipe_id !== false && $equipe_id > 0) {
            // Appel de la méthode qui gère la transaction et la cascade
            $equipeManager->supprimerEquipe($equipe_id);
            // La fonction supprimerEquipe gère la redirection et l'exit()
        } else {
            header("location: showEquipe.php?message=InvalidRequest");
            exit;
        }
    } else {
        header('HTTP/1.1 403 Forbidden');
        die('Accès non autorisé à la suppression');
    }
}
