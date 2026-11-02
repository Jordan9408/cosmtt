<?php

(static function () {
    // 1. Recherche prioritaire du .env HORS de la racine web (sécurité maximale de production)
    $parentEnv = dirname(__DIR__, 3) . '/.env';
    // 2. Recherche standard à la racine du projet
    $localEnv = dirname(__DIR__) . '/.env';

    $envPath = null;
    if (file_exists($parentEnv)) {
        $envPath = $parentEnv;
    } elseif (file_exists($localEnv)) {
        $envPath = $localEnv;
    } else {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Ignore comments
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        // Parse key-value pair
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip quotes if present
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match('/^\'(.*)\'$/', $value, $matches)) {
                $value = $matches[1];
            }

            // Set environment variables
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
            if (!isset($_SERVER[$key])) {
                $_SERVER[$key] = $value;
            }
        }
    }
})();
