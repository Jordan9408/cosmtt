<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COSMVTT</title>
    <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
    <link rel="stylesheet" href="/cosmtt/css/animation.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nanum+Brush+Script&family=Raleway:wght@800&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Nanum+Brush+Script&family=Open+Sans&family=Raleway:wght@800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

</head>

<body>

    <?php
    // Démarrage de la session si elle n'est pas déjà active
    require_once dirname(__DIR__, 3) . '/include/session_boot.php';
    
    // Vérification de la session utilisateur
    require_once dirname(__DIR__, 2) . '/includes/session_check.php';

    /**
     * Classe AppHeaderManager
     * Gère la sécurité CSRF initiale et le rendu de l'en-tête global.
     */
    class AppHeaderManager
    {
        /**
         * Initialise la sécurité au chargement de la page
         */
        public function __construct()
        {
            $this->ensureCsrfTokenExists();
        }

        /**
         * Crée un jeton CSRF sécurisé en session s'il n'existe pas encore
         */
        private function ensureCsrfTokenExists(): void
        {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }

        /**
         * Affiche la barre de navigation et le logo de l'en-tête
         */
        public function renderNavbar(): void
        {
            echo <<<HTML
            <div class="loader"></div>
            <header>
                <nav id="nav_btn">
                    <ul id="menu">
                        <li>
                            <a href="/cosmtt/admin/pages/home.php">HOME</a>
                        </li>
                        <li>
                            <a href="/cosmtt/admin/pages/deconnexion.php">DÉCONNEXION</a>
                        </li>
                    </ul>
                </nav>
                <a href="/cosmtt/admin/pages/home.php">
                    <img src="/cosmtt/img/logo_COSM.webp" alt="Logo COSMVTT" class="logo-cosmtt">
                </a>
            </header>
            HTML;
        }
    }

    // --- Instanciation et exécution automatique ---
    $headerManager = new AppHeaderManager();
    $headerManager->renderNavbar();
    ?>