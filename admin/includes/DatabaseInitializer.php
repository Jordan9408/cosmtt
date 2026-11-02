<?php

declare(strict_types=1);

final class DatabaseInitializer
{
    private const REQUIRED_TABLES = [
        'statistiques_visites',
        'users',
        'articles',
        'infos_club',
        'Equipes',
        'journeesChampionnat',
        'rencontres',
        'classementJoueurs',
        'password_reset_request',
        'visitors',
    ];

    public function __construct(private PDO $conn)
    {
    }

    public function initializeIfNeeded(): bool
    {
        $existingTables = $this->conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $existingTables = array_map('strtolower', $existingTables);

        foreach (self::REQUIRED_TABLES as $table) {
            if (!in_array(strtolower($table), $existingTables, true)) {
                $this->initializeAll();
                return true;
            }
        }

        return false;
    }

    public function initializeAll(): void
    {
        $this->createVisitesTable();
        $this->createUsersTable();
        $this->createArticlesTable();
        $this->createInfosClubTable();
        $this->createEquipesTable();
        $this->createJourneesTable();
        $this->createRencontresTable();
        $this->createClassementJoueursTable();
        $this->createPasswordResetTable();
        $this->createVisitorsTable();
    }

    private function createVisitesTable(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS `statistiques_visites` (
            `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
            `ip` VARCHAR(64) NOT NULL,
            `date_visite` DATE NOT NULL,
            `heure_visite` TIME NOT NULL,
            `appareil` VARCHAR(20) NOT NULL,
            `navigateur` VARCHAR(50) NOT NULL,
            UNIQUE KEY `unique_visite_jour` (`ip`, `date_visite`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function createUsersTable(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS `users` (
            `user_id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
            `firstName` VARCHAR(30) NOT NULL,
            `lastName` VARCHAR(30) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'superAdmin') NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function createArticlesTable(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS `articles` (
            `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
            `title_article` VARCHAR(255) NOT NULL,
            `date_evmt_article` DATE NOT NULL,
            `img_article` VARCHAR(255) DEFAULT NULL,
            `contenu_article` LONGTEXT NOT NULL,
            `pdf_article` VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function createInfosClubTable(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS `infos_club` (
            `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            `nom_club` VARCHAR(255) NOT NULL DEFAULT '',
            `horaires` TEXT NOT NULL,
            `tarif_adulte` VARCHAR(100) NOT NULL DEFAULT '',
            `tarif_mineurs` VARCHAR(100) NOT NULL DEFAULT '',
            `map_url` TEXT NOT NULL,
            `adresse` TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function createEquipesTable(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS `Equipes` (
            `equipe_id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
            `NomEquipe` VARCHAR(15) NOT NULL,
            `poules` ENUM('pouleA', 'pouleB') DEFAULT 'pouleA'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function createJourneesTable(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS `journeesChampionnat` (
            `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
            `journee_id` VARCHAR(100) NOT NULL,
            `dateDebut` DATE NOT NULL,
            `dateFin` DATE NOT NULL,
            `poule` ENUM('pouleA', 'pouleB') NOT NULL,
            `type_journee` ENUM('aller', 'retour') NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function createRencontresTable(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS `rencontres` (
            `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
            `journee_id` INT UNSIGNED NOT NULL,
            `equipe1` VARCHAR(255) NULL,
            `score1` INT DEFAULT NULL,
            `score2` INT DEFAULT NULL,
            `equipe2` VARCHAR(255) NULL,
            CONSTRAINT `fk_rencontres_journee`
                FOREIGN KEY (`journee_id`) REFERENCES `journeesChampionnat` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function createClassementJoueursTable(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS `classementJoueurs` (
            `ID` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
            `Nom` VARCHAR(255) NOT NULL,
            `Prenom` VARCHAR(255) NOT NULL,
            `Classement` INT NOT NULL,
            `PointsDebutSaison` INT NOT NULL,
            `PointsMensuels` INT NOT NULL,
            `EvolutionMensuelle` INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function createPasswordResetTable(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS `password_reset_request` (
            `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `date_request` DATETIME NOT NULL,
            `expires_date` DATETIME NOT NULL,
            `token` VARCHAR(64) NOT NULL,
            `fingerprint` VARCHAR(64) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function createVisitorsTable(): void
    {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS `visitors` (
            `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
            `ip_address` VARCHAR(64) NOT NULL,
            `visit_date` DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}