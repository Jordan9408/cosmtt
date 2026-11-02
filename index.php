<?php

declare(strict_types=1);

// ini_set('display_errors', '1');
// error_reporting(E_ALL);

ini_set('display_errors', '0');
ini_set('log_errors', '1');


include './html_partials/header.php';

/**
 * Classe CsrfToken
 * Gère la génération du token CSRF stocké en session.
 */
final class CsrfToken
{
    private const BYTES_LENGTH = 32;

    /**
     * Retourne le token CSRF courant, ou en génère un nouveau si absent.
     */
    public static function getOrCreate(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(self::BYTES_LENGTH));
        }

        return $_SESSION['csrf_token'];
    }
}

/**
 * Classe ContactFeedback
 * Centralise les messages de retour affichés après validation du formulaire de contact.
 */
final class ContactFeedback
{
    private const MESSAGES = [
        'NameContainsNumbers' => "Le nom et le prénom ne doivent comporter que des lettres (avec accents), des tirets, des apostrophes ou des espaces (au milieu). Les chiffres sont interdits.",
        'InvalidEmail'        => "L'adresse e-mail n'est pas valide. Format attendu : nom@exemple.fr",
        'InvalidPhone'        => "Le numéro de téléphone est incorrect. Il doit comporter exactement 10 chiffres (ex: 0601020304).",
        'FieldTooLong'        => "Votre message est trop volumineux ou l'un des champs dépasse la limite autorisée (250 caractères max).",
        'InvalidToken'        => "La session de sécurité a expiré. Veuillez rafraîchir la page et renvoyer votre formulaire.",
        'InvalidContent'      => "Sécurité : Votre message contient des éléments interdits (liens, code informatique ou caractères suspects).",
        'TooManyRequests'     => "Trop de tentatives d'envoi. \nVeuillez patienter 10 minutes avant de renvoyer un message.",
    ];

    /**
     * Retourne le message associé à un code de retour, ou une chaîne vide si inconnu.
     */
    public static function getMessageFor(string $code): string
    {
        return self::MESSAGES[$code] ?? '';
    }
}

/**
 * Classe IndexPage
 * Gère la logique d'affichage de la page d'accueil (POO).
 */
final class IndexPage
{
    private const MAX_ARTICLES_TO_SHOW = 2;
    private const FALLBACK_EXCERPT_LENGTH = 180;

    private ?ArticleManager $articleManager = null;
    public array $articles = [];
    public int $itemsToShow = 0;

    /**
     * Constructeur : Initialise les dépendances.
     * @param PDO $db Connexion à la base de données.
     */
    public function __construct(PDO $db)
    {
        try {
            $this->articleManager = new ArticleManager($db);
        } catch (Throwable $e) {
            error_log('[IndexPage] Erreur initialisation Manager: ' . $e->getMessage());
        }
    }

    /**
     * Charge les données nécessaires pour la page.
     */
    public function loadData(): void
    {
        if (!$this->articleManager) {
            return;
        }

        try {
            // Récupère la première page des articles
            $data = $this->articleManager->getArticlesPaginated(1);
            $this->articles = $data['articles'] ?? [];
            $this->itemsToShow = min(self::MAX_ARTICLES_TO_SHOW, count($this->articles));
        } catch (Throwable) {
            $this->articles = [];
            $this->itemsToShow = 0;
        }
    }

    /**
     * Génère un extrait du contenu de l'article.
     */
    public function getExcerpt(string $contenu, int $minLines = 1, int $maxLines = 2): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $contenu);

        $lines = array_values(array_filter(
            preg_split("/\n/", $normalized) ?: [],
            static fn (string $l): bool => trim($l) !== ''
        ));

        $linesCount = count($lines);
        if ($linesCount < $minLines) {
            $plain = trim(strip_tags($contenu));
            if ($plain === '') {
                return '';
            }

            $sentences = preg_split('/(?<=[\.\!\?])\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);
            $sentencesCount = $sentences ? count($sentences) : 0;

            if ($sentencesCount === 0) {
                return mb_strimwidth($plain, 0, self::FALLBACK_EXCERPT_LENGTH, '...');
            }

            $take = min($minLines, $sentencesCount);
            $selected = array_slice($sentences, 0, $take);

            return implode(' ', array_map('trim', $selected)) . '...';
        }

        $take = max($minLines, min($maxLines, $linesCount));
        $selected = array_slice($lines, 0, $take);

        return implode(' ', array_map('trim', $selected)) . '...';
    }

    /**
     * Formate la date de l'événement.
     */
    public function formatDate(?string $date): string
    {
        return ($this->articleManager && $date) ? $this->articleManager->formatDateFrench((string) $date) : '';
    }

    /**
     * Détermine l'identifiant de section selon la position de l'article.
     */
    public function getSectionId(int $index): string
    {
        return match ($index) {
            0 => 'criterium1',
            default => 'criterium2',
        };
    }

    /**
     * Sécurise une chaîne pour l'affichage HTML.
     */
    public function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

// -------------------------------------------------------------------------
// CSRF : génération du token AVANT d'inclure envoi_mail.php
// (envoi_mail.php le consomme et en régénère un nouveau)
// -------------------------------------------------------------------------
$csrfToken = CsrfToken::getOrCreate();

include './admin/pages/envoi_mail.php';

require_once './config/config.php';
require_once './admin/pages/classPages/article/ArticleManager.php';

// Initialisation de la connexion PDO
$config = new Config();
$conn = $config->getConnection();

// Initialisation et chargement
$page = new IndexPage($conn);
$page->loadData();

// -------------------------------------------------------------------------
// Message de retour après redirection (erreurs de validation)
// -------------------------------------------------------------------------
$feedbackCode = $_GET['message'] ?? '';
$feedbackText = ContactFeedback::getMessageFor(is_string($feedbackCode) ? $feedbackCode : '');
$feedbackText = $feedbackText !== '' ? $page->escape($feedbackText) : '';

// Récupération des données sauvegardées en session en cas d'erreur
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); // On nettoie la session

$contactAlert = '';
if (isset($envoiMail) && $envoiMail !== '') {
    $contactAlert = '✅ Succès : ' . $envoiMail;
} elseif ($feedbackText !== '') {
    $contactAlert = '⚠️ Attention : ' . $feedbackText;
}
?>

<main id="main_index"<?= !empty($contactAlert) ? ' data-alert="' . htmlspecialchars($contactAlert, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    <div id="gymnase">
        <h1>COS MARCILLY-EN-VILLETTE<br>TENNIS DE TABLE</h1>
    </div>
    <div id="container">
        <h2>DERNIERS ARTICLES</h2>
        <hr>

        <?php
		for ($i = 0; $i < $page->itemsToShow; $i++):
			$article = $page->articles[$i];
			$title = $article['title_article'] ?? '';
			$dateDisplay = $page->formatDate($article['date_evmt_article'] ?? null);
			$href = "criteriumArticles.php#article-" . (int) $article['id'];
			$imgSrc = $article['img_article'] ?? '';
			$excerpt = $page->getExcerpt(contenu: $article['contenu_article'] ?? '', minLines: 1, maxLines: 2);
			$sectionId = $page->getSectionId($i);
		?>
        <section id="<?= $sectionId ?>">
            <article>
                <a href="<?= $page->escape($href) ?>">
                    <?php if (!empty($imgSrc)): ?>
                    <img src="/cosmtt/docs/articles/articlesPhotos/<?= $page->escape($imgSrc) ?>?v=<?= (int)@filemtime(__DIR__ . '/docs/articles/articlesPhotos/' . $imgSrc) ?>"
                        alt="<?= $page->escape($title) ?>" class="img_criterium">
                    <?php endif; ?>
                </a>
                <aside>
                    <a href="<?= $page->escape($href) ?>">
                        <h3 class="title_article">
                            <?= $page->escape($title) ?>
                            <?php if (!empty($dateDisplay)): ?>
                            <span>- <?= $page->escape($dateDisplay) ?></span>
                            <?php endif; ?>
                        </h3>
                    </a>
                    <p><?= $page->escape($excerpt) ?></p>
                </aside>
            </article>
        </section>
        <hr>
        <?php endfor; ?>

        <?php if ($page->itemsToShow === 0): ?>
        <section>
            <p style="text-align:center; margin-top:30px;">Aucun article trouvé pour le moment.</p>
        </section>
        <hr>
        <?php endif; ?>

        <section>
            <div class="button">
                <a href="./criteriumArticles.php" id="abtn"><button class="btn">VISITEZ NOTRE BLOG</button></a>
            </div>
        </section>
    </div>
    <h2 id="title-contact">Contact</h2>

    <div id="contact">

        <?php
		// Messages affichés via alert JS (pour éviter doublons/HTML intrusif)
		?>

        <section class="align-horiz">
            <!-- enctype="multipart/form-data" retiré : aucun champ fichier dans ce formulaire -->
            <form action="" method="POST">
                <!-- Token CSRF : protège contre les attaques Cross-Site Request Forgery -->
                <input type="hidden" name="csrf_token" value="<?= $page->escape($csrfToken) ?>">
                <div>
                    <label for="last_name">Nom :</label><br>
                    <input type="text" id="last_name" name="last_name" placeholder="Martin" autocomplete="family-name"
                        value="<?= $page->escape($formData['last_name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="first_name">Prénom :</label><br>
                    <input type="text" id="first_name" name="first_name" placeholder="Pierre" autocomplete="given-name"
                        value="<?= $page->escape($formData['first_name'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="mail">Mail:</label><br>
                    <input type="email" id="mail" name="mail" placeholder="cosmarcillytennisdetable@laposte.net"
                        autocomplete="email" value="<?= $page->escape($formData['email'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="phone">Téléphone:</label><br>
                    <input type="tel" id="phone" name="phone" placeholder="Numero" autocomplete="tel"
                        value="<?= $page->escape($formData['phone'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="message">Message :</label><br>
                    <textarea id="message" name="message" placeholder="Message" required><?= $page->escape($formData['message'] ?? '') ?></textarea>
                </div>
                <input type="submit" id="submit" value="Envoyer">
            </form>
        </section>
    </div>
</main>
<?php include './html_partials/footer.php'; ?>