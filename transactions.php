<?php
require 'auth/session_guard.php';

$name         = $_SESSION['name'] ?? 'User';
$current_plan = $_SESSION['user_sub']['plan_name'] ?? 'Pro';
$razorpay_sub_id = $_SESSION['user_sub']['razorpay_subscription_id'] ?? null;

// Plans definition
$plans = [
  'Starter' => [
    'price'    => 9,
    'period'   => 'mo',
    'color'    => 'blue',
    'features' => ['5 simulations / month', 'Basic environments', 'CSV export', 'Community support'],
  ],
  'Pro' => [
    'price'    => 29,
    'period'   => 'mo',
    'color'    => 'orange',
    'features' => ['Unlimited simulations', 'All environments', 'MAVLink & CSV export', 'Priority support', 'Team collaboration (3 seats)'],
  ],
  'Enterprise' => [
    'price'    => 99,
    'period'   => 'mo',
    'color'    => 'accent',
    'features' => ['Unlimited simulations', 'Custom environments', 'All export formats', 'Dedicated support', 'Unlimited seats', 'SLA guarantee'],
  ],
];

// Billing history — replace with real Razorpay API / MongoDB query
$invoices = [
  ['date' => '1 May 2026', 'description' => 'Spaceborn Pro — Monthly', 'amount' => '$29.00', 'status' => 'paid',   'payment_id' => 'pay_Abc001'],
  ['date' => '1 Apr 2026', 'description' => 'Spaceborn Pro — Monthly', 'amount' => '$29.00', 'status' => 'paid',   'payment_id' => 'pay_Abc002'],
  ['date' => '1 Mar 2026', 'description' => 'Spaceborn Pro — Monthly', 'amount' => '$29.00', 'status' => 'paid',   'payment_id' => 'pay_Abc003'],
  ['date' => '1 Feb 2026', 'description' => 'Spaceborn Pro — Monthly', 'amount' => '$29.00', 'status' => 'paid',   'payment_id' => 'pay_Abc004'],
  ['date' => '1 Jan 2026', 'description' => 'Spaceborn Starter — Monthly', 'amount' => '$9.00', 'status' => 'paid','payment_id' => 'pay_Abc005'],
];

$next_billing = '1 Jun 2026';
$total_paid   = count(array_filter($invoices, fn($i) => $i['status'] === 'paid'));
$current_price = $plans[$current_plan]['price'] ?? 29;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Spaceborn — Plans &amp; Billing</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --primary:   #10256D;
      --secondary: #EE9346;
      --accent:    #28c840;
      --red:       #e05555;
      --blue:      #5b8def;
      --bg:        #0e1117;
      --bg2:       #141820;
      --surface:   #1a1f2e;
      --surface2:  #212637;
      --border:    rgba(255,255,255,0.06);
      --text:      #e8eaf0;
      --text2:     #8b92a8;
      --text3:     #5a6078;
      --neu-out:   6px 6px 14px #080b12, -4px -4px 10px #222840;
      --neu-in:    inset 4px 4px 10px #080b12, inset -3px -3px 8px #222840;
      --neu-btn:   3px 3px 8px #080b12, -2px -2px 6px #222840;
      --sidebar-w: 220px;
      --r: 14px;
    }
    [data-theme="light"] {
      --bg:       #e8eaf0; --bg2:      #dde0ea;
      --surface:  #eaecf4; --surface2: #f0f2f8;
      --border:   rgba(0,0,0,0.06);
      --text:     #1a1f35; --text2:    #5a6078; --text3:    #9099b8;
      --neu-out:  6px 6px 14px #c8cad4, -4px -4px 10px #ffffff;
      --neu-in:   inset 4px 4px 10px #c8cad4, inset -3px -3px 8px #ffffff;
      --neu-btn:  3px 3px 8px #c8cad4, -2px -2px 6px #ffffff;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; transition: background 0.3s, color 0.3s; }

    /* ── SIDEBAR ── */
    .sidebar { width: var(--sidebar-w); background: var(--bg2); display: flex; flex-direction: column; padding: 24px 16px; gap: 4px; position: fixed; top: 0; left: 0; bottom: 0; box-shadow: 4px 0 20px rgba(0,0,0,0.25); z-index: 20; transition: background 0.3s; }
    .sidebar-logo { display: flex; align-items: center; gap: 10px; padding: 6px 12px 20px; border-bottom: 1px solid var(--border); margin-bottom: 6px; }
    .sidebar-logo-text { font-family: 'Syne', sans-serif; font-size: 12.5px; font-weight: 700; letter-spacing: 0.05em; color: var(--primary); }
    .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; color: var(--text2); font-size: 13.5px; font-weight: 500; cursor: pointer; transition: all 0.18s; text-decoration: none; border: none; background: transparent; width: 100%; }
    .nav-item svg { flex-shrink: 0; opacity: 0.65; transition: opacity 0.18s; }
    .nav-item:hover { background: var(--surface); color: var(--text); }
    .nav-item:hover svg { opacity: 1; }
    .nav-item.active { box-shadow: var(--neu-out); color: var(--secondary); font-weight: 600; }
    .nav-item.active svg { opacity: 1; color: var(--secondary); }
    .sidebar-sep { font-size: 10px; font-weight: 600; letter-spacing: 0.1em; color: var(--text3); padding: 12px 14px 2px; text-transform: uppercase; }
    .avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; }
    .user-name  { font-size: 12.5px; font-weight: 600; }
    .user-role  { font-size: 11px; color: var(--text3); }
    .sidebar-bottom { margin-top: auto; padding-top: 14px; border-top: 1px solid var(--border); }
    .user-chip { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; background: var(--surface); box-shadow: var(--neu-in); }
    .user-actions { margin-left: auto; display: flex; gap: 4px; flex-shrink: 0; }
    .user-action-btn { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--text3); text-decoration: none; transition: background 0.18s, color 0.18s; }
    .user-action-btn:hover { background: var(--surface2); color: var(--text); }
    .user-action-btn.logout:hover { background: rgba(224,85,85,0.12); color: #e05555; }

    /* ── MAIN ── */
    .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

    /* ── TOPBAR ── */
    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 20px 32px; background: var(--bg); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 5; transition: background 0.3s; }
    .topbar-title { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; }
    .topbar-right { display: flex; align-items: center; gap: 12px; }
    .theme-icon { font-size: 13px; }
    .theme-toggle { width: 44px; height: 24px; background: var(--surface); box-shadow: var(--neu-in); border-radius: 12px; position: relative; cursor: pointer; border: none; transition: all 0.3s; flex-shrink: 0; }
    .theme-toggle::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: var(--secondary); box-shadow: 2px 2px 5px rgba(0,0,0,0.3); transition: transform 0.3s; }
    [data-theme="light"] .theme-toggle::after { transform: translateX(20px); }
    .topbar-icon-btn { width: 36px; height: 36px; border-radius: 10px; background: var(--surface); box-shadow: var(--neu-btn); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text2); transition: all 0.2s; }
    .topbar-icon-btn:hover { color: var(--text); box-shadow: var(--neu-out); }

    /* ── CONTENT ── */
    .content { padding: 28px 32px; display: flex; flex-direction: column; gap: 28px; flex: 1; }

    /* ── PAGE HEADER ── */
    .page-header h1 { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 4px; }
    .page-header p { font-size: 13px; color: var(--text2); }

    /* ── SECTION TITLE ── */
    .section-title { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; letter-spacing: -0.01em; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
    .section-title span { font-size: 11px; font-weight: 600; color: var(--text3); font-family: 'DM Sans', sans-serif; letter-spacing: 0.04em; text-transform: uppercase; }

    /* ── CURRENT PLAN BANNER ── */
    .plan-banner {
      background: var(--surface);
      border-radius: var(--r);
      box-shadow: var(--neu-out);
      padding: 22px 26px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      border-left: 3px solid var(--secondary);
    }
    .plan-banner-left { display: flex; flex-direction: column; gap: 4px; }
    .plan-banner-label { font-size: 10.5px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text3); }
    .plan-banner-name { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700; color: var(--secondary); }
    .plan-banner-sub { font-size: 12.5px; color: var(--text2); margin-top: 2px; }
    .badge-active { display: inline-flex; align-items: center; gap: 5px; background: rgba(40,200,64,0.12); color: var(--accent); border-radius: 6px; padding: 4px 10px; font-size: 11.5px; font-weight: 600; margin-top: 6px; width: fit-content; }
    .badge-active::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); animation: pulse-dot 1.5s ease-in-out infinite; }
    @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(0.8)} }
    .plan-banner-right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
    .renewal-info { text-align: right; }
    .plan-next-label { font-size: 11px; color: var(--text3); }
    .plan-next-date { font-size: 14px; font-weight: 600; color: var(--text); margin-top: 2px; }
    .plan-banner-actions { display: flex; gap: 8px; }
    .btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 9px; padding: 8px 16px; font-size: 12.5px; font-weight: 600; font-family: 'DM Sans', sans-serif; cursor: pointer; transition: all 0.18s; text-decoration: none; border: none; }
    .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text2); box-shadow: var(--neu-btn); }
    .btn-outline:hover { border-color: var(--red); color: var(--red); }
    .btn-primary { background: var(--secondary); color: #fff; box-shadow: 0 4px 14px rgba(238,147,70,0.3); }
    .btn-primary:hover { opacity: 0.88; transform: translateY(-1px); }
    .btn-ghost { background: var(--surface2); color: var(--text2); box-shadow: var(--neu-btn); }
    .btn-ghost:hover { color: var(--text); }

    /* ── SUMMARY CARDS ── */
    .summary-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
    .summary-card { background: var(--surface); border-radius: var(--r); box-shadow: var(--neu-out); padding: 18px 20px; }
    .summary-label { font-size: 11px; color: var(--text3); font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 6px; }
    .summary-value { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; }
    .summary-value.orange { color: var(--secondary); }
    .summary-value.blue   { color: var(--blue); }
    .summary-value.green  { color: var(--accent); }

    /* ── PLAN CARDS ── */
    .plans-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .plan-card { background: var(--surface); border-radius: var(--r); box-shadow: var(--neu-out); padding: 22px; display: flex; flex-direction: column; gap: 14px; position: relative; border: 1.5px solid transparent; transition: border-color 0.2s, transform 0.2s; }
    .plan-card:hover { transform: translateY(-2px); }
    .plan-card.current { border-color: var(--secondary); }
    .plan-card.popular { border-color: var(--blue); }
    .plan-card-badge { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
    .plan-card-badge.current-badge { background: var(--secondary); color: #fff; }
    .plan-card-badge.popular-badge { background: var(--blue); color: #fff; }
    .plan-card-name { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; }
    .plan-card-price { display: flex; align-items: baseline; gap: 4px; }
    .plan-card-price .amount { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; }
    .plan-card-price .period { font-size: 12px; color: var(--text3); }
    .plan-card-price .currency { font-size: 16px; font-weight: 600; color: var(--text2); margin-top: 4px; }
    .plan-features { list-style: none; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .plan-features li { display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; color: var(--text2); }
    .plan-features li::before { content: ''; width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; margin-top: 1px; }
    .plan-features.blue   li::before { background: rgba(91,141,239,0.2); box-shadow: 0 0 0 2px var(--blue); }
    .plan-features.orange li::before { background: rgba(238,147,70,0.2); box-shadow: 0 0 0 2px var(--secondary); }
    .plan-features.accent li::before { background: rgba(40,200,64,0.2); box-shadow: 0 0 0 2px var(--accent); }
    .plan-action { width: 100%; text-align: center; justify-content: center; padding: 10px; }
    .plan-action.upgrade { background: var(--secondary); color: #fff; box-shadow: 0 4px 14px rgba(238,147,70,0.25); }
    .plan-action.upgrade:hover { opacity: 0.88; }
    .plan-action.downgrade { background: transparent; border: 1px solid var(--border); color: var(--text2); }
    .plan-action.downgrade:hover { border-color: var(--red); color: var(--red); }
    .plan-action.current-plan { background: var(--surface2); color: var(--text3); cursor: default; }
    .plan-action.enterprise { background: linear-gradient(135deg, #28c840, #1a9e2e); color: #fff; box-shadow: 0 4px 14px rgba(40,200,64,0.25); }
    .plan-action.enterprise:hover { opacity: 0.88; }

    /* ── RENEWAL MANAGEMENT ── */
    .renewal-panel { background: var(--surface); border-radius: var(--r); box-shadow: var(--neu-out); padding: 22px 26px; display: flex; flex-direction: column; gap: 16px; }
    .renewal-row { display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
    .renewal-item { display: flex; flex-direction: column; gap: 3px; }
    .renewal-item-label { font-size: 11px; color: var(--text3); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; }
    .renewal-item-value { font-size: 14px; font-weight: 600; color: var(--text); }
    .renewal-divider { height: 1px; background: var(--border); }
    .renewal-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .cancel-zone { margin-top: 4px; padding: 14px 18px; border-radius: 10px; background: rgba(224,85,85,0.06); border: 1px solid rgba(224,85,85,0.15); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .cancel-zone p { font-size: 12.5px; color: var(--text2); }
    .cancel-zone strong { color: var(--text); }
    .btn-danger { background: transparent; border: 1px solid rgba(224,85,85,0.4); color: var(--red); }
    .btn-danger:hover { background: rgba(224,85,85,0.1); border-color: var(--red); }

    /* ── INVOICE TABLE ── */
    .inv-panel { background: var(--surface); border-radius: var(--r); box-shadow: var(--neu-out); overflow: hidden; }
    .inv-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px 0; }
    .inv-panel-title { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; }
    .inv-search-wrap { padding: 12px 22px; }
    .inv-search { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 9px; padding: 8px 14px; font-size: 13px; color: var(--text); font-family: 'DM Sans', sans-serif; box-shadow: var(--neu-in); outline: none; transition: border-color 0.18s; }
    .inv-search:focus { border-color: var(--secondary); }
    .inv-search::placeholder { color: var(--text3); }
    .inv-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .inv-table th { text-align: left; padding: 10px 22px; font-size: 10.5px; font-weight: 600; color: var(--text3); letter-spacing: 0.07em; text-transform: uppercase; border-bottom: 1px solid var(--border); background: var(--surface2); }
    .inv-table td { padding: 13px 22px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .inv-table tr:last-child td { border-bottom: none; }
    .inv-table tr:hover td { background: rgba(255,255,255,0.02); }
    .inv-id   { font-family: 'Syne', sans-serif; font-size: 12px; font-weight: 700; color: var(--text2); }
    .inv-date { color: var(--text2); font-size: 12.5px; }
    .inv-desc { color: var(--text); font-weight: 500; }
    .inv-amount { font-weight: 700; font-size: 13.5px; color: var(--text); }
    .status-badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 6px; padding: 3px 9px; font-size: 11.5px; font-weight: 600; }
    .status-badge.paid    { background: rgba(40,200,64,0.12);  color: var(--accent); }
    .status-badge.pending { background: rgba(238,147,70,0.12); color: var(--secondary); }
    .status-badge.failed  { background: rgba(224,85,85,0.12);  color: var(--red); }
    .rzp-link { font-size: 11.5px; color: var(--blue); text-decoration: none; opacity: 0.8; }
    .rzp-link:hover { opacity: 1; text-decoration: underline; }
    .inv-empty { padding: 40px; text-align: center; color: var(--text3); font-size: 13px; }

    /* ── ANIMATIONS ── */
    .fade-up { opacity: 0; transform: translateY(14px); animation: fadeUp 0.4s ease forwards; }
    @keyframes fadeUp { to { opacity:1; transform:none; } }
    .d1{animation-delay:.05s} .d2{animation-delay:.1s} .d3{animation-delay:.15s}
    .d4{animation-delay:.2s}  .d5{animation-delay:.25s} .d6{animation-delay:.3s}

    /* ── MODAL ── */
    .modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
    .modal-bg.open { display: flex; }
    .modal { background: var(--surface); border-radius: 16px; box-shadow: var(--neu-out); padding: 28px; max-width: 400px; width: 90%; }
    .modal h3 { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; margin-bottom: 10px; }
    .modal p { font-size: 13px; color: var(--text2); line-height: 1.6; margin-bottom: 20px; }
    .modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <svg width="22" height="22" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="14" fill="#10256D"/><circle cx="16" cy="16" r="6" fill="#EE9346"/><path d="M16 4v4M16 24v4M4 16h4M24 16h4" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
    <span class="sidebar-logo-text">SPACEBORN</span>
  </div>
  <a class="nav-item" href="dashboard.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard
  </a>
  <a class="nav-item" href="new-session.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>New Simulation
  </a>
  <a class="nav-item" href="simulations.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="2"/><line x1="2" y1="12" x2="8" y2="12"/><line x1="16" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="8"/><line x1="12" y1="16" x2="12" y2="22"/><circle cx="4" cy="4" r="2"/><circle cx="20" cy="4" r="2"/><circle cx="4" cy="20" r="2"/><circle cx="20" cy="20" r="2"/></svg>Simulations
  </a>
  <a class="nav-item active" href="billing.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>Plans &amp; Billing
  </a>
  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="avatar"><?= strtoupper(substr(trim($name),0,1)) ?></div>
      <div style="flex:1;min-width:0;">
        <div class="user-name"><?= htmlspecialchars($name) ?></div>
        <div class="user-role"><?= htmlspecialchars($current_plan) ?> plan</div>
      </div>
      <div class="user-actions">
        <a href="settings.php" class="user-action-btn" title="Settings">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </a>
        <a href="auth/logout.php" class="user-action-btn logout" title="Logout">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
      </div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <header class="topbar">
    <div class="topbar-title">Plans &amp; Billing</div>
    <div class="topbar-right">
      <span class="theme-icon" id="themeIcon">🌙</span>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
      <button class="topbar-icon-btn" aria-label="Notifications">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </button>
    </div>
  </header>

  <div class="content">

    <!-- Page header -->
    <div class="page-header fade-up d1">
      <h1>Plans &amp; Billing</h1>
      <p>Manage your subscription, upgrade your plan, and view payment history</p>
    </div>

    <!-- Current plan banner -->
    <div class="plan-banner fade-up d2">
      <div class="plan-banner-left">
        <div class="plan-banner-label">Current Plan</div>
        <div class="plan-banner-name"><?= htmlspecialchars($current_plan) ?></div>
        <div class="plan-banner-sub">$<?= $current_price ?>/mo · Renews automatically</div>
        <div class="badge-active">Active</div>
      </div>
      <div class="plan-banner-right">
        <div class="renewal-info">
          <div class="plan-next-label">Next billing date</div>
          <div class="plan-next-date"><?= $next_billing ?></div>
        </div>
        <div class="plan-banner-actions">
          <button class="btn btn-ghost" onclick="openModal('pauseModal')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
            Pause
          </button>
          <a href="billing_portal.php" class="btn btn-primary">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Manage
          </a>
        </div>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="summary-row fade-up d3">
      <div class="summary-card">
        <div class="summary-label">Plan</div>
        <div class="summary-value orange"><?= htmlspecialchars($current_plan) ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-label">Payments Made</div>
        <div class="summary-value blue"><?= $total_paid ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-label">Next Renewal</div>
        <div class="summary-value green"><?= $next_billing ?></div>
      </div>
    </div>

    <!-- Plan cards -->
    <div class="fade-up d4">
      <div class="section-title">
        Choose a Plan <span>Upgrade or downgrade anytime</span>
      </div>
      <div class="plans-grid">
        <?php foreach ($plans as $plan_name => $plan): ?>
        <?php
          $is_current  = ($plan_name === $current_plan);
          $plan_order  = array_keys($plans);
          $current_idx = array_search($current_plan, $plan_order);
          $this_idx    = array_search($plan_name, $plan_order);
          $is_upgrade  = $this_idx > $current_idx;
          $is_downgrade= $this_idx < $current_idx;
        ?>
        <div class="plan-card <?= $is_current ? 'current' : '' ?> <?= $plan_name === 'Pro' && !$is_current ? 'popular' : '' ?>">
          <?php if ($is_current): ?>
            <div class="plan-card-badge current-badge">Your Plan</div>
          <?php elseif ($plan_name === 'Pro' && !$is_current): ?>
            <div class="plan-card-badge popular-badge">Most Popular</div>
          <?php endif; ?>

          <div class="plan-card-name"><?= $plan_name ?></div>

          <div class="plan-card-price">
            <span class="currency">$</span>
            <span class="amount"><?= $plan['price'] ?></span>
            <span class="period">/<?= $plan['period'] ?></span>
          </div>

          <ul class="plan-features <?= $plan['color'] ?>">
            <?php foreach ($plan['features'] as $f): ?>
            <li><?= htmlspecialchars($f) ?></li>
            <?php endforeach; ?>
          </ul>

          <?php if ($is_current): ?>
            <button class="btn plan-action current-plan" disabled>Current Plan</button>
          <?php elseif ($is_upgrade): ?>
            <button class="btn plan-action upgrade" onclick="openModal('upgradeModal', '<?= $plan_name ?>', '<?= $plan['price'] ?>')">
              Upgrade to <?= $plan_name ?> →
            </button>
          <?php elseif ($is_downgrade): ?>
            <button class="btn plan-action downgrade" onclick="openModal('downgradeModal', '<?= $plan_name ?>', '<?= $plan['price'] ?>')">
              Downgrade to <?= $plan_name ?>
            </button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Renewal management -->
    <div class="fade-up d5">
      <div class="section-title">Renewal &amp; Subscription</div>
      <div class="renewal-panel">
        <div class="renewal-row">
          <div class="renewal-item">
            <div class="renewal-item-label">Subscription ID</div>
            <div class="renewal-item-value" style="font-family:monospace;font-size:12px;color:var(--text2);">
              <?= $razorpay_sub_id ? htmlspecialchars($razorpay_sub_id) : 'sub_XXXXXXXXXXXX' ?>
            </div>
          </div>
          <div class="renewal-item">
            <div class="renewal-item-label">Billing Cycle</div>
            <div class="renewal-item-value">Monthly</div>
          </div>
          <div class="renewal-item">
            <div class="renewal-item-label">Next Charge</div>
            <div class="renewal-item-value">$<?= $current_price ?>.00 on <?= $next_billing ?></div>
          </div>
          <div class="renewal-item">
            <div class="renewal-item-label">Payment Method</div>
            <div class="renewal-item-value">•••• 4242</div>
          </div>
        </div>
        <div class="renewal-divider"></div>
        <div class="renewal-actions">
          <a href="billing_portal.php?action=update_card" class="btn btn-ghost">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Update Payment Method
          </a>
          <button class="btn btn-ghost" onclick="openModal('pauseModal')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
            Pause Subscription
          </button>
        </div>
        <div class="cancel-zone">
          <p><strong>Cancel subscription</strong> — Access continues until <?= $next_billing ?>. No refunds on partial months.</p>
          <button class="btn btn-danger" onclick="openModal('cancelModal')">Cancel Subscription</button>
        </div>
      </div>
    </div>

    <!-- Transaction history -->
    <div class="inv-panel fade-up d6">
      <div class="inv-panel-header">
        <div class="inv-panel-title">Payment History</div>
      </div>
      <div class="inv-search-wrap">
        <input class="inv-search" id="invSearch" type="text" placeholder="Search by date, amount, or payment ID…" autocomplete="off"/>
      </div>
      <?php if (!empty($invoices)): ?>
      <table class="inv-table" id="invTable">
        <thead>
          <tr>
            <th>Payment ID</th>
            <th>Date</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Receipt</th>
          </tr>
        </thead>
        <tbody id="invBody">
          <?php foreach ($invoices as $inv): ?>
          <tr data-desc="<?= strtolower(htmlspecialchars($inv['description'])) ?> <?= strtolower($inv['payment_id']) ?> <?= strtolower($inv['date']) ?> <?= $inv['amount'] ?>">
            <td><span class="inv-id"><?= htmlspecialchars($inv['payment_id']) ?></span></td>
            <td><span class="inv-date"><?= htmlspecialchars($inv['date']) ?></span></td>
            <td><span class="inv-desc"><?= htmlspecialchars($inv['description']) ?></span></td>
            <td><span class="inv-amount"><?= htmlspecialchars($inv['amount']) ?></span></td>
            <td>
              <span class="status-badge <?= htmlspecialchars($inv['status']) ?>">
                <?= ucfirst(htmlspecialchars($inv['status'])) ?>
              </span>
            </td>
            <td>
              <!-- Razorpay hosted receipt — no PDF stored locally -->
              <a class="rzp-link" href="receipt.php?payment_id=<?= urlencode($inv['payment_id']) ?>" target="_blank">
                View ↗
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="inv-empty">No payments yet.</div>
      <?php endif; ?>
    </div>

  </div>
</main>

<!-- UPGRADE MODAL -->
<div class="modal-bg" id="upgradeModal">
  <div class="modal">
    <h3>Upgrade to <span id="upgradePlanName"></span></h3>
    <p>You'll be charged <strong id="upgradePrice"></strong>/mo starting today. Your current billing cycle will be prorated via Razorpay.</p>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('upgradeModal')">Cancel</button>
      <form method="POST" action="billing_action.php" style="display:inline">
        <input type="hidden" name="action" value="upgrade">
        <input type="hidden" name="plan" id="upgradeFormPlan">
        <button type="submit" class="btn btn-primary">Confirm Upgrade</button>
      </form>
    </div>
  </div>
</div>

<!-- DOWNGRADE MODAL -->
<div class="modal-bg" id="downgradeModal">
  <div class="modal">
    <h3>Downgrade to <span id="downgradePlanName"></span></h3>
    <p>Your plan will switch to <strong id="downgradePrice"></strong>/mo at the end of your current billing cycle on <strong><?= $next_billing ?></strong>.</p>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('downgradeModal')">Keep Current Plan</button>
      <form method="POST" action="billing_action.php" style="display:inline">
        <input type="hidden" name="action" value="downgrade">
        <input type="hidden" name="plan" id="downgradeFormPlan">
        <button type="submit" class="btn btn-danger">Confirm Downgrade</button>
      </form>
    </div>
  </div>
</div>

<!-- PAUSE MODAL -->
<div class="modal-bg" id="pauseModal">
  <div class="modal">
    <h3>Pause Subscription</h3>
    <p>Pausing stops future charges. Your access continues until <strong><?= $next_billing ?></strong>. You can resume anytime from this page.</p>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('pauseModal')">Cancel</button>
      <form method="POST" action="billing_action.php" style="display:inline">
        <input type="hidden" name="action" value="pause">
        <button type="submit" class="btn btn-primary">Pause Now</button>
      </form>
    </div>
  </div>
</div>

<!-- CANCEL MODAL -->
<div class="modal-bg" id="cancelModal">
  <div class="modal">
    <h3>Cancel Subscription</h3>
    <p>Are you sure? Access ends on <strong><?= $next_billing ?></strong>. Your data is retained for 30 days after cancellation. This cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('cancelModal')">Keep Subscription</button>
      <form method="POST" action="billing_action.php" style="display:inline">
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="btn btn-danger">Yes, Cancel</button>
      </form>
    </div>
  </div>
</div>

<script>
  /* ── THEME ── */
  (function() {
    var html   = document.documentElement;
    var toggle = document.getElementById('themeToggle');
    var icon   = document.getElementById('themeIcon');
    var saved  = localStorage.getItem('sb_theme');
    if (saved) html.setAttribute('data-theme', saved);
    function syncIcon() { icon.textContent = html.getAttribute('data-theme') === 'dark' ? '🌙' : '☀'; }
    syncIcon();
    toggle.addEventListener('click', function() {
      var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      localStorage.setItem('sb_theme', next);
      syncIcon();
    });
  })();

  /* ── SEARCH ── */
  document.getElementById('invSearch').addEventListener('input', function() {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#invBody tr').forEach(function(row) {
      row.style.display = (!q || row.dataset.desc.includes(q)) ? '' : 'none';
    });
  });

  /* ── MODALS ── */
  function openModal(id, plan, price) {
    document.getElementById(id).classList.add('open');
    if (id === 'upgradeModal' && plan) {
      document.getElementById('upgradePlanName').textContent = plan;
      document.getElementById('upgradePrice').textContent = '$' + price;
      document.getElementById('upgradeFormPlan').value = plan;
    }
    if (id === 'downgradeModal' && plan) {
      document.getElementById('downgradePlanName').textContent = plan;
      document.getElementById('downgradePrice').textContent = '$' + price;
      document.getElementById('downgradeFormPlan').value = plan;
    }
  }
  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
  }
  document.querySelectorAll('.modal-bg').forEach(function(bg) {
    bg.addEventListener('click', function(e) {
      if (e.target === bg) bg.classList.remove('open');
    });
  });
</script>
</body>
</html>