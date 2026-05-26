<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../includes/simulator_launch.php';

$user = $db->users->findOne(['email' => $_SESSION['email'] ?? '']);
if (!$user) {
    header('Location: ../auth/login.php');
    exit;
}

$planId = (int) ($user['sub_id'] ?? 0);
$wallet = (float) ($user['wallet_balance'] ?? 0.0);
$runPlan = strtoupper(trim((string) ($_GET['plan'] ?? '')));

$planFiles = [
    'BASIC' => 'basic.php',
    'PRO'   => 'pro.php',
    'MAX'   => 'max.php',
];

$planIdFiles = [
    1 => 'basic.php',
    2 => 'pro.php',
    3 => 'max.php',
];

// Active subscription opens the matching tier simulator.
if ($planId > 0 && isset($planIdFiles[$planId])) {
    header('Location: ' . $planIdFiles[$planId]);
    exit;
}

// Wallet-only: user picked a tier.
if ($planId <= 0 && $wallet > 0 && isset($planFiles[$runPlan])) {
    header('Location: ' . $planFiles[$runPlan] . '?run_plan=' . urlencode($runPlan));
    exit;
}

// Free basic trial: 10 minutes refreshed every 6 hours.
$trialState = sb_free_trial_state($user, false);
if ($planId <= 0 && $wallet <= 0 && $trialState['available']) {
    header('Location: basic.php?run_plan=FREE');
    exit;
}

// No access.
if ($planId <= 0 && $wallet <= 0) {
    header('Location: ../billing.php?msg=insufficient_funds');
    exit;
}

// Wallet balance but no plan chosen yet - free zone (bypasses picker).
header('Location: basic.php?run_plan=FREE&ppm=0.10');
exit;
