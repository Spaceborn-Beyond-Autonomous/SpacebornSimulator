<?php 
require "auth/session_guard.php";

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DroneSimSaaS — Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <style>
    /* ── TOKENS ── */
    :root {
      --primary:   #10256D;
      --secondary: #EE9346;
      --accent:    #28c840;

      /* DARK (default) */
      --bg:        #0e1117;
      --bg2:       #141820;
      --surface:   #1a1f2e;
      --surface2:  #212637;
      --border:    rgba(255,255,255,0.06);
      --text:      #e8eaf0;
      --text2:     #8b92a8;
      --text3:     #5a6078;

      /* Neumorphic shadows - dark */
      --neu-shadow-out:  6px 6px 14px #080b12, -4px -4px 10px #222840;
      --neu-shadow-in:   inset 4px 4px 10px #080b12, inset -3px -3px 8px #222840;
      --neu-shadow-btn:  3px 3px 8px #080b12, -2px -2px 6px #222840;

      --sidebar-w: 220px;
      --radius: 14px;
    }

    [data-theme="light"] {
      --bg:        #e8eaf0;
      --bg2:       #dde0ea;
      --surface:   #eaecf4;
      --surface2:  #f0f2f8;
      --border:    rgba(0,0,0,0.06);
      --text:      #1a1f35;
      --text2:     #5a6078;
      --text3:     #9099b8;

      --neu-shadow-out:  6px 6px 14px #c8cad4, -4px -4px 10px #ffffff;
      --neu-shadow-in:   inset 4px 4px 10px #c8cad4, inset -3px -3px 8px #ffffff;
      --neu-shadow-btn:  3px 3px 8px #c8cad4, -2px -2px 6px #ffffff;
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

    /* ── SIDEBAR ── */
    .sidebar {
      width: var(--sidebar-w);
      background: var(--bg2);
      display: flex;
      flex-direction: column;
      padding: 24px 16px;
      gap: 6px;
      position: fixed;
      top: 0; left: 0; bottom: 0;
      box-shadow: 4px 0 20px rgba(0,0,0,0.2);
      z-index: 10;
      transition: background 0.3s;
    }

    .sidebar-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 12px 20px;
      border-bottom: 1px solid var(--border);
      margin-bottom: 8px;
    }
    .sidebar-logo span {
      font-family: 'Syne', sans-serif;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.04em;
      color: var(--primary);
      [data-theme="light"] & { color: var(--primary); }
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 14px;
      border-radius: 10px;
      color: var(--text2);
      font-size: 13.5px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      border: none;
      background: transparent;
      width: 100%;
    }
    .nav-item svg { flex-shrink: 0; opacity: 0.7; }
    .nav-item:hover { background: var(--surface); color: var(--text); }
    .nav-item:hover svg { opacity: 1; }
    .nav-item.active {
      background: var(--neu-shadow-out);
      box-shadow: var(--neu-shadow-out);
      color: var(--secondary);
      font-weight: 600;
    }
    .nav-item.active svg { opacity: 1; color: var(--secondary); }

    .sidebar-section-label {
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 0.1em;
      color: var(--text3);
      padding: 14px 14px 4px;
      text-transform: uppercase;
    }

    .sidebar-bottom {
      margin-top: auto;
      padding-top: 14px;
      border-top: 1px solid var(--border);
    }
    .user-chip {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 10px;
      background: var(--surface); box-shadow: var(--neu-shadow-in);
    }
    .user-actions {
      margin-left: auto; display: flex; gap: 4px; flex-shrink: 0;
    }
    .user-action-btn {
      width: 28px; height: 28px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      color: var(--text3); text-decoration: none;
      transition: background 0.18s, color 0.18s;
    }
    .user-action-btn:hover { background: var(--surface2); color: var(--text); }
    .user-action-btn.logout:hover { background: rgba(224,85,85,0.12); color: #e05555; }


    .avatar {
      width: 32px; height: 32px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700; color: white;
      flex-shrink: 0;
    }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 11px; color: var(--text3); }

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
      font-size: 20px;
      font-weight: 700;
      letter-spacing: -0.02em;
    }
    .topbar-right { display: flex; align-items: center; gap: 12px; }

    /* Dark mode toggle */
    .theme-toggle {
      width: 44px; height: 24px;
      background: var(--surface);
      box-shadow: var(--neu-shadow-in);
      border-radius: 12px;
      position: relative;
      cursor: pointer;
      border: none;
      transition: all 0.3s;
      flex-shrink: 0;
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
      box-shadow: var(--neu-shadow-btn);
      border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: var(--text2);
      transition: all 0.2s;
    }
    .topbar-icon-btn:hover { color: var(--text); box-shadow: var(--neu-shadow-out); }

    /* ── CONTENT ── */
    .content {
      padding: 28px 32px;
      display: flex;
      flex-direction: column;
      gap: 24px;
      flex: 1;
    }

    /* ── STAT CARDS ── */
    .stat-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
    }
    .stat-card {
      background: var(--surface);
      border-radius: var(--radius);
      box-shadow: var(--neu-shadow-out);
      padding: 22px 24px;
      transition: box-shadow 0.2s, transform 0.2s;
      cursor: default;
    }
    .stat-card:hover {
      box-shadow: 8px 8px 20px #050810, -5px -5px 14px #252d48;
      transform: translateY(-2px);
    }
    [data-theme="light"] .stat-card:hover {
      box-shadow: 8px 8px 20px #b8bac6, -5px -5px 14px #ffffff;
    }
    .stat-label {
      font-size: 10.5px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text3);
      margin-bottom: 10px;
    }
    .stat-value {
      font-family: 'Syne', sans-serif;
      font-size: 28px;
      font-weight: 700;
      letter-spacing: -0.03em;
      line-height: 1;
      margin-bottom: 6px;
    }
    .stat-value.green { color: var(--accent); }
    .stat-value.orange { color: var(--secondary); }
    .stat-value.blue { color: #5b8def; }
    .stat-sub { font-size: 12px; color: var(--text3); }

    /* ── BANNER ── */
    .banner {
      background: linear-gradient(135deg, var(--primary) 0%, #1a3a8f 100%);
      border-radius: var(--radius);
      padding: 24px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: var(--neu-shadow-out);
    }
    .banner-text h3 {
      font-family: 'Syne', sans-serif;
      font-size: 17px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 4px;
    }
    .banner-text p { font-size: 13px; color: rgba(255,255,255,0.65); }
    .btn-primary {
      background: var(--secondary);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 11px 22px;
      font-size: 13.5px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(238,147,70,0.4);
      transition: opacity 0.2s, transform 0.15s;
      white-space: nowrap;
      font-family: 'DM Sans', sans-serif;
    }
    .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }

    /* ── TWO COLUMN LOWER ── */
    .lower-row {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 20px;
      align-items: start;
    }

    /* ── SESSIONS TABLE ── */
    .panel {
      background: var(--surface);
      border-radius: var(--radius);
      box-shadow: var(--neu-shadow-out);
      overflow: hidden;
    }
    .panel-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 22px 14px;
      border-bottom: 1px solid var(--border);
    }
    .panel-title {
      font-family: 'Syne', sans-serif;
      font-size: 14px;
      font-weight: 700;
    }
    .view-all {
      font-size: 12px;
      color: var(--secondary);
      text-decoration: none;
      font-weight: 500;
    }
    .view-all:hover { text-decoration: underline; }

    .session-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 22px;
      border-bottom: 1px solid var(--border);
      transition: background 0.15s;
    }
    .session-item:last-child { border-bottom: none; }
    .session-item:hover { background: var(--surface2); }

    .session-left { display: flex; align-items: center; gap: 14px; }
    .session-icon {
      width: 36px; height: 36px;
      border-radius: 10px;
      background: var(--bg);
      box-shadow: var(--neu-shadow-in);
      display: flex; align-items: center; justify-content: center;
      color: var(--secondary);
      flex-shrink: 0;
    }
    .session-name { font-size: 13.5px; font-weight: 600; margin-bottom: 2px; }
    .session-meta { font-size: 11.5px; color: var(--text3); }
    .session-right { display: flex; align-items: center; gap: 12px; }
    .session-time { font-size: 12px; color: var(--text2); font-variant-numeric: tabular-nums; }
    .badge {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.05em;
      padding: 4px 10px;
      border-radius: 20px;
      text-transform: uppercase;
    }
    .badge-completed { background: rgba(40,200,64,0.12); color: var(--accent); }
    .badge-active    { background: rgba(238,147,70,0.12); color: var(--secondary); }

    /* ── RIGHT PANEL ── */
    .right-col { display: flex; flex-direction: column; gap: 18px; }

    /* Getting Started */
    .checklist-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 0;
      font-size: 13px;
      border-bottom: 1px solid var(--border);
    }
    .checklist-item:last-child { border-bottom: none; }
    .checklist-item.done { color: var(--text3); text-decoration: line-through; }
    .check-circle {
      width: 18px; height: 18px;
      border-radius: 50%;
      flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
    }
    .check-circle.done { background: rgba(40,200,64,0.15); color: var(--accent); }
    .check-circle.todo { border: 1.5px solid var(--border); }

    .progress-bar-wrap {
      background: var(--surface2);
      box-shadow: var(--neu-shadow-in);
      border-radius: 99px;
      height: 6px;
      overflow: hidden;
      margin: 8px 0 4px;
    }
    .progress-bar-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--accent), #1be050);
      border-radius: 99px;
      width: 60%;
      transition: width 0.6s ease;
    }

    /* Warning */
    .warn-box {
      background: rgba(238,147,70,0.08);
      border: 1px solid rgba(238,147,70,0.2);
      border-radius: 10px;
      padding: 12px 14px;
      font-size: 12.5px;
      display: flex;
      gap: 10px;
      align-items: flex-start;
    }
    .warn-icon { color: var(--secondary); margin-top: 1px; flex-shrink: 0; }
    .warn-text strong { display: block; color: var(--secondary); font-size: 12.5px; margin-bottom: 2px; }
    .warn-link { color: var(--secondary); text-decoration: underline; cursor: pointer; }

    /* Weekly Usage */
    .usage-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 6px;
      align-items: end;
      height: 60px;
    }
    .usage-bar {
      border-radius: 4px;
      background: var(--accent);
      opacity: 0.6;
      transition: opacity 0.2s;
      cursor: default;
      min-height: 8px;
    }
    .usage-bar:hover { opacity: 1; }
    .usage-labels {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 6px;
      margin-top: 4px;
    }
    .usage-label { font-size: 9.5px; color: var(--text3); text-align: center; }

    /* Panel body padding */
    .panel-body { padding: 16px 22px; }

    /* Progress text */
    .progress-text { font-size: 11px; color: var(--text3); }

    /* ── RESPONSIVE ── */
    @media (max-width: 1100px) {
      .stat-row { grid-template-columns: repeat(2,1fr); }
      .lower-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 720px) {
      .sidebar { display: none; }
      .main { margin-left: 0; }
      .content { padding: 20px 16px; }
      .topbar { padding: 14px 16px; }
      .stat-row { grid-template-columns: repeat(2,1fr); gap: 12px; }
    }

    /* Fade in */
    .fade-up {
      opacity: 0;
      transform: translateY(16px);
      animation: fadeUp 0.45s ease forwards;
    }
    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }
    .delay-1 { animation-delay: 0.05s; }
    .delay-2 { animation-delay: 0.12s; }
    .delay-3 { animation-delay: 0.19s; }
    .delay-4 { animation-delay: 0.26s; }
    .delay-5 { animation-delay: 0.33s; }
    .delay-6 { animation-delay: 0.40s; }
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

<!-- ── SIDEBAR ── -->
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
    <span>DRONESIM</span>
  </div>

  <a class="nav-item active" href="dashboard.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a class="nav-item" href="new-session.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New Simulation
  </a>
  <a class="nav-item" href="simulations.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5,3 19,12 5,21"/></svg>
    Simulations
  </a>
  <a class="nav-item" href="transactions.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    Transactions
  </a>

  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="avatar"><?php echo strtoupper(substr(trim($_SESSION['name'] ?? 'U'), 0, 1)); ?></div>
      <div class="user-info">
        <div class="user-name"><?php echo htmlspecialchars($name); ?></div>
        <div class="user-role"><?php echo htmlspecialchars(($_SESSION['user_sub']['plan_name'] ?? 'Free') . ' plan'); ?></div>
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

<!-- ── MAIN ── -->
<main class="main">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-title">Dashboard</div>
    <div class="topbar-right">
      <!-- Dark mode toggle -->
      <span class="theme-icon" id="themeIcon">🌙</span>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode"></button>

      <button class="topbar-icon-btn" aria-label="Notifications">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </button>
    </div>
  </header>

  <!-- CONTENT -->
  <div class="content">

    <!-- STAT CARDS -->
    <div class="stat-row">
      <div class="stat-card fade-up delay-1">
        <div class="stat-label">Active Sessions</div>
        <div class="stat-value green">3</div>
        <div class="stat-sub">Running right now</div>
      </div>
      <div class="stat-card fade-up delay-2">
        <div class="stat-label">Total Flight Hours</div>
        <div class="stat-value blue">124.5h</div>
        <div class="stat-sub">Across 42 sessions</div>
      </div>
      <div class="stat-card fade-up delay-3">
        <div class="stat-label">This Week</div>
        <div class="stat-value" style="color:var(--text)">8</div>
        <div class="stat-sub">Sessions launched</div>
      </div>
      <div class="stat-card fade-up delay-4">
        <div class="stat-label">Plan Status</div>
        <div class="stat-value" style="color:var(--accent)">Active</div>
        <div class="stat-sub">Unlimited simulations</div>
      </div>
    </div>

    <!-- BANNER -->
    <div class="banner fade-up delay-5">
      <div class="banner-text">
        <h3>Ready for your next flight?</h3>
        <p>Configure a new simulation session with your choice of drone, environment, and weather.</p>
      </div>
      <button class="btn-primary">+ Start New Session</button>
    </div>

    <!-- LOWER ROW -->
    <div class="lower-row fade-up delay-6">

      <!-- Recent Sessions -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">Recent Sessions</div>
          <a class="view-all" href="#">View all →</a>
        </div>

        <div class="session-item">
          <div class="session-left">
            <div class="session-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </div>
            <div>
              <div class="session-name">Campus Mapping</div>
              <div class="session-meta">DJI Mavic 3 Enterprise · Today, 10:45 AM</div>
            </div>
          </div>
          <div class="session-right">
            <span class="session-time">24m 12s</span>
            <span class="badge badge-completed">Completed</span>
          </div>
        </div>

        <div class="session-item">
          <div class="session-left">
            <div class="session-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </div>
            <div>
              <div class="session-name">Wind Tolerance Test</div>
              <div class="session-meta">Autel EVO II · Yesterday</div>
            </div>
          </div>
          <div class="session-right">
            <span class="session-time">18m 05s</span>
            <span class="badge badge-completed">Completed</span>
          </div>
        </div>

        <div class="session-item">
          <div class="session-left">
            <div class="session-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </div>
            <div>
              <div class="session-name">FPV Racing Track 4</div>
              <div class="session-meta">DJI FPV · May 12</div>
            </div>
          </div>
          <div class="session-right">
            <span class="session-time">45m 38s</span>
            <span class="badge badge-completed">Completed</span>
          </div>
        </div>

        <div class="session-item">
          <div class="session-left">
            <div class="session-icon" style="color:var(--accent)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5,3 19,12 5,21"/></svg>
            </div>
            <div>
              <div class="session-name">Precision Landing — Urban</div>
              <div class="session-meta">DJI Mini 4 Pro · May 10</div>
            </div>
          </div>
          <div class="session-right">
            <span class="session-time">11m 50s</span>
            <span class="badge badge-completed">Completed</span>
          </div>
        </div>
      </div>

      <!-- Right column -->
      <div class="right-col">

        <!-- Getting Started -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">Getting Started</div>
          </div>
          <div class="panel-body">
            <div class="progress-bar-wrap"><div class="progress-bar-fill"></div></div>
            <div class="progress-text" style="margin-bottom:10px">3 / 5 complete</div>

            <div class="checklist-item done">
              <div class="check-circle done">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20,6 9,17 4,12"/></svg>
              </div>
              Create an account
            </div>
            <div class="checklist-item done">
              <div class="check-circle done">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20,6 9,17 4,12"/></svg>
              </div>
              Configure drone profile
            </div>
            <div class="checklist-item done">
              <div class="check-circle done">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20,6 9,17 4,12"/></svg>
              </div>
              Launch first simulation
            </div>
            <div class="checklist-item">
              <div class="check-circle todo"></div>
              Invite a team member
            </div>
            <div class="checklist-item">
              <div class="check-circle todo"></div>
              Export session data
            </div>
          </div>
        </div>

        <!-- Weekly Usage -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">Weekly Usage</div>
          </div>
          <div class="panel-body">
            <div class="usage-grid">
              <div class="usage-bar" style="height:55%"></div>
              <div class="usage-bar" style="height:80%"></div>
              <div class="usage-bar" style="height:100%"></div>
              <div class="usage-bar" style="height:40%"></div>
              <div class="usage-bar" style="height:70%"></div>
              <div class="usage-bar" style="height:65%"></div>
              <div class="usage-bar" style="height:30%; opacity:0.3"></div>
            </div>
            <div class="usage-labels">
              <div class="usage-label">Mon</div>
              <div class="usage-label">Tue</div>
              <div class="usage-label">Wed</div>
              <div class="usage-label">Thu</div>
              <div class="usage-label">Fri</div>
              <div class="usage-label">Sat</div>
              <div class="usage-label">Sun</div>
            </div>
          </div>
        </div>

      </div><!-- /right-col -->
    </div><!-- /lower-row -->

  </div><!-- /content -->
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
</script>
</body>
</html>