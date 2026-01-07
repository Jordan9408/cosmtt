<?php include('./html_partials/header.php') ?>
<main id="main_info_club">
    <div id="infoclub">
        <div id="hours">
            <img src="img/calendar.svg" alt="logo_calendrier" class="logos">
            <h4 class="titres">HORAIRE</h4>
            <p><?php echo ("L'entrainement se déroule le <br><strong>vendredi entre 20h30 et 23h00</strong><br> et le <strong>dimanche de 8h30 à 10h30</strong>.") ?></p>
        </div>
        <div id="price">
            <img src="img/euro.svg" alt="logo_euro" class="logos">
            <h4 class="titres">Licences</h4>
            <div id="tarifs">
                <table>
                    <tr>
                        <td rowspan="2" id="ufolep">UFOLEP</td>
                        <td>Adultes</td>
                        <td><?php echo ("55 €") ?></td>
                    </tr>
                    <tr>
                        <td>Enfants - Adolescents</td>
                        <td><?php echo ("40 €") ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div id="infocontact">
        <div id="info_contact">
            <section id="maps">
                <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d21453.748526389652!2d2.015965!3d47.767513!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x1f651e6a4bea1104!2sGymnase%20Jean-Marie%20Flattet!5e0!3m2!1sfr!2sfr!4v1672845801146!5m2!1sfr!2sfr" width="500" height="260" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </section>
            <section id="info_club">
                <h3>COS MARCILLY-EN-VILLETTE <br> Tennis de Table</h3>
                <div id="adresse_postale">
                    <p>380 Rue de Chilly,<br> 45240 Marcilly-en-Villette</p>
                </div>
                <div id="adresse_email">
                    <a href="#">cosmarcillytennisdetable@laposte.net</a>
                </div>
            </section>
        </div>
    </div>
</main>
<?php include('./html_partials/footer.php') ?>
