<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/auth/db.php';
use Aws\S3\S3Client;

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
    $result = $s3Client->listObjectsV2([
        'Bucket' => $bucketName
    ]);

    echo "Bucket: $bucketName\n";
    if (isset($result['Contents'])) {
        foreach ($result['Contents'] as $object) {
            echo " - " . $object['Key'] . " (" . $object['Size'] . " bytes)\n";
        }
    } else {
        echo "No objects found in bucket.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
