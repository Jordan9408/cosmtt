<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/config/load_env.php';

/**
 * Démarre la session avec des cookies durcis (HttpOnly, SameSite, Secure si HTTPS).
 * À inclure avant tout envoi HTML.
 */
if (session_status() !== PHP_SESSION_NONE) {
    return;
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

// Chemin de session déduit de APP_URL (fonctionne local + prod + sous-dossier)
$appPath = '/';
$configuredUrl = getenv('APP_URL');
if ($configuredUrl) {
    $parsed = parse_url($configuredUrl);
    $path = $parsed['path'] ?? '';
    $path = rtrim($path, '/');
    $appPath = $path !== '' ? $path . '/' : '/';
}

ini_set('session.use_strict_mode', '1');
$configuredUrl = getenv('APP_URL');
$canonicalParts = is_string($configuredUrl) ? parse_url($configuredUrl) : false;
if (
    PHP_SAPI !== 'cli'
    && is_array($canonicalParts)
    && !empty($canonicalParts['scheme'])
    && !empty($canonicalParts['host'])
    && !isset($canonicalParts['user'])
    && !isset($canonicalParts['pass'])
    && !array_key_exists('query', $canonicalParts)
    && !array_key_exists('fragment', $canonicalParts)
    && in_array(strtolower($canonicalParts['scheme']), ['http', 'https'], true)
) {
    $canonicalHost = trim($canonicalParts['host'], '[]');
    $canonicalHostIsValid = strtolower($canonicalHost) === 'localhost'
        || filter_var($canonicalHost, FILTER_VALIDATE_IP) !== false
        || filter_var($canonicalHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

    if ($canonicalHostIsValid) {
        $isLoopbackHost = in_array(strtolower($canonicalHost), ['localhost', '127.0.0.1', '::1'], true);
        $canonicalScheme = strtolower($canonicalParts['scheme']) === 'http' && $isLoopbackHost
            ? 'http'
            : 'https';
        $canonicalPort = $canonicalParts['port'] ?? null;
        if (($canonicalScheme === 'https' && $canonicalPort === 80) || ($canonicalScheme === 'http' && $canonicalPort === 443)) {
            $canonicalPort = null;
        }
        $hostForUrl = str_contains($canonicalHost, ':') ? '[' . $canonicalHost . ']' : $canonicalHost;
        $canonicalAuthority = $hostForUrl . ($canonicalPort !== null ? ':' . $canonicalPort : '');

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $requestIsSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || $forwardedProto === 'https';
        $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        if ($requestUri === '' || $requestUri[0] !== '/' || str_contains($requestUri, "\r") || str_contains($requestUri, "\n")) {
            $requestUri = '/';
        }

        if ($requestHost !== strtolower($canonicalAuthority) || ($canonicalScheme === 'https' && !$requestIsSecure)) {
            header('Location: ' . $canonicalScheme . '://' . $canonicalAuthority . $requestUri, true, 301);
            exit;
        }
    }
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $appPath !== '' ? $appPath : '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// Timeout absolu de session (anti vol de session persistant - max 2h selon normes de sécurité)
$absoluteLimit = 7200; // 2 heures
if (isset($_SESSION['auth_created']) && (time() - (int) $_SESSION['auth_created']) > $absoluteLimit) {
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
    $_SESSION['auth_created'] = time();
}

if (isset($_SESSION['user_id'])) {
    $sessionFingerprint = $_SESSION['auth_password_fingerprint'] ?? null;
    $sessionIsCurrent = false;

    if (is_string($sessionFingerprint) && preg_match('/^[a-f0-9]{64}$/D', $sessionFingerprint)) {
        try {
            require_once dirname(__DIR__) . '/config/config.php';
            $sessionDb = (new Config())->getConnection();
            $statement = $sessionDb->prepare('SELECT password FROM users WHERE user_id = :user_id LIMIT 1');
            $statement->execute(['user_id' => (int) $_SESSION['user_id']]);
            $currentPasswordHash = $statement->fetchColumn();
            $sessionIsCurrent = is_string($currentPasswordHash)
                && hash_equals($sessionFingerprint, hash('sha256', $currentPasswordHash));
        } catch (Throwable $e) {
            error_log('[Session] Validation de session impossible : ' . $e->getMessage());
        }
    }

    if (!$sessionIsCurrent) {
        unset(
            $_SESSION['email'],
            $_SESSION['firstName'],
            $_SESSION['role'],
            $_SESSION['user_id'],
            $_SESSION['auth_created'],
            $_SESSION['auth_password_fingerprint'],
            $_SESSION['last_activity']
        );
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        session_regenerate_id(true);
    }
}

if (!isset($_SESSION['auth_created'])) {
    $_SESSION['auth_created'] = time();
}
