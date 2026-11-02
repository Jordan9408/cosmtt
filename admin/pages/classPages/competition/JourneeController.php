<?php
/**
 * Classe contrôleur pour gérer l'insertion d'une journée et de ses rencontres.
 *
 * Modernisation : PHP 8+ (strict types, types de retour/params, nettoyage)
 */

declare(strict_types=1);

require_once __DIR__ . '/CalendrierIntegrityChecker.php';
require_once dirname(__DIR__, 3) . '/includes/ErrorMessages.php';

use Admin\Includes\ErrorMessages;

final class JourneeController
{
    private JourneeValidator $validator;
    private JourneeManager $journeeManager;
    private RencontreManager $rencontreManager;
    private CalendrierIntegrityChecker $integrityChecker;

    public function __construct(PDO $conn)
    {
        $this->validator = new JourneeValidator();
        $this->journeeManager = new JourneeManager($conn);
        $this->rencontreManager = new RencontreManager($conn);
        $this->integrityChecker = new CalendrierIntegrityChecker($conn);
    }

    /**
     * Gère la requête POST pour insérer une journée et de ses rencontres.
     *
     * @return array{errors: array, success: ?string}
     */
    public function handlePost(array $postData): array
    {
        $errors = array_merge(
            $this->validator->validerJournee($postData),
            $this->validator->validerRencontres($postData)
        );

        if (!empty($errors)) {
            return ['errors' => $errors, 'success' => null];
        }

        try {
            // Préparer les rencontres pour vérifier les collisions AVANT insertion
            $rencontresProposees = $this->preparerRencontres($postData, 0);

            // 1) Vérification période (anti-doublon / anti-chevauchement)
            $this->verifierPériode($postData);

            // 2) Vérification collision si la journée existe déjà
            $this->verifierCollisionsSiJourneeExiste($postData, $rencontresProposees);

            // 3) Insertion/obtention de la journée puis insertion des rencontres
            $journeeId = $this->journeeManager->obtenirOuCreerJournee(
                (string) $postData['journée'],
                (string) $postData['dateDebut'],
                (string) $postData['dateFin'],
                (string) $postData['poule'],
                (string) $postData['type_journee']
            );

            $rencontres = $this->preparerRencontres($postData, $journeeId);
            $this->rencontreManager->insererRencontres($rencontres);

            return ['errors' => [], 'success' => ''];
        } catch (Throwable $e) {
            return ['errors' => [ErrorMessages::get('GENERIC_ERROR')], 'success' => null];
        }
    }

    private function verifierPériode(array $postData): void
    {
        $journeesChevauchantes = $this->integrityChecker->journeesSurChevauchementPériode(
            (string) $postData['poule'],
            (string) $postData['type_journee'],
            (string) $postData['dateDebut'],
            (string) $postData['dateFin']
        );

        $nomNouvelleJournee = (string) ($postData['journée'] ?? '');
        $dateDebutNouvelle = (string) ($postData['dateDebut'] ?? '');
        $dateFinNouvelle = (string) ($postData['dateFin'] ?? '');

        foreach ($journeesChevauchantes as $j) {
            $nomExistant = (string) ($j['journee_id'] ?? '');
            $dateDebutExistant = (string) ($j['dateDebut'] ?? '');
            $dateFinExistant = (string) ($j['dateFin'] ?? '');

            // Règle :
            // - si la journée (même nom) existe déjà : comparer STRICTEMENT dateDebut/dateFin
            //    -> si diff => bloquer
            //    -> si identique => autoriser
            // - sinon (autre nom mais chevauchement) => bloquer

            if ($nomExistant === $nomNouvelleJournee) {
                $datesIdentiques = ($dateDebutExistant === $dateDebutNouvelle)
                    && ($dateFinExistant === $dateFinNouvelle);

                if (!$datesIdentiques) {
                    $message = ErrorMessages::format('CHAMPIONNAT_JOURNEE_DATES_DIFFERENTES', [$nomExistant]);
                    throw new RuntimeException($this->normaliserMessageErreur($message));
                }

                continue;
            }

            $message = ErrorMessages::get('CHAMPIONNAT_JOURNEE_CONFLIT_PERIODE');
            throw new RuntimeException($this->normaliserMessageErreur($message));
        }
    }

    private function verifierCollisionsSiJourneeExiste(array $postData, array $rencontresProposees): void
    {
        $journeeExisteDbId = $this->integrityChecker->journeeExiste(
            (string) $postData['poule'],
            (string) $postData['journée'],
            (string) $postData['type_journee']
        );

        if ($journeeExisteDbId === null) {
            return;
        }

        $collisions = $this->integrityChecker->equipesEnCollisionPourJournee($journeeExisteDbId, $rencontresProposees);
        if (empty($collisions)) {
            return;
        }

        $nbCollisions = count($collisions);
        $cle = $nbCollisions === 1
            ? 'CHAMPIONNAT_EQUIPES_DEJA_ENGAGEE'
            : 'CHAMPIONNAT_EQUIPES_DEJA_ENGAGEES';

        throw new RuntimeException(ErrorMessages::get($cle));
    }

    private function preparerRencontres(array $postData, int $journeeId): array
    {
        $equipe1s = (array) ($postData['equipe1'] ?? []);
        $score1s = (array) ($postData['score1'] ?? []);
        $score2s = (array) ($postData['score2'] ?? []);
        $equipe2s = (array) ($postData['equipe2'] ?? []);

        $rencontres = [];
        $nb = count($equipe1s);

        for ($i = 0; $i < $nb; $i++) {
            $score1 = isset($score1s[$i]) && $score1s[$i] !== '' ? (int) $score1s[$i] : null;
            $score2 = isset($score2s[$i]) && $score2s[$i] !== '' ? (int) $score2s[$i] : null;

            $equipe1 = ($equipe1s[$i] ?? null) === 'Aucune équipe' ? null : ($equipe1s[$i] ?? null);
            $equipe2 = ($equipe2s[$i] ?? null) === 'Aucune équipe' ? null : ($equipe2s[$i] ?? null);

            $rencontres[] = [
                'journee_id' => $journeeId,
                'equipe1' => $equipe1,
                'score1' => $score1,
                'score2' => $score2,
                'equipe2' => $equipe2,
            ];
        }

        return $rencontres;
    }

    private function normaliserMessageErreur(string $message): string
    {
        return str_replace("\r\n", "\n", $message);
    }
}

