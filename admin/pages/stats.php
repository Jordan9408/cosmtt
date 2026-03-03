<?php
include 'tracker.php';

// 1. CALCULS DES MOYENNES
// Nombre de jours écoulés dans le mois actuel où il y a eu des visites
$resDays = $conn->query("SELECT COUNT(DISTINCT date_visite) FROM statistiques_visites WHERE MONTH(date_visite) = MONTH(CURDATE())")->fetchColumn();
$daysCount = $resDays ?: 1;

// Total visites du mois actuel
$monthTotal = $conn->query("SELECT COUNT(*) FROM statistiques_visites WHERE MONTH(date_visite) = MONTH(CURDATE())")->fetchColumn();

$avgDay = round($monthTotal / $daysCount, 1);
$avgWeek = round($avgDay * 7, 1);

// Moyenne globale par mois (Total visites / Nombre de mois différents en base)
$globalTotal = $conn->query("SELECT COUNT(*) FROM statistiques_visites")->fetchColumn();
$monthCount = $conn->query("SELECT COUNT(DISTINCT MONTH(date_visite), YEAR(date_visite)) FROM statistiques_visites")->fetchColumn() ?: 1;
$avgMonthGlobal = round($globalTotal / $monthCount, 1);

// 2. INTENSITÉ HORAIRE
$hourlyData = array_fill(0, 24, 0);
$hoursQuery = $conn->query("SELECT HOUR(heure_visite) as h, COUNT(*) as nb FROM statistiques_visites GROUP BY h");
foreach($hoursQuery as $row) { $hourlyData[$row['h']] = $row['nb']; }

// 3. APPAREILS ET NAVIGATEURS
$devices = $conn->query("SELECT appareil, COUNT(*) as nb FROM statistiques_visites GROUP BY appareil")->fetchAll(PDO::FETCH_ASSOC);
$browsers = $conn->query("SELECT navigateur, COUNT(*) as nb FROM statistiques_visites GROUP BY navigateur")->fetchAll(PDO::FETCH_ASSOC);

// 4. ÉVOLUTION MENSUELLE
$monthlyData = $conn->query("SELECT YEAR(date_visite) AS year, MONTH(date_visite) AS month, COUNT(*) AS nb FROM statistiques_visites GROUP BY year, month ORDER BY year, month")->fetchAll(PDO::FETCH_ASSOC);
$monthlyLabels = [];
$monthlyValues = [];
foreach ($monthlyData as $row) {
    $monthlyLabels[] = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
    $monthlyValues[] = $row['nb'];
}
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
            background: #f8f9fa;
            color: #333;
        }
        .grid { 
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 20px; 
        }
        .card { 
            background: white; 
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
    </style>
</head>
<body>

    <div class="title-bar">Rapport de Trafic</div>

    <div class="grid">
        <div class="card">
            <h3>Moyennes du Mois</h3>
            <p><span class="big-num"><?php echo $avgDay; ?></span> Visites / jour</p>
            <p><span class="big-num"><?php echo $avgWeek; ?></span> Visites / semaine</p>
        </div>

        <div class="card">
            <h3>Moyenne Globale</h3>
            <p><span class="big-num"><?php echo $avgMonthGlobal; ?></span> Visites / mois</p>
            <p>Basé sur <?php echo $monthCount; ?> mois d'historique.</p>
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

    <script>
    // Graphique Heures (Barres)
    new Chart(document.getElementById('hourChart'), {
        type: 'bar',
        data: {
            labels: Array.from({length: 24}, (_, i) => i + "h"),
            datasets: [{ label: 'Visites', data: <?php echo json_encode(array_values($hourlyData)); ?>, backgroundColor: '#007bff' }]
        }
    });

    // Graphique Appareils (Camembert)
    new Chart(document.getElementById('deviceChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($devices, 'appareil')); ?>,
            datasets: [{ data: <?php echo json_encode(array_column($devices, 'nb')); ?>, backgroundColor: ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56'] }]
        }
    });

    // Graphique Navigateurs (Beignet)
    new Chart(document.getElementById('browserChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($browsers, 'navigateur')); ?>,
            datasets: [{ data: <?php echo json_encode(array_column($browsers, 'nb')); ?>, backgroundColor: ['#4bc0c0', '#ff9f40', '#9966ff', '#4caf50'] }]
        }
    });
    </script>
</body>
</html>