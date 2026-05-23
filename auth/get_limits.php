<?php
require_once __DIR__ . '/session_guard.php';
require_once __DIR__ . '/db.php'; // ensure env is loaded

header('Content-Type: application/json');

$wallet = isset($_SESSION['wallet_balance']) ? (float)$_SESSION['wallet_balance'] : 0.0;
$plan = strtoupper($_SESSION['user_sub']['plan_name'] ?? 'FREE');

$base_minutes = 0;
$ppm = 0.10; // default safe ppm

if ($plan === 'BASIS') {
    $base_minutes = (float)($_ENV['PLAN_BASIS_MINUTES'] ?? 60);
    $ppm = (float)($_ENV['PLAN_BASIS_PPM'] ?? 0.10);
} elseif ($plan === 'PRO') {
    $base_minutes = (float)($_ENV['PLAN_PRO_MINUTES'] ?? 1440);
    $ppm = (float)($_ENV['PLAN_PRO_PPM'] ?? 0.05);
} elseif ($plan === 'MAX') {
    $base_minutes = (float)($_ENV['PLAN_MAX_MINUTES'] ?? 43200);
    $ppm = (float)($_ENV['PLAN_MAX_PPM'] ?? 0.01);
} else {
    // Free plan
    $base_minutes = 5;
    $ppm = 0.50; // Expensive for free tier
}

// Ensure PPM isn't exactly 0 to avoid division by zero
if ($ppm <= 0) $ppm = 0.01;

// Total Seconds = (Base Plan Minutes * 60) + ((Wallet Balance / Plan PPM) * 60)
$max_seconds = ($base_minutes * 60) + (($wallet / $ppm) * 60);

echo json_encode([
    'wallet_balance' => $wallet,
    'plan' => $plan,
    'base_minutes' => $base_minutes,
    'ppm' => $ppm,
    'max_session_seconds' => $max_seconds
]);
