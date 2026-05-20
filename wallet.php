<?php
require 'auth/session_guard.php';

// PHP variables (from session / DB)
$wallet_balance   = $_SESSION['wallet'];           // e.g. 45.20
$name             = $_SESSION['user_sub']['name'];  // e.g. "Dev Patel"
$plan_name        = $_SESSION['user_sub']['plan_name'];
$auto_topup       = $_SESSION['user_sub']['auto_topup'] ?? true;
$topup_threshold  = $_SESSION['user_sub']['topup_threshold'] ?? 10.00;

// Initials from name
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', trim($name)), 0, 2)));

// Hours remaining (assuming $7.20/hr flight cost)
$hours_remaining = round($wallet_balance / 7.20, 1);

// Transaction history (from MongoDB in real app)
$transactions = [
  [
    'date'        => '15/5/2026, 5:50:42 PM',
    'type'        => 'top-up',
    'description' => 'Wallet Top Up (Visa •••• 4242)',
    'amount'      => '+$50.00',
    'status'      => 'completed',
    'amount_val'  => 50.00,
  ],
  [
    'date'        => '18/5/2026, 5:50:42 PM',
    'type'        => 'debit',
    'description' => 'Session: FPV Racing Track 4',
    'amount'      => '$5.46',
    'status'      => 'completed',
    'amount_val'  => -5.46,
  ],
  [
    'date'        => '19/5/2026, 5:50:42 PM',
    'type'        => 'debit',
    'description' => 'Session: Wind Tolerance Test',
    'amount'      => '$2.17',
    'status'      => 'completed',
    'amount_val'  => -2.17,
  ],
  [
    'date'        => '20/5/2026, 5:50:42 PM',
    'type'        => 'debit',
    'description' => 'Session: Campus Mapping',
    'amount'      => '$2.90',
    'status'      => 'completed',
    'amount_val'  => -2.90,
  ],
];

$total_spent    = array_sum(array_map(fn($t) => $t['amount_val'] < 0 ? abs($t['amount_val']) : 0, $transactions));
$total_toppedup = array_sum(array_map(fn($t) => $t['amount_val'] > 0 ? $t['amount_val'] : 0, $transactions));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>DroneSimSaaS — Wallet</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    /* ── TOKENS ──────────────────────────────────── */
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
      --bg:       #e8eaf0;
      --bg2:      #dde0ea;
      --surface:  #eaecf4;
      --surface2: #f0f2f8;
      --border:   rgba(0,0,0,0.06);
      --text:     #1a1f35;
      --text2:    #5a6078;
      --text3:    #9099b8;
      --neu-out:  6px 6px 14px #c8cad4, -4px -4px 10px #ffffff;
      --neu-in:   inset 4px 4px 10px #c8cad4, inset -3px -3px 8px #ffffff;
      --neu-btn:  3px 3px 8px #c8cad4, -2px -2px 6px #ffffff;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      display: flex;
      min-height: 100vh;
      transition: background 0.3s, color 0.3s;
    }

    /* ── SIDEBAR ──────────────────────────────────── */
    .sidebar {
      width: var(--sidebar-w);
      background: var(--bg2);
      display: flex;
      flex-direction: column;
      padding: 24px 16px;
      gap: 4px;
      position: fixed;
      top: 0; left: 0; bottom: 0;
      box-shadow: 4px 0 20px rgba(0,0,0,0.25);
      z-index: 20;
      transition: background 0.3s;
    }
    .sidebar-logo {
      display: flex; align-items: center; gap: 10px;
      padding: 6px 12px 20px;
      border-bottom: 1px solid var(--border);
      margin-bottom: 6px;
    }
    .sidebar-logo-text {
      font-family: 'Syne', sans-serif;
      font-size: 12.5px; font-weight: 700;
      letter-spacing: 0.05em; color: var(--primary);
    }
    .nav-item {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 14px; border-radius: 10px;
      color: var(--text2); font-size: 13.5px; font-weight: 500;
      cursor: pointer; transition: all 0.18s;
      text-decoration: none; border: none;
      background: transparent; width: 100%;
    }
    .nav-item svg { flex-shrink: 0; opacity: 0.65; transition: opacity 0.18s; }
    .nav-item:hover { background: var(--surface); color: var(--text); }
    .nav-item:hover svg { opacity: 1; }
    .nav-item.active {
      box-shadow: var(--neu-out);
      color: var(--secondary); font-weight: 600;
    }
    .nav-item.active svg { opacity: 1; color: var(--secondary); }
    .sidebar-sep {
      font-size: 10px; font-weight: 600;
      letter-spacing: 0.1em; color: var(--text3);
      padding: 12px 14px 2px; text-transform: uppercase;
    }
    .sidebar-bottom {
      margin-top: auto; padding-top: 14px;
      border-top: 1px solid var(--border);
      display: flex; flex-direction: column; gap: 8px;
    }
    .wallet-side {
      display: flex; justify-content: space-between; align-items: center;
      padding: 6px 12px; font-size: 11.5px; color: var(--text3);
    }
    .wallet-side strong { color: var(--accent); font-size: 13px; }
    .user-chip {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 10px;
      background: var(--surface); box-shadow: var(--neu-in);
    }
    .avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .user-name { font-size: 12.5px; font-weight: 600; }
    .user-role { font-size: 11px; color: var(--text3); }
    .logout-btn {
      display: flex; align-items: center; gap: 8px;
      padding: 9px 14px; border-radius: 10px;
      background: rgba(224,85,85,0.08);
      color: var(--red); font-size: 13px; font-weight: 500;
      cursor: pointer; border: none; width: 100%;
      font-family: 'DM Sans', sans-serif;
      transition: background 0.2s;
    }
    .logout-btn:hover { background: rgba(224,85,85,0.14); }

    /* ── MAIN ── */
    .main {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    /* ── TOPBAR ── */
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 32px;
      background: var(--bg);
      border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 5;
      transition: background 0.3s;
    }
    .topbar-title {
      font-family: 'Syne', sans-serif;
      font-size: 20px; font-weight: 700;
      letter-spacing: -0.02em;
    }
    .topbar-right { display: flex; align-items: center; gap: 12px; }

    .theme-toggle {
      width: 44px; height: 24px;
      background: var(--surface);
      box-shadow: var(--neu-in);
      border-radius: 12px;
      position: relative;
      cursor: pointer;
      border: none;
      transition: all 0.3s;
    }
    .theme-toggle::after {
      content: '';
      position: absolute;
      top: 3px; left: 3px;
      width: 18px; height: 18px;
      border-radius: 50%;
      background: var(--secondary);
      box-shadow: 2px 2px 5px rgba(0,0,0,0.3);
      transition: transform 0.3s;
    }
    [data-theme="light"] .theme-toggle::after { transform: translateX(20px); }
    .theme-icon { font-size: 13px; }

    .topbar-icon-btn {
      width: 36px; height: 36px;
      border-radius: 10px;
      background: var(--surface);
      box-shadow: var(--neu-btn);
      border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: var(--text2); transition: all 0.2s;
    }
    .topbar-icon-btn:hover { color: var(--text); box-shadow: var(--neu-out); }

    .wallet-badge {
      display: flex; align-items: center; gap: 6px;
      background: var(--surface);
      box-shadow: var(--neu-out);
      border-radius: 10px;
      padding: 8px 14px;
      font-size: 13px; font-weight: 600;
      color: var(--accent);
    }

    /* ── CONTENT ── */
    .content {
      padding: 28px 32px;
      display: flex;
      flex-direction: column;
      gap: 24px;
      flex: 1;
    }

    /* Page header */
    .page-header { display: flex; flex-direction: column; gap: 4px; }
    .page-header h1 {
      font-family: 'Syne', sans-serif;
      font-size: 22px; font-weight: 800;
      letter-spacing: -0.02em;
    }
    .page-header p { font-size: 13px; color: var(--text3); }

    /* ── MAIN GRID ── */
    .wallet-grid {
      display: grid;
      grid-template-columns: 1fr 420px;
      gap: 20px;
      align-items: start;
    }

    /* ── BALANCE CARD ── */
    .balance-card {
      background: var(--surface);
      border-radius: var(--r);
      box-shadow: var(--neu-out);
      padding: 28px 30px;
      position: relative;
      overflow: hidden;
      transition: box-shadow 0.2s;
    }
    .balance-card::before {
      content: '';
      position: absolute;
      top: -60px; right: -60px;
      width: 200px; height: 200px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(40,200,64,0.08) 0%, transparent 70%);
      pointer-events: none;
    }
    .balance-label {
      font-size: 10.5px; font-weight: 600;
      letter-spacing: 0.1em; text-transform: uppercase;
      color: var(--text3); margin-bottom: 12px;
    }
    .balance-amount {
      font-family: 'Syne', sans-serif;
      font-size: 52px; font-weight: 800;
      color: var(--accent);
      letter-spacing: -0.04em;
      line-height: 1;
      margin-bottom: 10px;
    }
    /* Animated counter */
    .balance-amount .cents {
      font-size: 32px;
      opacity: 0.85;
    }
    .balance-hours {
      font-size: 13px; color: var(--text2);
      margin-bottom: 20px;
    }
    .balance-hours span { color: var(--text); font-weight: 500; }

    .auto-topup-row {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 16px;
      background: var(--bg);
      box-shadow: var(--neu-in);
      border-radius: 10px;
    }
    .auto-topup-row .label { font-size: 12.5px; color: var(--text2); flex: 1; }
    .badge-active {
      font-size: 10px; font-weight: 700;
      letter-spacing: 0.06em;
      background: rgba(40,200,64,0.12);
      color: var(--accent);
      padding: 3px 10px; border-radius: 20px;
      text-transform: uppercase;
    }
    .threshold-text {
      font-size: 12px; color: var(--text3);
      font-variant-numeric: tabular-nums;
    }
    .threshold-text strong { color: var(--text); font-weight: 600; }

    /* Quick stats row */
    .quick-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
      margin-top: 18px;
    }
    .qstat {
      background: var(--bg);
      box-shadow: var(--neu-in);
      border-radius: 10px;
      padding: 14px 16px;
    }
    .qstat-label {
      font-size: 10px; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      color: var(--text3); margin-bottom: 6px;
    }
    .qstat-value {
      font-family: 'Syne', sans-serif;
      font-size: 20px; font-weight: 700;
    }
    .qstat-value.orange { color: var(--secondary); }
    .qstat-value.blue   { color: var(--blue); }
    .qstat-value.green  { color: var(--accent); }

    /* ── ADD FUNDS CARD ── */
    .add-funds-card {
      background: var(--surface);
      border-radius: var(--r);
      box-shadow: var(--neu-out);
      padding: 26px 28px;
    }
    .add-funds-title {
      font-family: 'Syne', sans-serif;
      font-size: 16px; font-weight: 700;
      margin-bottom: 18px;
    }

    .amount-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-bottom: 12px;
    }
    .amount-btn {
      background: var(--bg);
      box-shadow: var(--neu-btn);
      border: 1.5px solid transparent;
      border-radius: 10px;
      padding: 12px 10px;
      text-align: center;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px; font-weight: 600;
      color: var(--text2);
      cursor: pointer;
      transition: all 0.2s;
    }
    .amount-btn:hover {
      color: var(--text);
      box-shadow: var(--neu-out);
    }
    .amount-btn.selected {
      box-shadow: var(--neu-in);
      border-color: var(--accent);
      color: var(--accent);
    }

    .custom-input-wrap {
      position: relative;
      margin-bottom: 16px;
    }
    .custom-prefix {
      position: absolute;
      left: 14px; top: 50%;
      transform: translateY(-50%);
      font-size: 14px; font-weight: 600; color: var(--text3);
    }
    .custom-input {
      width: 100%;
      background: var(--bg);
      box-shadow: var(--neu-in);
      border: 1.5px solid transparent;
      border-radius: 10px;
      padding: 12px 16px 12px 28px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px; font-weight: 600;
      color: var(--text);
      outline: none;
      transition: border-color 0.2s;
    }
    .custom-input:focus { border-color: var(--accent); }
    .custom-input::placeholder { color: var(--text3); font-weight: 400; }

    .pay-btn {
      width: 100%;
      background: var(--accent);
      color: #0e1117;
      border: none;
      border-radius: 10px;
      padding: 14px;
      font-family: 'Syne', sans-serif;
      font-size: 15px; font-weight: 700;
      letter-spacing: 0.01em;
      cursor: pointer;
      box-shadow: 0 4px 18px rgba(40,200,64,0.35);
      transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      position: relative;
      overflow: hidden;
    }
    .pay-btn::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
      transform: translateX(-100%);
      transition: transform 0.5s;
    }
    .pay-btn:hover::after { transform: translateX(100%); }
    .pay-btn:hover { opacity: 0.93; transform: translateY(-1px); box-shadow: 0 6px 24px rgba(40,200,64,0.45); }
    .pay-btn:active { transform: translateY(0); }

    .pay-secure {
      text-align: center;
      font-size: 11px; color: var(--text3);
      margin-top: 8px;
      display: flex; align-items: center; justify-content: center; gap: 5px;
    }

    /* ── TRANSACTION TABLE ── */
    .tx-panel {
      background: var(--surface);
      border-radius: var(--r);
      box-shadow: var(--neu-out);
      overflow: hidden;
    }
    .tx-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 18px 24px 16px;
      border-bottom: 1px solid var(--border);
    }
    .tx-title {
      font-family: 'Syne', sans-serif;
      font-size: 15px; font-weight: 700;
    }
    .tx-filter-row { display: flex; gap: 8px; }
    .tx-filter-btn {
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 11.5px; font-weight: 600;
      cursor: pointer; border: none;
      background: var(--bg);
      box-shadow: var(--neu-btn);
      color: var(--text2);
      font-family: 'DM Sans', sans-serif;
      transition: all 0.18s;
    }
    .tx-filter-btn:hover { color: var(--text); }
    .tx-filter-btn.active {
      background: var(--surface2);
      box-shadow: var(--neu-in);
      color: var(--secondary);
    }

    .tx-table {
      width: 100%;
      border-collapse: collapse;
    }
    .tx-table thead tr {
      border-bottom: 1px solid var(--border);
    }
    .tx-table th {
      padding: 10px 24px;
      font-size: 10px; font-weight: 700;
      letter-spacing: 0.1em; text-transform: uppercase;
      color: var(--text3); text-align: left;
    }
    .tx-table tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background 0.15s;
    }
    .tx-table tbody tr:last-child { border-bottom: none; }
    .tx-table tbody tr:hover { background: var(--surface2); }
    .tx-table td {
      padding: 14px 24px;
      font-size: 13px; vertical-align: middle;
    }

    .tx-type-badge {
      display: inline-block;
      font-size: 9.5px; font-weight: 700;
      letter-spacing: 0.06em; text-transform: uppercase;
      padding: 3px 10px; border-radius: 20px;
    }
    .tx-type-badge.topup  { background: rgba(40,200,64,0.12); color: var(--accent); }
    .tx-type-badge.debit  { background: rgba(238,147,70,0.12); color: var(--secondary); }
    .tx-type-badge.refund { background: rgba(91,141,239,0.12); color: var(--blue); }

    .tx-amount { font-variant-numeric: tabular-nums; font-weight: 600; }
    .tx-amount.positive { color: var(--accent); }
    .tx-amount.negative { color: var(--secondary); }

    .tx-status-badge {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 10px; font-weight: 700;
      letter-spacing: 0.05em; text-transform: uppercase;
      padding: 3px 10px; border-radius: 20px;
    }
    .tx-status-badge.completed { background: rgba(40,200,64,0.10); color: var(--accent); }
    .tx-status-badge.pending   { background: rgba(91,141,239,0.10); color: var(--blue); }
    .tx-status-badge.failed    { background: rgba(224,85,85,0.10); color: var(--red); }
    .tx-status-badge::before {
      content: '';
      width: 5px; height: 5px;
      border-radius: 50%;
      background: currentColor;
      flex-shrink: 0;
    }

    /* ── ANIMATIONS ── */
    .fade-up {
      opacity: 0;
      transform: translateY(16px);
      animation: fadeUp 0.45s ease forwards;
    }
    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }
    .d1 { animation-delay: 0.05s; }
    .d2 { animation-delay: 0.12s; }
    .d3 { animation-delay: 0.19s; }
    .d4 { animation-delay: 0.26s; }
    .d5 { animation-delay: 0.33s; }

    /* Balance count-up */
    @keyframes countUp {
      from { opacity: 0; transform: translateY(8px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .balance-amount {
      animation: countUp 0.6s 0.15s ease both;
    }

    /* Shimmer on pay button hover */
    .pay-btn-label { position: relative; z-index: 1; }

    /* ── RESPONSIVE ── */
    @media (max-width: 1100px) {
      .wallet-grid { grid-template-columns: 1fr; }
      .add-funds-card { max-width: 480px; }
    }
    @media (max-width: 720px) {
      .sidebar { display: none; }
      .main { margin-left: 0; }
      .content { padding: 20px 16px; }
      .topbar { padding: 14px 16px; }
      .quick-stats { grid-template-columns: repeat(2,1fr); }
    }
  </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
      <circle cx="16" cy="16" r="4" fill="#10256D"/>
      <rect x="6" y="14" width="8" height="4" rx="2" fill="#10256D" opacity="0.7"/>
      <rect x="18" y="14" width="8" height="4" rx="2" fill="#10256D" opacity="0.7"/>
      <rect x="14" y="6" width="4" height="8" rx="2" fill="#10256D" opacity="0.7"/>
      <rect x="14" y="18" width="4" height="8" rx="2" fill="#10256D" opacity="0.7"/>
      <circle cx="7"  cy="7"  r="3" fill="#EE9346"/>
      <circle cx="25" cy="7"  r="3" fill="#EE9346"/>
      <circle cx="7"  cy="25" r="3" fill="#EE9346"/>
      <circle cx="25" cy="25" r="3" fill="#EE9346"/>
    </svg>
    <span class="sidebar-logo-text">DRONESIM</span>
  </div>

  <a class="nav-item" href="dashboard.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a class="nav-item" href="simulations.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5,3 19,12 5,21"/></svg>
    Simulations
  </a>
  <a class="nav-item" href="#">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    New Session
  </a>

  <div class="sidebar-sep">Account</div>
  <a class="nav-item active" href="wallet.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    Wallet
  </a>
  <a class="nav-item" href="#">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Team
  </a>
  <a class="nav-item" href="#">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
    Settings
  </a>

  <div class="sidebar-sep">System</div>
  <a class="nav-item" href="#">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    Admin
  </a>

  <div class="sidebar-bottom">
    <div class="wallet-side">
      <span>WALLET</span>
      <strong><?php echo '$' . number_format($wallet_balance, 2); ?></strong>
    </div>
    <div class="user-chip">
      <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
      <div>
        <div class="user-name"><?php echo htmlspecialchars($name); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($plan_name . ' plan'); ?></div>
      </div>
    </div>
    <a href="auth/logout.php" class="logout-btn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </div>
</aside>

<!-- ── MAIN ── -->
<main class="main">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-title">Wallet</div>
    <div class="topbar-right">
      <span class="theme-icon" id="themeIcon">🌙</span>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode"></button>

      <button class="topbar-icon-btn" aria-label="Notifications">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </button>
      <div class="wallet-badge">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        <?php echo htmlspecialchars('$' . number_format($wallet_balance, 2)); ?>
      </div>
    </div>
  </header>

  <!-- CONTENT -->
  <div class="content">

    <!-- Page header -->
    <div class="page-header fade-up d1">
      <h1>Wallet</h1>
      <p>Manage your balance and view transaction history</p>
    </div>

    <!-- MAIN WALLET GRID -->
    <div class="wallet-grid">

      <!-- LEFT: Balance + Stats -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Balance card -->
        <div class="balance-card fade-up d2">
          <div class="balance-label">Current Balance</div>

          <div class="balance-amount" id="balanceDisplay">
            <?php
              $parts = explode('.', number_format($wallet_balance, 2));
              echo '$' . $parts[0];
            ?><span class="cents">.<?php echo $parts[1]; ?></span>
          </div>

          <div class="balance-hours">≈ <span><?php echo $hours_remaining; ?> hours</span> of flight time</div>

          <div class="auto-topup-row">
            <div class="label">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:5px"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
              Auto top-up
            </div>
            <?php if ($auto_topup): ?>
              <span class="badge-active">ACTIVE</span>
            <?php endif; ?>
            <span class="threshold-text">Threshold <strong>$<?php echo number_format($topup_threshold, 2); ?></strong></span>
          </div>

          <!-- Quick stats -->
          <div class="quick-stats">
            <div class="qstat">
              <div class="qstat-label">Total Spent</div>
              <div class="qstat-value orange">$<?php echo number_format($total_spent, 2); ?></div>
            </div>
            <div class="qstat">
              <div class="qstat-label">Topped Up</div>
              <div class="qstat-value green">$<?php echo number_format($total_toppedup, 2); ?></div>
            </div>
            <div class="qstat">
              <div class="qstat-label">Transactions</div>
              <div class="qstat-value blue"><?php echo count($transactions); ?></div>
            </div>
          </div>
        </div>

      </div>

      <!-- RIGHT: Add Funds -->
      <div class="add-funds-card fade-up d3">
        <div class="add-funds-title">Add Funds</div>

        <div class="amount-grid" id="amountGrid">
          <button class="amount-btn" data-amount="10">$10</button>
          <button class="amount-btn" data-amount="25">$25</button>
          <button class="amount-btn selected" data-amount="50">$50</button>
          <button class="amount-btn" data-amount="100">$100</button>
          <button class="amount-btn" data-amount="250">$250</button>
          <button class="amount-btn" id="customBtn">Custom $</button>
        </div>

        <div class="custom-input-wrap" id="customWrap" style="display:none">
          <span class="custom-prefix">$</span>
          <input class="custom-input" id="customInput" type="number" min="1" max="9999" placeholder="Enter amount" />
        </div>

        <button class="pay-btn" id="payBtn">
          <span class="pay-btn-label">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-3px;margin-right:4px"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Pay $50
          </span>
        </button>

        <p class="pay-secure">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Secure payment via Stripe. Instant credit.
        </p>
      </div>

    </div>

    <!-- TRANSACTION HISTORY -->
    <div class="tx-panel fade-up d4">
      <div class="tx-header">
        <div class="tx-title">Transaction History</div>
        <div class="tx-filter-row">
          <button class="tx-filter-btn active" data-filter="all">All</button>
          <button class="tx-filter-btn" data-filter="top-up">Top-ups</button>
          <button class="tx-filter-btn" data-filter="debit">Debits</button>
        </div>
      </div>

      <table class="tx-table" id="txTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="txBody">
          <?php foreach ($transactions as $tx): ?>
          <tr data-type="<?php echo htmlspecialchars($tx['type']); ?>">
            <td style="color:var(--text2);font-size:12px"><?php echo htmlspecialchars($tx['date']); ?></td>
            <td>
              <span class="tx-type-badge <?php echo htmlspecialchars($tx['type'] === 'top-up' ? 'topup' : $tx['type']); ?>">
                <?php echo strtoupper($tx['type']); ?>
              </span>
            </td>
            <td><?php echo htmlspecialchars($tx['description']); ?></td>
            <td>
              <span class="tx-amount <?php echo $tx['amount_val'] > 0 ? 'positive' : 'negative'; ?>">
                <?php echo htmlspecialchars($tx['amount']); ?>
              </span>
            </td>
            <td>
              <span class="tx-status-badge <?php echo htmlspecialchars($tx['status']); ?>">
                <?php echo ucfirst(htmlspecialchars($tx['status'])); ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /content -->
</main>

<script>
  /* ── DARK / LIGHT TOGGLE ── */
  const html = document.documentElement;
  const tog  = document.getElementById('themeToggle');
  const icon = document.getElementById('themeIcon');
  tog.addEventListener('click', () => {
    const dark = html.dataset.theme === 'dark';
    html.dataset.theme = dark ? 'light' : 'dark';
    icon.textContent   = dark ? '☀' : '🌙';
  });

  /* ── AMOUNT SELECTION ── */
  let selectedAmount = 50;
  const amountGrid  = document.getElementById('amountGrid');
  const customBtn   = document.getElementById('customBtn');
  const customWrap  = document.getElementById('customWrap');
  const customInput = document.getElementById('customInput');
  const payBtn      = document.getElementById('payBtn');

  function updatePayBtn(amount) {
    payBtn.querySelector('.pay-btn-label').innerHTML =
      `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-3px;margin-right:4px"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>Pay $${parseFloat(amount).toFixed(2)}`;
  }

  amountGrid.addEventListener('click', e => {
    const btn = e.target.closest('[data-amount]');
    if (!btn) {
      if (e.target.closest('#customBtn')) {
        document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('selected'));
        customBtn.classList.add('selected');
        customWrap.style.display = 'block';
        customInput.focus();
        updatePayBtn(customInput.value || 0);
      }
      return;
    }
    document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    selectedAmount = parseFloat(btn.dataset.amount);
    customWrap.style.display = 'none';
    updatePayBtn(selectedAmount);
  });

  customInput.addEventListener('input', () => {
    selectedAmount = parseFloat(customInput.value) || 0;
    updatePayBtn(selectedAmount);
  });

  /* ── PAY BTN ── */
  payBtn.addEventListener('click', () => {
    const amount = customInput.closest('#customWrap').style.display !== 'none'
      ? parseFloat(customInput.value)
      : selectedAmount;
    if (!amount || amount <= 0) return;
    // In production: initiate Stripe checkout
    alert(`Redirecting to Stripe checkout for $${amount.toFixed(2)}…`);
  });

  /* ── TX FILTER ── */
  document.querySelectorAll('.tx-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tx-filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const filter = btn.dataset.filter;
      document.querySelectorAll('#txBody tr').forEach(row => {
        row.style.display = (filter === 'all' || row.dataset.type === filter) ? '' : 'none';
      });
    });
  });
</script>
</body>
</html>