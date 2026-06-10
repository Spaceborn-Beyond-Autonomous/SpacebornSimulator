<?php

declare(strict_types=1);

/**
 * Secure session configuration.
 * Must be included BEFORE session_start() on every page.
 */
function sb_configure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $isHttps = (
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '7200');
}

/**
 * Generate a CSRF token and store it in the session.
 * Call this on any page that renders a form or needs the token for JS.
 */
function sb_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from a form POST (hidden field).
 * Call this at the top of any form-based POST handler.
 */
function sb_verify_csrf_form(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    $stored = (string) ($_SESSION['csrf_token'] ?? '');

    if ($stored === '' || $token === '' || !hash_equals($stored, $token)) {
        http_response_code(403);
        die('Invalid or missing CSRF token.');
    }
}

/**
 * Validate CSRF token from a JSON API request (X-CSRF-Token header).
 * Call this at the top of any JSON POST handler.
 */
function sb_verify_csrf_header(): void
{
    $token  = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = (string) ($_SESSION['csrf_token'] ?? '');

    if ($stored === '' || $token === '' || !hash_equals($stored, $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

/**
 * Simple IP-based rate limiting.
 * Call this at the top of auth endpoints to prevent brute force attacks.
 * 
 * @param string $key Identifier for the rate limit (e.g., 'login', 'oauth')
 * @param int $maxAttempts Maximum attempts allowed in the window
 * @param int $windowSeconds Time window in seconds
 * @return bool True if rate limit exceeded, false if OK to proceed
 */
function sb_rate_limit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ip = preg_replace('/[^a-fA-F0-9.:]/', '', $ip); // Sanitize IP
    
    $cacheFile = sys_get_temp_dir() . '/sb_rl_' . $key . '_' . md5($ip) . '.json';
    $now = time();
    
    $data = ['count' => 0, 'reset_at' => $now + $windowSeconds];
    
    if (file_exists($cacheFile)) {
        $loaded = @json_decode(file_get_contents($cacheFile), true);
        if (is_array($loaded) && isset($loaded['count'], $loaded['reset_at'])) {
            if ($loaded['reset_at'] > $now) {
                $data = $loaded;
            }
        }
    }
    
    if ($data['count'] >= $maxAttempts && $data['reset_at'] > $now) {
        return true; // Rate limit exceeded
    }
    
    $data['count']++;
    @file_put_contents($cacheFile, json_encode($data));
    
    return false;
}

/**
 * Get remaining seconds until rate limit resets.
 */
function sb_rate_limit_remaining(string $key): int
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ip = preg_replace('/[^a-fA-F0-9.:]/', '', $ip);
    
    $cacheFile = sys_get_temp_dir() . '/sb_rl_' . $key . '_' . md5($ip) . '.json';
    
    if (file_exists($cacheFile)) {
        $loaded = @json_decode(file_get_contents($cacheFile), true);
        if (is_array($loaded) && isset($loaded['reset_at'])) {
            return max(0, (int)$loaded['reset_at'] - time());
        }
    }
    
    return 0;
}