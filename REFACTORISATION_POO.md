# Refactorisation en POO - Page ShowChampionnat

## Vue d'ensemble

La page `showChampionnat.php` a été refactorisée en utilisant la **Programmation Orientée Objet (POO)** pour améliorer la maintenabilité, la testabilité et la séparation des responsabilités.

## Architecture

### 1. **ChampionnatViewManager.php**

Classe gestionnaire principal qui encapsule toute la logique métier de la page.

**Responsabilités :**

- Extraction et validation de la poule
- Gestion des requêtes POST et GET
- Traitement des opérations (ajout, modification, suppression de journées)
- Chargement et organisation des données
- Gestion des messages d'erreur et de succès

**Méthodes publiques :**

- `process()` : Processus principal d'exécution
- Getters pour tous les éléments de données

### 2. **ChampionnatViewRenderer.php**

Classe de rendu responsable de toute la génération HTML.

**Responsabilités :**

- Génération du HTML pour chaque section
- Rendu conditionnel basé sur le mode édition
- Échappement et sécurité HTML

**Méthodes publiques :**

- `renderMessages()` : Affiche erreurs et succès
- `renderPouleSelector()` : Sélecteur de poule
- `renderClassement()` : Tableau du classement
- `renderActionButtons()` : Boutons d'action
- `renderAddJourneePopup()` : Formulaire popup
- `renderEditFormStart()` / `renderEditFormEnd()` : Gestion du formulaire
- `renderCalendar()` : Calendrier des journées
- `renderScripts()` : Scripts JavaScript

### 3. **showChampionnat.php** (Refactorisé)

La page devient très simple et lisible :

```php
// Initialisation
$viewManager = new ChampionnatViewManager($conn, $role);
$viewManager->process();

// Rendu
$renderer = new ChampionnatViewRenderer($viewManager, $role);
```

Puis :

```html
<?php $renderer->renderMessages(); ?> <?php $renderer->renderPouleSelector(); ?>
<?php $renderer->renderClassement(); ?>
<!-- etc... -->
```

## Avantages de cette refactorisation

### ✅ Séparation des responsabilités

- **Métier** : `ChampionnatViewManager`
- **Présentation** : `ChampionnatViewRenderer`
- **Contrôleur** : `showChampionnat.php`

### ✅ Maintenabilité améliorée

- Code organisé en classes logiques
- Méthodes bien nommées et documentées
- Réduction de la redondance

### ✅ Réutilisabilité

- Les classes peuvent être utilisées dans d'autres pages
- Logique métier indépendante de la présentation

### ✅ Testabilité

- Chaque classe peut être testée indépendamment
- Injection de dépendances facilitée

### ✅ Lisibilité

- La page HTML est beaucoup plus claire
- Suppression du mélange logic/présentation

## Fonctionnalités conservées

✅ Gestion des poules (A et B)
✅ Affichage du classement
✅ Mode édition / Mode affichage
✅ Ajout de journées et matchs
✅ Modification des scores
✅ Suppression de journées
✅ Conservation des données en cas d'erreur
✅ Messages d'erreur et succès

## Points clés de l'implémentation

### Gestion des erreurs

Les erreurs de validation restent en mode édition avec les données saisies :

```php
if (empty($this->errors)) {
    $this->redirect(...);
} else {
    $this->isEditMode = true;
    $this->keepPostedScores();
}
```

### Sécurité

- Échappement HTML via `escapeHtml()`
- Validation des permissions (`$this->role`)
- Gestion sécurisée des redirections

### Séparation aller/retour

```php
private function separateJournees()
{
    foreach ($this->journees as $journee) {
        if (isset($journee['type_journee']) && $journee['type_journee'] === 'retour') {
            $this->journeesRetour[] = $journee;
        } else {
            $this->journeesAller[] = $journee;
        }
    }
}
```

## Structure des fichiers

```
admin/pages/
├── classPages/competition/
│   ├── ChampionnatViewManager.php      (Nouvelle classe)
│   ├── ChampionnatViewRenderer.php     (Nouvelle classe)
│   ├── JourneeValidator.php            (Existant)
│   ├── JourneeManager.php              (Existant)
│   ├── JourneeController.php           (Existant)
│   ├── EquipeManager.php               (Existant)
│   ├── RencontreManager.php            (Existant)
│   └── ChampionnatManager.php          (Existant)
└── adminPage/championnat/rencontres/
    └── showChampionnat.php             (Refactorisé)
```

## Migration / Évolution future

Cette refactorisation est prête pour :

- ✅ Convertir en MVC complet
- ✅ Ajouter des tests unitaires
- ✅ Intégrer un moteur de templates (Twig, Blade)
- ✅ API REST pour le rendu en JSON
- ✅ Caching des données
