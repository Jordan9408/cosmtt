<?php
declare(strict_types=1);

namespace Admin\Includes;

/**
 * Centralise les messages d'erreur et les utilitaires de génération.
 */
final class ErrorMessages
{
    /** @var array<string,string> */
    private static array $messages = [
        // Sécurité
        'CSRF_INVALID' => 'Erreur de sécurité : Jeton CSRF invalide.',
        'CSRF_INVALID_VALIDATE' => 'Erreur de sécurité : Jeton de validation corrompu.',
        'PERMISSION_DENIED' => "Vous n'avez pas les autorisations pour accéder à cette page.",
        'SESSION_EXPIRED' => 'Votre session a expiré. Veuillez vous reconnecter.',

        // Authentification
        'AUTH_CSRF_INVALID' => 'Erreur de sécurité : Session de connexion invalide.',
        'AUTH_TOO_MANY_ATTEMPTS' => "Trop de tentatives de connexion. Pour des raisons de sécurité, votre compte est temporairement verrouillé pour une durée de 15 minutes.\nDurée restante : %(1) minutes.",
        'AUTH_EMAIL_INVALID' => 'Format d\'adresse e-mail incorrect.',
        'AUTH_PASSWORD_EMPTY' => 'Le mot de passe est obligatoire.',
        'AUTH_LOGIN_FAILED' => 'E-mail ou mot de passe incorrect.',
        'AUTH_INTERNAL_ERROR' => 'Une erreur interne est survenue. Veuillez réessayer plus tard.',
        'AUTH_FIELDS_REQUIRED' => "Veuillez remplir tous les champs.",
        'AUTH_USER_NOT_FOUND' => "Utilisateur introuvable.",
        'AUTH_UPDATE_ERROR' => "Erreur lors de la mise à jour du mot de passe.",

        // --- Contact & Messagerie (index.php) ---
        'CONTACT_SUCCESS'            => "Votre message a été envoyé avec succès.",
        'CONTACT_ERROR'              => "Erreur lors de l'envoi du message. Veuillez vérifier vos informations.",

        // Réinitialisation de mot de passe
        'AUTH_RESET_EMAIL_INVALID' => 'Adresse email invalide pour la réinitialisation.',
        'AUTH_RESET_MAIL_SENT' => 'Si un compte est associé à cet e-mail, un lien de réinitialisation a été envoyé à votre boite mail.',
        'AUTH_RESET_SEND_ERROR' => 'Le serveur de messagerie a rencontré un problème lors de l\'envoi.',
        'AUTH_RESET_SAVE_ERROR' => 'Erreur lors de la sauvegarde de la demande de réinitialisation.',
        'AUTH_TOKEN_MISSING' => 'Jeton de récupération manquant.',
        'AUTH_TOKEN_INVALID' => 'Le lien de récupération est invalide ou a expiré.',
        'AUTH_PASSWORD_MISMATCH' => 'Les deux mots de passe ne sont pas identiques.',
        'AUTH_PASSWORD_UPDATED' => 'Votre mot de passe a été modifié avec succès.'. "\n" . 'Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.',

        // Validation / requêtes
        'USER_ID_INVALID' => 'Erreur : Identifiant utilisateur invalide.',
        'REQUEST_INVALID' => 'Requête invalide.',

        // Utilisateurs - Modification
        'USER_ADD_FAILED' => "Échec de l'ajout de l'utilisateur.",
        'USER_UPDATE_FAILED' => "Échec de la modification de l'utilisateur.",
        'USER_DELETE_FAILED' => "Erreur lors de la suppression de l'utilisateur.",

        // Classement des joueurs
        'JOUER_CSRF_INVALID' => 'Erreur de sécurité : Jeton CSRF invalide.',
        'JOUER_DELETE_INVALID_TOKEN' => 'Erreur de sécurité : Jeton de suppression invalide.',
        'JOUER_FETCH_FAILED' => 'Impossible de récupérer la liste des joueurs.',
        'JOUER_NOT_FOUND' => 'Joueur introuvable.',
        'JOUER_NOM_REQUIRED' => 'Le nom est obligatoire.',
        'JOUER_PRENOM_REQUIRED' => 'Le prénom est obligatoire.',
        'JOUER_CLASSEMENT_MIN' => 'Le classement doit être au moins 5.',
        'JOUER_POINTS_DEBUT_SAISON_MIN' => 'Les points de début de saison doivent être au minimum 500.',
        'JOUER_POINTS_MENSUELS_POSITIVE' => 'Les points mensuels doivent être positifs.',
        'JOUER_ADD_ERROR' => "Une erreur technique est survenue lors de l'ajout.",
        'JOUER_MASS_UPDATE_REQUIRED_FIELDS' => 'Le nom et le prénom sont obligatoires pour tous les joueurs.',
        'JOUER_MASS_UPDATE_ERROR' => 'Une erreur est survenue lors de la mise à jour en masse.',
        'JOUER_DELETE_FAILED' => 'Échec de la suppression du joueur.',

        // Utilisateurs - Champs
        'ROLE_INVALID' => ' Rôle invalide',
        'FIRSTNAME_INVALID' => "Prénom invalide",
        'LASTNAME_INVALID' => "Nom invalide",
        'EMAIL_INVALID' => 'Email invalide',
        'PASSWORD_TOO_SHORT' => 'Mot de passe trop court (min 12 caractères).',
        'PASSWORD_NO_CAPITAL' => 'Le mot de passe doit contenir au moins une majuscule.',
        'PASSWORD_NO_LOWERCASE' => 'Le mot de passe doit contenir au moins une minuscule.',
        'PASSWORD_NO_NUMBER' => 'Le mot de passe doit contenir au moins un chiffre.',
        'PASSWORD_NO_SPECIAL' => 'Le mot de passe doit contenir au moins un caractère spécial.',
        'PASSWORDS_MISMATCH' => 'Le mot de passe doit être identique à la confirmation.',
        'NAME_TOO_LONG' => 'Prénom/Nom trop long.',
        'EMAIL_TOO_LONG' => 'Email trop long.',

        // --- Erreurs Générales & Système ---
        'GENERIC_ERROR' => "Une erreur inattendue est survenue.",
        'DATABASE_ERROR' => "Une erreur est survenue lors de la communication avec la base de données.",
        'ID_INVALID'             => 'Identifiant (ID) invalide.',

        // Championnat - Rencontres / Journées
        'CHAMPIONNAT_JOURNEE_DATES_DIFFERENTES' => 'La journée "%(1)" existe déjà avec des dates différentes.\nVeuillez vérifier les dates pour la journée "%(1)".',
        'CHAMPIONNAT_JOURNEE_CONFLIT_PERIODE' => 'Il y a déjà une journée de Championnat durant cette période.\nVeuillez vérifier la journée et les dates.',
        'CHAMPIONNAT_EQUIPES_DEJA_ENGAGEE' => 'Une équipe est déjà engagée dans un match lors de cette journée.\nUne équipe ne peut disputer qu’une seule rencontre par journée.',
        'CHAMPIONNAT_EQUIPES_DEJA_ENGAGEES' => 'Plusieurs équipes sont déjà engagées dans un match lors de cette journée.\nUne équipe ne peut disputer qu’une seule rencontre par journée.',
        'UPLOAD_LIMIT_EXCEEDED' => "Erreur : Le volume total des fichiers envoyés dépasse la limite du serveur. Veuillez envoyer moins de fichiers à la fois.",

        // Articles & Médias
        'ARTICLE_TITLE_DATE_REQ' => 'Le titre et la date sont obligatoires.',
        'ARTICLE_DATE_INVALID'   => 'Le format de la date est invalide.',
        'ARTICLE_TITLE_TOO_LONG' => 'Le titre est trop long (maximum 150 caractères).',
        'ARTICLE_TITLE_INVALID_CHARS' => 'Le titre contient des caractères non autorisés.',
        'ARTICLE_NOT_FOUND'      => 'Aucun article trouvé pour le moment.',
        'ARTICLE_LOAD_ERROR'     => 'Impossible de charger les articles.',
        'ARTICLE_CREATE_FAILED'  => 'Impossible de créer l\'article.',
        'ARTICLE_UPDATE_FAILED'  => 'Impossible de mettre à jour l\'article.',
        'ARTICLE_DELETE_FAILED'  => 'Impossible de supprimer l\'article.',
        'GD_SUPPORT_MISSING'     => 'Erreur serveur : La librairie GD avec support WebP est requise.',

        'IMAGE_SIZE_EXCEEDED'    => 'L\'image est trop volumineuse.',
        'IMAGE_FORMAT_DENIED'    => 'Format d\'image non autorisé. Formats acceptés : JPG, JPEG, PNG.',
        'IMAGE_MIME_DENIED'      => 'Type MIME de l\'image non autorisé.',
        'IMAGE_MALWARE'          => 'Menace détectée par l\'antivirus serveur dans l\'image.',
        'IMAGE_CORRUPT'          => 'Format d\'image non reconnu ou fichier corrompu.',
        'IMAGE_CONV_ERROR'       => 'Erreur lors de la conversion de l\'image en WebP.',
        'IMAGE_WRITE_ERROR'      => 'Erreur lors de l\'écriture du fichier image.',

        // Galerie
        'GALERIE_GD_MISSING' => 'Erreur de configuration serveur : La librairie d\'images GD avec le support WebP est requise mais n\'est pas activée.',
        'GALERIE_EVENT_TITLE_DATE_REQ' => 'Le titre et la date sont obligatoires.',
        'GALERIE_EVENT_TITLE_TOO_LONG' => 'Le titre est trop long (maximum 150 caractères).',
        'GALERIE_EVENT_TITLE_INVALID_CHARS' => 'Le titre contient des caractères non autorisés.',
        'GALERIE_EVENT_DATE_INVALID' => 'Le format de la date est invalide.',
        'GALERIE_EVENT_NOT_FOUND' => 'Événement introuvable.',
        'GALERIE_EVENT_CONFLICT_SAME_TITLE_DATE' => 'Un événement avec ce titre et cette date existe déjà.',
        'GALERIE_EVENT_CONFLICT_SAME_TITLE_SEASON' => 'Un événement avec ce titre existe déjà dans la même saison : %s.',
        'GALERIE_EVENT_CONFLICT_SAME_DATE' => 'Un événement existe déjà à cette date : %s.',
        'GALERIE_EVENT_CREATE_FAILED' => 'La création ou la récupération de l\'événement a échoué.',
        'GALERIE_FILE_DATA_MISSING' => 'Données de fichier manquantes.',
        'GALERIE_FILE_DUPLICATE' => 'Cette image existe déjà dans cet événement.',
        'GALERIE_GET_IMAGES_ERROR' => 'Erreur lors de la récupération des images.',
        'GALERIE_PARAMS_MISSING' => 'Paramètres manquants.',
        'GALERIE_EDIT_ERROR' => 'Erreur lors de la modification.',
        'GALERIE_DELETE_ERROR' => 'Erreur lors de la suppression.',
        'GALERIE_MEDIA_ID_INVALID' => 'ID média invalide.',
        'GALERIE_MEDIA_NOT_FOUND' => 'Média introuvable.',
        'GALERIE_REORDER_EMPTY' => 'Liste d\'IDs vide pour le réordonnancement.',
        'GALERIE_REORDER_ERROR' => 'Erreur lors du réordonnancement.',
        'GALERIE_LOAD_PAGE_ERROR' => 'Erreur lors du chargement de la page.',
        'GALERIE_NO_EVENTS_FOUND' => 'Aucun événement n\'a été trouvé dans la galerie.',
        'GALERIE_NO_IMAGE_PROCESSED' => 'Aucune image n\'a été traitée.',
        'GALERIE_EVENT_BLOCKED' => 'AJOUT BLOQUÉ : Conflit détecté.',
        'GALERIE_LOAD_ERROR'         => "Erreur lors de la récupération de la galerie : %(1)",

        // Galerie - Controller / AJAX & formulaires
        'GALERIE_CONTROLLER_CSRF_INVALID' => 'Erreur de sécurité : Jeton CSRF invalide.',
        'GALERIE_CONTROLLER_GD_MISSING' => 'Erreur de configuration serveur : La librairie d\'images GD avec le support WebP est requise mais n\'est pas activée.',
        'GALERIE_CONTROLLER_EVENT_TITLE_DATE_REQ' => 'Le titre et la date sont obligatoires.',
        'GALERIE_CONTROLLER_EVENT_DATE_INVALID' => 'Le format de la date est invalide.',
        'GALERIE_CONTROLLER_EVENT_NOT_FOUND' => 'Événement introuvable.',
        'GALERIE_CONTROLLER_EVENT_CREATE_FAILED' => 'La création ou la récupération de l\'événement a échoué.',
        'GALERIE_CONTROLLER_IMAGE_DATA_MISSING' => 'Données manquantes.',
        'GALERIE_CONTROLLER_IMAGE_DUPLICATE' => 'Cette image existe déjà dans cet événement.',
        'GALERIE_CONTROLLER_ERROR_GENERIC' => 'Erreur : %(1)',
        'GALERIE_CONTROLLER_AJOUT_BLOQUE' => 'AJOUT BLOQUÉ : Conflit détecté.',

        'GALERIE_CONTROLLER_GET_IMAGES_ERROR' => 'Erreur lors de la récupération des images.',
        'GALERIE_CONTROLLER_PAGE_LOAD_ERROR' => 'Erreur lors du chargement de la page.',

        // AJAX - Paramètres invalides
        'GALERIE_CONTROLLER_PARAMS_MISSING' => 'Paramètres manquants.',
        'GALERIE_CONTROLLER_DATE_INVALID' => 'Format de date invalide.',
        'GALERIE_CONTROLLER_ID_INVALID' => 'ID invalide.',
        'GALERIE_CONTROLLER_TITLE_REQUIRED' => 'Le titre est obligatoire.',
        'GALERIE_CONTROLLER_DATE_REQUIRED_FORMAT' => 'La date est obligatoire et doit être au format YYYY-MM-DD.',
        'GALERIE_CONTROLLER_EDIT_ERROR' => 'Erreur lors de la modification.',
        'GALERIE_CONTROLLER_DELETE_ERROR' => 'Erreur lors de la suppression.',
'GALERIE_CONTROLLER_DELETE_MEDIA_ERROR' => 'Erreur lors de la suppression.',
        'GALERIE_CONTROLLER_MODIF_ERREURS' => '❌ Erreurs lors de la modification :\n\n%(1)',
        'GALERIE_CONTROLLER_MEDIA_ID_INVALID' => 'ID média invalide.',

        'GALERIE_CONTROLLER_MEDIA_NOT_FOUND' => 'Média introuvable.',
        'GALERIE_CONTROLLER_REORDER_ERROR' => 'Erreur lors du réordonnement.',
        'GALERIE_CONTROLLER_REORDER_EMPTY' => 'Liste d\'IDs vide.',

        'PDF_SIZE_EXCEEDED'      => 'Le document PDF est trop volumineux.',
        'PDF_FORMAT_DENIED'      => 'Le document doit être au format PDF.',
        'PDF_MIME_DENIED'        => 'Type MIME du PDF non valide.',
        'PDF_MALWARE'            => 'Menace détectée par l\'antivirus serveur dans le PDF.',
        'PDF_SAVE_ERROR'         => 'Erreur lors de l\'enregistrement du PDF temporaire.',

        'CONTENT_REQUIRED'       => 'Le contenu de l\'article est obligatoire.',
        'CONTENT_HTML_DENIED'    => 'Le contenu ne doit contenir que du texte et des nombres. Les balises HTML ne sont pas autorisées.',
        'CONTENT_SIZE_EXCEEDED'  => 'Le contenu est trop volumineux (max 100 000 caractères).',
        'CONTENT_SUSPICIOUS'     => 'Contenu suspect détecté (caractères répétés anormalement).',
        'EQUIPE_NOM_INVALID_CHARS' => 'Le nom de l\'équipe contient des caractères non autorisés.',

        // Informations Club & Validations Génériques
        'VALIDATION_NULL_CHAR'         => '%(1) : Caractères nuls détectés (Tentative d\'obfuscation).',
        'VALIDATION_DANGEROUS_CONTENT' => '%(1) : %(2)',
        'TARIF_NOT_INTEGER'            => '%(1) : Le prix doit être un nombre entier. Vous avez entré : \'%(2)\'',
        'TARIF_RANGE_ERROR'            => '%(1) : Le prix doit être entre 0 et 200 €. Vous avez entré : %(2)€',
        'TARIF_GENERIC_INVALID'        => '%(1) : Le prix de la licence doit être un entier.',
        'MAP_URL_IFRAME_CONTENT_DENIED' => 'URL de la carte : Contenu non autorisé détecté en dehors de la balise iframe.',
        'MAP_URL_IFRAME_SRC_ERROR'     => 'URL de la carte invalide : impossible d\'extraire le src de l\'iframe.',
        'MAP_URL_INVALID_URL'          => 'URL de la carte invalide. Une URL http(s) est attendue.',
        'MAP_URL_SCHEME_DENIED'        => 'Schéma non autorisé pour l\'URL de la carte (seulement http/https).',
        'MAP_URL_HOST_DENIED'          => 'Hôte de l\'URL de la carte non autorisé. Seuls Google Maps, OpenStreetMap ou Bing sont autorisés.',
        'INFOS_CLUB_UPDATE_ERROR'      => 'Une erreur est survenue lors de l\'enregistrement. Contactez l\'administrateur.',
        'INFOS_CLUB_FETCH_ERROR'       => 'Erreur lors de la récupération des informations du club : %(1)',

        // Championnat - Equipes
        'AddSuccess'             => '',
        'DeleteSuccess'          => '',
        'DeleteFail'             => 'Échec de la suppression de l\'équipe.',
        'EquipeExists'           => 'Cette équipe existe déjà dans cette poule.',
        'EmptyFields'            => 'Veuillez remplir tous les champs.',
        'InvalidRequest'         => 'Requête de suppression invalide.',
    ];

    public const DEFAULT_GENERIC_MESSAGE = "Une erreur inattendue est survenue.";

    /**
     * Vérifie si une clé de message d'erreur existe.
     */
    public static function has(string $key): bool
    {
        return isset(self::$messages[$key]);
    }

    /**
     * Retourne le message associé à la clé ou le message générique par défaut.
     */
    public static function get(string $key): string
    {
        return self::$messages[$key] ?? self::$messages['GENERIC_ERROR'] ?? self::DEFAULT_GENERIC_MESSAGE;
    }

    public static function genericError(): string
    {
        return self::get('GENERIC_ERROR');
    }

    /**
     * Remplace les placeholders %(1), %(2), ... dans le message.
     * Exemple: format('CLE', ['val1'])
     *
     * @param array<int,string|int|float> $args
     */
    public static function format(string $key, array $args = []): string
    {
        $msg = self::get($key);
        foreach ($args as $idx => $val) {
            $n = (string)($idx + 1);
            $msg = str_replace('%(' . $n . ')', (string)$val, $msg);
        }
        return $msg;
    }


    /**
     * Combine plusieurs messages d'erreur (pour afficher une seule alert).
     * @param array<int,string> $errors
     */
    public static function formatMany(array $errors, bool $withGenericPrefix = false): string
    {
        $errors = array_values(array_filter($errors, static fn($e) => is_string($e) && $e !== ''));
        if (empty($errors)) {
            return self::get('GENERIC_ERROR');
        }

        if ($withGenericPrefix) {
            return self::get('GENERIC_ERROR') . "\n\n" . implode("\n\n", $errors);
        }

        return implode("\n\n", $errors);
    }

    // Helpers dédiés à ModifyUser (convenance)
    public static function firstnameInvalid(): string { return self::get('FIRSTNAME_INVALID'); }
    public static function lastnameInvalid(): string { return self::get('LASTNAME_INVALID'); }
    public static function emailInvalid(): string { return self::get('EMAIL_INVALID'); }
    public static function roleInvalid(): string { return self::get('ROLE_INVALID'); }
    public static function passwordTooShort(): string { return self::get('PASSWORD_TOO_SHORT'); }
}
