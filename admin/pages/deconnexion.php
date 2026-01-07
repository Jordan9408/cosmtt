<?php
// Démarre ou récupère une session PHP
$_SESSION = array();

// Vérifie si l'utilisation des cookies pour les sessions est activée
if (ini_get("session.use_cookies")) {
    // Obtient les paramètres des cookies de session
    $params = session_get_cookie_params();

    // Supprime le cookie de session en définissant une date d'expiration passée
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );

    // Redirige l'utilisateur vers la page d'accueil (index.php)
    header('Location: ./index.php'); 
}

?>