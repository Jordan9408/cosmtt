<?php
declare(strict_types=1);

namespace Admin\ClassPages\Club;

use Admin\Includes\ErrorMessages;

/**
 * Classe Validations
 * Gère la validation de sécurité pour tous les contenus du club
 */
class Validations
{
    /**
     * Patterns dangereux à détecter
     */
    private const DANGEROUS_PATTERNS = [
        '/(<\?php|<\?|<script|javascript:|vbscript:|data:|\?>|<\%|\%>)/i' => 'Injection de balises exécutables (PHP, JS, ASP)',
        '/(eval|system|exec|shell_exec|passthru|proc_open|pcntl_exec|assert|base64_decode|gzinflate|gzuncompress|popen)\s*[\(\[ ]/i' => 'Signature de malware ou fonction système dangereuse',
        '/(const|let|var|function)\s*[=\(\[]/i' => 'Déclaration de logique JavaScript détectée',
        '/(`{3}|```|<html>|<body|<head|<link|<meta|<style|<form|<iframe|<object|<embed)/i' => 'Structure HTML ou bloc de code non autorisé',
        '/(on\w+\s*=|\bonfocus\b|\bonload\b|\bonchange\b|\bonclick\b|\bonmouseover\b)/i' => 'Attribut d\'événement JavaScript détecté',
        '/(document\s*\.\s*cookie|window\s*\.\s*location|document\s*\.\s*write|innerHTML|innerText)/i' => 'Manipulation DOM ou vol de session détecté',
        '/(UNION\s+SELECT|INSERT\s+INTO|UPDATE\s+.*SET|DELETE\s+FROM|DROP\s+TABLE)/i' => 'Tentative d\'injection SQL détectée',
        '/(__proto__|constructor|prototype)/i' => 'Tentative de pollution de prototype détectée',
        '/(https?:\/\/[^\s]+|www\.[^\s]+)/i' => 'Les liens et URLs ne sont pas autorisés dans le message pour prévenir le spam',
        '/<!--.*-->/s' => 'Commentaires HTML suspects',
        '/\x00|\x0b|\x0c/i' => 'Caractères de contrôle nuls ou malveillants détectés'
    ];

    /**
     * Génère le code JavaScript pour détecter les erreurs dans les iframes
     * 
     * @return string Code JavaScript pour la détection d'erreurs d'iframe
     */
    public static function getIframeErrorDetectionScript(): string
    {
        return "
        /**
         * Détecte les erreurs de chargement dans les iframes et affiche une alerte
         * Cette fonction doit être appelée après le chargement de la page
         */
        function detectIframeErrors() {
            const iframes = document.querySelectorAll('iframe');
            iframes.forEach(function(iframe, index) {
                iframe.addEventListener('error', function() {
                    alert('❌ Erreur de chargement détectée dans l\\'iframe #' + (index + 1) + '.\\n\\n' +
                          'Vérifiez que l\\'URL source est valide et accessible.\\n' +
                          'Si vous utilisez un code iframe complet, assurez-vous qu\\'il provient d\\'une source fiable.');
                });
                iframe.addEventListener('load', function() {
                    // Chargement réussi
                });
            });
        }
        window.detectIframeErrors = detectIframeErrors;
        ";
    }

    /**
     * Valide le contenu du champ (Horaires, Adresse, Nom du club)
     * 
     * @param string $content Le contenu à valider
     * @param string $fieldName Le nom du champ
     * @return array ['valid' => bool, 'value' => string, 'error' => string]
     */
    public static function validateCleanContent($content, $fieldName)
    {
        if (empty($content)) {
            return [
                'valid' => true,
                'value' => ''
            ];
        }

        // Détection des tentatives d'obfuscation par caractères nuls
        if (preg_match('/\x00/', $content)) {
            return ['valid' => false, 'error' => ErrorMessages::format('VALIDATION_NULL_CHAR', [$fieldName])];
        }

        foreach (self::DANGEROUS_PATTERNS as $pattern => $message) {
            if (preg_match($pattern, $content)) {
                return [
                    'valid' => false,
                    'error' => ErrorMessages::format('VALIDATION_DANGEROUS_CONTENT', [$fieldName, $message])
                ];
            }
        }

        return [
            'valid' => true,
            'value' => $content
        ];
    }

    /**
     * Valide un tarif (prix de licence)
     * 
     * @param string $tarifRaw Le tarif brut saisi par l'utilisateur
     * @param string $fieldName Le nom du champ
     * @return array ['valid' => bool, 'value' => string, 'error' => string]
     */
    public static function validateTarif($tarifRaw, $fieldName)
    {
        // Accepter uniquement : chiffres, espaces ET €
        // PAS de virgule ni point (pas de décimales)
        if (!preg_match('/^[0-9\s€]*$/', $tarifRaw)) {
            return [
                'valid' => false,
                'error' => ErrorMessages::format('TARIF_GENERIC_INVALID', [$fieldName])
            ];
        }

        // Nettoyer la valeur : supprimer ALL espaces et €
        $tarifClean = str_replace([' ', '€'], '', $tarifRaw);
        $tarifClean = trim($tarifClean);

        // Si vide, autoriser (champ optionnel)
        if (empty($tarifClean)) {
            return [
                'valid' => true,
                'value' => ''
            ];
        }

        // Vérifier que c'est un nombre entier valide
        if (!ctype_digit($tarifClean)) {
            return [
                'valid' => false,
                'error' => ErrorMessages::format('TARIF_NOT_INTEGER', [$fieldName, $tarifRaw])
            ];
        }

        $tarifInt = intval($tarifClean);

        // Vérifier la plage (0 à 200)
        if ($tarifInt < 0 || $tarifInt > 200) {
            return [
                'valid' => false,
                'error' => ErrorMessages::format('TARIF_RANGE_ERROR', [$fieldName, $tarifInt])
            ];
        }

        return [
            'valid' => true,
            'value' => strval($tarifInt)
        ];
    }

    /**
     * Valide une URL Google Maps
     * 
     * @param string $mapUrl L'URL à valider
     * @return array ['valid' => bool, 'url' => string, 'error' => string]
     */
    public static function validateMapUrl($mapUrl)
    {
        if (empty($mapUrl)) {
            return ['valid' => true, 'url' => ''];
        }

        // Si l'utilisateur a fourni un <iframe>, extraire l'attribut src
        $src = $mapUrl;
        $hasIframe = str_contains($mapUrl, '<iframe');
        
        if ($hasIframe) {
            // Sécurité supplémentaire : si on détecte un iframe, on vérifie qu'il n'y a pas d'autres balises suspectes autour
            // strip_tags avec '<iframe>' permet de ne garder QUE les iframes
            $stripped = strip_tags($mapUrl, '<iframe>');
            if ($stripped !== $mapUrl) {
                return ['valid' => false, 'error' => ErrorMessages::get('MAP_URL_IFRAME_CONTENT_DENIED')];
            }

            preg_match('/src=["\']([^"\']+)["\']/', $mapUrl, $m);
            $src = $m[1] ?? '';
            if (empty($src)) {
                return ['valid' => false, 'error' => ErrorMessages::get('MAP_URL_IFRAME_SRC_ERROR')];
            }
        }

        // Vérifier que c'est une URL valide
        if (!filter_var($src, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => ErrorMessages::get('MAP_URL_INVALID_URL')];
        }

        $parts = parse_url($src);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host   = strtolower($parts['host'] ?? '');
        $path   = $parts['path'] ?? '';

        // N'autoriser que https
        if ($scheme !== 'https') {
            return ['valid' => false, 'error' => ErrorMessages::get('MAP_URL_SCHEME_DENIED')];
        }

        // Whitelist stricte : chaque entrée = [hôte autorisé, préfixe de chemin requis]
        // Google Maps : seul /maps/embed est embeddable (les autres paths renvoient X-Frame-Options: SAMEORIGIN)
        $allowedHosts = [
            ['www.google.com',          '/maps/embed'],
            ['maps.googleapis.com',     '/maps/'],
            ['www.openstreetmap.org',   '/export/embed.html'],
            ['www.bing.com',            '/maps/embed'],
        ];

        $matched = false;
        foreach ($allowedHosts as [$allowedHost, $requiredPath]) {
            if ($host === $allowedHost && str_starts_with($path, $requiredPath)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            return ['valid' => false, 'error' => ErrorMessages::get('MAP_URL_HOST_DENIED')];
        }

        return ['valid' => true, 'url' => $src];
    }
}
?>