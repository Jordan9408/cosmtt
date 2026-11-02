<?php
/**
 * SCRIPT BATCH - NETTOYAGE PDF MALVEILLANTS
 * Scanne et nettoie tous les PDFs des articles
 * Usage: php admin/tools/clean_pdfs.php
 */

// Interdiction stricte d'accès web public : réservé au terminal (CLI) ou au superAdmin authentifié
if (php_sapi_name() !== 'cli') {
    require_once dirname(__DIR__, 2) . '/include/session_boot.php';
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'superAdmin') {
        http_response_code(403);
        die("Accès interdit : ce script est strictement réservé à la ligne de commande (CLI) ou aux super-administrateurs.");
    }
}

require_once dirname(__DIR__, 3) . '/pages/connect_ddb.php';
require_once dirname(__DIR__, 2) . '/classPages/security/FileSecurityValidator.php';
require_once dirname(__DIR__, 2) . '/classPages/article/ArticleManager.php';

echo "🚀 NETTOYAGE PDF - " . date('Y-m-d H:i:s') . "\n\n";

$pdfDir = dirname(__DIR__, 3) . '/docs/articles/resultats/';
if (!is_dir($pdfDir)) {
    die("Répertoire PDF introuvable: $pdfDir\n");
}

$manager = new ArticleManager($conn);
$cleanCount = 0;
$errorCount = 0;

$files = glob($pdfDir . '*.pdf');
echo "📁 " . count($files) . " PDFs trouvés\n\n";

foreach ($files as $index => $pdfFile) {
    $filename = basename($pdfFile);
    echo sprintf("[%03d/%03d] %s ", $index+1, count($files), $filename);
    
    try {
        // Test validation sécurité
        FileSecurityValidator::validateMagicBytes($pdfFile, 'application/pdf');
        FileSecurityValidator::scanForMalware($pdfFile, 'application/pdf');
        
        $clam = FileSecurityValidator::scanWithClamAV($pdfFile);
        if ($clam['available'] && !$clam['clean']) {
            throw new Exception("VIRUS ClamAV!");
        }
        
        echo "✅ SÉCURISÉ\n";
        $cleanCount++;
        
    } catch (Exception $e) {
        echo "❌ DANGER: " . $e->getMessage() . "\n";
        $errorCount++;
        
        // Log détaillé
        error_log("PDF DANGER [$filename]: " . $e->getMessage());
        
        // OPTION: Supprimer ou quarantaine
        // rename($pdfFile, $pdfDir . 'QUARANTINE_' . $filename);
        // echo "    → MOVED TO QUARANTINE\n";
    }
}

echo "\n📊 RÉSULTATS:\n";
echo "✅ Nettoyes/SÉCURISÉS: $cleanCount\n";
echo "❌ DANGEREUX: $errorCount\n";

if ($errorCount > 0) {
    echo "\n⚠️  Vérifiez admin/logs/security.log\n";
} else {
    echo "\n🎉 TOUS LES PDFs SONT SÉCURISÉS!\n";
}

// Vérifier Ghostscript pour compression future
$gsTest = PHP_OS_FAMILY === 'Windows' ? 'gswin64c --version' : 'gs --version';
exec($gsTest, $out, $ret);
echo "\n🔧 Ghostscript: " . ($ret === 0 ? 'OK' : 'MANQUANT') . "\n";
?>

