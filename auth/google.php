<?php

require __DIR__ . '/../vendor/autoload.php';
require 'db.php';

require_once __DIR__ . '/session_config.php';
sb_configure_session();
session_start();

// Rate limiting — prevent OAuth abuse
if (sb_rate_limit('oauth', 10, 300)) {
    $remaining = sb_rate_limit_remaining('oauth');
    http_response_code(429);
    echo "Too many login attempts. Please try again in {$remaining} seconds.";
    exit;
}

$client = new Google\Client();

$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? '');
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

$scheme = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$appRoot = dirname($scriptDir);
$baseUrl = !empty($_ENV['APP_URL']) ? rtrim($_ENV['APP_URL'], '/') : rtrim($scheme . '://' . $host . $appRoot, '/');

$client->setRedirectUri($baseUrl . '/auth/callback.php');

$client->addScope("email");
$client->addScope("profile");

$login_url = $client->createAuthUrl();

header('Location: ' . $login_url);
exit();

?>