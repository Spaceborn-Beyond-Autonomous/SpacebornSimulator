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
$trialState = ['available' => false, 'active' => false, 'remaining_seconds' => 0, 'started_at' => 0, 'reset_at' => 0];

if ($planId > 0) {
    $subExpiresAt = 0;
    if (isset($user['sub_expires_at']) && $user['sub_expires_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $subExpiresAt = $user['sub_expires_at']->toDateTime()->getTimestamp();
    }

    // If not started yet, return the full duration so they see the starting limit.
    if (empty($user['sub_started'])) {
        if ($planId === 1) {
            $timeSeconds = 3600;
            $baseMinutes = 60;
        } elseif ($planId === 2) {
            $timeSeconds = 86400;
            $baseMinutes = 1440;
        } elseif ($planId === 3) {
            $timeSeconds = -1; // Unlimited
            $baseMinutes = 43200;
        }
    } else {
        if ($planId === 3) {
            $timeSeconds = -1; // Unlimited
            $baseMinutes = 43200;
        } else {
            $now = time();
            $timeSeconds = max(0, $subExpiresAt - $now);
            if ($planId === 1) {
                $baseMinutes = 60;
            } elseif ($planId === 2) {
                $baseMinutes = 1440;
            }
        }
    }

    if ($planId === 1) {
        $planName = 'BASIC';
    } elseif ($planId === 2) {
        $planName = 'PRO';
    } elseif ($planId === 3) {
        $planName = 'MAX';
    }
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

if ($planId > 0) {
    $subActive = true;
    $subRemainingSeconds = max(0, (int) $timeSeconds);
}

if ($trialMode) {
    $subActive = false;
    $subRemainingSeconds = 0;
    $walletSeconds = 0;
}

$now = time();
$accessExpiresAt = $timeSeconds > 0 ? $now + $timeSeconds : 0;

echo json_encode([
    'success' => true,
    'plan_id' => $planId,
    'plan_name' => $planName,
    'wallet_balance' => $wallet,
    'base_minutes' => $baseMinutes,
    'ppm' => $ppm,
    'time_remaining_seconds' => $timeSeconds,
    'max_session_seconds' => $timeSeconds,
    'sub_active' => $subActive,
    'sub_expires_at' => $planId > 0 && !empty($user['sub_started']) && isset($user['sub_expires_at']) && $user['sub_expires_at'] instanceof MongoDB\BSON\UTCDateTime
        ? $user['sub_expires_at']->toDateTime()->getTimestamp()
        : 0,
    'sub_remaining_seconds' => $subRemainingSeconds,
    'wallet_seconds' => $walletSeconds,
    'access_expires_at' => $accessExpiresAt,
    'trial_mode' => $trialMode,
    'trial_reset_at' => $trialState['reset_at'] ?? 0,
]);
