<?php

declare(strict_types=1);

/**
 * Contrôleur de déconnexion pour la gestion de la session utilisateur
 */
class deconnexionController
{
    /**
     * Exécute le processus complet de déconnexion
     */
    public function logout(): void
    {
        // Démarre ou récupère la session si ce n'est pas déjà fait
        require_once dirname(__DIR__, 2) . '/include/session_boot.php';

        // Réinitialise le tableau des variables de session
        $_SESSION = [];

        // Détruit la session côté serveur pour une sécurité accrue
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Vérifie si l'utilisation des cookies pour les sessions est activée
        if (ini_get('session.use_cookies')) {
            // Obtient les paramètres des cookies de session
            $params = session_get_cookie_params();

            // Supprime le cookie de session en définissant une date d'expiration passée
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Redirige l'utilisateur vers la page d'accueil publique
        $appPath = '/';
        $configuredUrl = getenv('APP_URL');
        if ($configuredUrl) {
            $parsed = parse_url($configuredUrl);
            $path = $parsed['path'] ?? '';
            $appPath = rtrim($path, '/') !== '' ? rtrim($path, '/') . '/' : '/';
        } elseif (isset($_SERVER['SCRIPT_NAME'])) {
            $appPath = str_contains($_SERVER['SCRIPT_NAME'], '/cosmtt/') ? '/cosmtt/' : '/';
        }
        $this->redirect($appPath . 'index.php');
    }

    /**
     * Effectue une redirection HTTP sécurisée et interrompt le script
     */
    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

// Instanciation et exécution automatique du contrôleur pour préserver le comportement original
$controller = new deconnexionController();
$controller->logout();