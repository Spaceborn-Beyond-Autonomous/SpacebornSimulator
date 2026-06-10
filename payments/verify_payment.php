<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session_config.php';
sb_configure_session();
session_start();

if (!isset($_SESSION['email'])) {
    header('Location: ../billing.php?payment=missing');
    exit;
}

// CSRF validation — form-based POST
sb_verify_csrf_form();

require_once __DIR__ . '/razorpay.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../billing.php?payment=invalid');
    exit;
}

$orderId   = (string) ($_POST['razorpay_order_id']   ?? '');
$paymentId = (string) ($_POST['razorpay_payment_id'] ?? '');
$signature = (string) ($_POST['razorpay_signature']  ?? '');
$source    = (string) ($_POST['source']              ?? 'billing');

$redirect = '../billing.php';

if ($orderId === '' || $paymentId === '' || $signature === '') {
    header('Location: ' . $redirect . '?payment=missing');
    exit;
}

if (!sb_verify_razorpay_signature($orderId, $paymentId, $signature)) {
    header('Location: ' . $redirect . '?payment=failed');
    exit;
}

$pending = $_SESSION['pending_order'] ?? null;
if (
    !is_array($pending)
    || isset($pending['type'])
    || ($pending['order_id'] ?? '') !== $orderId
) {
    header('Location: ' . $redirect . '?payment=failed');
    exit;
}

$planId = (int) ($pending['plan_id'] ?? 0);
if ($planId < 1) {
    header('Location: ' . $redirect . '?payment=failed');
    exit;
}

try {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    require dirname(__DIR__) . '/auth/db.php';

    if (isset($db)) {
        $email = (string) $_SESSION['email'];

        require_once __DIR__ . '/razorpay.php';
        $catalog = sb_plan_catalog();
        $plan    = $catalog[$planId] ?? null;

        if (!$plan) {
            header('Location: ' . $redirect . '?payment=failed');
            exit;
        }

        $now          = time();
        $activated_at = new MongoDB\BSON\UTCDateTime((int) ($now * 1000));
        $expires_at   = new MongoDB\BSON\UTCDateTime((int) (($now + ($plan['minutes'] * 60)) * 1000));

        $db->users->updateOne(
            ['email' => $email],
            [
                '$set' => [
                    'sub_id'           => $planId,
                    'sub_started'      => true,
                    'sub_activated_at' => $activated_at,
                    'sub_expires_at'   => $expires_at,
                ],
            ]
        );

        $db->invoices->insertOne([
            'email'       => $email,
            'created_at'  => $activated_at,
            'description' => 'Plan Purchase — ' . $plan['name'],
            'amount'      => $plan['amount_usd'],
            'status'      => 'paid',
            'payment_id'  => $paymentId,
        ]);

        $_SESSION['sub_started']      = true;
        $_SESSION['sub_activated_at'] = $now;
        $_SESSION['sub_expires_at']   = $now + ($plan['minutes'] * 60);

        $subCol   = $db->subscriptions;
        $user_sub = $subCol->findOne(['id' => $planId]);

        $_SESSION['user_sub'] = [
            'id'               => (string) ($user_sub['id']               ?? ''),
            'plan_id'          => (int)    ($user_sub['id']               ?? 0),
            'plan_name'        => (string) ($user_sub['plan_name']        ?? ''),
            'ppm'              => (float)  ($user_sub['ppm']              ?? 0.0),
            '3ds_hours'        => (int)    ($user_sub['3ds_hours']        ?? 0),
            'drone_profile'    => (array)  ($user_sub['drone_profile']    ?? []),
            'flight_scenarios' => (array)  ($user_sub['flight_scenarios'] ?? []),
            'wpm'              => (float)  ($user_sub['wpm']              ?? 0.0),
            'PID_tuning'       => (bool)   ($user_sub['PID_tuning']       ?? false),
            'export'           => (bool)   ($user_sub['export']           ?? false),
            'MV_logs'          => (bool)   ($user_sub['MV_logs']          ?? false),
            'GLB_cust'         => (bool)   ($user_sub['GLB_cust']         ?? false),
            'JS'               => (bool)   ($user_sub['JS']               ?? false),
            'CS'               => (bool)   ($user_sub['CS']               ?? false),
            'TM_HUD'           => (bool)   ($user_sub['TM_HUD']           ?? false),
            'env'              => (array)  ($user_sub['env']              ?? []),
        ];
    }
} catch (Throwable $e) {
    error_log('Payment verification error: ' . $e->getMessage());
    header('Location: ' . $redirect . '?payment=error');
    exit;
}

unset($_SESSION['pending_order']);

header('Location: ' . $redirect . '?payment=success&payment_id=' . urlencode($paymentId));
exit;