<?php
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';

header('Content-Type: application/json');

$email = $_SESSION['email'] ?? '';
if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user = $db->users->findOne(['email' => $email]);
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$planId = (int)($user['sub_id'] ?? 0);
$wallet = (float)($user['wallet_balance'] ?? 0.0);

$ppm = (float)($_GET['ppm'] ?? 0.10);

$time_seconds = 0;
$plan_name = 'FREE';

if ($planId > 0) {
    if ($planId === 1) {
        $time_seconds = 3600;
        $plan_name = 'BASIC';
    } elseif ($planId === 2) {
        $time_seconds = 86400;
        $plan_name = 'PRO';
    } elseif ($planId === 3) {
        $time_seconds = -1; // Unlimited
        $plan_name = 'MAX';
    }
} else {
    if ($ppm > 0) {
        $time_seconds = ($wallet / $ppm) * 60;
    } else {
        $time_seconds = 0;
    }
    $plan_name = 'FREE';
}

echo json_encode([
    'success' => true,
    'plan_id' => $planId,
    'plan_name' => $plan_name,
    'wallet_balance' => $wallet,
    'time_remaining_seconds' => $time_seconds
]);
