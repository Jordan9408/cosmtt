# TODO - Optimisation de showChampionnat1.php

## Étapes à suivre pour optimiser le code

- [x] Supprimer les définitions de classes dupliquées dans showChampionnat1.php (JourneeValidator, JourneeManager, JourneeController, EquipeManager, RencontreManager, ChampionnatManager) et ajouter require_once pour inclure les fichiers séparés.
- [x] Créer admin/classes/ChampionnatView.php pour séparer la présentation HTML de la logique métier.
- [x] Créer admin/classes/utils.php avec une fonction escapeHtml() globale pour éviter XSS.
- [x] Optimiser le calcul de classement en ajoutant un cache (via session) pour éviter recalculs inutiles.
- [x] Créer admin/tests/ChampionnatTest.php pour les tests unitaires (validation, calcul classement).
- [x] Ajouter des commentaires explicatifs seulement où nécessaire.
- [x] Renforcer la sécurité : vérifier les rôles plus strictement, utiliser prepared statements partout (déjà fait), ajouter CSRF protection si possible.
- [x] Améliorer la lisibilité : renommer variables si nécessaire, utiliser des constantes pour les rôles.
- [x] Corriger FILTER_SANITIZE_STRING deprecated en FILTER_SANITIZE_FULL_SPECIAL_CHARS.
- [x] Tester les changements localement avec WAMP.
- [x] Exécuter les tests unitaires (installer PHPUnit si nécessaire).
- [x] Revue de code pour sécurité.
- [x] Audit DB pour performance si besoin.
