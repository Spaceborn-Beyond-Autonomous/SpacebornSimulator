<?php

require __DIR__ . '/../vendor/autoload.php';
require 'db.php';

session_start();

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