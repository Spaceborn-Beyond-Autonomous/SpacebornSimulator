<?php
require 'auth/session_guard.php';

$name    = $_SESSION['name'] ?? 'User';
$plan    = $_SESSION['user_sub']['plan_name'] ?? 'Free';

// Transaction history — replace with real MongoDB query
$transactions = [
  [
    'date'        => '15/5/2026, 9:51:44 PM',
    'type'        => 'top-up',
    'description' => 'Wallet Top Up (Visa •••• 4242)',
    'amount'      => '+$50.00',
    'status'      => 'completed',
    'amount_val'  => 50.00,
  ],
  [
    'date'        => '18/5/2026, 9:51:44 PM',
    'type'        => 'debit',
    'description' => 'Session: FPV Racing Track 4',
    'amount'      => '$5.46',
    'status'      => 'completed',
    'amount_val'  => -5.46,
  ],
  [
    'date'        => '19/5/2026, 9:51:44 PM',
    'type'        => 'debit',
    'description' => 'Session: Wind Tolerance Test',
    'amount'      => '$2.17',
    'status'      => 'completed',
    'amount_val'  => -2.17,
  ],
  [
    'date'        => '20/5/2026, 9:51:44 PM',
    'type'        => 'debit',
    'description' => 'Session: Campus Mapping',
    'amount'      => '$2.90',
    'status'      => 'completed',
    'amount_val'  => -2.90,
  ],
];

$total_spent    = array_sum(array_map(fn($t) => $t['amount_val'] < 0 ? abs($t['amount_val']) : 0, $transactions));
$total_toppedup = array_sum(array_map(fn($t) => $t['amount_val'] > 0 ? $t['amount_val'] : 0, $transactions));
$total_count    = count($transactions);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>DroneSimSaaS — Transactions</title>
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
    .sidebar {
      width: var(--sidebar-w); background: var(--bg2);
      display: flex; flex-direction: column;
      padding: 24px 16px; gap: 4px;
      position: fixed; top: 0; left: 0; bottom: 0;
      box-shadow: 4px 0 20px rgba(0,0,0,0.25);
      z-index: 20; transition: background 0.3s;
    }
    .sidebar-logo { display: flex; align-items: center; gap: 10px; padding: 6px 12px 20px; border-bottom: 1px solid var(--border); margin-bottom: 6px; }
    .sidebar-logo-text { font-family: 'Syne', sans-serif; font-size: 12.5px; font-weight: 700; letter-spacing: 0.05em; color: var(--primary); }
    .nav-item {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 14px; border-radius: 10px;
      color: var(--text2); font-size: 13.5px; font-weight: 500;
      cursor: pointer; transition: all 0.18s;
      text-decoration: none; border: none; background: transparent; width: 100%;
    }
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
    .content { padding: 28px 32px; display: flex; flex-direction: column; gap: 24px; flex: 1; }

    /* ── PAGE HEADER ── */
    .page-header h1 { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 4px; }
    .page-header p { font-size: 13px; color: var(--text2); }

    /* ── SUMMARY CARDS ── */
    .summary-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .summary-card { background: var(--surface); border-radius: var(--r); box-shadow: var(--neu-out); padding: 22px 24px; }
    .summary-label { font-size: 10.5px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text3); margin-bottom: 10px; }
    .summary-value { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 700; letter-spacing: -0.03em; line-height: 1; }
    .summary-value.green  { color: var(--accent); }
    .summary-value.orange { color: var(--secondary); }
    .summary-value.blue   { color: var(--blue); }

    /* ── TRANSACTION PANEL ── */
    .tx-panel { background: var(--surface); border-radius: var(--r); box-shadow: var(--neu-out); overflow: hidden; }
    .tx-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 16px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 12px; }
    .tx-title { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; }
    .tx-filter-row { display: flex; gap: 6px; }
    .tx-filter-btn { padding: 6px 14px; border-radius: 8px; border: none; font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 600; cursor: pointer; background: var(--surface2); color: var(--text2); transition: all 0.18s; }
    .tx-filter-btn.active { background: var(--secondary); color: #fff; box-shadow: 0 3px 10px rgba(238,147,70,0.3); }
    .tx-filter-btn:hover:not(.active) { color: var(--text); background: var(--bg2); }

    /* Search bar */
    .tx-search-wrap { padding: 14px 24px; border-bottom: 1px solid var(--border); }
    .tx-search { width: 100%; padding: 10px 16px; border: none; border-radius: 8px; background: var(--bg2); box-shadow: var(--neu-in); color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none; transition: box-shadow 0.2s; }
    .tx-search::placeholder { color: var(--text3); }
    .tx-search:focus { box-shadow: var(--neu-in), 0 0 0 2px rgba(238,147,70,0.2); }

    /* Table */
    .tx-table { width: 100%; border-collapse: collapse; }
    .tx-table thead th { padding: 11px 24px; text-align: left; font-size: 10.5px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text3); border-bottom: 1px solid var(--border); background: var(--bg2); }
    .tx-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
    .tx-table tbody tr:last-child { border-bottom: none; }
    .tx-table tbody tr:hover { background: var(--surface2); }
    .tx-table td { padding: 14px 24px; font-size: 13px; vertical-align: middle; }
    .tx-type-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
    .tx-type-badge.topup  { background: rgba(91,141,239,0.15); color: var(--blue); }
    .tx-type-badge.debit  { background: rgba(238,147,70,0.12); color: var(--secondary); }
    .tx-amount { font-weight: 600; font-variant-numeric: tabular-nums; font-size: 13.5px; }
    .tx-amount.positive { color: var(--accent); }
    .tx-amount.negative { color: var(--secondary); }
    .tx-status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
    .tx-status-badge.completed { background: rgba(40,200,64,0.12); color: var(--accent); }
    .tx-status-badge.pending   { background: rgba(238,147,70,0.12); color: var(--secondary); }
    .tx-status-badge.failed    { background: rgba(224,85,85,0.12);  color: var(--red); }

    /* Empty state */
    .tx-empty { padding: 48px 24px; text-align: center; color: var(--text3); font-size: 13px; }
    .tx-empty svg { opacity: 0.3; margin-bottom: 12px; }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) { .summary-row { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 720px) { .sidebar { display: none; } .main { margin-left: 0; } .content { padding: 20px 16px; } .topbar { padding: 14px 16px; } .summary-row { grid-template-columns: 1fr 1fr; } .tx-table thead { display: none; } .tx-table td { display: block; padding: 6px 16px; } .tx-table tr { border-bottom: 1px solid var(--border); padding: 10px 0; display: block; } }

    /* Fade in */
    .fade-up { opacity: 0; transform: translateY(14px); animation: fadeUp 0.4s ease forwards; }
    @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
    .d1 { animation-delay: 0.04s; } .d2 { animation-delay: 0.10s; } .d3 { animation-delay: 0.17s; }
  </style>
  <script>
    /* Apply saved theme before first paint to avoid flash */
    (function(){
      var t = localStorage.getItem('sb_theme') || 'dark';
      document.documentElement.setAttribute('data-theme', t);
    })();
  </script>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
      <circle cx="16" cy="16" r="4" fill="#10256D"/>
      <rect x="6" y="14" width="8" height="4" rx="2" fill="#10256D" opacity="0.7"/>
      <rect x="18" y="14" width="8" height="4" rx="2" fill="#10256D" opacity="0.7"/>
      <rect x="14" y="6" width="4" height="8" rx="2" fill="#10256D" opacity="0.7"/>
      <rect x="14" y="18" width="4" height="8" rx="2" fill="#10256D" opacity="0.7"/>
      <circle cx="7" cy="7" r="3" fill="#EE9346"/>
      <circle cx="25" cy="7" r="3" fill="#EE9346"/>
      <circle cx="7" cy="25" r="3" fill="#EE9346"/>
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
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New Session
  </a>

  <div class="sidebar-sep">Account</div>
  <a class="nav-item active" href="transactions.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    Transactions
  </a>
  <a class="nav-item" href="#">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Team
  </a>

  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="avatar"><?php echo strtoupper(substr(trim($name), 0, 1)); ?></div>
      <div class="user-info">
        <div class="user-name"><?php echo htmlspecialchars($name); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($plan . ' plan'); ?></div>
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
    <div class="topbar-title">Transactions</div>
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
      <h1>Transactions</h1>
      <p>Your complete payment and session billing history</p>
    </div>

    <!-- Summary stats -->
    <div class="summary-row fade-up d2">
      <div class="summary-card">
        <div class="summary-label">Total Topped Up</div>
        <div class="summary-value green">$<?php echo number_format($total_toppedup, 2); ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-label">Total Spent</div>
        <div class="summary-value orange">$<?php echo number_format($total_spent, 2); ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-label">All Transactions</div>
        <div class="summary-value blue"><?php echo $total_count; ?></div>
      </div>
    </div>

    <!-- Transaction table -->
    <div class="tx-panel fade-up d3">
      <div class="tx-header">
        <div class="tx-title">History</div>
        <div class="tx-filter-row">
          <button class="tx-filter-btn active" data-filter="all">All</button>
          <button class="tx-filter-btn" data-filter="top-up">Top-ups</button>
          <button class="tx-filter-btn" data-filter="debit">Debits</button>
        </div>
      </div>

      <div class="tx-search-wrap">
        <input class="tx-search" id="txSearch" type="text" placeholder="Search by description…" autocomplete="off"/>
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
          <tr data-type="<?php echo htmlspecialchars($tx['type']); ?>"
              data-desc="<?php echo strtolower(htmlspecialchars($tx['description'])); ?>">
            <td style="color:var(--text2);font-size:12px"><?php echo htmlspecialchars($tx['date']); ?></td>
            <td>
              <span class="tx-type-badge <?php echo $tx['type'] === 'top-up' ? 'topup' : htmlspecialchars($tx['type']); ?>">
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

      <?php if (empty($transactions)): ?>
      <div class="tx-empty">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" display="block" style="margin:0 auto 12px"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        No transactions yet.
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<script>
  /* ── THEME TOGGLE ── */
  (function() {
    var html = document.documentElement;
    var toggle = document.getElementById('themeToggle');
    var icon   = document.getElementById('themeIcon');

    // Sync icon with current theme on load
    function syncIcon() {
      icon.textContent = html.getAttribute('data-theme') === 'dark' ? '🌙' : '☀';
    }
    syncIcon();

    toggle.addEventListener('click', function() {
      var isDark = html.getAttribute('data-theme') === 'dark';
      var next   = isDark ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      localStorage.setItem('sb_theme', next);
      syncIcon();
    });
  })();

  /* Filter buttons */
  let activeFilter = 'all';
  let searchQuery  = '';

  document.querySelectorAll('.tx-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tx-filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeFilter = btn.dataset.filter;
      applyFilters();
    });
  });

  /* Search */
  document.getElementById('txSearch').addEventListener('input', function() {
    searchQuery = this.value.toLowerCase();
    applyFilters();
  });

  function applyFilters() {
    document.querySelectorAll('#txBody tr').forEach(row => {
      const typeMatch = activeFilter === 'all' || row.dataset.type === activeFilter;
      const descMatch = !searchQuery || row.dataset.desc.includes(searchQuery);
      row.style.display = (typeMatch && descMatch) ? '' : 'none';
    });
  }
</script>
</body>
</html>