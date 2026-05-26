<?php
require_once __DIR__ . '/session_guard.php';
require_once __DIR__ . '/db.php'; // ensure env is loaded
require_once __DIR__ . '/../includes/simulator_launch.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$email = $_SESSION['email'] ?? '';
$user = $db->users->findOne(['email' => $email]);
if (!$user) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

$wallet = (float)($user['wallet_balance'] ?? 0.0);
$current_plan_id = (int)($user['sub_id'] ?? 0);

$sub_started = isset($user['sub_started']) ? (bool)$user['sub_started'] : false;
$sub_expires_at = 0;
if (isset($user['sub_expires_at']) && $user['sub_expires_at'] instanceof MongoDB\BSON\UTCDateTime) {
    $sub_expires_at = $user['sub_expires_at']->toDateTime()->getTimestamp();
}

// 1. Expiration check & automatic revert to FREE
if ($current_plan_id > 0 && $sub_started && $sub_expires_at > 0 && time() > $sub_expires_at) {
    $db->users->updateOne(
        ['email' => $email],
        [
            '$set' => [
                'sub_id'           => 0,
                'sub_started'      => false,
                'sub_activated_at' => null,
                'sub_expires_at'   => null
            ]
        ]
    );
    $current_plan_id = 0;
    $sub_started = false;
    $sub_expires_at = 0;

    // Reset session user_sub to FREE
    $_SESSION['sub_activated_at'] = null;
    $_SESSION['sub_expires_at']   = null;
    $_SESSION['user_sub'] = [
        'id'               => '0',
        'plan_id'          => 0,
        'plan_name'        => 'FREE',
        'ppm'              => 0.50,
        '3ds_hours'        => 0,
        'drone_profile'    => ['Research F450'],
        'flight_scenarios' => ['Normal Flight'],
        'wpm'              => 0,
        'PID_tuning'       => false,
        'export'           => false,
        'MV_logs'          => false,
        'GLB_cust'         => false,
        'JS'               => false,
        'CS'               => false,
        'TM_HUD'           => false,
        'env'              => ['Daytime'],
    ];
}

// 2. Determine active plan details
$has_active_subscription = ($current_plan_id > 0);
$run_plan = $_GET['run_plan'] ?? '';

if (!$has_active_subscription) {
    if (empty($run_plan)) {
        if ($wallet > 0) {
            // Prompt wallet users to select a plan inside the simulator
            echo json_encode([
                'requires_plan_selection' => true,
                'wallet_balance'          => $wallet
            ]);
            exit;
        }

        // Default to the free basic trial when no wallet balance is available.
        $run_plan = 'FREE';
    }
    
    // Running on wallet balance using chosen tier features/pricing
    $plan = strtoupper($run_plan);
} else {
    // Running on active subscription
    $plan_catalog_names = [1 => 'BASIC', 2 => 'PRO', 3 => 'MAX'];
    $plan = $plan_catalog_names[$current_plan_id] ?? 'FREE';
}

$base_minutes = 0;
$ppm = 0.10; // default safe ppm
$trial_mode = false;

if ($plan === 'BASIC') {
    $base_minutes = (float)($_ENV['PLAN_BASIC_MINUTES'] ?? 60);
    $ppm = (float)($_ENV['PLAN_BASIC_PPM'] ?? 0.10);
} elseif ($plan === 'PRO') {
    $base_minutes = (float)($_ENV['PLAN_PRO_MINUTES'] ?? 1440);
    $ppm = (float)($_ENV['PLAN_PRO_PPM'] ?? 0.05);
} elseif ($plan === 'MAX') {
    $base_minutes = (float)($_ENV['PLAN_MAX_MINUTES'] ?? 43200);
    $ppm = (float)($_ENV['PLAN_MAX_PPM'] ?? 0.01);
} else {
    $base_minutes = 5;
    $ppm = 0.50;
}

if ($ppm <= 0 && !$trial_mode) $ppm = 0.01;

$remaining_seconds = 0;

if ($has_active_subscription) {
    if (!$sub_started) {
        // Start the paid subscription on first flight!
        $now = time();
        $activated_at = new MongoDB\BSON\UTCDateTime((int)($now * 1000));
        $expires_at   = new MongoDB\BSON\UTCDateTime((int)(($now + ($base_minutes * 60)) * 1000));
        
        $db->users->updateOne(
            ['email' => $email],
            [
                '$set' => [
                    'sub_started'      => true,
                    'sub_activated_at' => $activated_at,
                    'sub_expires_at'   => $expires_at
                ]
            ]
        );
        
        // Update session
        $_SESSION['sub_started']      = true;
        $_SESSION['sub_activated_at'] = $now;
        $_SESSION['sub_expires_at']   = $now + ($base_minutes * 60);
        $sub_expires_at = $_SESSION['sub_expires_at'];
        $sub_started = true;

        $remaining_seconds = $base_minutes * 60;
    } else {
        $now = time();
        $remaining_seconds = max(0, $sub_expires_at - $now);
    }
} else {
    $trial_state = sb_free_trial_state($user, false);

    if ($wallet > 0 && $ppm > 0) {
        // Wallet run has no free base seconds (they pay per minute from wallet balance)
        $remaining_seconds = (int) (($wallet / $ppm) * 60);
        $base_minutes = 0;
    } elseif ($trial_state['available']) {
        $remaining_seconds = (int) $trial_state['remaining_seconds'];
        $base_minutes = 10;
        $ppm = 0.0;
        $trial_mode = true;
    } else {
        $remaining_seconds = 0;
        $base_minutes = 10;
        $ppm = 0.0;
        $trial_mode = true;
    }
}

if ($remaining_seconds > 0) {
    $wallet_seconds = 0;
    $max_seconds = $remaining_seconds;
} else {
    $wallet_seconds = ($wallet > 0 && $ppm > 0) ? (int) (($wallet / $ppm) * 60) : 0;
    $max_seconds = $wallet_seconds;
}
$now = time();
$access_expires_at = $max_seconds > 0 ? $now + $max_seconds : 0;

echo json_encode([
    'wallet_balance'      => $wallet,
    'plan'                => $plan,
    'base_minutes'        => $base_minutes,
    'ppm'                 => $ppm,
    'max_session_seconds' => $max_seconds,
    'sub_active'          => $has_active_subscription,
    'sub_expires_at'      => $has_active_subscription && $sub_started ? $sub_expires_at : 0,
    'sub_remaining_seconds' => $remaining_seconds,
    'wallet_seconds'      => $wallet_seconds,
    'access_expires_at'   => $access_expires_at,
]);
