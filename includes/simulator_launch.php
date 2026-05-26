<?php

declare(strict_types=1);

function sb_current_user_record(array|object|null $user = null): array
{
    if ($user !== null) {
        return is_array($user) ? $user : (array) $user;
    }

    $email = $_SESSION['email'] ?? '';
    if ($email !== '' && isset($GLOBALS['db']) && isset($GLOBALS['db']->users)) {
        $found = $GLOBALS['db']->users->findOne(['email' => $email]);
        if ($found) {
            return is_array($found) ? $found : (array) $found;
        }
    }

    return [];
}

function sb_mongo_timestamp(mixed $value): int
{
    if ($value instanceof MongoDB\BSON\UTCDateTime) {
        return $value->toDateTime()->getTimestamp();
    }

    if (is_int($value)) {
        return $value;
    }

    if (is_string($value) && ctype_digit($value)) {
        return (int) $value;
    }

    return 0;
}

function sb_free_trial_window_seconds(): int
{
    return 10 * 60;
}

function sb_free_trial_refresh_seconds(): int
{
    return 6 * 60 * 60;
}

function sb_free_trial_state(array|object|null $user = null, bool $consume = false): array
{
    $row = sb_current_user_record($user);
    $planId = (int) ($row['sub_id'] ?? 0);
    $wallet = (float) ($row['wallet_balance'] ?? 0.0);

    $state = [
        'eligible' => ($planId <= 0 && $wallet <= 0),
        'active' => false,
        'available' => false,
        'remaining_seconds' => 0,
        'started_at' => 0,
        'reset_at' => 0,
    ];

    if (!$state['eligible']) {
        return $state;
    }

    $email = $_SESSION['email'] ?? '';
    $now = time();
    $windowSeconds = sb_free_trial_window_seconds();
    $refreshSeconds = sb_free_trial_refresh_seconds();

    $startedAt = sb_mongo_timestamp($row['free_trial_started_at'] ?? null);
    $resetAt = sb_mongo_timestamp($row['free_minutes_reset_at'] ?? null);
    $usedSeconds = (int) ($row['free_minutes_used'] ?? 0);

    $persistState = function (array $fields) use ($email): void {
        if ($email === '' || !isset($GLOBALS['db']) || !isset($GLOBALS['db']->users)) {
            return;
        }

        $GLOBALS['db']->users->updateOne(
            ['email' => $email],
            ['$set' => $fields]
        );
    };

    if ($startedAt > 0) {
        $sessionExpiresAt = $startedAt + $windowSeconds;

        if ($now >= $sessionExpiresAt) {
            $usedSeconds = $windowSeconds;
            if ($resetAt <= 0) {
                $resetAt = $startedAt + $refreshSeconds;
            }

            if ($now >= $resetAt) {
                if ($consume) {
                    $newStartedAt = $now;
                    $newResetAt = $now + $refreshSeconds;

                    $persistState([
                        'free_trial_started_at' => new MongoDB\BSON\UTCDateTime($newStartedAt * 1000),
                        'free_minutes_used' => 0,
                        'free_minutes_reset_at' => new MongoDB\BSON\UTCDateTime($newResetAt * 1000),
                    ]);

                    return [
                        'eligible' => true,
                        'active' => true,
                        'available' => true,
                        'remaining_seconds' => $windowSeconds,
                        'started_at' => $newStartedAt,
                        'reset_at' => $newResetAt,
                    ];
                }

                return [
                    'eligible' => true,
                    'active' => false,
                    'available' => true,
                    'remaining_seconds' => $windowSeconds,
                    'started_at' => 0,
                    'reset_at' => $resetAt,
                ];
            }

            $persistState([
                'free_minutes_used' => $usedSeconds,
                'free_minutes_reset_at' => new MongoDB\BSON\UTCDateTime($resetAt * 1000),
            ]);

            return [
                'eligible' => true,
                'active' => false,
                'available' => false,
                'remaining_seconds' => 0,
                'started_at' => $startedAt,
                'reset_at' => $resetAt,
            ];
        }

        $remainingSeconds = max(0, $sessionExpiresAt - $now);
        $usedSeconds = max($usedSeconds, $windowSeconds - $remainingSeconds);

        if ($consume) {
            $persistState([
                'free_minutes_used' => $usedSeconds,
                'free_minutes_reset_at' => $resetAt > 0
                    ? new MongoDB\BSON\UTCDateTime($resetAt * 1000)
                    : new MongoDB\BSON\UTCDateTime(($startedAt + $refreshSeconds) * 1000),
            ]);
        }

        return [
            'eligible' => true,
            'active' => true,
            'available' => true,
            'remaining_seconds' => $remainingSeconds,
            'started_at' => $startedAt,
            'reset_at' => $resetAt > 0 ? $resetAt : ($startedAt + $refreshSeconds),
        ];
    }

    if ($resetAt > 0 && $now < $resetAt) {
        return [
            'eligible' => true,
            'active' => false,
            'available' => false,
            'remaining_seconds' => 0,
            'started_at' => 0,
            'reset_at' => $resetAt,
        ];
    }

    if ($consume) {
        $startedAt = $now;
        $resetAt = $now + $refreshSeconds;

        $persistState([
            'free_trial_started_at' => new MongoDB\BSON\UTCDateTime($startedAt * 1000),
            'free_minutes_used' => 0,
            'free_minutes_reset_at' => new MongoDB\BSON\UTCDateTime($resetAt * 1000),
        ]);

        return [
            'eligible' => true,
            'active' => true,
            'available' => true,
            'remaining_seconds' => $windowSeconds,
            'started_at' => $startedAt,
            'reset_at' => $resetAt,
        ];
    }

    return [
        'eligible' => true,
        'active' => false,
        'available' => true,
        'remaining_seconds' => $windowSeconds,
        'started_at' => 0,
        'reset_at' => $now + $refreshSeconds,
    ];
}

/**
 * Resolve which simulator entry URL to open for the current user.
 *
 * @return array{url: string, plan_id: int, plan_name: string, needs_picker: bool, free_trial: bool}
 */
function sb_simulator_launch_info(array|object|null $user = null): array
{
    $row = sb_current_user_record($user);
    $planId = (int) ($row['sub_id'] ?? ($_SESSION['user_sub']['plan_id'] ?? 0));
    $wallet = (float) ($row['wallet_balance'] ?? ($_SESSION['wallet_balance'] ?? 0.0));

    $byId = [
        1 => ['file' => 'basic.php', 'name' => 'BASIC'],
        2 => ['file' => 'pro.php',   'name' => 'PRO'],
        3 => ['file' => 'max.php',   'name' => 'MAX'],
    ];

    if ($planId > 0 && isset($byId[$planId])) {
        return [
            'url' => 'simulator/' . $byId[$planId]['file'],
            'plan_id' => $planId,
            'plan_name' => $byId[$planId]['name'],
            'needs_picker' => false,
            'free_trial' => false,
        ];
    }

    if ($wallet > 0) {
        return [
            'url' => 'simulator/index.php',
            'plan_id' => 0,
            'plan_name' => 'FREE',
            'needs_picker' => true,
            'free_trial' => false,
        ];
    }

    $freeTrial = sb_free_trial_state($row, false);
    if ($freeTrial['available']) {
        return [
            'url' => 'simulator/basic.php?run_plan=FREE',
            'plan_id' => 0,
            'plan_name' => 'BASIC',
            'needs_picker' => false,
            'free_trial' => true,
        ];
    }

    return [
        'url' => 'billing.php?msg=insufficient_funds',
        'plan_id' => 0,
        'plan_name' => 'FREE',
        'needs_picker' => false,
        'free_trial' => false,
    ];
}

function sb_can_launch_simulator(array|object|null $user = null): bool
{
    $info = sb_simulator_launch_info($user);
    return $info['plan_id'] > 0 || $info['free_trial'] || $info['needs_picker'];
}
