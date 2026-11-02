<?php

declare(strict_types=1);

namespace Admin\Pages\Class;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

final class PasswordResetMailer
{
    public static function send(string $toEmail, string $subject, string $body, string $fromName, string $fromAddress): bool
    {
        try {
            // Inclure PHPMailer (évite dépendance à un autoload)
            require_once dirname(__DIR__, 3) . '/phpmailer/PHPMailer.php';
            require_once dirname(__DIR__, 3) . '/phpmailer/SMTP.php';
            require_once dirname(__DIR__, 3) . '/phpmailer/Exception.php';
            require_once __DIR__ . '/EmailConfig.php';

            $mail = new PHPMailer(true);

            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // Debug SMTP (à laisser à 0 en prod)
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = static fn(string $str, int $level = 0) => error_log('[SMTP][DEBUG]['.$level.'] '.$str);


            $mail->isSMTP();
            $mail->Host = \Admin\Pages\Class\EmailConfig::smtpHost();
            $mail->Port = \Admin\Pages\Class\EmailConfig::smtpPort();
            $mail->SMTPAuth = \Admin\Pages\Class\EmailConfig::smtpUser() !== '';

            if ($mail->SMTPAuth) {
                $mail->Username = \Admin\Pages\Class\EmailConfig::smtpUser();
                $mail->Password = \Admin\Pages\Class\EmailConfig::smtpPass();
            }

            $secure = \Admin\Pages\Class\EmailConfig::smtpSecure();
            if ($secure !== null && $secure !== '') {
                // PHPMailer accepte tls/ssl/empty
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                // Ajustement : si on passe 'ssl' on force ENCRYPTION_SMTPS via le mapping ci-dessous.
                $secureLower = strtolower($secure);
                if ($secureLower === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($secureLower === 'tls') {
                    // tls => STARTTLS
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
            }

            if (!\Admin\Pages\Class\EmailConfig::useStartTLS()) {
                // Ne pas forcer la config STARTTLS/SSL côté PHPMailer.
                // (Si SMTPSecure vide, PHPMailer n'essaiera pas de démarrer le chiffrement)
                $mail->SMTPSecure = '';
            }

            // Options SSL sécurisées (vérification TLS activée par défaut contre les attaques MitM [C-01])
            $mail->SMTPOptions = \Admin\Pages\Class\EmailConfig::smtpOptions();

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($toEmail);

            // Headers / contenu
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;

            // Envoyer
            $mail->send();
            return true;
        } catch (PHPMailerException | \Throwable $e) {
            // Ne pas casser le flow : on log.
            error_log('[PasswordResetMailer] ' . $e->getMessage());
            @file_put_contents(dirname(__DIR__, 3) . '/storage/mail_error.log', date('Y-m-d H:i:s') . ' [PasswordResetMailer] ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return false;
        }
    }
}