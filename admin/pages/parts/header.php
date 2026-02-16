<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>COSMVTT</title>
    <!-- <link rel="stylesheet" href="../../../css/style.css"> -->
    <link rel="stylesheet" href="/cosmtt/admin/css/style_admin.css">
    <link rel="stylesheet" href="/cosmtt/admin/includes/session_check.php">
    <!-- <link rel="stylesheet" href="/cosmtt/admin/css/popup.css"> -->
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
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once dirname(__DIR__, 2) . '/includes/session_check.php';
    ?>
    <header>
        <nav>
            <ul id="menu">
                <li>
                    <a href="/cosmtt/admin/pages/home.php">HOME</a>
                </li>
                <li>
                    <a href="/cosmtt/index.php">DÉCONNEXION</a>
                </li>
            </ul>

        </nav>
        <a href="/cosmtt/admin/pages/home.php"><img src="/cosmtt/img/logo_COSM.webp" alt="Logo COSMVTT"
                class="logo-cosmtt"></a>
    </header>