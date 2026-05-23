<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized. Please log in first.']);
    exit;
}

require_once __DIR__ . '/razorpay.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Invalid method']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
$amountUsd = (float) ($body['amount_usd'] ?? 0);
$source = (string) ($body['source'] ?? 'billing');

if ($amountUsd < 1 || $amountUsd > 500) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Top-up amount must be between $1 and $500.']);
    exit;
}

sb_load_env();
$usdToInr = (float) ($_ENV['USD_TO_INR'] ?? getenv('USD_TO_INR') ?: 83);
if ($usdToInr <= 0) {
    $usdToInr = 83;
}

$amountInPaise = (int) round($amountUsd * $usdToInr * 100);
if ($amountInPaise < 100) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Amount too small for payment processing.']);
    exit;
}

$receipt = 'sb_topup_' . time() . '_' . bin2hex(random_bytes(4));

try {
    $order = sb_create_razorpay_order(
        $amountInPaise,
        $receipt,
        [
            'type' => 'wallet_topup',
            'amount_usd' => (string) $amountUsd,
            'source' => $source,
            'email' => (string) ($_SESSION['email'] ?? ''),
        ]
    );

    $_SESSION['pending_order'] = [
        'type' => 'wallet_topup',
        'amount_usd' => $amountUsd,
        'order_id' => (string) ($order['id'] ?? ''),
        'source' => $source,
        'receipt' => $receipt,
    ];

    $keys = sb_razorpay_keys();

    echo json_encode([
        'ok' => true,
        'key_id' => $keys['key_id'],
        'order_id' => (string) $order['id'],
        'amount' => (int) $order['amount'],
        'currency' => (string) $order['currency'],
        'description' => 'Wallet top-up ($' . number_format($amountUsd, 2) . ')',
        'prefill' => [
            'name' => (string) ($_SESSION['name'] ?? ''),
            'email' => (string) ($_SESSION['email'] ?? ''),
        ],
    ]);
} catch (Throwable $e) {
    $isConfigError = str_contains($e->getMessage(), 'Missing Razorpay credentials');
    http_response_code($isConfigError ? 400 : 500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ]);
}
