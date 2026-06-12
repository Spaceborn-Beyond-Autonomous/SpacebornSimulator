<?php
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../billing.php?msg=error"); exit;
}

// CSRF validation — must be before any business logic
sb_verify_csrf_form();

$target_plan_id = (int)($_POST['plan_id'] ?? 0);
if ($target_plan_id < 1 || $target_plan_id > 3) {
    header("Location: ../billing.php?msg=error"); exit;
}

$email = $_SESSION['email'] ?? '';
if (!$email) {
    header("Location: ../billing.php?msg=error"); exit;
}

$usersCol = $db->users;
$user = $usersCol->findOne(['email' => $email]);
if (!$user) {
    header("Location: ../billing.php?msg=error"); exit;
}

$current_plan_id = (int)($user['sub_id'] ?? 1);

// Ensure this is actually a downgrade
if ($target_plan_id >= $current_plan_id) {
    header("Location: ../billing.php?msg=error"); exit;
}

// Calculate the prorated refund
$now = time();
$sub_expires_at = isset($user['sub_expires_at']) && $user['sub_expires_at'] instanceof MongoDB\BSON\UTCDateTime
    ? $user['sub_expires_at']->toDateTime()->getTimestamp()
    : 0;

$remaining_sec = max(0, $sub_expires_at - $now);

$current_plan_duration_sec = 0;
$current_plan_price = 0;
$current_plan_name = 'BASIC';

if ($current_plan_id === 3) {
    $current_plan_duration_sec = (float)($_ENV['PLAN_MAX_MINUTES'] ?? 43200) * 60;
    $current_plan_price = (float)($_ENV['PLAN_MAX_PRICE'] ?? 20);
    $current_plan_name = 'MAX';
} elseif ($current_plan_id === 2) {
    $current_plan_duration_sec = (float)($_ENV['PLAN_PRO_MINUTES'] ?? 1440) * 60;
    $current_plan_price = (float)($_ENV['PLAN_PRO_PRICE'] ?? 5);
    $current_plan_name = 'PRO';
} elseif ($current_plan_id === 1) {
    $current_plan_duration_sec = (float)($_ENV['PLAN_BASIC_MINUTES'] ?? 60) * 60;
    $current_plan_price = (float)($_ENV['PLAN_BASIC_PRICE'] ?? 1);
    $current_plan_name = 'BASIC';
}

$refund_amount = 0.0;
if ($current_plan_duration_sec > 0) {
    $refund_amount = ($remaining_sec / $current_plan_duration_sec) * $current_plan_price;
}
if ($refund_amount < 0) $refund_amount = 0.0;
if ($refund_amount > $current_plan_price) $refund_amount = $current_plan_price;

// Round to 2 decimal places
$refund_amount = round($refund_amount * 0.5, 2);

// Target plan settings
$target_minutes = 60;
$target_plan_name = 'BASIC';
if ($target_plan_id === 3) {
    $target_minutes = (float)($_ENV['PLAN_MAX_MINUTES'] ?? 43200);
    $target_plan_name = 'MAX';
} elseif ($target_plan_id === 2) {
    $target_minutes = (float)($_ENV['PLAN_PRO_MINUTES'] ?? 1440);
    $target_plan_name = 'PRO';
} else {
    $target_minutes = (float)($_ENV['PLAN_BASIC_MINUTES'] ?? 60);
    $target_plan_name = 'BASIC';
}

$new_activated_at = new MongoDB\BSON\UTCDateTime((int)($now * 1000));
$new_expires_at = new MongoDB\BSON\UTCDateTime((int)(($now + ($target_minutes * 60)) * 1000));

// Update database user document
$new_wallet_balance = (float)($user['wallet_balance'] ?? 0.0) + $refund_amount;
$usersCol->updateOne(
    ['email' => $email],
    [
        '$set' => [
            'sub_id' => $target_plan_id,
            'wallet_balance' => $new_wallet_balance,
            'sub_started' => false,
            'sub_activated_at' => null,
            'sub_expires_at' => null
        ]
    ]
);

// Insert refund record in invoices
if ($refund_amount > 0) {
    $db->invoices->insertOne([
        'email' => $email,
        'created_at' => new MongoDB\BSON\UTCDateTime((int)($now * 1000)),
        'description' => 'Prorated Downgrade Refund (' . $current_plan_name . ' -> ' . $target_plan_name . ')',
        'amount' => -$refund_amount, // Negative represent money returned/refunded
        'status' => 'paid',
        'payment_id' => 'ref_' . bin2hex(random_bytes(6))
    ]);
}

// Get the new subscription details for the session
$subCol = $db->subscriptions;
$user_sub = $subCol->findOne(['id' => $target_plan_id]);

// Update PHP Session variables
$_SESSION['wallet_balance'] = $new_wallet_balance;
$_SESSION['sub_started'] = false;
$_SESSION['sub_activated_at'] = null;
$_SESSION['sub_expires_at'] = null;
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

// Redirect to billing page with success message
header("Location: ../billing.php?msg=downgrade_success");
exit;
?>
