<?php

declare(strict_types=1);

function sb_load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (class_exists('Dotenv\\Dotenv')) {
        $rootDir = dirname(__DIR__);
        $envDir = $rootDir . '/env';
        if (is_dir($envDir) && file_exists($envDir . '/.env')) {
            $dotenv = Dotenv\Dotenv::createImmutable($envDir);
            $dotenv->safeLoad();
        } else {
            $dotenv = Dotenv\Dotenv::createImmutable($rootDir);
            $dotenv->safeLoad();
        }
    } else {
        $rootDir = dirname(__DIR__);
        $candidateFiles = [
            $rootDir . '/env/.env',
            $rootDir . '/.env',
        ];

        $envFile = null;
        foreach ($candidateFiles as $file) {
            if (file_exists($file)) {
                $envFile = $file;
                break;
            }
        }

        if ($envFile !== null) {
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
                    continue;
                }

                [$name, $value] = array_map('trim', explode('=', $line, 2));
                if ($name === '') {
                    continue;
                }

                $value = trim($value, " \t\n\r\0\x0B\"'");
                $_ENV[$name] = $value;
                putenv($name . '=' . $value);
            }
        }
    }

    $loaded = true;
}

function sb_razorpay_keys(): array
{
    sb_load_env();

    $keyId = $_ENV['RAZORPAY_KEY_ID'] ?? getenv('RAZORPAY_KEY_ID') ?: '';
    $keySecret = $_ENV['RAZORPAY_KEY_SECRET'] ?? getenv('RAZORPAY_KEY_SECRET') ?: '';

    return [
        'key_id' => trim((string) $keyId),
        'key_secret' => trim((string) $keySecret),
    ];
}

function sb_env_bool(string $name, bool $default = false): bool
{
    sb_load_env();

    $raw = $_ENV[$name] ?? getenv($name);
    if ($raw === false || $raw === null) {
        return $default;
    }

    $value = strtolower(trim((string) $raw));
    if ($value === '') {
        return $default;
    }

    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function sb_get_usd_to_inr_rate(): float
{
    $cacheFile = sys_get_temp_dir() . '/sb_usd_inr_rate.json';
    $cacheTime = 3600 * 12; // Cache for 12 hours
    $defaultRate = 96.0;

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if (isset($data['rate']) && is_numeric($data['rate'])) {
            return (float) $data['rate'];
        }
    }

    try {
        // Fetch dynamically from open ExchangeRate-API (no API key required)
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $response = @file_get_contents('https://open.er-api.com/v6/latest/USD', false, $ctx);
        if ($response) {
            $json = json_decode($response, true);
            if (isset($json['rates']['INR']) && is_numeric($json['rates']['INR'])) {
                $rate = (float) $json['rates']['INR'];
                @file_put_contents($cacheFile, json_encode(['rate' => $rate]));
                return $rate;
            }
        }
    } catch (Throwable $e) {
        // Ignore and fallback
    }

    return $defaultRate;
}

function sb_plan_catalog(): array
{
    $usd_to_inr = sb_get_usd_to_inr_rate();
    $to_paise = 100;
    
    return [
        1 => ['name' => 'BASIS', 'amount_usd' => 1,  'amount_inr' => (int)(1 * $usd_to_inr * $to_paise),  'description' => 'BASIS 1 Hour access'],
        2 => ['name' => 'PRO',   'amount_usd' => 5,  'amount_inr' => (int)(5 * $usd_to_inr * $to_paise),  'description' => 'PRO 1 Day access'],
        3 => ['name' => 'MAX',   'amount_usd' => 20, 'amount_inr' => (int)(20 * $usd_to_inr * $to_paise), 'description' => 'MAX 1 Month access'],
    ];
}

function sb_create_razorpay_order(int $amountInPaise, string $receipt, array $notes = []): array
{
    $keys = sb_razorpay_keys();
    $verifySsl = sb_env_bool('RAZORPAY_SSL_VERIFY', true);
    if ($keys['key_id'] === '' || $keys['key_secret'] === '') {
        throw new RuntimeException('Missing Razorpay credentials.');
    }

    $payload = [
        'amount' => $amountInPaise,
        'currency' => 'INR',
        'receipt' => $receipt,
        'payment_capture' => 1,
        'notes' => $notes,
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $keys['key_id'] . ':' . $keys['key_secret'],
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr !== '') {
        throw new RuntimeException('Razorpay request failed: ' . $curlErr);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || $httpCode < 200 || $httpCode >= 300) {
        $errMsg = is_array($decoded) && isset($decoded['error']['description'])
            ? (string) $decoded['error']['description']
            : 'Unable to create order.';
        throw new RuntimeException($errMsg);
    }

    return $decoded;
}

function sb_verify_razorpay_signature(string $orderId, string $paymentId, string $signature): bool
{
    $keys = sb_razorpay_keys();
    if ($keys['key_secret'] === '') {
        return false;
    }

    $payload = $orderId . '|' . $paymentId;
    $generated = hash_hmac('sha256', $payload, $keys['key_secret']);

    return hash_equals($generated, $signature);
}
