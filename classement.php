<?php include('./html_partials/header.php') ?>

<main id="clas_joueurs">
    <h2 class="classement_joueurs">Classement des joueurs du club</h2>
    <table id="tabjoueurs">
        <tbody>
            <tr>
                <th>NOM PRÉNOM</th>
                <th>CLASSEMENT</th>
                <th>POINTS <br> Début de saison</th>
                <th>POINTS MENSUELS</th>
                <th>EVOLUTION MENSUELLE</th>
            </tr>
            <tr class="tabcouleur1">
                <td class="td_nom">DAUPHIN Hugo</td>
                <td>5</td>
                <td><?php $ptsDebSaison = 519;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 532;
                    echo $ptsActuel; ?></td>
                <td class="pointsgagne"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur2">
                <td class="td_nom">DELARD Hugo</td>
                <td>5</td>
                <td><?php $ptsDebSaison = 500;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 489;
                    echo $ptsActuel; ?></td>
                <td class="pointsperdu"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur1">
                <td class="td_nom">DESREUMAUX Cédric</td>
                <td>5</td>
                <td><?php $ptsDebSaison = 500;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 473;
                    echo $ptsActuel; ?></td>
                <td class="pointsperdu"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur2">
                <td class="td_nom">IMBAULT Jean-Marie</td>
                <td>5</td>
                <td><?php $ptsDebSaison = 500;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 525;
                    echo $ptsActuel; ?></td>
                <td class="pointsgagne"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur1">
                <td class="td_nom">LISSAJOUX Pierre</td>
                <td>8</td>
                <td><?php $ptsDebSaison = 845;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 824;
                    echo $ptsActuel; ?></td>
                <td class="pointsperdu"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur2">
                <td class="td_nom">MAILLET Olivier</td>
                <td>7</td>
                <td><?php $ptsDebSaison = 747;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 756;
                    echo $ptsActuel; ?></td>
                <td class="pointsgagne"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur1">
                <td class="td_nom">MARCHET Véronique</td>
                <td>5</td>
                <td><?php $ptsDebSaison = 500;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 471;
                    echo $ptsActuel; ?></td>
                <td class="pointsperdu"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur2">
                <td class="td_nom">MULON Nicolas</td>
                <td>5</td>
                <td><?php $ptsDebSaison = 500;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 507;
                    echo $ptsActuel; ?></td>
                <td class="pointsgagne"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur1">
                <td class="td_nom">MULON Stéphane</td>
                <td>5</td>
                <td><?php $ptsDebSaison = 500;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 507;
                    echo $ptsActuel; ?></td>
                <td class="pointsgagne"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur2">
                <td class="td_nom">REBOUTIER Florian</td>
                <td>5</td>
                <td><?php $ptsDebSaison = 500;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 505;
                    echo $ptsActuel; ?></td>
                <td class="pointsgagne"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur1">
                <td class="td_nom">RICHARD Jordan</td>
                <td>5</td>
                <td><?php $ptsDebSaison = 566;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 575;
                    echo $ptsActuel; ?></td>
                <td class="pointsgagne"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
            <tr class="tabcouleur2">
                <td class="td_nom">SAMAIN Jean-Luc</td>
                <td>7</td>
                <td><?php $ptsDebSaison = 811;
                    echo $ptsDebSaison; ?></td>
                <td><?php $ptsActuel = 796;
                    echo $ptsActuel; ?></td>
                <td class="pointsperdu"><?php $ecartPts = $ptsActuel - $ptsDebSaison;
                                        echo $ecartPts; ?></td>
            </tr>
        </tbody>
    </table>
    <h2 class="classement_joueurs">Barème de points</h2>
    <table id="bareme">
        <tbody id="tabpoints">
            <tr>
                <th colspan="5">COMPTAGE DES POINTS</th>
            </tr>
            <tr>
                <th>écart de points</th>
                <th>VICTOIRES NORMALES</th>
                <th>DÉFAITES NORMALES</th>
                <th>VICTOIRES ANORMALES</th>
                <th>DÉFAITES ANORMALES</th>
            </tr>
            <tr class="tabcouleur3">
                <td>0-24</td>
                <td>+6</td>
                <td>-5</td>
                <td>+6</td>
                <td>-5</td>
            </tr>
            <tr class="tabcouleur4">
                <td>25-49</td>
                <td>+5.5</td>
                <td>-4.5</td>
                <td>+7</td>
                <td>-6</td>
            </tr>
            <tr class="tabcouleur3">
                <td>50-99</td>
                <td>+5</td>
                <td>-4</td>
                <td>+8</td>
                <td>-7</td>
            </tr>
            <tr class="tabcouleur4">
                <td>100-149</td>
                <td>+4</td>
                <td>-3</td>
                <td>+10</td>
                <td>-8</td>
            </tr>
            <tr class="tabcouleur3">
                <td>150-199</td>
                <td>+3</td>
                <td>-2</td>
                <td>+13</td>
                <td>-10</td>
            </tr>
            <tr class="tabcouleur4">
                <td>200-299</td>
                <td>+2</td>
                <td>-1</td>
                <td>+17</td>
                <td>-12.5</td>
            </tr>
            <tr class="tabcouleur3">
                <td>300-399</td>
                <td>+1</td>
                <td>-0.5</td>
                <td>+22</td>
                <td>-16</td>
            </tr>
            <tr class="tabcouleur4">
                <td>400-499</td>
                <td>+0.5</td>
                <td>0</td>
                <td>+28</td>
                <td>-20</td>
            </tr>
            <tr class="tabcouleur3">
                <td>500+</td>
                <td>0</td>
                <td>0</td>
                <td>+40</td>
                <td>-29</td>
            </tr>
        </tbody>
    </table>
</main>
<script src="../../../../js/app.js"></script>
<?php include('./html_partials/footer.php') ?>