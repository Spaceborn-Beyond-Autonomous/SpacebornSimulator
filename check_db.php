<?php
require_once __DIR__ . '/auth/db.php';

$flight = $db->flights->findOne([], ['sort' => ['created_at' => -1]]);
if ($flight) {
    echo "Latest Flight:\n";
    echo "ID: " . $flight['_id'] . "\n";
    echo "Name: " . $flight['name'] . "\n";
    echo "Telemetry URL: " . ($flight['telemetry_url'] ?? 'null') . "\n";
    echo "Telemetry URLs count: ";
    $urls = $flight['telemetry_urls'] ?? [];
    if (is_object($urls) && method_exists($urls, 'count')) {
        echo $urls->count();
    } elseif (is_array($urls)) {
        echo count($urls);
    } else {
        echo "unknown type";
    }
    echo "\n";
    
    // Print contents
    if ($urls) {
        foreach($urls as $u) {
            echo " - Time: " . ($u['time'] ?? 'null') . ", URL: " . ($u['url'] ?? 'null') . "\n";
        }
    }
} else {
    echo "No flights found.\n";
}
