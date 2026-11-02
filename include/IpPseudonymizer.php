<?php

declare(strict_types=1);

/**
 * Pseudonymise une adresse IP avant stockage (RGPD).
 * HMAC-SHA256 : irréversible sans le secret, tout en conservant
 * l’unicité d’un visiteur pour les stats quotidiennes.
 */
final class IpPseudonymizer
{
    public static function hash(?string $rawIp): string
    {
        $rawIp = $rawIp ?: '0.0.0.0';
        $secret = getenv('IP_HASH_SECRET');
        if ($secret === false || $secret === '') {
            $secret = hash(
                'sha256',
                (string) getenv('DB_NAME') . '|' . (string) getenv('DB_USER') . '|cosmtt-ip-pepper'
            );
        }

        return hash_hmac('sha256', $rawIp, $secret);
    }

    public static function looksLikeRawIp(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Convertit les IPs encore stockées en clair (tables statistiques_visites et visitors).
     */
    public static function migrateStoredIps(PDO $db): void
    {
        try {
            $db->exec('ALTER TABLE `statistiques_visites` MODIFY `ip` VARCHAR(64) NOT NULL');
        } catch (PDOException $e) {
            // Table absente ou colonne déjà compatible
        }

        self::migrateStatistiques($db);
        self::migrateVisitors($db);
    }

    private static function migrateStatistiques(PDO $db): void
    {
        $stmt = $db->query(
            'SELECT `id`, `ip` FROM `statistiques_visites` WHERE CHAR_LENGTH(`ip`) <= 45'
        );
        $update = $db->prepare(
            'UPDATE `statistiques_visites` SET `ip` = :hash WHERE `id` = :id'
        );
        $delete = $db->prepare(
            'DELETE FROM `statistiques_visites` WHERE `id` = :id'
        );

        foreach ($stmt as $row) {
            $current = (string) $row['ip'];
            if (!self::looksLikeRawIp($current)) {
                continue;
            }

            try {
                $update->execute([
                    ':hash' => self::hash($current),
                    ':id' => $row['id'],
                ]);
            } catch (PDOException $e) {
                $delete->execute([':id' => $row['id']]);
            }
        }
    }

    private static function migrateVisitors(PDO $db): void
    {
        $exists = $db->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'visitors'"
        )->fetchColumn();

        if (!(int) $exists) {
            return;
        }

        $columns = $db->query('SHOW COLUMNS FROM `visitors`')->fetchAll(PDO::FETCH_COLUMN);
        $hasId = in_array('id', $columns, true);

        try {
            $db->exec('ALTER TABLE `visitors` MODIFY `ip_address` VARCHAR(64) NOT NULL');
        } catch (PDOException $e) {
            // Table absente ou colonne déjà compatible
        }

        if ($hasId) {
            $stmt = $db->query(
                'SELECT `id`, `ip_address` FROM `visitors` WHERE CHAR_LENGTH(`ip_address`) <= 45'
            );
            $update = $db->prepare(
                'UPDATE `visitors` SET `ip_address` = :hash WHERE `id` = :id'
            );
            foreach ($stmt as $row) {
                $current = (string) $row['ip_address'];
                if (!self::looksLikeRawIp($current)) {
                    continue;
                }
                $update->execute([
                    ':hash' => self::hash($current),
                    ':id' => $row['id'],
                ]);
            }
            return;
        }

        $stmt = $db->query(
            'SELECT DISTINCT `ip_address` FROM `visitors` WHERE CHAR_LENGTH(`ip_address`) <= 45'
        );
        $update = $db->prepare(
            'UPDATE `visitors` SET `ip_address` = :hash WHERE `ip_address` = :old'
        );
        foreach ($stmt as $row) {
            $current = (string) $row['ip_address'];
            if (!self::looksLikeRawIp($current)) {
                continue;
            }
            $update->execute([
                ':hash' => self::hash($current),
                ':old' => $current,
            ]);
        }
    }
}
