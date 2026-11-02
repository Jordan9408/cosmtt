<?php
/**
 * Diagnostic des problèmes de sécurisation PDF
 */

if (php_sapi_name() !== 'cli') {
    require_once dirname(__DIR__, 2) . '/include/session_boot.php';
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'superAdmin') {
        http_response_code(403);
        die("Accès interdit : cet outil de diagnostic est strictement réservé aux super-administrateurs ou à la ligne de commande (CLI).");
    }
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

class PDFDiagnostic
{
    private array $errors = [];
    private string $pdfDir;
    private string $rootDir;

    public function __construct()
    {
        // Remonte de admin/tools/ vers la racine du projet
        $this->rootDir = dirname(__DIR__, 2);
        $this->pdfDir = $this->rootDir . '/docs/articles/resultats/';
    }

    public function run(): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo "=== DIAGNOSTIC PDF SECURITY ===\n\n";

        $this->testIncludes();
        $this->testDependencies();
        $this->testDirectories();
        $this->printSummary();

        echo "\n\n=== FIN DU DIAGNOSTIC ===\n";
    }

    private function testIncludes(): void
    {
        echo "1️⃣ TEST DES INCLUDES:\n---\n";
        $base = $this->rootDir . '/admin/pages/classPages/security/';
        $this->testClassLoad($base . 'FileSecurityValidator.php', 'FileSecurityValidator');
        $this->testClassLoad($base . 'PDFSecurityProcessor.php', 'PDFSecurityProcessor');
    }

    private function testClassLoad(string $path, string $className): void
    {
        try {
            if (!file_exists($path)) {
                echo "{$className}.php NOT FOUND at: $path\n";
                $this->errors[] = "{$className} not found";
                return;
            }
            require_once $path;
            $loaded = class_exists($className);
            echo ($loaded ? "✅" : "❌") . " {$className} " . ($loaded ? "loaded" : "class not found") . "\n";
            if (!$loaded) {
                $this->errors[] = "{$className} class not found";
            }
        } catch (Exception $e) {
            echo "❌ Error loading {$className}: " . $e->getMessage() . "\n";
            $this->errors[] = "{$className} error: " . $e->getMessage();
        }
    }

    private function testDependencies(): void
    {
        echo "\n2️⃣ TEST DES DÉPENDANCES:\n---\n";
        $execAvailable = function_exists('exec');
        echo ($execAvailable ? "✅" : "❌") . " exec() disponible\n";

        if (!$execAvailable) {
            $this->errors[] = "exec() is disabled";
            return;
        }

        $gsAvailable = false;
        foreach (['gswin64c', 'gs'] as $cmd) {
            exec("where $cmd 2>nul", $out, $ret);
            if ($ret === 0) { $gsAvailable = true; break; }
        }
        echo ($gsAvailable ? "✅" : "⚠️") . " Ghostscript disponible\n";

        // Moteur natif d'optimisation et réécriture PDF (sans dépendance externe requise)
        $zlibAvailable = extension_loaded('zlib');
        echo ($zlibAvailable ? "✅" : "❌") . " Extension PHP Zlib (compression/décompression Flate native) disponible\n";
        if (!$zlibAvailable) {
            $this->errors[] = "PHP extension zlib is not loaded";
        }
    }

    private function testDirectories(): void
    {
        echo "\n3️⃣ TEST DES RÉPERTOIRES:\n---\n";
        $readable = is_readable($this->pdfDir);
        $writable = is_writable($this->pdfDir);

        echo "Répertoire PDF: {$this->pdfDir}\n";
        echo ($readable ? "✅" : "❌") . " Lecture\n" . ($writable ? "✅" : "❌") . " Écriture\n";

        if (!$writable) {
            $this->errors[] = "PDF directory not writable";
        }
    }

    private function printSummary(): void
    {
        echo "\n4️⃣ RÉSUMÉ:\n---\n";

        if (empty($this->errors)) {
            echo "TOUS LES TESTS RÉUSSIS!\n\nLe système est prêt à fonctionner.\n";
            echo "Si vous avez toujours des problèmes, vérifiez:\n";
            echo "  - Les logs PHP (error_log)\n  - Le chemin exact du PDF uploadé\n  - Les permissions du dossier\n";
            return;
        }

        echo "ERREURS DÉTECTÉES:\n\n";
        foreach ($this->errors as $i => $error) {
            echo ($i + 1) . ". $error\n";
        }
        echo "\nActions requises:\n";

        $actions = [
            'exec() is disabled' => 'Activer exec() dans php.ini',
            'FileSecurityValidator not found' => 'Vérifier que FileSecurityValidator.php existe',
            'PDFSecurityProcessor not found' => 'Vérifier que PDFSecurityProcessor.php existe',
            'PDF directory not writable' => 'Donner les permissions d\'écriture au dossier docs/articles/resultats/'
        ];

        foreach ($actions as $key => $action) {
            if (in_array($key, $this->errors)) {
                echo "  - $action\n";
            }
        }
    }
}

if (php_sapi_name() === 'cli' || (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))) {
    $diagnostic = new PDFDiagnostic();
    $diagnostic->run();
}
