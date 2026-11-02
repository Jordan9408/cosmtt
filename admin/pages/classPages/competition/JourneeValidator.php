<?php
/**
 * Classe JourneeValidator : Validation des données d'une journée et de ses rencontres.
 *
 * Modernisation : POO propre + PHP 8+ (strict types, typage, nettoyage)
 */

declare(strict_types=1);

final class JourneeValidator
{
    /**
     * Valide les informations d'une journée (numéro, dates, type) et normalise certains champs.
     *
     * @param array<string, mixed> $postData
     * @return array<int, string> Liste des erreurs
     */
    public function validerJournee(array &$postData): array
    {
        $errors = [];

        // Normalisation : si seul le numéro est saisi, on ajoute le préfixe "Journée".
        if (isset($postData['journée'])) {
            $val = trim((string) $postData['journée']);

            if ($val !== '' && ctype_digit($val)) {
                $val = 'Journée ' . $val;
            }

            if ($val !== '') {
                // Met en forme : première lettre en majuscule, le reste en minuscule.
                $first = mb_substr($val, 0, 1, 'UTF-8');
                $rest = mb_substr($val, 1, null, 'UTF-8');
                $postData['journée'] = mb_strtoupper($first, 'UTF-8') . mb_strtolower($rest, 'UTF-8');
            }
        }

        $journee = filter_var((string) ($postData['journée'] ?? ''), FILTER_UNSAFE_RAW);
        if (trim((string) $journee) === '') {
            $errors[] = 'Le numéro de la journée est obligatoire.';
        }

        $dateDebut = trim((string) ($postData['dateDebut'] ?? ''));
        $dateFin = trim((string) ($postData['dateFin'] ?? ''));
        if ($dateDebut === '' || $dateFin === '') {
            $errors[] = 'Les dates de début et de fin sont obligatoires.';
        } else {
            $dateDebutObj = DateTime::createFromFormat('Y-m-d', $dateDebut);
            $dateFinObj = DateTime::createFromFormat('Y-m-d', $dateFin);

            if (!$dateDebutObj || !$dateFinObj) {
                $errors[] = 'Format de date invalide.';
            } elseif ($dateDebutObj > $dateFinObj) {
                $errors[] = 'La date de début doit être antérieure à la date de fin.';
            }
        }

        $typeJournee = (string) ($postData['type_journee'] ?? '');
        if (!in_array($typeJournee, ['aller', 'retour'], true)) {
            $errors[] = 'Le type de journée (Aller/Retour) est obligatoire et invalide.';
        }

        return $errors;
    }

    /**
     * Valide les données des rencontres multiples (équipes, scores).
     *
     * @param array<string, mixed> $postData
     * @return array<int, string> Liste des erreurs
     */
    public function validerRencontres(array $postData): array
    {
        $errors = [];

        $equipe1s = (array) ($postData['equipe1'] ?? []);
        $score1s = (array) ($postData['score1'] ?? []);
        $score2s = (array) ($postData['score2'] ?? []);
        $equipe2s = (array) ($postData['equipe2'] ?? []);

        $counts = [
            count($equipe1s),
            count($score1s),
            count($score2s),
            count($equipe2s),
        ];

        if (count(array_unique($counts, SORT_NUMERIC)) !== 1) {
            return ['Erreur dans les données des matchs : nombres de champs incohérents.'];
        }

        $numMatches = $counts[0];
        if ($numMatches === 0) {
            return ['Au moins un match doit être ajouté.'];
        }

        for ($i = 0; $i < $numMatches; $i++) {
            $equipe1 = filter_var((string) ($equipe1s[$i] ?? ''), FILTER_UNSAFE_RAW);
            $equipe2 = filter_var((string) ($equipe2s[$i] ?? ''), FILTER_UNSAFE_RAW);

            // Obligatoire sauf "Aucune équipe"
            if (trim((string) $equipe1) === '' && $equipe1 !== 'Aucune équipe') {
                $errors[] = 'L\'équipe domicile du match ' . ($i + 1) . ' est obligatoire.';
            }
            if (trim((string) $equipe2) === '' && $equipe2 !== 'Aucune équipe') {
                $errors[] = 'L\'équipe extérieur du match ' . ($i + 1) . ' est obligatoire.';
            }

            // Deux équipes différentes si ce n'est pas une équipe "vide"
            if ($equipe1 === $equipe2 && $equipe1 !== 'Aucune équipe' && $equipe2 !== 'Aucune équipe') {
                $errors[] = 'Les équipes du match ' . ($i + 1) . ' doivent être différentes.';
            }

            // Scores optionnels : si fournis, doivent être des entiers dans [0..14]
            $this->validerScore($score1s[$i] ?? null, $i + 1, 'Score 1', $errors);
            $this->validerScore($score2s[$i] ?? null, $i + 1, 'Score 2', $errors);
        }

        return $errors;
    }

    /**
     * Valide un score optionnel.
     *
     * @param mixed $value
     * @param int $matchIndex
     * @param string $label
     * @param array<int, string> $errors (par référence)
     */
    private function validerScore(mixed $value, int $matchIndex, string $label, array &$errors): void
    {
        $raw = $value === null ? '' : (string) $value;
        $raw = trim($raw);

        if ($raw === '') {
            return; // score non fourni => OK
        }

        $score = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 14]]);
        if ($score === false) {
            $errors[] = $label . ' du match ' . $matchIndex . ' invalide. Veuillez saisir un entier entre 0 et 14.';
        }
    }
}

