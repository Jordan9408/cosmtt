<?php
declare(strict_types=1);

$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    exit('Fichier non spécifié.');
}

// Sécurité anti-path traversal (on ne garde que le nom du fichier)
$filename = basename($file);

// Vérification stricte de l'extension PDF
if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(403);
    exit('Format de fichier non autorisé.');
}

$filePath = __DIR__ . '/docs/articles/resultats/' . $filename;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Vider et désactiver tout tampon de sortie pour éviter toute corruption ou interruption
while (ob_get_level()) {
    ob_end_clean();
}

$fileSize = (int)filesize($filePath);
$cleanFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

// En-têtes pour l'affichage direct et fluide dans le visualiseur PDF du navigateur
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $cleanFilename . '"');
header('Content-Length: ' . (string)$fileSize);
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
