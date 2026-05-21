<?php

require 'auth/session_guard.php';



$sessions = [
  [
    'id'          => 'sess_001',
    'name'        => 'Campus Mapping',
    'drone'       => 'DJI Mavic 3 Enterprise',
    'environment' => 'Urban',
    'weather'     => 'Clear',
    'mode'        => 'Autonomous',
    'duration'    => '24m 12s',
    'status'      => 'completed',
    'date'        => 'Today, 10:45 AM',
    'log'         => 'logs/sess_001.zip',
  ],
  [
    'id'          => 'sess_002',
    'name'        => 'Wind Tolerance Test',
    'drone'       => 'Autel EVO II',
    'environment' => 'Mountain',
    'weather'     => 'Windy',
    'mode'        => 'Manual',
    'duration'    => '18m 05s',
    'status'      => 'completed',
    'date'        => 'Yesterday',
    'log'         => 'logs/sess_002.zip',
  ],
  [
    'id'          => 'sess_003',
    'name'        => 'FPV Racing Track 4',
    'drone'       => 'DJI FPV',
    'environment' => 'Stadium',
    'weather'     => 'Clear',
    'mode'        => 'FPV',
    'duration'    => '45m 38s',
    'status'      => 'completed',
    'date'        => 'May 12',
    'log'         => 'logs/sess_003.zip',
  ],
  [
    'id'          => 'sess_004',
    'name'        => 'Precision Landing — Urban',
    'drone'       => 'DJI Mini 4 Pro',
    'environment' => 'Urban',
    'weather'     => 'Cloudy',
    'mode'        => 'Autonomous',
    'duration'    => '11m 50s',
    'status'      => 'completed',
    'date'        => 'May 10',
    'log'         => 'logs/sess_004.zip',
  ],
  [
    'id'          => 'sess_005',
    'name'        => 'Night Forest Patrol',
    'drone'       => 'Autel EVO II Pro',
    'environment' => 'Forest',
    'weather'     => 'Night',
    'mode'        => 'Autonomous',
    'duration'    => '33m 21s',
    'status'      => 'failed',
    'date'        => 'May 8',
    'log'         => 'logs/sess_005.zip',
  ],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>DroneSimSaaS — Simulations</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    /* ── TOKENS ──────────────────────────────────── */
    :root {
      --primary:   #10256D;
      --secondary: #EE9346;
      --accent:    #28c840;
      --red:       #e05555;

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
    .sidebar-bottom { margin-top: auto; padding-top: 14px; border-top: 1px solid var(--border); }
    .user-chip { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; background: var(--surface); box-shadow: var(--neu-in); }
    .user-actions { margin-left: auto; display: flex; gap: 4px; flex-shrink: 0; }
    .user-action-btn { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--text3); text-decoration: none; transition: background 0.18s, color 0.18s; }
    .user-action-btn:hover { background: var(--surface2); color: var(--text); }
    .user-action-btn.logout:hover { background: rgba(224,85,85,0.12); color: #e05555; }
    .avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; }
    .user-name { font-size: 12.5px; font-weight: 600; }
    .user-role { font-size: 11px; color: var(--text3); }


    /* ── MAIN ──────────────────────────────────── */
    .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

    /* ── TOPBAR ──────────────────────────────────── */
    .topbar {
      display: flex; align-items: center; justify-content: space-between;
      padding: 18px 32px;
      border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 10;
      background: var(--bg); transition: background 0.3s;
    }
    .topbar-title { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; }
    .topbar-right { display: flex; align-items: center; gap: 12px; }
    .theme-toggle {
      width: 44px; height: 24px; border-radius: 12px;
      background: var(--surface); box-shadow: var(--neu-in);
      border: none; cursor: pointer; position: relative; transition: all 0.3s;
    }
    .theme-toggle::after {
      content: ''; position: absolute;
      top: 3px; left: 3px; width: 18px; height: 18px;
      border-radius: 50%; background: var(--secondary);
      box-shadow: 2px 2px 5px rgba(0,0,0,0.3);
      transition: transform 0.3s;
    }
    [data-theme="light"] .theme-toggle::after { transform: translateX(20px); }
    .icon-btn {
      width: 36px; height: 36px; border-radius: 10px;
      background: var(--surface); box-shadow: var(--neu-btn);
      border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: var(--text2); transition: all 0.18s;
    }
    .icon-btn:hover { color: var(--text); box-shadow: var(--neu-out); }
    .new-session-btn {
      display: flex; align-items: center; gap: 7px;
      background: var(--secondary); color: #fff;
      border: none; border-radius: 10px;
      padding: 9px 18px; font-size: 13px; font-weight: 600;
      cursor: pointer; font-family: 'DM Sans', sans-serif;
      box-shadow: 0 4px 14px rgba(238,147,70,0.35);
      transition: opacity 0.18s, transform 0.15s;
    }
    .new-session-btn:hover { opacity: 0.9; transform: translateY(-1px); }

    /* ── CONTENT ──────────────────────────────────── */
    .content { padding: 28px 32px; flex: 1; display: flex; flex-direction: column; gap: 20px; }

    .page-header { display: flex; align-items: flex-end; justify-content: space-between; }
    .page-header h2 { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; letter-spacing: -0.03em; }
    .page-header p { font-size: 13px; color: var(--text3); margin-top: 4px; }

    /* ── FILTER BAR ──────────────────────────────── */
    .filter-bar {
      display: flex; align-items: center; gap: 12px;
      background: var(--surface); border-radius: var(--r);
      box-shadow: var(--neu-in);
      padding: 12px 18px;
    }
    .search-wrap {
      flex: 1; display: flex; align-items: center; gap: 10px;
    }
    .search-wrap svg { color: var(--text3); flex-shrink: 0; }
    .search-input {
      background: transparent; border: none; outline: none;
      font-family: 'DM Sans', sans-serif; font-size: 13.5px;
      color: var(--text); width: 100%;
    }
    .search-input::placeholder { color: var(--text3); }
    .filter-divider { width: 1px; height: 22px; background: var(--border); }
    .filter-select {
      background: transparent; border: none; outline: none;
      font-family: 'DM Sans', sans-serif; font-size: 13px;
      color: var(--text2); cursor: pointer; padding: 2px 6px;
      appearance: none; -webkit-appearance: none;
    }
    .filter-select option { background: var(--surface2); }
    .select-wrap { display: flex; align-items: center; gap: 4px; }
    .select-wrap svg { color: var(--text3); pointer-events: none; }

    /* ── SESSIONS LIST ──────────────────────────── */
    .sessions-list { display: flex; flex-direction: column; gap: 10px; }

    .session-card {
      background: var(--surface);
      border-radius: var(--r);
      box-shadow: var(--neu-out);
      overflow: hidden;
      transition: box-shadow 0.2s;
    }
    .session-card:hover { box-shadow: 8px 8px 20px #050810, -5px -5px 14px #252d48; }
    [data-theme="light"] .session-card:hover { box-shadow: 8px 8px 20px #b8bac6, -5px -5px 14px #ffffff; }

    .session-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 22px; cursor: pointer; user-select: none;
      transition: background 0.15s;
    }
    .session-row:hover { background: var(--surface2); }

    .session-row-left { display: flex; align-items: center; gap: 14px; }
    .session-drone-icon {
      width: 38px; height: 38px; border-radius: 10px;
      background: var(--bg); box-shadow: var(--neu-in);
      display: flex; align-items: center; justify-content: center;
      color: var(--secondary); flex-shrink: 0;
    }
    .session-card.status-failed .session-drone-icon { color: var(--red); }

    .s-name { font-size: 14px; font-weight: 600; margin-bottom: 3px; }
    .s-meta { font-size: 12px; color: var(--text3); }
    .s-meta span { margin-right: 6px; }

    .session-row-right { display: flex; align-items: center; gap: 14px; }
    .s-duration { font-size: 13px; color: var(--text2); font-variant-numeric: tabular-nums; }
    .s-cost { font-size: 13px; font-weight: 600; color: var(--accent); }
    .s-badge {
      font-size: 10px; font-weight: 700;
      letter-spacing: 0.06em; text-transform: uppercase;
      padding: 4px 11px; border-radius: 20px;
    }
    .badge-completed { background: rgba(40,200,64,0.12); color: var(--accent); }
    .badge-failed    { background: rgba(224,85,85,0.12);  color: var(--red); }
    .badge-running   { background: rgba(238,147,70,0.12); color: var(--secondary); }
    .expand-arrow {
      width: 26px; height: 26px; border-radius: 8px;
      background: var(--surface2); box-shadow: var(--neu-in);
      display: flex; align-items: center; justify-content: center;
      color: var(--text3); transition: transform 0.25s, color 0.18s;
      flex-shrink: 0;
    }
    .session-card.open .expand-arrow { transform: rotate(180deg); color: var(--secondary); }

    /* ── EXPANDED PANEL ──────────────────────────── */
    .session-detail {
      max-height: 0; overflow: hidden;
      transition: max-height 0.35s cubic-bezier(0.4,0,0.2,1);
      border-top: 1px solid transparent;
    }
    .session-card.open .session-detail {
      max-height: 300px;
      border-top-color: var(--border);
    }
    .detail-inner { padding: 20px 22px; display: flex; flex-direction: column; gap: 16px; }

    .detail-grid {
      display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
    }
    .detail-field {}
    .detail-label { font-size: 10px; font-weight: 600; letter-spacing: 0.09em; text-transform: uppercase; color: var(--text3); margin-bottom: 5px; }
    .detail-value { font-size: 14px; font-weight: 500; }
    .detail-value.cost { color: var(--accent); font-weight: 700; }
    .detail-value.failed { color: var(--red); }

    .detail-actions { display: flex; gap: 10px; }
    .btn-outline {
      display: flex; align-items: center; gap: 7px;
      padding: 9px 16px; border-radius: 10px;
      border: 1.5px solid var(--border);
      background: var(--bg); box-shadow: var(--neu-btn);
      color: var(--text2); font-size: 13px; font-weight: 500;
      cursor: pointer; font-family: 'DM Sans', sans-serif;
      transition: all 0.18s; text-decoration: none;
    }
    .btn-outline:hover { color: var(--text); border-color: var(--secondary); box-shadow: var(--neu-out); }
    .btn-solid {
      display: flex; align-items: center; gap: 7px;
      padding: 9px 16px; border-radius: 10px;
      background: var(--secondary); color: #fff;
      border: none; font-size: 13px; font-weight: 600;
      cursor: pointer; font-family: 'DM Sans', sans-serif;
      box-shadow: 0 4px 12px rgba(238,147,70,0.3);
      transition: opacity 0.18s; text-decoration: none;
    }
    .btn-solid:hover { opacity: 0.88; }

    /* ── EMPTY STATE ──────────────────────────── */
    .empty-state {
      text-align: center; padding: 60px 20px;
      background: var(--surface); border-radius: var(--r);
      box-shadow: var(--neu-in); display: none;
    }
    .empty-state svg { color: var(--text3); margin-bottom: 14px; }
    .empty-state h3 { font-family:'Syne',sans-serif; font-size:16px; margin-bottom:6px; }
    .empty-state p { font-size:13px; color:var(--text3); }

    /* ── SUMMARY BAR ──────────────────────────── */
    .summary-bar {
      display: flex; align-items: center; gap: 20px;
      padding: 12px 20px; border-radius: var(--r);
      background: var(--surface); box-shadow: var(--neu-in);
      font-size: 12.5px; color: var(--text2);
      flex-wrap: wrap;
    }
    .summary-item { display: flex; align-items: center; gap: 6px; }
    .summary-dot { width: 7px; height: 7px; border-radius: 50%; }
    .summary-val { font-weight: 600; color: var(--text); }

    /* ── FADE IN ──────────────────────────────── */
    .fade-up { opacity:0; transform:translateY(14px); animation:fu 0.4s ease forwards; }
    @keyframes fu { to { opacity:1; transform:translateY(0); } }
    .d1{animation-delay:.04s} .d2{animation-delay:.10s} .d3{animation-delay:.16s}
    .d4{animation-delay:.22s} .d5{animation-delay:.28s} .d6{animation-delay:.34s}

    /* ── RESPONSIVE ──────────────────────────── */
    @media(max-width:900px) {
      .detail-grid { grid-template-columns: repeat(2,1fr); }
    }
    @media(max-width:720px) {
      .sidebar { display:none; }
      .main { margin-left:0; }
      .content { padding:18px 14px; }
      .topbar { padding:14px 16px; }
      .detail-grid { grid-template-columns: 1fr 1fr; }
    }
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

<!-- ── SIDEBAR ──────────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
      <circle cx="16" cy="16" r="4" fill="#10256D"/>
      <rect x="6"  y="14" width="8" height="4" rx="2" fill="#10256D" opacity=".7"/>
      <rect x="18" y="14" width="8" height="4" rx="2" fill="#10256D" opacity=".7"/>
      <rect x="14" y="6"  width="4" height="8" rx="2" fill="#10256D" opacity=".7"/>
      <rect x="14" y="18" width="4" height="8" rx="2" fill="#10256D" opacity=".7"/>
      <circle cx="7"  cy="7"  r="3" fill="#EE9346"/>
      <circle cx="25" cy="7"  r="3" fill="#EE9346"/>
      <circle cx="7"  cy="25" r="3" fill="#EE9346"/>
      <circle cx="25" cy="25" r="3" fill="#EE9346"/>
    </svg>
    <span class="sidebar-logo-text">DRONESIM</span>
  </div>

  <a class="nav-item" href="dashboard.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a class="nav-item active" href="sessions.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5,3 19,12 5,21"/></svg>
    Simulations
  </a>
  <a class="nav-item" href="new-session.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
    New Session
  </a>

  <div class="sidebar-sep">Account</div>
  <a class="nav-item" href="transactions.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    Billing
  </a>
  <a class="nav-item" href="team.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Team
  </a>
  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="avatar"><?= strtoupper(substr(trim($name), 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($name) ?></div>
        <div class="user-role"><?= htmlspecialchars($_SESSION['user_sub']['plan_name'] . ' plan') ?></div>
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

<!-- ── MAIN ──────────────────────────────────────────── -->
<main class="main">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-title">Simulations</div>
    <div class="topbar-right">
      <span id="themeIcon" style="font-size:13px">🌙</span>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
      <button class="icon-btn" aria-label="Notifications">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </button>
      <button class="new-session-btn" onclick="location.href='new-session.php'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Session
      </button>
    </div>
  </header>

  <!-- CONTENT -->
  <div class="content">

    <!-- Page header -->
    <div class="page-header fade-up d1">
      <div>
        <h2>Sessions</h2>
        <p>All your simulation runs in one place</p>
      </div>
      <button class="new-session-btn" onclick="location.href='new-session.php'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        + New Session
      </button>
    </div>

    <!-- Summary bar -->
    <div class="summary-bar fade-up d2">
      <div class="summary-item">
        <div class="summary-dot" style="background:var(--accent)"></div>
        <span><?= count(array_filter($sessions, fn($s)=>$s['status']==='completed')) ?></span>
        <span class="summary-val">Completed</span>
      </div>
      <div class="summary-item">
        <div class="summary-dot" style="background:var(--red)"></div>
        <span><?= count(array_filter($sessions, fn($s)=>$s['status']==='failed')) ?></span>
        <span class="summary-val">Failed</span>
      </div>
      <div class="summary-item">
        <div class="summary-dot" style="background:var(--secondary)"></div>
        <span><?= count($sessions) ?></span>
        <span class="summary-val">Total Sessions</span>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar fade-up d3">
      <div class="search-wrap">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="search-input" id="searchInput" type="text" placeholder="Search sessions..." autocomplete="off"/>
      </div>
      <div class="filter-divider"></div>
      <div class="select-wrap">
        <select class="filter-select" id="statusFilter">
          <option value="">All Status</option>
          <option value="completed">Completed</option>
          <option value="failed">Failed</option>
          <option value="running">Running</option>
        </select>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>
      </div>
      <div class="filter-divider"></div>
      <div class="select-wrap">
        <select class="filter-select" id="droneFilter">
          <option value="">All Drones</option>
          <?php
            $drones = array_unique(array_column($sessions, 'drone'));
            foreach($drones as $d) echo "<option value=\"".htmlspecialchars($d)."\">".htmlspecialchars($d)."</option>";
          ?>
        </select>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>
      </div>
    </div>

    <!-- Sessions list -->
    <div class="sessions-list fade-up d4" id="sessionsList">

      <?php foreach($sessions as $i => $s): ?>
      <div class="session-card status-<?= $s['status'] ?>"
           data-name="<?= strtolower($s['name']) ?>"
           data-drone="<?= $s['drone'] ?>"
           data-status="<?= $s['status'] ?>">

        <!-- Collapsed row -->
        <div class="session-row" onclick="toggleCard(this)">
          <div class="session-row-left">
            <div class="session-drone-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="2"/>
                <line x1="2" y1="12" x2="8" y2="12"/>
                <line x1="16" y1="12" x2="22" y2="12"/>
                <line x1="12" y1="2" x2="12" y2="8"/>
                <line x1="12" y1="16" x2="12" y2="22"/>
                <circle cx="4" cy="4" r="2"/><circle cx="20" cy="4" r="2"/>
                <circle cx="4" cy="20" r="2"/><circle cx="20" cy="20" r="2"/>
              </svg>
            </div>
            <div>
              <div class="s-name"><?= htmlspecialchars($s['name']) ?></div>
              <div class="s-meta">
                <span><?= htmlspecialchars($s['drone']) ?></span>·
                <span><?= htmlspecialchars($s['environment']) ?></span>·
                <span><?= htmlspecialchars($s['date']) ?></span>
              </div>
            </div>
          </div>
          <div class="session-row-right">
            <span class="s-duration"><?= $s['duration'] ?></span>
            <span class="s-badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span>
            <div class="expand-arrow">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6,9 12,15 18,9"/></svg>
            </div>
          </div>
        </div>

        <!-- Expanded detail -->
        <div class="session-detail">
          <div class="detail-inner">
            <div class="detail-grid">
              <div class="detail-field">
                <div class="detail-label">Drone Model</div>
                <div class="detail-value"><?= htmlspecialchars($s['drone']) ?></div>
              </div>
              <div class="detail-field">
                <div class="detail-label">Environment</div>
                <div class="detail-value"><?= htmlspecialchars($s['environment']) ?></div>
              </div>
              <div class="detail-field">
                <div class="detail-label">Duration</div>
                <div class="detail-value"><?= $s['duration'] ?></div>
              </div>
              <div class="detail-field">
                <div class="detail-label">Weather</div>
                <div class="detail-value"><?= htmlspecialchars($s['weather']) ?></div>
              </div>
              <div class="detail-field">
                <div class="detail-label">Mode</div>
                <div class="detail-value <?= $s['status']==='failed'?'failed':'' ?>"><?= htmlspecialchars($s['mode']) ?></div>
              </div>
            </div>
            <div class="detail-actions">
              <a class="btn-outline" href="session-detail.php?id=<?= $s['id'] ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Details
              </a>
              <a class="btn-solid" href="<?= htmlspecialchars($s['log']) ?>" download>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download Logs
              </a>
            </div>
          </div>
        </div>

      </div>
      <?php endforeach; ?>

    </div><!-- /sessions-list -->

    <!-- Empty state (shown via JS when no results) -->
    <div class="empty-state" id="emptyState">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <h3>No sessions found</h3>
      <p>Try adjusting your search or filter.</p>
    </div>

  </div><!-- /content -->
</main>

<script>
  /* ── THEME TOGGLE ── */
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

  /* ── EXPAND / COLLAPSE ── */
  function toggleCard(row) {
    const card = row.closest('.session-card');
    const isOpen = card.classList.contains('open');
    // Close all others
    document.querySelectorAll('.session-card.open').forEach(c => c.classList.remove('open'));
    if (!isOpen) card.classList.add('open');
  }

  /* ── SEARCH + FILTER ── */
  const searchInput  = document.getElementById('searchInput');
  const statusFilter = document.getElementById('statusFilter');
  const droneFilter  = document.getElementById('droneFilter');
  const emptyState   = document.getElementById('emptyState');

  function filterSessions() {
    const q      = searchInput.value.toLowerCase().trim();
    const status = statusFilter.value;
    const drone  = droneFilter.value;
    let visible  = 0;

    document.querySelectorAll('.session-card').forEach(card => {
      const nameMatch   = !q      || card.dataset.name.includes(q);
      const statusMatch = !status || card.dataset.status === status;
      const droneMatch  = !drone  || card.dataset.drone === drone;
      const show = nameMatch && statusMatch && droneMatch;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    emptyState.style.display = visible === 0 ? 'block' : 'none';
  }

  searchInput.addEventListener('input', filterSessions);
  statusFilter.addEventListener('change', filterSessions);
  droneFilter.addEventListener('change', filterSessions);
</script>
</body>
</html>