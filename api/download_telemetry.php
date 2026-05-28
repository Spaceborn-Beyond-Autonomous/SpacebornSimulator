<?php
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';

$email = $_SESSION['email'] ?? '';
$id = $_GET['id'] ?? '';
$idx = isset($_GET['idx']) ? (int)$_GET['idx'] : 0;

$user_row = $db->users->findOne(['email' => $email]);
if (!$user_row || ((int)($user_row['sub_id'] ?? 0)) < 2) {
    header('HTTP/1.1 403 Forbidden');
    exit('PRO tier required to download telemetry.');
}

try {
    $oid = new MongoDB\BSON\ObjectId($id);
    $flight = $db->flights->findOne(['_id' => $oid, 'email' => $email]);
} catch (Exception $e) {
    $flight = null;
}

if (!$flight) {
    header('HTTP/1.1 404 Not Found');
    exit('Flight not found.');
}

$telemetry_url = null;
$telemetry_urls = $flight['telemetry_urls'] ?? [];
if (!empty($telemetry_urls) && isset($telemetry_urls[$idx])) {
    $telem = $telemetry_urls[$idx];
    $telemetry_url = $telem['url'] ?? $telem;
}
if (empty($telemetry_url)) {
    $telemetry_url = $flight['telemetry_url'] ?? null;
}

if (empty($telemetry_url)) {
    header('HTTP/1.1 404 Not Found');
    exit('Telemetry data not found.');
}

$filename = "telemetry_{$id}_{$idx}.json";

header('Content-Description: File Transfer');
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Stream the file
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $telemetry_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // echo directly
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
curl_close($ch);
exit;
