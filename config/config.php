<?php

require_once __DIR__ . '/load_env.php';

class Config
{
   private $host;
   private $username;
   private $password;
   private $database;
   private $conn; // Cette variable va contenir la connexion à la base de données

    public function __construct() {
       $this->host = getenv('DB_HOST');
       $this->username = getenv('DB_USER');
       $this->password = getenv('DB_PASS');
       $this->database = getenv('DB_NAME');

        // Création de la connexion à la base de données avec charset explicite et désactivation de l'émulation des requêtes préparées
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            error_log('[BDD Critical Error] ' . $e->getMessage());
            throw new RuntimeException('Erreur de connexion au serveur de base de données.');
        }
    }

   public function getConnection()
   {
       return $this->conn;
   }
}

?>
