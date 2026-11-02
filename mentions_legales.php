<?php
declare(strict_types=1);

$pageTitle = "Mentions Légales & Politique de Confidentialité - COSMTT";
include './html_partials/header.php';
?>

<div class="title-bar">Mentions Légales & Politique de Confidentialité</div>

<main class="admin-grid" id="main_mentions_legales">

    <!-- Card 1 : Éditeur du site -->
    <div class="card_mentions_legales">
        <div class="card-header">
            <h3>Éditeur du Site</h3>
        </div>
        <p class="no-margin">
            Le présent site web est édité par l'association sportive <strong>COSM Tennis de Table</strong> (Club Omnisports de Marcilly-en-Villette - Section Tennis de Table).<br>
            <strong>Siège social :</strong> Marcilly-en-Villette, France.<br>
            <strong>Responsables de la publication :</strong> L'équipe dirigeante du COSM Tennis de Table.
        </p>
    </div>

    <!-- Card 2 : Hébergement du site -->
    <div class="card_mentions_legales">
        <div class="card-header">
            <h3>Hébergement du Site</h3>
        </div>
        <p class="no-margin">
            Le site est hébergé sur les infrastructures du serveur du club.<br>
            Pour toute question relative à l'hébergement ou au fonctionnement technique du site, vous pouvez contacter l'administration du club via la page de contact.
        </p>
    </div>

    <!-- Card 3 : Données personnelles -->
    <div class="card_mentions_legales">
        <div class="card-header">
            <h3>Protection des Données Personnelles (RGPD & CNIL)</h3>
        </div>
        <p>
            Conformément au Règlement Général sur la Protection des Données (RGPD - UE 2016/679) et à la loi « Informatique et Libertés » modifiée, le COSM Tennis de Table s'engage à garantir la protection et la confidentialité des données personnelles des visiteurs.
        </p>
        <ul>
            <li><strong>Pseudonymisation des adresses IP :</strong> Dans le cadre du suivi statistique de fréquentation du site, les adresses IP des visiteurs sont immédiatement et irréversiblement pseudonymisées à l'aide d'un hachage cryptographique sécurisé (HMAC-SHA256), conformément à la Délibération CNIL n° 2020-091. Aucune adresse IP complète n'est conservée en clair.</li>
            <li><strong>Absence de transmission à des tiers :</strong> Aucune donnée personnelle ou de navigation n'est vendue, louée ni transmise à des tiers ou des régies publicitaires.</li>
            <li><strong>Finalité du traitement :</strong> Les données collectées via le formulaire de contact (nom, prénom, email, téléphone, message) servent exclusivement à répondre aux demandes d'information transmises au club.</li>
        </ul>
    </div>

    <!-- Card 4 : Cookies & Traceurs -->
    <div class="card_mentions_legales">
        <div class="card-header">
            <h3>Gestion des Cookies & Traceurs</h3>
        </div>
        <p>
            Le site utilise uniquement des cookies et identifiants techniques strictement nécessaires à son bon fonctionnement et à sa sécurité (cookies de session sécurisés <code>HttpOnly</code> et <code>SameSite=Lax</code>).
        </p>
        <p class="no-margin">
            Conformément aux directives de la CNIL, la mesure d'audience anonyme et strictement interne étant exemptée de consentement préalable, aucune bannière intrusive ou cookie tiers publicitaire n'est déposé sur votre équipement.
        </p>
    </div>

    <!-- Card 5 : Vos droits -->
    <div class="card_mentions_legales">
        <div class="card-header">
            <h3>Vos Droits Informatique et Libertés</h3>
        </div>
        <p>
            Conformément aux articles 15 à 22 du RGPD, vous disposez des droits suivants concernant vos données personnelles :
        </p>
        <ul>
            <li>Droit d'accès et de communication de vos données.</li>
            <li>Droit de rectification et de mise à jour de vos données.</li>
            <li>Droit d'effacement (« droit à l'oubli »).</li>
            <li>Droit de limitation du traitement.</li>
        </ul>
        <p class="no-margin">
            Pour exercer l'un de ces droits, vous pouvez contacter le responsable de publication du club via la page de contact. En cas de doute non résolu, vous disposez également du droit de former une réclamation auprès de la CNIL (Commission Nationale de l'Informatique et des Libertés - <a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">www.cnil.fr</a>).
        </p>
    </div>

    <!-- Card 6 : Propriété intellectuelle -->
    <div class="card_mentions_legales">
        <div class="card-header">
            <h3>Propriété Intellectuelle</h3>
        </div>
        <p class="no-margin">
            L'ensemble des contenus (textes, logos, photographies, documents PDF) présents sur ce site est la propriété exclusive du COSM Tennis de Table, sauf mention contraire. Toute reproduction ou représentation totale ou partielle sans autorisation préalable est interdite.
        </p>
    </div>

</main>

<?php include './html_partials/footer.php'; ?>
