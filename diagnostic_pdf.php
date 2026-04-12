<?php
/**
 * Diagnostic des problèmes de sécurisation PDF
 * Accès: http://localhost/cosmtt/diagnostic_pdf.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== DIAGNOSTIC PDF SECURITY ===\n\n";

// 1. Test des includes
echo "1️⃣ TEST DES INCLUDES:\n";
echo "---\n";

$errors = [];

// Test FileSecurityValidator
try {
    $path = dirname(__FILE__) . '/admin/pages/classPages/security/FileSecurityValidator.php';
    if (!file_exists($path)) {
        echo "❌ FileSecurityValidator.php NOT FOUND at: $path\n";
        $errors[] = "FileSecurityValidator not found";
    } else {
        require_once $path;
        if (class_exists('FileSecurityValidator')) {
            echo "✅ FileSecurityValidator loaded\n";
        } else {
            echo "❌ FileSecurityValidator class not found\n";
            $errors[] = "FileSecurityValidator class not found";
        }
    }
} catch (Exception $e) {
    echo "❌ Error loading FileSecurityValidator: " . $e->getMessage() . "\n";
    $errors[] = "FileSecurityValidator error: " . $e->getMessage();
}

// Test PDFSecurityProcessor
try {
    $path = dirname(__FILE__) . '/admin/pages/classPages/security/PDFSecurityProcessor.php';
    if (!file_exists($path)) {
        echo "❌ PDFSecurityProcessor.php NOT FOUND at: $path\n";
        $errors[] = "PDFSecurityProcessor not found";
    } else {
        require_once $path;
        if (class_exists('PDFSecurityProcessor')) {
            echo "✅ PDFSecurityProcessor loaded\n";
        } else {
            echo "❌ PDFSecurityProcessor class not found\n";
            $errors[] = "PDFSecurityProcessor class not found";
        }
    }
} catch (Exception $e) {
    echo "❌ Error loading PDFSecurityProcessor: " . $e->getMessage() . "\n";
    $errors[] = "PDFSecurityProcessor error: " . $e->getMessage();
}

// 2. Test des dépendances système
echo "\n2️⃣ TEST DES DÉPENDANCES:\n";
echo "---\n";

$exec_available = function_exists('exec');
echo ($exec_available ? "✅" : "❌") . " exec() disponible\n";
if (!$exec_available) {
    $errors[] = "exec() is disabled";
}

// Test Ghostscript
$gs_available = false;
if ($exec_available) {
    exec('where gswin64c 2>nul', $out, $ret);
    $gs_available = ($ret === 0);
    
    if (!$gs_available) {
        exec('where gs 2>nul', $out, $ret);
        $gs_available = ($ret === 0);
    }
}
echo ($gs_available ? "✅" : "⚠️") . " Ghostscript disponible\n";

// Test QPDF
$qpdf_available = false;
if ($exec_available) {
    exec('where qpdf 2>nul', $out, $ret);
    $qpdf_available = ($ret === 0);
}
echo ($qpdf_available ? "✅" : "⚠️") . " QPDF disponible\n";

// 3. Test des répertoires
echo "\n3️⃣ TEST DES RÉPERTOIRES:\n";
echo "---\n";

$pdf_dir = dirname(__FILE__) . '/docs/articles/resultats/';
$readable = is_readable($pdf_dir);
$writable = is_writable($pdf_dir);

echo "Répertoire PDF: $pdf_dir\n";
echo ($readable ? "✅" : "❌") . " Lecture\n";
echo ($writable ? "✅" : "❌") . " Écriture\n";

if (!$writable) {
    $errors[] = "PDF directory not writable";
}

// 4. Résumé
echo "\n4️⃣ RÉSUMÉ:\n";
echo "---\n";

if (empty($errors)) {
    echo "✅ TOUS LES TESTS RÉUSSIS!\n\n";
    echo "Le système est prêt à fonctionner.\n";
    echo "Si vous avez toujours des problèmes, vérifiez:\n";
    echo "  - Les logs PHP (error_log)\n";
    echo "  - Le chemin exact du PDF uploadé\n";
    echo "  - Les permissions du dossier\n";
} else {
    echo "❌ ERREURS DÉTECTÉES:\n\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". $error\n";
    }
    echo "\n⚠️ Actions requises:\n";
    
    if (in_array("exec() is disabled", $errors)) {
        echo "  - Activer exec() dans php.ini\n";
    }
    if (in_array("FileSecurityValidator not found", $errors)) {
        echo "  - Vérifier que FileSecurityValidator.php existe\n";
    }
    if (in_array("PDFSecurityProcessor not found", $errors)) {
        echo "  - Vérifier que PDFSecurityProcessor.php existe\n";
    }
    if (in_array("PDF directory not writable", $errors)) {
        echo "  - Donner les permissions d'écriture au dossier docs/articles/resultats/\n";
    }
}

echo "\n\n=== FIN DU DIAGNOSTIC ===\n";
?>
