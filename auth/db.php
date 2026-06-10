<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Set default timezone for dates displayed on the UI
date_default_timezone_set('Asia/Kolkata');

// Secure session configuration — must come before any session_start()
require_once __DIR__ . '/session_config.php';
sb_configure_session();

// ── CORS ──────────────────────────────────────────────────────────────
// Only allow requests from the configured APP_URL.
// Never fall back to wildcard — if APP_URL is missing, deny all origins.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origin = rtrim($_ENV['APP_URL'] ?? '', '/');
$isAllowed = false;

if ($origin !== '' && $allowed_origin !== '' && $allowed_origin !== '*') {
    $normalised_origin = rtrim($origin, '/');
    if (strcasecmp($normalised_origin, $allowed_origin) === 0) {
        $isAllowed = true;
    } elseif (preg_match('/\.onrender\.com$/i', parse_url($origin, PHP_URL_HOST) ?? '')) {
        $isAllowed = true;
    }
}

if ($isAllowed) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Vary: Origin");
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

// ── MongoDB connection ────────────────────────────────────────────────
try {
    $client = new MongoDB\Client($_ENV['MONGODB_URI']);
    $db = $client->certanity;
} catch (Exception $e) {
    http_response_code(500);
    error_log('MongoDB connection failed: ' . $e->getMessage());
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}