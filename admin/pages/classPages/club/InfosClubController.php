<?php
declare(strict_types=1);

namespace Admin\ClassPages\Club;

use Admin\Includes\ErrorMessages;

/**
 * Classe InfosClubController
 * Gère la logique de traitement des informations du club
 */
class InfosClubController
{
    private InfosClubManager $manager;

    public function __construct(InfosClubManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Traite la soumission du formulaire
     * 
     * @param array $postData Les données POST
     * @param string $csrfToken Le token CSRF stocké en session
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function processFormSubmission(array $postData, string $csrfToken): array
    {
        // Vérification CSRF à temps constant (protection anti-timing attack [M-03])
        $submittedToken = (string)($postData['csrf_token'] ?? '');
        if ($csrfToken === '' || $submittedToken === '' || !hash_equals($csrfToken, $submittedToken)) {
            return [
                'success' => false,
                'message' => ErrorMessages::get('CSRF_INVALID')
            ];
        }

        // Récupération et préparation des données
        // Les champs nom_club et adresse sont échappés car pas de balises HTML attendues
        $nomClub = trim(htmlspecialchars($postData['nom_club'] ?? '', ENT_QUOTES, 'UTF-8'));
        $horaires = trim($postData['horaires'] ?? ''); // Conserve \n et espaces
        $mapUrl = trim($postData['map_url'] ?? '');
        $adresse = trim(htmlspecialchars($postData['adresse'] ?? '', ENT_QUOTES, 'UTF-8'));
        $tarifAdulteRaw = $postData['tarif_adulte'] ?? '';
        $tarifMineursRaw = $postData['tarif_mineurs'] ?? '';

        // Validation du contenu
        $errors = [];

        // Valider le nom du club
        $validationNomClub = Validations::validateCleanContent($nomClub, 'Nom du club');
        if (!$validationNomClub['valid']) {
            $errors[] = $validationNomClub['error'];
        }

        // Valider les horaires
        $validationHoraires = Validations::validateCleanContent($horaires, 'Horaires d\'entraînements');
        if (!$validationHoraires['valid']) {
            $errors[] = $validationHoraires['error'];
        }

        // Valider l'adresse
        $validationAdresse = Validations::validateCleanContent($adresse, 'Adresse');

        if (!$validationAdresse['valid']) {
            $errors[] = $validationAdresse['error'];
        }

        // Valider les tarifs
        $validationAdulte = Validations::validateTarif($tarifAdulteRaw, 'Tarif Adultes');
        if (!$validationAdulte['valid']) {
            $errors[] = $validationAdulte['error'];
        }

        $validationMineurs = Validations::validateTarif($tarifMineursRaw, 'Tarif Mineurs');
        if (!$validationMineurs['valid']) {
            $errors[] = $validationMineurs['error'];
        }

        // Valider l'URL de la carte
        $validationMapUrl = Validations::validateMapUrl($mapUrl);

        if (!$validationMapUrl['valid']) {
            $errors[] = $validationMapUrl['error'];
        } else {
            $mapUrl = $validationMapUrl['url'];
        }

        // Si des erreurs sont détectées, les retourner
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => implode("\n\n", $errors),
                'postData' => $postData
            ];
        }

        // Tous les validations OK, enregistrer en base
        try {
            $dataToSave = [
                'nom_club'      => $nomClub,
                'horaires'      => $horaires,
                'tarif_adulte'  => $validationAdulte['value'],
                'tarif_mineurs' => $validationMineurs['value'],
                'map_url'       => $mapUrl,
                'adresse'       => $adresse,
            ];

            $this->manager->updateInfosClub($dataToSave);

            return [
                'success' => true,
                'message' => ""
            ];
        } catch (\Exception $e) {
            // Logger l'erreur côté serveur et renvoyer un message générique au client
            error_log('[InfosClub] Erreur lors de la mise à jour : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => ErrorMessages::get('INFOS_CLUB_UPDATE_ERROR'),
                'postData' => $postData
            ];
        }
    }

    /**
     * Récupère les infos actuelles du club
     * 
     * @return array
     */
    public function getInfosClub()
    {
        try {
            return $this->manager->getInfosClub();
        } catch (\Exception $e) {
            return [
                'error' => ErrorMessages::format('INFOS_CLUB_FETCH_ERROR', [$e->getMessage()])
            ];
        }
    }
}
?>
