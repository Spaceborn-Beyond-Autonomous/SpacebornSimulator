<?php

require __DIR__ . '/../vendor/autoload.php';
require 'db.php';

require_once __DIR__ . '/session_config.php';
sb_configure_session();
session_start();

$client = new Google\Client();

$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? '');
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

$scheme = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$appRoot = dirname($scriptDir);
$baseUrl = !empty($_ENV['APP_URL']) ? rtrim($_ENV['APP_URL'], '/') : rtrim($scheme . '://' . $host . $appRoot, '/');

$client->setRedirectUri($baseUrl . '/auth/callback.php');

if (isset($_GET['code'])) {

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (!isset($token['error'])) {

        $client->setAccessToken($token['access_token']);

        // Use namespaced Google Service if available, otherwise fall back to direct HTTP userinfo call
        if (class_exists('Google\\Service\\Oauth2')) {
            $google_service = new Google\Service\Oauth2($client);
            $data = $google_service->userinfo->get();
        } else {
            $accessToken = $token['access_token'];
            $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200) {
                $data = json_decode($resp, true);
            } else {
                echo "Failed fetching userinfo (HTTP " . intval($httpCode) . ")";
                exit();
            }
        }

        $userEmail = $data['email'];
        $user = $db->users->findOne(['email' => $userEmail]);

        if (!$user) {
            $insertResult = $db->users->insertOne([
                'name' => $data['name'],
                'email' => $userEmail,
                'password' => '', 
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'org_id' => '',
                'auth_provid' => 1, 
                'sub_id' => 0,
                'sub_started' => false,
                'sub_activated_at' => null,
                'sub_expires_at' => null,
                'wallet_balance' => 0.0,
                'free_minutes_used' => 0,
                'free_trial_started_at' => null,
                'free_minutes_reset_at' => null,
                'is_verified' => true,
                'verification_token' => ''
            ]);
            $user = $db->users->findOne(['_id' => $insertResult->getInsertedId()]);
        }

        $_SESSION['email'] = $user['email'];
        
        $sub = $db->subscriptions;
        $user_sub = $sub->findOne(['id' => $user['sub_id']]);

        $sessCol = $db->sessions;
        $result = $sessCol->insertOne(['email' => $user['email'] , "is_running" => true]);

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
            
            if ($sub_expires_at && time() > $sub_expires_at) {
                $db->users->updateOne(
                    ['email' => $user['email']],
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

        $_SESSION['id'] = (string)$result->getInsertedId();
        $_SESSION['name'] = $user['name'];
        $_SESSION['wallet_balance'] = (float)($user['wallet_balance'] ?? 0.0);
        $_SESSION['sub_started'] = $sub_started;
        $_SESSION['sub_activated_at'] = $sub_activated_at;
        $_SESSION['sub_expires_at'] = $sub_expires_at;
        $_SESSION['free_minutes_used'] = (int)($user['free_minutes_used'] ?? 0);
        $_SESSION['free_trial_started_at'] = isset($user['free_trial_started_at']) && $user['free_trial_started_at'] instanceof MongoDB\BSON\UTCDateTime
            ? $user['free_trial_started_at']->toDateTime()->getTimestamp()
            : null;
        $_SESSION['free_minutes_reset_at'] = isset($user['free_minutes_reset_at']) && $user['free_minutes_reset_at'] instanceof MongoDB\BSON\UTCDateTime
            ? $user['free_minutes_reset_at']->toDateTime()->getTimestamp()
            : null;
        $_SESSION['user_sub'] = [
            'id'               => (string) ($user_sub['id'] ?? '0'),
            'plan_id'          => (int)    ($user_sub['id'] ?? 0),
            'plan_name'        => (string) ($user_sub['plan_name'] ?? 'FREE'),
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
        
        $_SESSION['picture'] = $data['picture'] ?? '';
        $_SESSION['logged_in'] = true;

        header("Location: ../dashboard.php");
        exit();

    } else {

        echo "Google Login Failed: " . htmlspecialchars($token['error']);
    }
} else {
    echo "Google callback did not receive a code parameter.";
}
?>
