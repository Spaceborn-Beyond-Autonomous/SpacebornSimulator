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

$sub_started = isset($user['sub_started']) ? (bool)$user['sub_started'] : false;
$sub_activated_at = null;
$sub_expires_at = null;

if ($sub_started) {
    if (isset($user['sub_activated_at']) && $user['sub_activated_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $sub_activated_at = $user['sub_activated_at']->toDateTime()->getTimestamp();
    }
    if (isset($user['sub_expires_at']) && $user['sub_expires_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $sub_expires_at = $user['sub_expires_at']->toDateTime()->getTimestamp();
    }
    
    // Auto-revert if expired
    if ($sub_expires_at && time() > $sub_expires_at) {
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
        $sub_started = false;
        $sub_activated_at = null;
        $sub_expires_at = null;
        
        // Fetch FREE subscription profile
        $user_sub = $sub->findOne(['id' => 0]);
        if (!$user_sub) {
            $user_sub = [
                'id' => 0,
                'plan_name' => 'FREE',
                'ppm' => 0.50,
                'drone_profile' => ['Research F450'],
                'flight_scenarios' => ['Normal Flight'],
                'env' => ['Daytime']
            ];
        }
    }
}

$_SESSION['id'] = (string)$result -> getInsertedId();
$_SESSION['name'] = $user['name'];
$_SESSION['wallet_balance'] = (float)($user['wallet_balance'] ?? 50.0);
$_SESSION['sub_started'] = $sub_started;
$_SESSION['sub_activated_at'] = $sub_activated_at;
$_SESSION['sub_expires_at'] = $sub_expires_at;
$_SESSION['user_sub'] = [
    'id'               => (string) ($user_sub['id'] ?? '1'),
    'plan_id'          => (int)    ($user_sub['id'] ?? 1),
    'plan_name'        => (string) ($user_sub['plan_name'] ?? 'BASIS'),
    'ppm'              => (float)  ($user_sub['ppm'] ?? 0.10),
    '3ds_hours'        => (int)    ($user_sub['3ds_hours'] ?? 1),
    'drone_profile'    => (array)  ($user_sub['drone_profile'] ?? ['Research F450']),
    'flight_scenarios' => (array)  ($user_sub['flight_scenarios'] ?? ['Normal Flight']),
    'wpm'              => (float)  ($user_sub['wpm'] ?? 0),
    'PID_tuning'       => (bool)   ($user_sub['PID_tuning'] ?? false),
    'export'           => (bool)   ($user_sub['export'] ?? false),
    'MV_logs'          => (bool)   ($user_sub['MV_logs'] ?? false),
    'GLB_cust'         => (bool)   ($user_sub['GLB_cust'] ?? false),
    'JS'               => (bool)   ($user_sub['JS'] ?? false),
    'CS'               => (bool)   ($user_sub['CS'] ?? false),
    'TM_HUD'           => (bool)   ($user_sub['TM_HUD'] ?? false),
    'env'              => (array)  ($user_sub['env'] ?? ['Daytime']),
];

error_log('Attempting to redirect to dashboard');
header('Location: ../dashboard.php');
exit();

?>