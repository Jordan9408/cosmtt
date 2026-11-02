<?php

/**
 * EnvoiMailPage
 *
 * Gère la réception du formulaire de contact et l'envoi de l'e-mail.
 * Tout le code est dans ce fichier unique (contrainte mono-fichier).
 *
 * Responsabilités :
 *  - Valider et assainir les champs du formulaire
 *  - Formater le nom / prénom pour l'affichage
 *  - Construire et envoyer l'e-mail
 *  - Rediriger avec le code de résultat approprié
 */
class EnvoiMailPage
{
    // -------------------------------------------------------------------------
    // Constantes de configuration
    // -------------------------------------------------------------------------

    private const MAX_NAME_LEN    = 60;
    private const MAX_EMAIL_LEN   = 254;
    private const MAX_PHONE_LEN   = 10;   // chiffres uniquement
    private const MAX_MESSAGE_LEN = 250;  // hors retours à la ligne

    // Regex nom : commence et finit par une lettre Unicode,
    // le milieu peut contenir des lettres, espaces, tirets, apostrophes.
    private const NAME_REGEX = "/^[\p{L}](?:[\p{L} \-']*[\p{L}])?$/u";

    // -------------------------------------------------------------------------
    // Champs internes
    // -------------------------------------------------------------------------

    private string $lastName  = '';
    private string $firstName = '';
    private string $email     = '';
    private string $phone     = '';
    private string $phoneDigits = '';
    private string $message   = '';

    /** Message de succès exposé à l'appelant (vide si non envoyé). */
    public string $successMessage = '';

    // -------------------------------------------------------------------------
    // Point d'entrée public
    // -------------------------------------------------------------------------

    /**
     * Lance le traitement si la requête est un POST.
     * En cas d'erreur, redirige immédiatement.
     * En cas de succès, renseigne $this->successMessage.
     */
    public function handle(): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            return;
        }

        $this->checkRateLimit();
        $this->validateCsrf();
        $this->collectInput();
        $this->validateCrlf();
        $this->validateLengths();
        $this->validateNames();
        $this->validateEmail();
        $this->validatePhone();
        $this->validateMessageLength();
        $this->validateMessageContent();

        // Formatage des champs après validation
        $this->lastName  = $this->formatNom($this->lastName);
        $this->firstName = $this->formatPrenom($this->firstName);

        $this->sendMail();
    }

    // -------------------------------------------------------------------------
    // Collecte et nettoyage initial
    // -------------------------------------------------------------------------

    private function collectInput(): void
    {
        $this->lastName   = trim((string)($_POST["last_name"] ?? ''));
        $this->firstName  = trim((string)($_POST["first_name"] ?? ''));
        $this->email      = trim((string)($_POST["mail"]       ?? ''));
        $this->phone      = trim((string)($_POST["phone"]      ?? ''));
        $this->message    = trim((string)($_POST["message"]    ?? ''));

        // Accepte les espaces dans le téléphone à la saisie (ex : "06 24 47 65 64")
        $this->phoneDigits = preg_replace('/\s+/u', '', $this->phone) ?? '';
    }

    // -------------------------------------------------------------------------
    // Validations
    // -------------------------------------------------------------------------

    /**
     * Limite les soumissions du formulaire de contact (3 envois par tranche de 10 min par IP).
     */
    private function checkRateLimit(): void
    {
        require_once dirname(__DIR__, 2) . '/include/RateLimiter.php';

        $limiter = new RateLimiter(
            storageDir: dirname(__DIR__, 2) . '/storage/rate_limit',
            maxAttempts: 3,
            windowSeconds: 600,
        );

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ipKey = 'contact_mail_' . hash('sha256', $clientIp);

        if ($limiter->isLocked($ipKey)) {
            $this->redirect("TooManyRequests");
        }

        $limiter->hit($ipKey);
    }

    /**
     * Vérifie le jeton CSRF : protège contre les soumissions forgées (CSRF).
     * Redirige immédiatement avec le code 'InvalidToken' en cas d'échec.
     */
    private function validateCsrf(): void
    {
        if (
            !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            $this->redirect("InvalidToken");
        }
    }

    /**
     * Bloque toute injection d'en-tête : CR/LF interdits dans les champs
     * utilisés dans les headers e-mail (tout sauf le message).
     */
    private function validateCrlf(): void
    {
        $fields = [$this->lastName, $this->firstName, $this->email, $this->phone];
        foreach ($fields as $field) {
            if (str_contains($field, "\r") || str_contains($field, "\n")) {
                $this->redirect("FieldTooLong");
            }
        }
    }

    /** Contrôle les longueurs maximales des champs nom et prénom. */
    private function validateLengths(): void
    {
        if ($this->lastName  !== '' && mb_strlen($this->lastName)  > self::MAX_NAME_LEN) {
            $this->redirect("FieldTooLong");
        }
        if ($this->firstName !== '' && mb_strlen($this->firstName) > self::MAX_NAME_LEN) {
            $this->redirect("FieldTooLong");
        }
    }

    /** Vérifie le format des noms (lettres Unicode, tirets, apostrophes, espaces). */
    private function validateNames(): void
    {
        if ($this->lastName  === '' || !preg_match(self::NAME_REGEX, $this->lastName) ||
            $this->firstName === '' || !preg_match(self::NAME_REGEX, $this->firstName)) {
            $this->redirect("NameContainsNumbers");
        }
    }

    /** Vérifie le format et la longueur de l'adresse e-mail. */
    private function validateEmail(): void
    {
        if (mb_strlen($this->email) > self::MAX_EMAIL_LEN ||
            !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect("InvalidEmail");
        }
    }

    /** Vérifie que le téléphone contient exactement 10 chiffres. */
    private function validatePhone(): void
    {
        if (mb_strlen($this->phoneDigits) > self::MAX_PHONE_LEN ||
            !preg_match("/^\d{10}$/", $this->phoneDigits)) {
            $this->redirect("InvalidPhone");
        }
    }

    /**
     * Vérifie la longueur du message.
     * Les retours à la ligne ne sont pas comptés dans la limite.
     */
    private function validateMessageLength(): void
    {
        $messageForLimit = preg_replace('/\R/u', '', $this->message);
        if ($this->message === '' || mb_strlen($messageForLimit ?? '') > self::MAX_MESSAGE_LEN) {
            $this->redirect("FieldTooLong");
        }
    }

    /**
     * Contrôle de sécurité du contenu (code malveillant, liens, injection).
     * Dépend de Validations::validateCleanContent().
     */
    private function validateMessageContent(): void
    {
        require_once dirname(__DIR__) . '/includes/ErrorMessages.php';
        require_once __DIR__ . '/classPages/club/Validations.php';

        $check = \Admin\ClassPages\Club\Validations::validateCleanContent($this->message, "Message");
        if (!$check['valid']) {
            $this->redirect("InvalidContent");
        }
    }

    // -------------------------------------------------------------------------
    // Formatage
    // -------------------------------------------------------------------------

    /**
     * Première lettre d'une chaîne en majuscule (UTF-8).
     */
    private function ucFirst(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        if ($s === '') {
            return '';
        }
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8')
             . mb_substr($s, 1, null, 'UTF-8');
    }

    /**
     * Formate un prénom :
     * - première lettre de chaque mot en majuscule
     * - gère les tirets et les apostrophes (ex : jean-marie -> Jean-Marie)
     */
    private function formatPrenom(string $s): string
    {
        $s     = mb_strtolower($s, 'UTF-8');
        $words = preg_split('/\s+/u', trim($s)) ?: [];

        $formatted = array_map(function (string $word): string {
            $apostParts = explode("'", $word);
            $apostParts = array_map(function (string $seg): string {
                $hyphenParts = explode('-', $seg);
                $hyphenParts = array_map(fn(string $p): string => $this->ucFirst($p), $hyphenParts);
                return implode('-', $hyphenParts);
            }, $apostParts);
            return implode("'", $apostParts);
        }, $words);

        return implode(' ', $formatted);
    }

    /**
     * Formate un nom de famille :
     * - tout en majuscules
     * - après apostrophe : majuscules également (ex : o'neil -> O'NEIL)
     */
    private function formatNom(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }

        if (mb_strpos($s, "'") === false) {
            return mb_strtoupper($s, 'UTF-8');
        }

        $parts     = explode("'", $s);
        $formatted = [];

        foreach ($parts as $seg) {
            $hyphenParts = explode('-', $seg);
            $hyphenParts = array_map(
                fn(string $p): string => mb_strtoupper($p, 'UTF-8'),
                $hyphenParts
            );
            $formatted[] = implode('-', $hyphenParts);
        }

        return implode("'", $formatted);
    }

    // -------------------------------------------------------------------------
    // Envoi de l'e-mail (via PHPMailer)
    // -------------------------------------------------------------------------

    private function sendMail(): void
    {
        $subject = "Nouveau message de {$this->lastName} {$this->firstName}";

        $body  = "Nom: {$this->lastName}\n";
        $body .= "Prénom: {$this->firstName}\n";
        $body .= "Email: {$this->email}\n";
        $body .= "Téléphone: {$this->phoneDigits}\n\n";
        $body .= "Message:\n{$this->message}\n";

        try {
            // Charger la config SMTP + EmailConfig
            require_once dirname(__DIR__, 2) . '/config/email.php';
            require_once __DIR__ . '/class/EmailConfig.php';

            // Charger PHPMailer
            require_once dirname(__DIR__, 2) . '/phpmailer/PHPMailer.php';
            require_once dirname(__DIR__, 2) . '/phpmailer/SMTP.php';
            require_once dirname(__DIR__, 2) . '/phpmailer/Exception.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // Debug SMTP dans error_log (0 en prod)
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = static fn(string $str, int $level = 0) => error_log('[CONTACT_SMTP][DEBUG][' . $level . '] ' . $str);

            // Configuration SMTP depuis EmailConfig
            $mail->isSMTP();
            $mail->Host       = \Admin\Pages\Class\EmailConfig::smtpHost();
            $mail->Port       = \Admin\Pages\Class\EmailConfig::smtpPort();
            $mail->SMTPAuth   = \Admin\Pages\Class\EmailConfig::smtpUser() !== '';

            if ($mail->SMTPAuth) {
                $mail->Username = \Admin\Pages\Class\EmailConfig::smtpUser();
                $mail->Password = \Admin\Pages\Class\EmailConfig::smtpPass();
            }

            $secure = \Admin\Pages\Class\EmailConfig::smtpSecure();
            if ($secure !== null && $secure !== '') {
                $secureLower = strtolower($secure);
                if ($secureLower === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($secureLower === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }
            }

            // Options SSL sécurisées (vérification TLS activée par défaut contre les attaques MitM [C-01])
            $mail->SMTPOptions = \Admin\Pages\Class\EmailConfig::smtpOptions();

            // Expéditeur technique (compte SMTP), Reply-To = visiteur
            $fromNameClean = str_replace(['"', "\r", "\n"], '', "{$this->lastName} {$this->firstName}");
            $mail->setFrom(\Admin\Pages\Class\EmailConfig::fromAddress(), \Admin\Pages\Class\EmailConfig::fromName());
            $mail->addReplyTo($this->email, $fromNameClean);
            $mail->addAddress(\Admin\Pages\Class\EmailConfig::recipientAddress());

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();

            $this->successMessage = "Votre message a été transmis avec succès a l'équipe du COS Marcilly. \nNous vous répondrons dans les plus brefs delais.";

        } catch (\PHPMailer\PHPMailer\Exception | \Throwable $e) {
            error_log('[EnvoiMailPage] PHPMailer Error: ' . $e->getMessage());
            @file_put_contents(dirname(__DIR__, 2) . '/storage/mail_error.log', date('Y-m-d H:i:s') . ' [EnvoiMailPage] ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }

    // -------------------------------------------------------------------------
    // Utilitaire
    // -------------------------------------------------------------------------

    /**
     * Redirige vers la page d'accueil avec le code d'erreur donné
     * et arrête l'exécution du script.
     */
    private function redirect(string $errorCode): never
    {
        // Sauvegarde des champs saisis en session
        $_SESSION['form_data'] = [
            'last_name'  => $this->lastName,
            'first_name' => $this->firstName,
            'mail'       => $this->email,
            'phone'      => $this->phone,
            'message'    => $this->message,
        ];
        header("location:index.php?message={$errorCode}#contact");
        exit();
    }
}

// =============================================================================
// Exécution
// =============================================================================

$page = new EnvoiMailPage();
$page->handle();

// La variable $envoiMail est conservée pour rester compatible avec le reste
// de l'application (ex : index.php qui l'affiche).
$envoiMail = $page->successMessage;