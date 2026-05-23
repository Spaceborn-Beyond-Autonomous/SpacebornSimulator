<?php
require_once __DIR__ . '/auth/session_guard.php';
require_once __DIR__ . '/auth/db.php';

// Validate input parameters
$status     = $_GET['status'] ?? '';
$plan_id    = (int)($_GET['plan_id'] ?? 0);
$payment_id = $_GET['payment_id'] ?? '';

if ($status !== 'success' || $plan_id < 2 || $plan_id > 3 || empty($payment_id)) {
    header("Location: billing.php?msg=payment_failed");
    exit;
}

$email = $_SESSION['email'] ?? '';
if (!$email) {
    die("User session not found.");
}

$usersCol = $db->users;
$user = $usersCol->findOne(['email' => $email]);
if (!$user) {
    die("User not found.");
}

$current_plan_id = (int)($user['sub_id'] ?? 1);
if ($plan_id <= $current_plan_id) {
    // If the user somehow tries to upgrade to their own plan or lower via callback manipulation
    header("Location: billing.php");
    exit;
}

// Retrieve target plan limits and pricing
$target_price   = 0.0;
$target_minutes = 60.0;
$target_name    = 'BASIS';

if ($plan_id === 3) {
    $target_price   = (float)($_ENV['PLAN_MAX_PRICE'] ?? 20);
    $target_minutes = (float)($_ENV['PLAN_MAX_MINUTES'] ?? 43200);
    $target_name    = 'MAX';
} elseif ($plan_id === 2) {
    $target_price   = (float)($_ENV['PLAN_PRO_PRICE'] ?? 5);
    $target_minutes = (float)($_ENV['PLAN_PRO_MINUTES'] ?? 1440);
    $target_name    = 'PRO';
}

$now = time();
$activated_at = new MongoDB\BSON\UTCDateTime((int)($now * 1000));
$expires_at   = new MongoDB\BSON\UTCDateTime((int)(($now + ($target_minutes * 60)) * 1000));

// Update database for subscription
$usersCol->updateOne(
    ['email' => $email],
    [
        '$set' => [
            'sub_id'           => $plan_id,
            'sub_started'      => false,
            'sub_activated_at' => null,
            'sub_expires_at'   => null
        ]
    ]
);

// Insert invoice record for Razorpay payment
$db->invoices->insertOne([
    'email'       => $email,
    'created_at'  => new MongoDB\BSON\UTCDateTime((int)($now * 1000)),
    'description' => 'Plan Upgrade to ' . $target_name . ' (Razorpay)',
    'amount'      => $target_price,
    'status'      => 'paid',
    'payment_id'  => $payment_id
]);

// Reload subscription from DB to set user session
$subCol = $db->subscriptions;
$user_sub = $subCol->findOne(['id' => $plan_id]);

if (!$user_sub) {
    die("Subscription plan definition not found in database. Please seed subscriptions.");
}

$_SESSION['sub_started']      = false;
$_SESSION['sub_activated_at'] = null;
$_SESSION['sub_expires_at']   = null;
$_SESSION['user_sub'] = [
    'id'               => (string) ($user_sub['id'] ?? ''),
    'plan_id'          => (int)    ($user_sub['id'] ?? 0),
    'plan_name'        => (string) ($user_sub['plan_name'] ?? ''),
    'ppm'              => (float)  ($user_sub['ppm'] ?? 0.0),
    '3ds_hours'        => (int)    ($user_sub['3ds_hours'] ?? 0),
    'drone_profile'    => (array)  ($user_sub['drone_profile'] ?? []),
    'flight_scenarios' => (array)  ($user_sub['flight_scenarios'] ?? []),
    'wpm'              => (float)  ($user_sub['wpm'] ?? 0.0),
    'PID_tuning'       => (bool)   ($user_sub['PID_tuning'] ?? false),
    'export'           => (bool)   ($user_sub['export'] ?? false),
    'MV_logs'          => (bool)   ($user_sub['MV_logs'] ?? false),
    'GLB_cust'         => (bool)   ($user_sub['GLB_cust'] ?? false),
    'JS'               => (bool)   ($user_sub['JS'] ?? false),
    'CS'               => (bool)   ($user_sub['CS'] ?? false),
    'TM_HUD'           => (bool)   ($user_sub['TM_HUD'] ?? false),
    'env'              => (array)  ($user_sub['env'] ?? []),
];

header("Location: billing.php?msg=upgrade_success");
exit;
?>
