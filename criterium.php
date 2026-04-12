<?php 
include('./html_partials/header.php');

/**
 * Convertit une date française au format YYYY-MM-DD
 * Exemple: "8 janvier 2023" -> "2023-01-08"
 */
function convertFrenchDateToISO(string $frenchDate): ?string {
    $frenchDate = trim($frenchDate);
    
    // Tableau de correspondance mois français -> numéro
    $monthsMap = [
        'janvier' => '01', 'février' => '02', 'mars' => '03', 'avril' => '04',
        'mai' => '05', 'juin' => '06', 'juillet' => '07', 'août' => '08',
        'septembre' => '09', 'octobre' => '10', 'novembre' => '11', 'décembre' => '12'
    ];
    
    // Pattern pour extraire jour, mois (texte) et année
    // Accepte: "8 janvier 2023", "08 janvier 2023", "1er janvier 2023", etc.
    if (preg_match('/(\d{1,2})(?:er)?\s+([a-z]+)\s+(\d{4})/i', $frenchDate, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $monthText = strtolower($matches[2]);
        $year = $matches[3];
        
        $month = $monthsMap[$monthText] ?? null;
        
        if ($month) {
            return "$year-$month-$day";
        }
    }
    
    return null;
}

/**
 * Extrait le titre et la date d'un h2 formaté comme "Titre – Date"
 */
function extractTitleAndDate(string $h2Text): array {
    $parts = explode(' – ', $h2Text);
    
    $title = trim($parts[0] ?? '');
    $dateStr = trim($parts[1] ?? '');
    
    $isoDate = convertFrenchDateToISO($dateStr);
    
    return [
        'title' => $title,
        'dateDisplay' => $dateStr,
        'isoDate' => $isoDate
    ];
}
?>
<main id="criterium">
    <h1 id="crit">CRITÉRIUMS</h1>
    <?php 
    // Tableau des critériums
    $criteriums = [
        [
            'heading' => 'Critérium Ufolep Régional A – 8 janvier 2023',
            'id' => 'reg_A',
            'image' => './img/photos_regional_a/IMG_1907.JPG',
            'imageAlt' => 'criterium_08/01/2023',
            'paragraphs' => [
                'Pour la première compétition de l\'année 2023 avec le critérium Ufolep Régional A qui ont eu lieu ce dimanche 8 janvier 2023.',
                'Cette année 65 participants se sont donnés rendez vous au gymnase de Ménestreau pour tenter de se qualifier au National Ufolep A qui auront lieu à Châteauneuf en Thymerais (28) les 1er et 2 juillet 2023.',
                'Le club de Marcilly a répondu présent avec 6 joueurs pour 1 podiums.'
            ],
            'resultsFile' => './resultats/Resultats_criterium_Reg_A_Menestreau.pdf'
        ],
        [
            'heading' => 'Critérium Ufolep Départemental A – 4 décembre 2022',
            'id' => 'dep_A',
            'image' => './img/criterium_04_12.jpg',
            'imageAlt' => 'criterium_04/12/2022',
            'paragraphs' => [
                'Le critérium Ufolep Départemental A qui ont eu lieu ce dimanche 4 décembre 2022.',
                'Cette année 56 participants se sont donnés rendez vous au gymnase de Ménestreau pour se qualifier au Régional Ufolep A qui auront lieu à Ménestreau le 8 janvier 2023.',
                'Le club de Marcilly a répondu présent avec 8 joueurs pour 4 podiums.'
            ],
            'resultsFile' => './resultats/Resultats_criterium_Dep_A_Menestreau.pdf'
        ],
        [
            'heading' => 'Critérium Ufolep Départemental B – 20 novembre 2022',
            'id' => 'dep_B',
            'image' => './img/criterium_20_11.jpg',
            'imageAlt' => 'criterium_20/11/2022',
            'paragraphs' => [
                'Pour la première compétition de la saison avec les critériums Ufolep Départemental B qui ont eu lieu ce dimanche 20 novembre 2022.',
                'Cette année 46 participants se sont donnés rendez vous au gymnase de Marcilly-en-Villette pour se qualifier au Régional Ufolep B qui auront lieu à Châteauneuf en Thymerais (28) le 12 Mars 2023.',
                'Le club de Marcilly a répondu présent avec 11 joueurs pour 6 podiums.'
            ],
            'resultsFile' => './resultats/Resultats_criterium_Dep_B_Marcilly.pdf'
        ]
    ];
    
    foreach ($criteriums as $index => $crit) {
        $titleData = extractTitleAndDate($crit['heading']);
        $photoLink = '#';
        
        // Générer le lien vers la galerie si la date est valide
        if ($titleData['isoDate']) {
            $photoLink = './galeries_photos.php?title=' . urlencode($titleData['title']) . '&date=' . urlencode($titleData['isoDate']);
        }
    ?>
    <section>
        <h2 id="<?= htmlspecialchars($crit['id']) ?>"><?= htmlspecialchars($crit['heading']) ?></h2>
        <img src="<?= htmlspecialchars($crit['image']) ?>" alt="<?= htmlspecialchars($crit['imageAlt']) ?>">
        <?php foreach ($crit['paragraphs'] as $para): ?>
        <p><?= htmlspecialchars($para) ?></p>
        <?php endforeach; ?>
        <a href="<?= htmlspecialchars($crit['resultsFile']) ?>" alt="Résultats critérium" class="liens_crit" target="_blank">Résultats complets</a>
        <!-- <br> -->
        <a href="<?= htmlspecialchars($photoLink) ?>" class="liens_crit" alt="Photos" target="_blank">📸 Photos</a>
    </section>
    <?php if ($index < count($criteriums) - 1): ?>
    <hr class="hr_criterium">
    <?php endif; ?>
    <?php } ?>
</main>
<?php include('./html_partials/footer.php') ?>
