<?php

// Inclusion des fichiers nécessaires
require_once dirname(__DIR__, 3) . '/connect_ddb.php';
require_once dirname(__DIR__, 3) . '/parts/header.php';
require_once dirname(__DIR__, 4) . '/includes/security.php';

// Chargement du manager
define('CLASSPAGES_PATH', dirname(__DIR__, 3) . '/classPages/equipes/');
require_once CLASSPAGES_PATH . 'EquipesManager.php';

$equipeManager = new EquipeManager($conn);
$message = null;

// Gestion de l'ajout d'équipe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nomEquipe'], $_POST['poule'])) {
    $equipeManager->ajouterEquipe($_POST['nomEquipe'], $_POST['poule']);
}

// Gestion de la suppression d'équipe
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    if (!in_array($role, ['superAdmin', 'admin'])) {
        header('HTTP/1.1 403 Forbidden');
        die('Accès non autorisé à la suppression');
    }

    $equipe_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($equipe_id && $equipe_id > 0) {
        $equipeManager->supprimerEquipe($equipe_id);
    } else {
        header("Location: /showEquipe.php?message=InvalidRequest");
        exit;
    }
}

// Récupération du message d'erreur si présent
if (isset($_GET['message'])) {
    $messages = [
        'EmptyFields' => "Veuillez remplir tous les champs obligatoires.",
        'EquipeExists' => "L'équipe existe déjà dans la poule sélectionnée.",
        'DeleteSuccess' => "",
        'DeleteFail' => "Échec de la suppression.",
        'InvalidRequest' => "Requête invalide."
    ];

    $message = $messages[$_GET['message']] ?? "Une erreur inconnue s'est produite.";
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des équipes</title>
    <link rel="stylesheet" href="/cosmtt/admin/css/popup.css">
</head>

<body>
    
    <div class="title-bar">Liste des équipes</div>

    <main>
        <a href="javascript:void(0);" class="lienPopup" id="showPopup">Ajouter une équipe</a>

        <!-- Popup d'ajout d'équipe -->
        <div class="popup" id="teamPopup">
            <div class="popup-card">
                <div class="popup-content">
                    <h3>AJOUTER UNE ÉQUIPE</h3>
                    <form method="POST" action="" class="form-ajout">
                        <div class="form-group">
                            <input type="text" name="nomEquipe" class="input-popup"
                                placeholder="Nom de l'équipe" required>
                            <label for="poule">Division:</label>
                            <select name="poule" id="poule" required>
                                <option value="PouleA">Poule A</option>
                                <option value="PouleB">Poule B</option>
                            </select>
                        </div>
                        <input type="submit" value="Ajouter" class="input-submit" name="send">
                        <input type="button" id="cancelPopup" class="input-submit" value="Annuler">
                    </form>
                </div>
            </div>
        </div>

        <!-- Affichage des équipes -->
        <div class="table-container">
            <section class="show-tab">
                <?php $equipeManager->displayEquipes("PouleA"); ?>
            </section>
            <section class="show-tab">
                <?php $equipeManager->displayEquipes("PouleB"); ?>
            </section>
        </div>
    </main>

    <script src="/cosmtt/admin/js/app.js"></script>

    <?php if ($message): ?>
        <script>
            // Affichage du message d'erreur/succès en alert,
            alert("<?= addslashes($message); ?>");
        </script>
    <?php endif; ?>
    <?php
        // Inclusion du footer
        include_once dirname(__DIR__, 4) . '/pages/parts/footer.php';
    ?>
</body>

</html>