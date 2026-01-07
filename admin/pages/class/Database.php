<?php
/**
 * Classe DatabaseConnection
 * Gère la connexion à la base de données via PDO en utilisant le pattern Singleton.
 * Assure une seule instance de connexion pour toute l'application.
 */
class DatabaseConnection
{
    /**
     * Instance unique de la connexion PDO
     * @var PDO|null
     */
    private static ?PDO $instance = null;

    /**
     * Instance unique de la classe Config
     * @var Config|null
     */
    private static ?Config $configInstance = null;

    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private function __construct()
    {
        // Ne rien faire ici
    }

    /**
     * Récupère l'instance unique de la connexion PDO
     * @return PDO
     * @throws PDOException en cas d'erreur de connexion
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            if (self::$configInstance === null) {
                require_once __DIR__ . './../../../config/config.php';
                self::$configInstance = new Config();
            }

            try {
                self::$instance = self::$configInstance->getConnection();
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Gestion sécurisée de l'erreur
                throw new PDOException("Erreur de connexion à la base de données : " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    /**
     * Empêche la duplication de l'instance (clonage)
     */
    private function __clone()
    {
    }

    /**
     * Empêche la désérialisation de l'instance
     */
    public function __wakeup()
    {
        throw new \Exception("Désérialisation non autorisée.");
    }
}
?>
