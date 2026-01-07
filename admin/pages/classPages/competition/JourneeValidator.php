<?php 
/**
 * Classe JourneeValidator : Responsable de la validation des données d'une journée et de ses rencontres.
 * Elle vérifie que les informations fournies sont cohérentes et respectent les règles métier.
 */
class JourneeValidator
{
    /**
     * Méthode validerJournee : Valide les données générales de la journée (numéro, dates).
     * @param array $postData : Données POST soumises par l'utilisateur.
     * @return array : Liste des erreurs trouvées, vide si valide.
     */
    public function validerJournee(array $postData): array
    {
        $errors = []; // Tableau pour stocker les erreurs de validation

        // Nettoyer et vérifier le numéro de la journée
        $journee = filter_var($postData['journée'] ?? '');
        if (empty($journee)) {
            $errors[] = "Le numéro de la journée est obligatoire.";
        }

        // Nettoyer et vérifier les dates de début et fin
        $dateDebut = filter_var($postData['dateDebut'] ?? '');
        $dateFin = filter_var($postData['dateFin'] ?? '');
        if (empty($dateDebut) || empty($dateFin)) {
            $errors[] = "Les dates de début et de fin sont obligatoires.";
        } else {
            // Créer des objets DateTime pour vérifier le format et l'ordre
            $dateDebutObj = DateTime::createFromFormat('Y-m-d', $dateDebut);
            $dateFinObj = DateTime::createFromFormat('Y-m-d', $dateFin);
            if (!$dateDebutObj || !$dateFinObj) {
                $errors[] = "Format de date invalide.";
            } elseif ($dateDebutObj > $dateFinObj) {
                $errors[] = "La date de début doit être antérieure à la date de fin.";
            }
        }

        // NOUVELLE VALIDATION
        $typeJournee = filter_var($postData['type_journee'] ?? '');
        if (!in_array($typeJournee, ['aller', 'retour'])) {
            $errors[] = "Le type de journée (Aller/Retour) est obligatoire et invalide.";
        }

        return $errors; // Retourner les erreurs trouvées
    }

    /**
     * Méthode validerRencontres : Valide les données des rencontres multiples (équipes, scores).
     * @param array $postData : Données POST contenant les rencontres.
     * @return array : Liste des erreurs, vide si valide.
     */
    public function validerRencontres(array $postData): array
    {
        $errors = []; // Tableau pour les erreurs

        // Récupérer les tableaux des équipes et scores depuis POST
        $equipe1s = (array) ($postData['equipe1'] ?? []);
        $score1s = (array) ($postData['score1'] ?? []);
        $score2s = (array) ($postData['score2'] ?? []);
        $equipe2s = (array) ($postData['equipe2'] ?? []);

        // Vérifier que tous les tableaux ont la même longueur
        $counts = [count($equipe1s), count($score1s), count($score2s), count($equipe2s)];
        if (count(array_unique($counts)) !== 1) {
            $errors[] = "Erreur dans les données des matchs : nombres de champs incohérents.";
            return $errors;
        }

        $numMatches = $counts[0]; // Nombre de matchs
        if ($numMatches === 0) {
            $errors[] = "Au moins un match doit être ajouté.";
            return $errors;
        }

        // Boucle pour valider chaque match
        for ($i = 0; $i < $numMatches; $i++) {
            // Nettoyer les noms d'équipes
            $equipe1 = filter_var($equipe1s[$i] ?? '');
            $equipe2 = filter_var($equipe2s[$i] ?? '');

            // Vérifier que les équipes sont obligatoires sauf "Aucune équipe"
            if (empty($equipe1) && $equipe1 !== "Aucune équipe") {
                $errors[] = "L'équipe domicile du match " . ($i + 1) . " est obligatoire.";
            }
            if (empty($equipe2) && $equipe2 !== "Aucune équipe") {
                $errors[] = "L'équipe extérieur du match " . ($i + 1) . " est obligatoire.";
            }

            // Vérifier que les équipes sont différentes si elles ne sont pas "Aucune équipe"
            if ($equipe1 === $equipe2 && $equipe1 !== "Aucune équipe") {
                $errors[] = "Les équipes du match " . ($i + 1) . " doivent être différentes.";
            }

            // Valider les scores seulement s'ils sont fournis (optionnels)
            $score1Str = $score1s[$i] ?? '';
            $score2Str = $score2s[$i] ?? '';
            if (!empty($score1Str)) {
                $score1 = filter_var($score1Str, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 14]]);
                if ($score1 === false) {
                    $errors[] = "Score 1 du match " . ($i + 1) . " invalide. Veuillez saisir un entier entre 0 et 14.";
                }
            } else {
                $score1 = null; // Score non fourni
            }
            if (!empty($score2Str)) {
                $score2 = filter_var($score2Str, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 14]]);
                if ($score2 === false) {
                    $errors[] = "Score 2 du match " . ($i + 1) . " invalide. Veuillez saisir un entier entre 0 et 14.";
                }
            } else {
                $score2 = null; // Score non fourni
            }
        }

        return $errors; // Retourner les erreurs
    }
}
// var_dump('JourneeValidator.php - Ok');
?>