<?php
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../includes/simulator_launch.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No input provided.']);
    exit;
}

$email = $_SESSION['email'] ?? '';
if (!$email) {
    echo json_encode(['success' => false, 'error' => 'User not logged in.']);
    exit;
}

$flightsCol = $db->flights;
$usersCol = $db->users;
$now = time();

try {
    // 1. Insert flight session record
    $flightsCol->insertOne([
        'email' => $email,
        'name' => $input['name'] ?? 'Simulation Session',
        'drone' => $input['drone'] ?? 'Unknown Drone',
        'environment' => $input['environment'] ?? 'Unknown',
        'weather' => $input['weather'] ?? 'Clear',
        'mode' => $input['mode'] ?? 'Manual',
        'duration' => (int)($input['duration'] ?? 0),
        'status' => $input['status'] ?? 'completed',
        'telemetry_url' => $input['telemetry_url'] ?? null,
        'telemetry_urls' => $input['telemetry_urls'] ?? [],
        'created_at' => new MongoDB\BSON\UTCDateTime($now * 1000)
    ]);
    
    // 2. Perform wallet billing deduction
    $user = $usersCol->findOne(['email' => $email]);
    if ($user) {
        $wallet = (float)($user['wallet_balance'] ?? 0.0);
        $duration = (float)($input['duration'] ?? 0); // flight duration in seconds
        $plan = strtoupper($input['plan'] ?? 'FREE');
        $ppm = (float)($input['ppm'] ?? 0.10);
        $planId = (int) ($user['sub_id'] ?? 0);
        $sub_remaining_seconds = 0.0;

        if ($planId > 0) {
            $paidState = sb_paid_plan_state($user, false);
            $sub_remaining_seconds = (float) ($paidState['remaining_seconds'] ?? 0);
            if ($planId === 1) {
                $ppm = (float) ($_ENV['PLAN_BASIC_PPM'] ?? $ppm);
            } elseif ($planId === 2) {
                $ppm = (float) ($_ENV['PLAN_PRO_PPM'] ?? $ppm);
            } elseif ($planId === 3) {
                $ppm = (float) ($_ENV['PLAN_MAX_PPM'] ?? $ppm);
            }
        }
        
        $charge = 0.0;
        if ($sub_remaining_seconds > 0) {
            if ($duration > $sub_remaining_seconds) {
                $excess_seconds = $duration - $sub_remaining_seconds;
                $charge = ($excess_seconds / 60.0) * $ppm;
            }
        } else {
            // Charging for full flight time
            $charge = ($duration / 60.0) * $ppm;
        }
        
        if ($charge > 0) {
            $new_balance = max(0.0, $wallet - $charge);
            $usersCol->updateOne(
                ['email' => $email],
                ['$set' => ['wallet_balance' => $new_balance]]
            );
            $_SESSION['wallet_balance'] = $new_balance;
            
            // Insert invoice for flight session charge
            $db->invoices->insertOne([
                'email' => $email,
                'created_at' => new MongoDB\BSON\UTCDateTime((int)($now * 1000)),
                'description' => 'Simulation Session Charge (' . $plan . ')',
                'amount' => $charge,
                'status' => 'paid',
                'payment_id' => 'chg_' . bin2hex(random_bytes(6))
            ]);
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
