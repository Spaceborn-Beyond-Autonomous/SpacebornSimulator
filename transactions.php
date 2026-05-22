<?php
require 'auth/session_guard.php';

$name = $_SESSION['name'] ?? 'User';
$plan = $_SESSION['user_sub']['plan_name'] ?? 'Pro';

// Subscription billing history — replace with real MongoDB query
$invoices = [
  [
    'date'        => '1 May 2026',
    'description' => 'Spaceborn Pro — Monthly Subscription',
    'amount'      => '$29.00',
    'status'      => 'paid',
    'invoice_id'  => 'INV-2026-005',
  ],
  [
    'date'        => '1 Apr 2026',
    'description' => 'Spaceborn Pro — Monthly Subscription',
    'amount'      => '$29.00',
    'status'      => 'paid',
    'invoice_id'  => 'INV-2026-004',
  ],
  [
    'date'        => '1 Mar 2026',
    'description' => 'Spaceborn Pro — Monthly Subscription',
    'amount'      => '$29.00',
    'status'      => 'paid',
    'invoice_id'  => 'INV-2026-003',
  ],
  [
    'date'        => '1 Feb 2026',
    'description' => 'Spaceborn Pro — Monthly Subscription',
    'amount'      => '$29.00',
    'status'      => 'paid',
    'invoice_id'  => 'INV-2026-002',
  ],
  [
    'date'        => '1 Jan 2026',
    'description' => 'Spaceborn Pro — Monthly Subscription',
    'amount'      => '$1.00',
    'status'      => 'paid',
    'invoice_id'  => 'INV-2026-001',
  ],
];

$next_billing = '1 Jun 2026';
$total_paid   = count(array_filter($invoices, fn($i) => $i['status'] === 'paid'));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Spaceborn — Billing</title>
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
    .content { padding: 28px 32px; display: flex; flex-direction: column; gap: 24px; flex: 1; }

    /* ── PAGE HEADER ── */
    .page-header h1 { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 4px; }
    .page-header p { font-size: 13px; color: var(--text2); }

    /* ── PLAN BANNER ── */
    .plan-banner {
      background: var(--surface);
      border-radius: var(--r);
      box-shadow: var(--neu-out);
      padding: 24px 28px;
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
    .plan-banner-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
    .plan-next-label { font-size: 11px; color: var(--text3); }
    .plan-next-date { font-size: 14px; font-weight: 600; color: var(--text); }
    .badge-active { display: inline-flex; align-items: center; gap: 5px; background: rgba(40,200,64,0.12); color: var(--accent); border-radius: 6px; padding: 4px 10px; font-size: 11.5px; font-weight: 600; margin-top: 6px; }
    .badge-active::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); animation: pulse-dot 1.5s ease-in-out infinite; }
    @keyframes pulse-dot { 0%,100%{box-shadow:0 0 0 0 rgba(40,200,64,0.5);} 50%{box-shadow:0 0 0 5px rgba(40,200,64,0);} }

    /* ── SUMMARY CARDS ── */
    .summary-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .summary-card { background: var(--surface); border-radius: var(--r); box-shadow: var(--neu-out); padding: 20px 22px; }
    .summary-label { font-size: 10.5px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text3); margin-bottom: 10px; }
    .summary-value { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 700; letter-spacing: -0.03em; line-height: 1; }
    .summary-value.green  { color: var(--accent); }
    .summary-value.orange { color: var(--secondary); }
    .summary-value.blue   { color: var(--blue); }

    /* ── INVOICE TABLE ── */
    .inv-panel { background: var(--surface); border-radius: var(--r); box-shadow: var(--neu-out); overflow: hidden; }
    .inv-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 16px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 12px; }
    .inv-panel-title { font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; }

    .inv-search-wrap { padding: 12px 24px; border-bottom: 1px solid var(--border); }
    .inv-search { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 9px; padding: 9px 14px; font-family: 'DM Sans', sans-serif; font-size: 13px; color: var(--text); outline: none; box-shadow: var(--neu-in); transition: border-color 0.18s; }
    .inv-search::placeholder { color: var(--text3); }
    .inv-search:focus { border-color: var(--secondary); }

    table.inv-table { width: 100%; border-collapse: collapse; }
    .inv-table thead tr { border-bottom: 1px solid var(--border); }
    .inv-table th { padding: 11px 20px; font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text3); text-align: left; }
    .inv-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
    .inv-table tbody tr:last-child { border-bottom: none; }
    .inv-table tbody tr:hover { background: rgba(255,255,255,0.02); }
    .inv-table td { padding: 14px 20px; font-size: 13px; vertical-align: middle; }

    .inv-id { font-family: 'Syne', sans-serif; font-size: 11.5px; font-weight: 600; color: var(--text3); letter-spacing: 0.03em; }
    .inv-desc { font-size: 13px; color: var(--text); font-weight: 500; }
    .inv-amount { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; color: var(--text); }
    .inv-date { font-size: 12px; color: var(--text2); }

    .status-badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 6px; padding: 4px 10px; font-size: 11.5px; font-weight: 600; }
    .status-badge.paid { background: rgba(40,200,64,0.1); color: var(--accent); }
    .status-badge.pending { background: rgba(238,147,70,0.1); color: var(--secondary); }
    .status-badge.failed { background: rgba(224,85,85,0.1); color: var(--red); }



    .inv-empty { padding: 48px 24px; text-align: center; color: var(--text3); font-size: 13px; }

    /* ── ANIMATIONS ── */
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px);} to{opacity:1;transform:translateY(0);} }
    .fade-up { animation: fadeUp 0.4s cubic-bezier(.22,1,.36,1) both; }
    .d1{animation-delay:.05s;} .d2{animation-delay:.1s;} .d3{animation-delay:.15s;} .d4{animation-delay:.2s;}

    <br>
  </style>
  <script>(function(){var t=localStorage.getItem('sb_theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <svg width="26" height="26" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="4" fill="#10256D"/><rect x="6" y="14" width="8" height="4" rx="2" fill="#10256D" opacity=".7"/><rect x="18" y="14" width="8" height="4" rx="2" fill="#10256D" opacity=".7"/><rect x="14" y="6" width="4" height="8" rx="2" fill="#10256D" opacity=".7"/><rect x="14" y="18" width="4" height="8" rx="2" fill="#10256D" opacity=".7"/><circle cx="7" cy="7" r="3" fill="#EE9346"/><circle cx="25" cy="7" r="3" fill="#EE9346"/><circle cx="7" cy="25" r="3" fill="#EE9346"/><circle cx="25" cy="25" r="3" fill="#EE9346"/></svg>
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
  <a class="nav-item active" href="transactions.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>Transactions
  </a>
  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="avatar"><?= strtoupper(substr(trim($name),0,1)) ?></div>
      <div style="flex:1;min-width:0;">
        <div class="user-name"><?= htmlspecialchars($name) ?></div>
        <div class="user-role"><?= htmlspecialchars($plan) ?> plan</div>
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
    <div class="topbar-title">Billing</div>
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
      <h1>Billing &amp; Subscription</h1>
      <p>Your active plan and monthly payment history</p>
    </div>

    <!-- Plan banner -->
    <div class="plan-banner fade-up d2">
      <div class="plan-banner-left">
        <div class="plan-banner-label">Current Plan</div>
        <div class="plan-banner-name"><?= htmlspecialchars($plan) ?></div>
        <div class="plan-banner-sub">Unlimited simulations · All environments · MAVLink export</div>
        <div class="badge-active">Active</div>
      </div>
      <div class="plan-banner-right">
        <div class="plan-next-label">Next billing date</div>
        <div class="plan-next-date"><?= $next_billing ?></div>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="summary-row fade-up d3">
      <div class="summary-card">
        <div class="summary-label">Plan</div>
        <div class="summary-value orange"><?= htmlspecialchars($plan) ?></div>
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

    <!-- Invoice table -->
    <div class="inv-panel fade-up d4">
      <div class="inv-panel-header">
        <div class="inv-panel-title">Payment History</div>
      </div>

      <div class="inv-search-wrap">
        <input class="inv-search" id="invSearch" type="text" placeholder="Search invoices…" autocomplete="off"/>
      </div>

      <?php if (!empty($invoices)): ?>
      <table class="inv-table" id="invTable">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Date</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="invBody">
          <?php foreach ($invoices as $inv): ?>
          <tr data-desc="<?= strtolower(htmlspecialchars($inv['description'])) ?> <?= strtolower($inv['invoice_id']) ?>">
            <td><span class="inv-id"><?= htmlspecialchars($inv['invoice_id']) ?></span></td>
            <td><span class="inv-date"><?= htmlspecialchars($inv['date']) ?></span></td>
            <td><span class="inv-desc"><?= htmlspecialchars($inv['description']) ?></span></td>
            <td><span class="inv-amount"><?= htmlspecialchars($inv['amount']) ?></span></td>
            <td>
              <span class="status-badge <?= htmlspecialchars($inv['status']) ?>">
                <?= ucfirst(htmlspecialchars($inv['status'])) ?>
              </span>
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

<script>
  /* ── THEME ── */
  (function() {
    var html = document.documentElement;
    var toggle = document.getElementById('themeToggle');
    var icon   = document.getElementById('themeIcon');
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
</script>
</body>
</html>