<?php session_start(); ?>

<?php //require_once "../pages/parts/header.html"; 
?>

<?php if (isset($_SESSION['super-admin']) || isset($_SESSION['admin'])) : ?>

    <?php if (isset($_SESSION['super-admin'])) {
        $user_id = $_SESSION['super-admin'][0];
    } ?>

    <?php if (isset($_SESSION['admin'])) {
        $user_id = $_SESSION['admin'][0];
    } ?>

    <style>
        ul {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }

        li {
            list-style: none;
            border: 1px solid #12247a;
            border-radius: 5px;
            padding: 5px;
            width: 250px;
            text-align: center;
            margin-top: 20px;
            color: white;
            background-color: #12247a;
        }

        a {
            text-decoration: none;
            color: white;
        }
    </style>

    <h1>Bonjour <?= $user_id ?></h1>
    <h3>Que voulez-vous faire ?</h3>

    <div>
        <ul>
            <li><a href="./user/addUser.php">Ajouter un utilisateur</a></li>
            <li><a href="./user/modifyUser.php">Modifier un utilisateur</a></li>
            <?php if (isset($_SESSION['super-admin'])) : ?>
                <li><a href="./user/deleteUser.php">Supprimer un utilisateur</a></li>
            <?php endif ?>

            <li><a href="./article/addArticle.php">Ajouter une article</a></li>
            <li><a href="./article/modifyArticle.php">Modifier une article</a></li>
            <?php if (isset($_SESSION['super-admin'])) : ?>
                <li><a href="./article/deleteArticle.php">Supprimer un article</a></li>
            <?php endif ?>


            <li><a href="./photo/addPhoto.php">Ajouter de photos</a></li>
            <li><a href="./photo/modifyPhoto.php">Modifier de photos</a></li>
            <?php if (isset($_SESSION['super-admin'])) : ?>
                <li><a href="./photo/deletePhoto.php">Supprimer de photos</a></li>
            <?php endif ?>


            <li><a href="./rencontre/addrencontre.php">Ajouter de rencontres</a></li>
            <li><a href="./rencontre/modifyrencontre.php">Modifier de rencontres</a></li>
            <?php if (isset($_SESSION['super-admin'])) : ?>
                <li><a href="./rencontre/deleterencontre.php">Supprimer de rencontres</a></li>
            <?php endif ?>


            <li><a href="./rencontre/resultat/addresultat.php">Ajouter de résultat</a></li>
            <li><a href="./rencontre/resultat/modifyresultat.php">Modifier de résultat</a></li>
            <?php if (isset($_SESSION['super-admin'])) : ?>
                <li><a href="./rencontre/resultat/deleteresultat.php">Supprimer de résultat</a></li>
            <?php endif ?>


            <li><a href="./classJoueur/addjoueur.php">Ajouter de photos</a></li>
            <li><a href="./classJoueur/modifyresultat.php">Modifier de photos</a></li>
            <?php if (isset($_SESSION['super-admin'])) : ?>
                <li><a href="./classJoueur/deleteresultat.php">Supprimer de photos</a></li>
            <?php endif ?>
        </ul>
    </div>

    <br>
    <a href="index.php" style="color: black">DECONNECTION</a>


    <?php require_once "../pages/parts/footer.html" 
    ?>
<?php else : ?>
    <?php //header('location:index.php') ?>
<?php endif ?>