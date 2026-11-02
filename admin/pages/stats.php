<?php
// Définition des rôles autorisés (accès superAdmin strict - [H-01])
$requiredRoles = ['superAdmin'];
require_once dirname(__DIR__, 1) . '/includes/security.php';

// Défense en profondeur : vérification stricte du rôle superAdmin
if ($role !== 'superAdmin') {
    http_response_code(403);
    header('Location: /cosmtt/admin/pages/home.php?unauthorized=1');
    exit;
}

require_once __DIR__ . '/tracker.php';


/**
 * Gestionnaire des analyses et statistiques de trafic.
 */
class TrafficAnalytics {
    // Utilisation des propriétés promues de PHP 8
    public function __construct(private PDO $db) {}

    /**
     * Calcule les moyennes de visites (journalières et hebdomadaires) pour le mois en cours.
     */
    public function getMonthlyAverages(): array {
        // Nombre de jours écoulés dans le mois actuel où il y a eu des visites
        $resDays = $this->db->query("SELECT COUNT(DISTINCT date_visite) FROM statistiques_visites WHERE MONTH(date_visite) = MONTH(CURDATE())")->fetchColumn();
        $daysCount = $resDays ?: 1;

        // Total visites du mois actuel
        $monthTotal = $this->db->query("SELECT COUNT(*) FROM statistiques_visites WHERE MONTH(date_visite) = MONTH(CURDATE())")->fetchColumn();

        $avgDay = round($monthTotal / $daysCount, 1);
        $avgWeek = round($avgDay * 7, 1);

        return [
            'avg_day' => $avgDay,
            'avg_week' => $avgWeek
        ];
    }

    /**
     * Calcule la moyenne globale par mois et le nombre de mois d'historique.
     */
    public function getGlobalMonthlyAverage(): array {
        $globalTotal = $this->db->query("SELECT COUNT(*) FROM statistiques_visites")->fetchColumn();
        $monthCount = $this->db->query("SELECT COUNT(DISTINCT MONTH(date_visite), YEAR(date_visite)) FROM statistiques_visites")->fetchColumn() ?: 1;
        $avgMonthGlobal = round($globalTotal / $monthCount, 1);

        return [
            'avg_month_global' => $avgMonthGlobal,
            'month_count' => $monthCount
        ];
    }

    /**
     * Récupère la répartition de l'intensité horaire sur 24 heures.
     */
    public function getHourlyData(): array {
        $hourlyData = array_fill(0, 24, 0);
        $hoursQuery = $this->db->query("SELECT HOUR(heure_visite) as h, COUNT(*) as nb FROM statistiques_visites GROUP BY h");
        
        foreach ($hoursQuery as $row) {
            $hourlyData[$row['h']] = (int)$row['nb'];
        }
        
        return $hourlyData;
    }

    /**
     * Récupère les données de répartition par type d'appareil.
     */
    public function getDeviceData(): array {
        return $this->db->query("SELECT appareil, COUNT(*) as nb FROM statistiques_visites GROUP BY appareil")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les données de répartition par navigateur.
     */
    public function getBrowserData(): array {
        return $this->db->query("SELECT navigateur, COUNT(*) as nb FROM statistiques_visites GROUP BY navigateur")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'évolution mensuelle globale sous forme de labels (YYYY-MM) et de valeurs.
     */
    /**
     * Récupère les données d'évolution mensuelle calées sur la saison (Septembre à Août).
     */
    public function getMonthlyEvolution(): array {
        // Détermination automatique de l'année de début de la saison actuelle
        $currentMonth = (int)date('n'); // Mois actuel (1 à 12)
        $currentYear = (int)date('Y');  // Année actuelle

        // Si on est entre janvier et août, la saison a commencé en septembre de l'année précédente
        $startYear = ($currentMonth >= 9) ? $currentYear : ($currentYear - 1);
        $endYear = $startYear + 1;

        $startDate = "{$startYear}-09-01";
        $endDate = "{$endYear}-08-31";

        // Requête SQL filtrée sur la période de la saison
        $sql = "SELECT YEAR(date_visite) AS year, MONTH(date_visite) AS month, COUNT(*) AS nb 
                FROM statistiques_visites 
                WHERE date_visite BETWEEN :start_date AND :end_date
                GROUP BY year, month 
                ORDER BY year ASC, month ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);
        
        $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Initialisation des 12 mois de la saison pour s'assurer qu'ils apparaissent même s'ils sont à 0
        $monthsStructure = [];
        for ($m = 9; $m <= 12; $m++) {
            $monthsStructure["{$startYear}-" . str_pad($m, 2, '0', STR_PAD_LEFT)] = 0;
        }
        for ($m = 1; $m <= 8; $m++) {
            $monthsStructure["{$endYear}-" . str_pad($m, 2, '0', STR_PAD_LEFT)] = 0;
        }

        // Remplissage avec les données réelles de la base
        foreach ($monthlyData as $row) {
            $key = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
            if (isset($monthsStructure[$key])) {
                $monthsStructure[$key] = (int)$row['nb'];
            }
        }

        // Noms des mois en français pour un affichage plus propre sur le graphique
        $frenchMonths = [
            1 => 'Janv', 2 => 'Févr', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juil', 8 => 'Août', 9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'
        ];

        $labels = [];
        $values = [];

        foreach ($monthsStructure as $dateKey => $totalVisits) {
            [$year, $month] = explode('-', $dateKey);
            // Exemple de label : "Sept 2025"
            $labels[] = $frenchMonths[(int)$month] . ' ' . $year;
            $values[] = $totalVisits;
        }

        return ['labels' => $labels, 'values' => $values];
    }
}

// Initialisation de la classe avec l'instance PDO ($conn issue de tracker.php / connect_ddb.php)
$analytics = new TrafficAnalytics($conn);

// Extraction des données pour la vue
$monthlyAverages   = $analytics->getMonthlyAverages();
$globalAverages    = $analytics->getGlobalMonthlyAverage();
$hourlyData        = $analytics->getHourlyData();
$devices           = $analytics->getDeviceData();
$browsers          = $analytics->getBrowserData();
$monthlyEvolution  = $analytics->getMonthlyEvolution();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Analyse Trafic Simple</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    body {
        font-family: sans-serif;
        background: #fff;
        color: #333;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin: 17px 145px;
    }

    .card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .big-num {
        font-size: 2.5rem;
        font-weight: bold;
        color: #007bff;
        display: block;
    }

    h3 {
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
    }

    #monthlyChart {
        margin: 5em 0;
    }

    @media screen and (max-width: 768px) {
        .grid {
            margin: 5px auto;
        }
    }
    </style>
</head>

<body>

    <div class="title-bar">Rapport de Trafic</div>

    <div class="grid">
        <div class="card">
            <h3>Moyennes du Mois</h3>
            <p><span class="big-num"><?php echo $monthlyAverages['avg_day']; ?></span> Visites / jour</p>
            <p><span class="big-num"><?php echo $monthlyAverages['avg_week']; ?></span> Visites / semaine</p>
        </div>

        <div class="card">
            <h3>Moyenne Globale</h3>
            <p><span class="big-num"><?php echo $globalAverages['avg_month_global']; ?></span> Visites / mois</p>
            <p>Basé sur <?php echo $globalAverages['month_count']; ?> mois d'historique.</p>
        </div>

        <div class="card">
            <h3>Intensité Horaire</h3>
            <canvas id="hourChart"></canvas>
        </div>

        <div class="card">
            <h3>Appareils</h3>
            <canvas id="deviceChart"></canvas>
        </div>

        <div class="card">
            <h3>Navigateurs</h3>
            <canvas id="browserChart"></canvas>
        </div>

        <div class="card">
            <h3>Évolution Mensuelle</h3>
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>
    <?php require_once __DIR__.'/parts/footer.php';?>

    <?php
    $statsPayload = [
        'hourlyData' => array_values($hourlyData),
        'devices'    => [
            'labels' => array_column($devices, 'appareil'),
            'values' => array_map('intval', array_column($devices, 'nb')),
        ],
        'browsers'   => [
            'labels' => array_column($browsers, 'navigateur'),
            'values' => array_map('intval', array_column($browsers, 'nb')),
        ],
        'monthly'    => [
            'labels' => $monthlyEvolution['labels'],
            'values' => array_map('intval', $monthlyEvolution['values']),
        ],
    ];
    ?>
    <script type="application/json" id="statsData"><?= json_encode($statsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?></script>
    <script src="/cosmtt/admin/js/stats.js"></script>
</body>

</html>