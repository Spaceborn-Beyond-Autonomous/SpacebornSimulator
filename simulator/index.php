<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../includes/simulator_launch.php';

$user = $db->users->findOne(['email' => $_SESSION['email'] ?? '']);
if (!$user) {
    header('Location: ../auth/login.php');
    exit;
}

$planId = (int) ($user['sub_id'] ?? 0);
$wallet = (float) ($user['wallet_balance'] ?? 0.0);
$runPlan = strtoupper(trim((string) ($_GET['plan'] ?? '')));

$planFiles = [
    'BASIS' => 'basis.html',
    'PRO'   => 'pro.html',
    'MAX'   => 'max.html',
];

$planIdFiles = [
    1 => 'basis.html',
    2 => 'pro.html',
    3 => 'max.html',
];

// Active subscription → open matching tier simulator
if ($planId > 0 && isset($planIdFiles[$planId])) {
    header('Location: ' . $planIdFiles[$planId]);
    exit;
}

// Wallet-only: user picked a tier
if ($planId <= 0 && $wallet > 0 && isset($planFiles[$runPlan])) {
    header('Location: ' . $planFiles[$runPlan] . '?run_plan=' . urlencode($runPlan));
    exit;
}

// No access
if ($planId <= 0 && $wallet <= 0) {
    header('Location: ../billing.php?msg=insufficient_funds');
    exit;
}

// Wallet balance but no plan chosen yet — show picker
$pickerPlans = [
    ['id' => 'BASIS', 'name' => 'BASIS', 'desc' => '1 hour access', 'rate' => '$0.10/min', 'file' => 'basis.html'],
    ['id' => 'PRO',   'name' => 'PRO',   'desc' => '1 day access',  'rate' => '$0.05/min', 'file' => 'pro.html'],
    ['id' => 'MAX',   'name' => 'MAX',   'desc' => 'Unlimited access', 'rate' => '$0.01/min', 'file' => 'max.html'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CERTANITY · Choose Simulation Tier</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--p:#4F8EF7;--s:#EE9346;--bg:#141720;--surf:#1c2130;--txt:#e2e8f4;--txt2:#a8b8d0;--txt3:#6e84a0;}
    *{box-sizing:border-box;margin:0;padding:0}
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg);color:var(--txt);font-family:'Inter',sans-serif;padding:24px}
    .card{background:var(--surf);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:32px;width:100%;max-width:680px;box-shadow:0 20px 50px rgba(0,0,0,.45)}
    h1{font-family:'Space Grotesk',sans-serif;font-size:26px;color:var(--s);margin-bottom:8px}
    p{font-size:14px;color:var(--txt2);line-height:1.6;margin-bottom:22px}
    .wallet{font-size:13px;font-weight:600;margin-bottom:18px;color:var(--txt2)}
    .wallet strong{color:#4CAF50}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
    .plan{background:#11182c;border:1.5px solid rgba(255,255,255,.06);border-radius:12px;padding:20px;text-align:center;display:flex;flex-direction:column;gap:10px;transition:border-color .2s,transform .2s}
    .plan:hover{border-color:var(--s);transform:translateY(-2px)}
    .plan h2{font-family:'Space Grotesk',sans-serif;font-size:18px}
    .plan .desc{font-size:12px;color:var(--txt3)}
    .plan .rate{font-size:15px;font-weight:700;color:#4CAF50}
    .plan a{display:block;margin-top:auto;padding:9px 0;border-radius:8px;background:var(--s);color:#fff;text-decoration:none;font-size:12px;font-weight:600}
    .foot{margin-top:20px;display:flex;justify-content:space-between;align-items:center;font-size:13px}
    .foot a{color:var(--p);text-decoration:none}
    @media(max-width:640px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="card">
    <h1>Select Simulation Tier</h1>
    <p>You do not have an active subscription. Choose a tier to run the simulator using your wallet balance. Charges apply per minute based on the tier rate.</p>
    <div class="wallet">Wallet balance: <strong>$<?= number_format($wallet, 2) ?></strong></div>
    <div class="grid">
      <?php foreach ($pickerPlans as $p): ?>
      <div class="plan">
        <h2><?= htmlspecialchars($p['name']) ?></h2>
        <div class="desc"><?= htmlspecialchars($p['desc']) ?></div>
        <div class="rate"><?= htmlspecialchars($p['rate']) ?></div>
        <a href="index.php?plan=<?= urlencode($p['id']) ?>">Launch <?= htmlspecialchars($p['name']) ?></a>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="foot">
      <a href="../billing.php">Go to Billing</a>
      <a href="../dashboard.php">← Dashboard</a>
    </div>
  </div>
</body>
</html>
