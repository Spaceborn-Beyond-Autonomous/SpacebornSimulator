<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session_config.php';
sb_configure_session();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized. Please log in first.']);
    exit;
}

// CSRF validation — JSON endpoint uses header-based token
sb_verify_csrf_header();

require_once __DIR__ . '/razorpay.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Invalid method']);
    exit;
}

$raw    = file_get_contents('php://input');
$body   = json_decode($raw ?: '{}', true);
$planId = (int) ($body['plan_id'] ?? 0);
$source = (string) ($body['source'] ?? 'index');

$catalog = sb_plan_catalog();
if (!isset($catalog[$planId])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid plan selected']);
    exit;
}

$plan    = $catalog[$planId];
$receipt = 'sb_' . $planId . '_' . time() . '_' . bin2hex(random_bytes(4));

try {
    $order = sb_create_razorpay_order(
        (int) $plan['amount_inr'],
        $receipt,
        [
            'plan_id'   => (string) $planId,
            'plan_name' => (string) $plan['name'],
            'source'    => $source,
            'email'     => (string) ($_SESSION['email'] ?? ''),
        ]
    );

    $_SESSION['pending_order'] = [
        'plan_id'  => $planId,
        'order_id' => (string) ($order['id'] ?? ''),
        'source'   => $source,
        'receipt'  => $receipt,
    ];

    $keys = sb_razorpay_keys();

    echo json_encode([
        'ok'          => true,
        'key_id'      => $keys['key_id'],
        'order_id'    => (string) $order['id'],
        'amount'      => (int) $order['amount'],
        'currency'    => (string) $order['currency'],
        'plan_name'   => (string) $plan['name'],
        'description' => (string) $plan['description'],
        'prefill'     => [
            'name'  => (string) ($_SESSION['name']  ?? ''),
            'email' => (string) ($_SESSION['email'] ?? ''),
        ],
    ]);
} catch (Throwable $e) {
    $isConfigError = str_contains($e->getMessage(), 'Missing Razorpay credentials');
    http_response_code($isConfigError ? 400 : 500);
    echo json_encode([
        'ok'      => false,
        'message' => $e->getMessage(),
    ]);
}