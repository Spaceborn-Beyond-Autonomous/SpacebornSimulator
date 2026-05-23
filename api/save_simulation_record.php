<?php
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$telemetryUrl = $input['telemetry_url'] ?? '';
$duration = $input['duration'] ?? 0;
$sizeBytes = $input['size_bytes'] ?? 0;
$droneProfile = $input['drone_profile'] ?? 'unknown';

if (empty($telemetryUrl)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing telemetry URL']);
    exit;
}

try {
    $db = getDB();
    $userId = $_SESSION['user_id'];
    
    $simRecord = [
        'user_id' => new MongoDB\BSON\ObjectId($userId),
        'duration_seconds' => (int)$duration,
        'size_bytes' => (int)$sizeBytes,
        'drone_profile' => $droneProfile,
        'telemetry_url' => $telemetryUrl,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
    ];
    
    $result = $db->simulations->insertOne($simRecord);
    
    echo json_encode([
        'success' => true,
        'message' => 'Simulation saved successfully',
        'id' => (string)$result->getInsertedId()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
