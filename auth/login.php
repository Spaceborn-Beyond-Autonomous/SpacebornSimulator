<?php 
    require 'db.php';

    if($_SERVER['REQUEST_METHOD'] !== 'POST'){

        die('Invalid Request');
    }

    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($email === "" || $pass === "") {

    die("Email and password are required.");
}

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email address.");
}

    $users = $db->users;

    $user = $users->findOne(['email' => $email]);

if (!$user) {
    die("User not found.");
}

if (!password_verify($pass, $user['password'])) {
    die("Invalid password.");
}

if(empty($user['is_verified'])){
    die('Please verify your email');
}

session_start();

$_SESSION['email'] = $email;
//Write the if statement verifying the expiry
$sub = $db -> subscriptions;
$user_sub = $sub -> findOne(['id' => $user['sub_id']]);


$sessCol = $db -> sessions;

$result = $sessCol ->insertOne(['email' => $email , "is_running" => true]);

$sub_activated_at = null;
if (isset($user['sub_activated_at']) && $user['sub_activated_at'] instanceof MongoDB\BSON\UTCDateTime) {
    $sub_activated_at = $user['sub_activated_at']->toDateTime()->getTimestamp();
} else {
    $sub_activated_at = time();
    $db->users->updateOne(['email' => $email], ['$set' => ['sub_activated_at' => new MongoDB\BSON\UTCDateTime($sub_activated_at * 1000)]]);
}

$sub_expires_at = null;
if (isset($user['sub_expires_at']) && $user['sub_expires_at'] instanceof MongoDB\BSON\UTCDateTime) {
    $sub_expires_at = $user['sub_expires_at']->toDateTime()->getTimestamp();
} else {
    $plan_name = strtoupper($user_sub['plan_name'] ?? 'BASIS');
    $minutes = 60;
    if ($plan_name === 'PRO') $minutes = (float)($_ENV['PLAN_PRO_MINUTES'] ?? 1440);
    elseif ($plan_name === 'MAX') $minutes = (float)($_ENV['PLAN_MAX_MINUTES'] ?? 43200);
    else $minutes = (float)($_ENV['PLAN_BASIS_MINUTES'] ?? 60);
    
    $sub_expires_at = $sub_activated_at + ((int)$minutes * 60);
    $db->users->updateOne(['email' => $email], ['$set' => ['sub_expires_at' => new MongoDB\BSON\UTCDateTime($sub_expires_at * 1000)]]);
}

$_SESSION['id'] = (string)$result -> getInsertedId();
$_SESSION['name'] = $user['name'];
$_SESSION['wallet_balance'] = (float)($user['wallet_balance'] ?? 50.0);
$_SESSION['sub_activated_at'] = $sub_activated_at;
$_SESSION['sub_expires_at'] = $sub_expires_at;
$_SESSION['user_sub'] = [
    'id'               => (string) ($user_sub['id'] ?? ''),
    'plan_id'          => (int)    ($user_sub['id'] ?? 0),
    'plan_name'        => (string) ($user_sub['plan_name'] ?? ''),
    'ppm'              => (float)  ($user_sub['ppm'] ?? 0),
    '3ds_hours'        => (int)    ($user_sub['3ds_hours'] ?? 0),
    'drone_profile'    => (array)  ($user_sub['drone_profile'] ?? []),
    'flight_scenarios' => (array)  ($user_sub['flight_scenarios'] ?? []),
    'wpm'              => (float)  ($user_sub['wpm'] ?? 0),
    'PID_tuning'       => (bool)   ($user_sub['PID_tuning'] ?? false),
    'export'           => (bool)   ($user_sub['export'] ?? false),
    'MV_logs'          => (bool)   ($user_sub['MV_logs'] ?? false),
    'GLB_cust'         => (bool)   ($user_sub['GLB_cust'] ?? false),
    'JS'               => (bool)   ($user_sub['JS'] ?? false),
    'CS'               => (bool)   ($user_sub['CS'] ?? false),
    'TM_HUD'           => (bool)   ($user_sub['TM_HUD'] ?? false),
    'env'              => (array)  ($user_sub['env'] ?? []),
];

error_log('Attempting to redirect to dashboard');
header('Location: ../dashboard.php');
exit();

?>