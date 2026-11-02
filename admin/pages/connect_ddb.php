<?php

require_once dirname(__DIR__, 2) . '/config/load_env.php';

class Database {
    // Propriété pour stocker l'instance PDO
    private ?PDO $conn = null;

    /**
     * Établit et retourne la connexion à la base de données (Pattern Singleton simplifié)
     * * @return PDO
     */
    public function getConnection(): PDO {
        // On ne crée la connexion que si elle n'existe pas déjà
        if ($this->conn === null) {
            try {
                $host = getenv('DB_HOST') ?: 'localhost';
                $dbname = getenv('DB_NAME') ?: '';
                $user = getenv('DB_USER') ?: '';
                $pass = getenv('DB_PASS') ?: '';

                $dsn = "mysql:host=" . $host . ";dbname=" . $dbname . ";charset=utf8mb4";
                
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];

                $this->conn = new PDO($dsn, $user, $pass, $options);
                
            } catch (PDOException $e) {
                // Gestion sécurisée des erreurs
                error_log("Erreur de connexion : " . $e->getMessage());
                die("Une erreur de connexion au serveur est survenue. Veuillez réessayer plus tard.");
            }
        }
        
        return $this->conn;
    }
}

// --- Astuce de compatibilité ---
// Pour éviter de casser le reste de ton site qui utilise déjà la variable $conn,
// on instancie la classe et on récupère la connexion directement à la fin du fichier.
global $conn;

$database = new Database($conn);
$conn = $database->getConnection();
