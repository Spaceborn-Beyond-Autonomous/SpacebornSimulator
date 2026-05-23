<?php
require_once __DIR__ . '/../auth/db.php';

$subCol = $db->subscriptions;

// Clear existing subscriptions to avoid duplicates
$subCol->deleteMany([]);

$plans = [
    [
        'id'               => 1,
        'plan_name'        => 'BASIS',
        'ppm'              => (float)($_ENV['PLAN_BASIS_PPM'] ?? 0.10),
        '3ds_hours'        => 1,
        'drone_profile'    => ['Research F450'],
        'flight_scenarios' => ['Normal Flight'],
        'wpm'              => 0.0,
        'PID_tuning'       => false,
        'export'           => false,
        'MV_logs'          => false,
        'GLB_cust'         => false,
        'JS'               => false,
        'CS'               => false,
        'TM_HUD'           => false,
        'env'              => ['Daytime']
    ],
    [
        'id'               => 2,
        'plan_name'        => 'PRO',
        'ppm'              => (float)($_ENV['PLAN_PRO_PPM'] ?? 0.05),
        '3ds_hours'        => 24,
        'drone_profile'    => ['Research F450', 'Indoor'],
        'flight_scenarios' => ['Normal Flight', 'Wind'],
        'wpm'              => 0.0,
        'PID_tuning'       => false,
        'export'           => false,
        'MV_logs'          => true,
        'GLB_cust'         => false,
        'JS'               => false,
        'CS'               => false,
        'TM_HUD'           => true,
        'env'              => ['Daytime', 'Nighttime', 'Sunset']
    ],
    [
        'id'               => 3,
        'plan_name'        => 'MAX',
        'ppm'              => (float)($_ENV['PLAN_MAX_PPM'] ?? 0.01),
        '3ds_hours'        => 720,
        'drone_profile'    => ['Research F450', 'Indoor', 'Racing', 'Custom'],
        'flight_scenarios' => ['Normal Flight', 'Wind', 'Motor Failure', 'GPS Denied', 'Waypoints'],
        'wpm'              => 100.0,
        'PID_tuning'       => true,
        'export'           => true,
        'MV_logs'          => true,
        'GLB_cust'         => true,
        'JS'               => true,
        'CS'               => true,
        'TM_HUD'           => true,
        'env'              => ['Daytime', 'Nighttime', 'Sunset', 'Industrial', 'Forest', 'Rainy']
    ]
];

$inserted = 0;
foreach ($plans as $plan) {
    $result = $subCol->insertOne($plan);
    if ($result->getInsertedCount() > 0) {
        $inserted++;
    }
}

echo "Successfully seeded $inserted plan subscriptions.\n";
?>
