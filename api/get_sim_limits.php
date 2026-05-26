<?php
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../includes/simulator_launch.php';

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

$planId = (int) ($user['sub_id'] ?? 0);
$wallet = (float) ($user['wallet_balance'] ?? 0.0);
$ppm = (float) ($_GET['ppm'] ?? 0.10);

$timeSeconds = 0;
$planName = 'FREE';
$subActive = false;
$subRemainingSeconds = 0;
$walletSeconds = 0;
$baseMinutes = 0;
$trialMode = false;
$paidState = null;
$trialState = ['available' => false, 'active' => false, 'remaining_seconds' => 0, 'started_at' => 0, 'reset_at' => 0];

if ($planId > 0) {
    $paidState = sb_paid_plan_state($user, true);
    $timeSeconds = ($paidState['plan_id'] === 3) ? -1 : (int) $paidState['remaining_seconds'];
    $planName = (string) ($paidState['plan_name'] ?? 'FREE');
    $subActive = (bool) ($paidState['active'] ?? false);
    $subRemainingSeconds = ($timeSeconds > 0) ? (int) $timeSeconds : 0;
    $baseMinutes = $planId === 1 ? 60 : ($planId === 2 ? 1440 : 43200);
} else {
    if ($wallet > 0 && ($ppm > 0)) {
        $walletSeconds = (int) (($wallet / $ppm) * 60);
        $timeSeconds = $walletSeconds;
        $planName = 'FREE';
    } else {
        $trialState = sb_free_trial_state($user, true);

        if ($trialState['available']) {
            $trialMode = true;
            $planName = 'BASIC';
            $timeSeconds = (int) $trialState['remaining_seconds'];
            $ppm = 0.0;
            $baseMinutes = 10;
        } else {
            $timeSeconds = 0;
            $planName = 'BASIC';
            $ppm = 0.0;
            $baseMinutes = 10;
        }
    }
}

if ($trialMode) {
    $subActive = false;
    $subRemainingSeconds = 0;
    $walletSeconds = 0;
}

$now = time();
$accessExpiresAt = $timeSeconds > 0 ? $now + $timeSeconds : 0;
$effectivePlanId = (int) ($paidState['plan_id'] ?? $planId);

echo json_encode([
    'success' => true,
    'plan_id' => $effectivePlanId,
    'plan_name' => $planName,
    'wallet_balance' => $wallet,
    'base_minutes' => $baseMinutes,
    'ppm' => $ppm,
    'time_remaining_seconds' => $timeSeconds,
    'max_session_seconds' => $timeSeconds,
    'sub_active' => $subActive,
    'sub_expires_at' => $paidState['expires_at'] ?? 0,
    'sub_remaining_seconds' => $subRemainingSeconds,
    'wallet_seconds' => $walletSeconds,
    'access_expires_at' => $accessExpiresAt,
    'trial_mode' => $trialMode,
    'trial_reset_at' => $trialState['reset_at'] ?? 0,
]);
