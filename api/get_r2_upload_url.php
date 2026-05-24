<?php
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $s3Client = new S3Client([
        'region' => 'auto',
        'endpoint' => $_ENV['R2_ENDPOINT'],
        'use_path_style_endpoint' => true,
        'version' => 'latest',
        'credentials' => [
            'key' => $_ENV['R2_ACCESS_KEY_ID'],
            'secret' => $_ENV['R2_SECRET_ACCESS_KEY'],
        ]
    ]);

    $bucketName = $_ENV['R2_BUCKET_NAME'];
    $publicUrlBase = $_ENV['R2_PUBLIC_URL'];
    
    // Generate unique filename for the session
    $userId = $_SESSION['user_id'] ?? 'unknown';
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    $filename = "telemetry_{$userId}_{$timestamp}_{$random}.json";
    
    $cmd = $s3Client->getCommand('PutObject', [
        'Bucket' => $bucketName,
        'Key' => $filename,
        'ContentType' => 'application/json'
    ]);

    $request = $s3Client->createPresignedRequest($cmd, '+15 minutes');

    $presignedUrl = (string)$request->getUri();
    $publicUrl = rtrim($publicUrlBase, '/') . '/' . $filename;

    echo json_encode([
        'success' => true,
        'uploadUrl' => $presignedUrl,
        'publicUrl' => $publicUrl,
        'fileKey' => $filename
    ]);

} catch (AwsException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'S3 Configuration Error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server Error',
        'message' => $e->getMessage()
    ]);
}
