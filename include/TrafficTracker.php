<?php

declare(strict_types=1);

require_once __DIR__ . '/IpPseudonymizer.php';

/**
 * Gestionnaire de suivi et d'enregistrement des statistiques de trafic (RGPD-compliant).
 * Comptabilise l'ensemble des visiteurs (connectés ou non).
 */
class TrafficTracker
{
    private string $userAgent;
    private string $ip;

    public function __construct(
        private PDO $db,
        ?string $userAgent = null,
        ?string $rawIp = null
    ) {
        $this->userAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $this->ip = IpPseudonymizer::hash($rawIp ?? ($_SERVER['REMOTE_ADDR'] ?? null));
    }

    /**
     * Détermine le type d'appareil du visiteur.
     */
    public function getDevice(): string
    {
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $this->userAgent)) {
            return 'Tablette';
        }
        if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $this->userAgent)) {
            return 'Mobile';
        }
        if (preg_match('/(smart-tv|googletv|appletv|hbbtv|povw|netcast)/i', $this->userAgent)) {
            return 'TV';
        }
        return 'PC';
    }

    /**
     * Détermine le navigateur du visiteur.
     */
    public function getBrowser(): string
    {
        return match (true) {
            str_contains($this->userAgent, 'Edge') || str_contains($this->userAgent, 'Edg') => 'Edge',
            str_contains($this->userAgent, 'Firefox') => 'Firefox',
            str_contains($this->userAgent, 'Qwant')   => 'Qwant',
            str_contains($this->userAgent, 'Chrome')  => 'Chrome',
            str_contains($this->userAgent, 'Safari')  => 'Safari',
            str_contains($this->userAgent, 'Brave')   => 'Brave',
            default                                   => 'Autre'
        };
    }

    /**
     * Enregistre ou met à jour la visite du jour dans la table statistiques_visites.
     */
    public function logVisit(): bool
    {
        try {
            $sql = "INSERT INTO statistiques_visites (ip, date_visite, heure_visite, appareil, navigateur) 
                    VALUES (:ip, CURDATE(), CURTIME(), :device, :browser)
                    ON DUPLICATE KEY UPDATE 
                    heure_visite = CURTIME(), 
                    appareil = VALUES(appareil), 
                    navigateur = VALUES(navigateur)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':ip'      => $this->ip,
                ':device'  => $this->getDevice(),
                ':browser' => $this->getBrowser()
            ]);
        } catch (PDOException $e) {
            error_log('[TrafficTracker] Erreur lors de l\'enregistrement de la visite : ' . $e->getMessage());
            return false;
        }
    }
}
