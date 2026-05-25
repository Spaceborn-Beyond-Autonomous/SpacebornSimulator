<?php
require_once __DIR__ . '/auth/db.php';
$flights = $db->flights->find([], ['sort' => ['created_at' => -1], 'limit' => 1])->toArray();
foreach($flights as $s) {
    $telemetry_urls = $s['telemetry_urls'] ?? [];
    if (empty($telemetry_urls) && !empty($s['telemetry_url'])) {
        $telemetry_urls = [['time' => 'End of flight', 'url' => $s['telemetry_url']]];
    }
    
    echo "Is empty? " . (empty($telemetry_urls) ? 'yes' : 'no') . "\n";
    echo "Count: " . count($telemetry_urls) . "\n";
    foreach($telemetry_urls as $idx => $telem) {
        $time = $telem['time'] ?? 'Saved';
        $url = $telem['url'] ?? $telem;
        echo "Button: Telemetry ($time) -> URL: $url\n";
    }
}
