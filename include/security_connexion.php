<?php

declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once __DIR__ . '/../admin/includes/ErrorMessages.php';
require_once __DIR__ . '/../admin/pages/class/Auth.php';
require_once __DIR__ . '/RateLimiter.php';

try {
    $auth = new Auth();
} catch (RuntimeException $e) {
    error_log('[Connexion] Initialisation de l’authentification impossible : ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit(\Admin\Includes\ErrorMessages::get('AUTH_INTERNAL_ERROR'));
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$rateLimiter = new RateLimiter(
    storageDir: dirname(__DIR__) . '/storage/rate_limit',
    maxAttempts: 5,
    windowSeconds: 900,
);
