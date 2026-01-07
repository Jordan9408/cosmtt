<?php

class Config
{
   private $host = "localhost"; // Adresse du serveur MySQL
   private $username = "root"; // Nom d'utilisateur de la base de données
   private $password = ""; // Mot de passe de la base de données
   private $database = "bd_cosmtt_php"; // Nom de la base de données
   private $conn; // Cette variable va contenir la connexion à la base de données

   public function __construct()
   {
       try {
           // Connexion à la base de données
           $this->conn = new PDO("mysql:host={$this->host};dbname={$this->database}", $this->username, $this->password);
           $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       } catch (PDOException $e) {
           die("Erreur de connexion à la base de données : " . $e->getMessage());
       }
   }

   public function getConnection()
   {
       return $this->conn;
   }
}

// Suppression de l'instanciation de Database et appel à getConnection()
// $db = new Database();
// $conn = $db->getConnection();
?>
