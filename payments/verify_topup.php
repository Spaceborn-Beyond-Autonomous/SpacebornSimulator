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
    || ($pending['type']     ?? '') !== 'wallet_topup'
    || ($pending['order_id'] ?? '') !== $orderId
) {
    header('Location: ' . $redirect . '?payment=failed');
    exit;
}

$amountUsd = (float) ($pending['amount_usd'] ?? 0);
if ($amountUsd <= 0) {
    header('Location: ' . $redirect . '?payment=failed');
    exit;
}

try {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (file_exists(dirname(__DIR__) . '/auth/db.php')) {
        require dirname(__DIR__) . '/auth/db.php';

        if (isset($db)) {
            $email = (string) $_SESSION['email'];

            $db->users->updateOne(
                ['email' => $email],
                ['$inc' => ['wallet_balance' => $amountUsd]]
            );

            $user       = $db->users->findOne(['email' => $email]);
            $newBalance = (float) ($user['wallet_balance'] ?? 0);
            $_SESSION['wallet_balance'] = $newBalance;

            if (isset($db->payments)) {
                $db->payments->insertOne([
                    'email'      => $email,
                    'type'       => 'wallet_topup',
                    'provider'   => 'razorpay',
                    'order_id'   => $orderId,
                    'payment_id' => $paymentId,
                    'amount_usd' => $amountUsd,
                    'status'     => 'paid',
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                ]);
            }

            $db->invoices->insertOne([
                'email'       => $email,
                'created_at'  => new MongoDB\BSON\UTCDateTime(),
                'description' => 'Wallet Top-up (Razorpay)',
                'amount'      => $amountUsd,
                'status'      => 'paid',
                'payment_id'  => $paymentId,
            ]);
        }
    }
} catch (Throwable $e) {
    header('Location: ' . $redirect . '?payment=error');
    exit;
}

unset($_SESSION['pending_order']);

header('Location: ' . $redirect . '?payment=success&payment_id=' . urlencode($paymentId) . '&msg=topup_success');
exit;