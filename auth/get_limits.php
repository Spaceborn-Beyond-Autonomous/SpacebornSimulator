<?php
require_once __DIR__ . '/session_guard.php';
require_once __DIR__ . '/db.php'; // ensure env is loaded

header('Content-Type: application/json');

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
        // Prompt user to select a plan inside the simulator
        echo json_encode([
            'requires_plan_selection' => true,
            'wallet_balance'          => $wallet
        ]);
        exit;
    }
    
    // Running on wallet balance using chosen tier features/pricing
    $plan = strtoupper($run_plan);
} else {
    // Running on active subscription
    $plan_catalog_names = [1 => 'BASIS', 2 => 'PRO', 3 => 'MAX'];
    $plan = $plan_catalog_names[$current_plan_id] ?? 'FREE';
}

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
    $base_minutes = 5;
    $ppm = 0.50;
}

if ($ppm <= 0) $ppm = 0.01;

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
        
        $remaining_seconds = $base_minutes * 60;
    } else {
        $now = time();
        $remaining_seconds = max(0, $sub_expires_at - $now);
    }
} else {
    // Wallet run has 0 base seconds (they pay per minute from wallet balance)
    $remaining_seconds = 0;
}

$max_seconds = $remaining_seconds + (($wallet / $ppm) * 60);

echo json_encode([
    'wallet_balance'      => $wallet,
    'plan'                => $plan,
    'base_minutes'        => $base_minutes,
    'ppm'                 => $ppm,
    'max_session_seconds' => $max_seconds,
    'sub_active'          => $has_active_subscription
]);
