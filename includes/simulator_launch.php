<?php

declare(strict_types=1);

/**
 * Resolve which simulator entry URL to open for the current user.
 *
 * @return array{url: string, plan_id: int, plan_name: string, needs_picker: bool}
 */
function sb_simulator_launch_info(array|object|null $user = null): array
{
    $planId = 0;
    $wallet = 0.0;

    if ($user !== null) {
        $planId = (int) ($user['sub_id'] ?? 0);
        $wallet = (float) ($user['wallet_balance'] ?? 0.0);
    } else {
        $planId = (int) ($_SESSION['user_sub']['plan_id'] ?? $_SESSION['sub_id'] ?? 0);
        $wallet = (float) ($_SESSION['wallet_balance'] ?? 0.0);
    }

    $byId = [
        1 => ['file' => 'basic.html', 'name' => 'BASIC'],
        2 => ['file' => 'pro.html',   'name' => 'PRO'],
        3 => ['file' => 'max.html',   'name' => 'MAX'],
    ];

    if ($planId > 0 && isset($byId[$planId])) {
        return [
            'url' => 'simulator/' . $byId[$planId]['file'],
            'plan_id' => $planId,
            'plan_name' => $byId[$planId]['name'],
            'needs_picker' => false,
        ];
    }

    if ($wallet > 0) {
        return [
            'url' => 'simulator/index.php',
            'plan_id' => 0,
            'plan_name' => 'FREE',
            'needs_picker' => true,
        ];
    }

    return [
        'url' => 'simulator/index.php',
        'plan_id' => 0,
        'plan_name' => 'FREE',
        'needs_picker' => false,
    ];
}

function sb_can_launch_simulator(array|object|null $user = null): bool
{
    $info = sb_simulator_launch_info($user);
    if (!$info['needs_picker'] && $info['plan_id'] <= 0) {
        $wallet = $user !== null
            ? (float) ($user['wallet_balance'] ?? 0.0)
            : (float) ($_SESSION['wallet_balance'] ?? 0.0);
        return $wallet > 0;
    }
    return $info['plan_id'] > 0 || $info['needs_picker'];
}
