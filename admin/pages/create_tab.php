<?php
require_once('connect_ddb.php');

try {
    // Connexion à MySQL sans spécifier de base de données
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Création de la base de données
    $sql_create_db = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`";
    $conn->exec($sql_create_db);

    // Connexion à la base de données créée
    // $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    // $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Création de la table "statistiques des visites"
    $tab_visiteurs = "CREATE TABLE IF NOT EXISTS `statistiques_visites` (
        `id` int(11) UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `ip` varchar(64) NOT NULL,
        `date_visite` date NOT NULL,
        `heure_visite` time NOT NULL,
        `appareil` varchar(20) NOT NULL,
        `navigateur` varchar(50) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_visite_jour` (`ip`, `date_visite`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->exec($tab_visiteurs);

    // Renommer la colonne 'maps' en 'navigateur' si elle existe (pour compatibilité)
    try {
        $alter_visiteurs = "ALTER TABLE `statistiques_visites` CHANGE `maps` `navigateur` VARCHAR(50) NOT NULL";
        $conn->exec($alter_visiteurs);
    } catch (PDOException $e) {
        // Si la colonne 'maps' n'existe pas ou est déjà renommée, ignorer l'erreur
    }

    // Création de la table "users"
    $tab_users = "CREATE TABLE IF NOT EXISTS `users` (
        `user_id` INT(6) UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `firstName` VARCHAR(30) NOT NULL,
        `lastName` VARCHAR(30) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'superAdmin') DEFAULT 'admin'
    ) ENGINE=InnoDB";
    $conn->exec($tab_users);

    // Création de la table "password_reset_request"
    $tab_password_reset_request = "CREATE TABLE IF NOT EXISTS `password_reset_request` (
        `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY, 
        `user_id` INT NOT NULL, 
        `date_request` DATETIME NOT NULL, -- date et heure de la réinitialisation 
        `token` VARCHAR(255) NOT NULL, 
        PRIMARY KEY (`id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
    ) ENGINE=InnoDB";
    $conn->exec($tab_password_reset_request);

    // Création de la table "criteriums"
    $tab_criteriums = "CREATE TABLE IF NOT EXISTS `criteriums` (
        `id_crit` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `title_crit` VARCHAR(255) NOT NULL,
        `image_crit` VARCHAR(255) NOT NULL,
        `contenu_crit` TEXT NOT NULL,
        `pdf_crit` LONGBLOB
    ) ENGINE=InnoDB";
    $conn->exec($tab_criteriums);

        // Création de la table "galerie"
    $tab_galerie = "CREATE TABLE IF NOT EXISTS `section_Galerie` (
        `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `titre` VARCHAR(255) NOT NULL,
        `date_evenement` DATE NOT NULL,
        `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `date_modification` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `id_user` INT UNSIGNED NOT NULL,
        FOREIGN KEY (`id_user`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
        INDEX (`date_evenement`),
        INDEX (`date_creation`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($tab_galerie);

    // Création de la table "media"
    $tab_media = "CREATE TABLE IF NOT EXISTS `media` (
        `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `id_galerie` INT UNSIGNED NOT NULL,
        `nom_fichier` VARCHAR(255) NOT NULL,
        `chemin_fichier` VARCHAR(250) NOT NULL,
        `type_media` VARCHAR(50) NOT NULL,
        `taille_fichier` INT UNSIGNED NOT NULL,
        `largeur` SMALLINT UNSIGNED,
        `hauteur` SMALLINT UNSIGNED,
        `ordre_affichage` SMALLINT UNSIGNED DEFAULT 0,
        `hash_fichier` VARCHAR(64) DEFAULT NULL,
        `date_upload` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `date_modification` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`id_galerie`) REFERENCES `section_Galerie`(`id`) ON DELETE CASCADE,
        INDEX (`id_galerie`),
        INDEX (`date_upload`),
        INDEX (`ordre_affichage`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($tab_media);

    // Création de la table "miniatures"
    $tab_miniature = "CREATE TABLE IF NOT EXISTS `miniatures` (
        `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `id_media` INT UNSIGNED NOT NULL UNIQUE,
        `chemin_miniature` VARCHAR(250) NOT NULL,
        `largeur` SMALLINT UNSIGNED,
        `hauteur` SMALLINT UNSIGNED,
        `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`id_media`) REFERENCES `media`(`id`) ON DELETE CASCADE,
        INDEX (`date_creation`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($tab_miniature);

    // Création de la table "JourneesChampionnat"
    $tab_journeesChampionnat = "CREATE TABLE IF NOT EXISTS `journeesChampionnat` (
        `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `journee_id` VARCHAR(50) NOT NULL,
        `dateDebut` VARCHAR(10) NOT NULL COMMENT 'format DD/MM/YYYY',
        `dateFin`   VARCHAR(10) NOT NULL COMMENT 'format DD/MM/YYYY',
        `poule` ENUM('pouleA', 'pouleB') DEFAULT 'pouleA',
        `type_journee` ENUM('aller', 'retour') DEFAULT 'aller' 
    ) ENGINE=InnoDB";
        $conn->exec($tab_journeesChampionnat);
    // echo $tab_journeesChampionnat;
    // Création de la table "Equipes"

    $tab_equipes = "CREATE TABLE IF NOT EXISTS `Equipes` (
        `equipe_id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `NomEquipe` VARCHAR(15) NOT NULL,
        `poules` ENUM('pouleA', 'pouleB') DEFAULT 'pouleA'
    ) ENGINE=InnoDB";
    $conn->exec($tab_equipes);

    // Création de la table "Rencontres"
    $tab_rencontres = "CREATE TABLE IF NOT EXISTS `rencontres` (
        `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `journee_id` INT NOT NULL,
        `equipe1` VARCHAR(255) NULL,
        `score1` INT DEFAULT NULL,
        `score2` INT DEFAULT NULL,
        `equipe2` VARCHAR(255) NULL,
        FOREIGN KEY (`journee_id`) REFERENCES journeesChampionnat(id) ON DELETE CASCADE
    ) ENGINE=InnoDB";
    $conn->exec($tab_rencontres);

    // Création de la table "classementJoueurs"
    $tab_classJoueurs = "CREATE TABLE IF NOT EXISTS `classementJoueurs` (
        `ID` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
        `Nom` VARCHAR(255) NOT NULL,
        `Prenom` VARCHAR(255) NOT NULL,
        `Classement` INT NOT NULL,
        `PointsDebutSaison` INT NOT NULL,
        `PointsMensuels` INT NOT NULL,
        `EvolutionMensuelle` INT NOT NULL
    ) ENGINE=InnoDB";
    $conn->exec($tab_classJoueurs);

    // echo "Base de données et tables créées avec succès !<br>";    
} catch (PDOException $e) {
    echo "Erreur lors de la création de la base de données et des tables : " . $e->getMessage();
}