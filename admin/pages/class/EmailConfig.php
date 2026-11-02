<?php

declare(strict_types=1);

namespace Admin\Pages\Class;

require_once dirname(__DIR__, 3) . '/config/load_env.php';

/**
 * Configuration e-mail centralisée.
 * Les identifiants SMTP viennent uniquement de .env (aucune valeur secrète ici).
 */
final class EmailConfig
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return $value;
    }

    public static function smtpHost(): string
    {
        return (string) self::get('SMTP_HOST', 'smtp.gmail.com');
    }

    public static function smtpPort(): int
    {
        return (int) self::get('SMTP_PORT', 587);
    }

    public static function smtpUser(): string
    {
        return (string) self::get('SMTP_USER', '');
    }

    public static function smtpPass(): string
    {
        return (string) self::get('SMTP_PASS', '');
    }

    public static function smtpSecure(): ?string
    {
        // 'tls' ou 'ssl' selon ton fournisseur.
        // Sur WAMP / STARTTLS, souvent 'tls'.
        $secure = self::get('SMTP_SECURE', 'tls');
        if ($secure === null || $secure === '') {
            return null;
        }
        return (string) $secure;
    }

    public static function fromAddress(): string
    {
        return (string) self::get('SMTP_FROM_ADDRESS', 'no-reply@cosmtt.fr');
    }

    public static function fromName(): string
    {
        return (string) self::get('SMTP_FROM_NAME', 'COSM Marcilly');
    }

    public static function recipientAddress(): string
    {
        return (string) self::get('CONTACT_RECIPIENT_EMAIL', 'cosmarcillytennisdetable@laposte.net');
    }

    public static function useStartTLS(): bool
    {
        // true => PHPMailer::SMTPSecure='tls' avec SMTP_KEEPALIVE/STARTTLS
        // (PHPMailer gère ça via TLS).
        $v = strtolower((string) self::get('SMTP_USE_STARTTLS', 'true'));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Indique si la vérification des certificats TLS/SSL doit être active.
     * Activé par défaut (true) pour prévenir les attaques Man-in-the-Middle (MitM) [C-01].
     */
    public static function smtpVerifyPeer(): bool
    {
        $v = strtolower((string) self::get('SMTP_VERIFY_PEER', 'true'));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Chemin optionnel vers un fichier bundle CA (ex: cacert.pem).
     */
    public static function smtpCaFile(): ?string
    {
        $caFile = self::get('SMTP_CA_FILE');
        if ($caFile === null || $caFile === '') {
            return null;
        }
        return (string) $caFile;
    }

    /**
     * Options de contexte SSL pour PHPMailer.
     * Par défaut : vérification stricte du certificat et du nom d'hôte.
     */
    public static function smtpOptions(): array
    {
        $verifyPeer = self::smtpVerifyPeer();

        if (!$verifyPeer) {
            error_log('[SECURITY WARNING] SMTP TLS/SSL certificate verification is disabled. Risk of Man-in-the-Middle attacks!');
        }

        $ssl = [
            'verify_peer'       => $verifyPeer,
            'verify_peer_name'  => $verifyPeer,
            'allow_self_signed' => !$verifyPeer,
        ];

        $caFile = self::smtpCaFile();
        if ($caFile !== null && is_file($caFile)) {
            $ssl['cafile'] = $caFile;
        }

        return ['ssl' => $ssl];
    }
}