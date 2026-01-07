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

    // Création de la table "users"
    $tab_users = "CREATE TABLE IF NOT EXISTS `users` (
        `user_id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `firstName` VARCHAR(30) NOT NULL,
        `lastName` VARCHAR(30) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'superAdmin') DEFAULT 'admin'
    ) ENGINE=InnoDB";
    $conn->exec($tab_users);

    // Création de la table "password_reset_request"
    $tab_password_reset_request = "CREATE TABLE IF NOT EXISTS `password_reset_request` (
        `id` INT NOT NULL AUTO_INCREMENT, 
        `user_id` INT NOT NULL, 
        `date_request` DATETIME NOT NULL, 
        `token` VARCHAR(255) NOT NULL, 
        PRIMARY KEY (`id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
    ) ENGINE=InnoDB";
    $conn->exec($tab_password_reset_request);

    // Création de la table "criteriums"
    $tab_criteriums = "CREATE TABLE IF NOT EXISTS `criteriums` (
        `id_crit` INT PRIMARY KEY AUTO_INCREMENT,
        `title_crit` VARCHAR(255) NOT NULL,
        `image_crit` VARCHAR(255) NOT NULL,
        `contenu_crit` TEXT NOT NULL,
        `pdf_crit` LONGBLOB
    ) ENGINE=InnoDB";
    $conn->exec($tab_criteriums);

    // Création de la table "Categories_photos"
    $tab_sections = "CREATE TABLE IF NOT EXISTS `Categories_photos` (
        `section_id` INT PRIMARY KEY AUTO_INCREMENT,
        `section_title` VARCHAR(255) NOT NULL,
        `section_date` DATETIME NOT NULL
    ) ENGINE=InnoDB";
    $conn->exec($tab_sections);

    // Création de la table "Photos"
    $tab_photos = "CREATE TABLE IF NOT EXISTS `Photos` (
        `photo_id` INT PRIMARY KEY AUTO_INCREMENT,
        `section_id` INT NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        FOREIGN KEY (`section_id`) REFERENCES `Categories_photos`(`section_id`)
    ) ENGINE=InnoDB";
    $conn->exec($tab_photos);

    // Création de la table "JourneesChampionnat"
    $tab_journeesChampionnat = "CREATE TABLE IF NOT EXISTS `journeesChampionnat` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
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
        `equipe_id` INT PRIMARY KEY AUTO_INCREMENT,
        `NomEquipe` VARCHAR(15) NOT NULL,
        `poules` ENUM('pouleA', 'pouleB') DEFAULT 'pouleA'
    ) ENGINE=InnoDB";
    $conn->exec($tab_equipes);

    // Création de la table "Rencontres"
    $tab_rencontres = "CREATE TABLE IF NOT EXISTS `rencontres` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
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
        `ID` INT PRIMARY KEY AUTO_INCREMENT,
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