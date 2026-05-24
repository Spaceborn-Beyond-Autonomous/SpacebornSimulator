<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['email'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/razorpay.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../billing.php?payment=invalid');
    exit;
}

$orderId = (string) ($_POST['razorpay_order_id'] ?? '');
$paymentId = (string) ($_POST['razorpay_payment_id'] ?? '');
$signature = (string) ($_POST['razorpay_signature'] ?? '');
$planId = (int) ($_POST['plan_id'] ?? 0);
$source = (string) ($_POST['source'] ?? 'index');

$redirect = $source === 'billing' ? '../billing.php' : '../index.php';

if ($orderId === '' || $paymentId === '' || $signature === '' || $planId <= 0) {
    header('Location: ' . $redirect . '?payment=missing');
    exit;
}

if (!sb_verify_razorpay_signature($orderId, $paymentId, $signature)) {
    header('Location: ' . $redirect . '?payment=failed');
    exit;
}

try {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (isset($_SESSION['email']) && file_exists(dirname(__DIR__) . '/auth/db.php')) {
        require dirname(__DIR__) . '/auth/db.php';

        if (isset($db)) {
            $email = (string) $_SESSION['email'];
            $db->users->updateOne(
                ['email' => $email],
                ['$set' => [
                    'sub_id' => $planId,
                    'sub_started' => false,
                    'sub_activated_at' => null,
                    'sub_expires_at' => null,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            // Sync session variables
            $_SESSION['sub_started'] = false;
            $_SESSION['sub_activated_at'] = null;
            $_SESSION['sub_expires_at'] = null;

            $userSub = $db->subscriptions->findOne(['id' => $planId]);
            if ($userSub) {
                $_SESSION['user_sub'] = [
                    'id'               => (string) ($userSub['id'] ?? ''),
                    'plan_id'          => (int)    ($userSub['id'] ?? 0),
                    'plan_name'        => (string) ($userSub['plan_name'] ?? ''),
                    'ppm'              => (float)  ($userSub['ppm'] ?? 0),
                    '3ds_hours'        => (int)    ($userSub['3ds_hours'] ?? 0),
                    'drone_profile'    => (array)  ($userSub['drone_profile'] ?? []),
                    'flight_scenarios' => (array)  ($userSub['flight_scenarios'] ?? []),
                    'wpm'              => (float)  ($userSub['wpm'] ?? 0),
                    'PID_tuning'       => (bool)   ($userSub['PID_tuning'] ?? false),
                    'export'           => (bool)   ($userSub['export'] ?? false),
                    'MV_logs'          => (bool)   ($userSub['MV_logs'] ?? false),
                    'GLB_cust'         => (bool)   ($userSub['GLB_cust'] ?? false),
                    'JS'               => (bool)   ($userSub['JS'] ?? false),
                    'CS'               => (bool)   ($userSub['CS'] ?? false),
                    'TM_HUD'           => (bool)   ($userSub['TM_HUD'] ?? false),
                    'env'              => (array)  ($userSub['env'] ?? []),
                    'razorpay_last_payment_id' => $paymentId,
                ];
            }

            if (isset($db->payments)) {
                $db->payments->insertOne([
                    'email' => $email,
                    'plan_id' => $planId,
                    'provider' => 'razorpay',
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'status' => 'paid',
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                ]);
            }

            // Sync with invoices collection
            $catalog = sb_plan_catalog();
            $catalog_plan = $catalog[$planId] ?? null;
            $target_name = $catalog_plan ? $catalog_plan['name'] : 'BASIC';
            
            $target_price = 1.0;
            if ($planId === 3) {
                $target_price = (float)($_ENV['PLAN_MAX_PRICE'] ?? 20);
            } elseif ($planId === 2) {
                $target_price = (float)($_ENV['PLAN_PRO_PRICE'] ?? 5);
            } else {
                $target_price = (float)($_ENV['PLAN_BASIC_PRICE'] ?? 1);
            }
            
            $db->invoices->insertOne([
                'email'       => $email,
                'created_at'  => new MongoDB\BSON\UTCDateTime(),
                'description' => 'Plan Upgrade to ' . $target_name . ' (Razorpay)',
                'amount'      => $target_price,
                'status'      => 'paid',
                'payment_id'  => $paymentId
            ]);
        }
    }
} catch (Throwable $e) {
    header('Location: ' . $redirect . '?payment=error');
    exit;
}

header('Location: ' . $redirect . '?payment=success&payment_id=' . urlencode($paymentId));
exit;
