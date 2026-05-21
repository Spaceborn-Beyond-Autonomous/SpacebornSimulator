<?php
require 'auth/session_guard.php';

$name     = htmlspecialchars($_SESSION['name']    ?? 'Demo Pilot');
$email    = htmlspecialchars($_SESSION['email']   ?? 'pilot@example.com');
$initials = strtoupper(substr(trim($name), 0, 1));
$plan     = htmlspecialchars($_SESSION['user_sub']['plan_name'] ?? 'Free');

// Handle form saves
$profile_saved = false;
$password_saved = false;
$notif_saved    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['save_profile'])) {
    // TODO: persist profile changes
    $name  = htmlspecialchars($_POST['full_name'] ?? $name);
    $email = htmlspecialchars($_POST['email']     ?? $email);
    $profile_saved = true;
  } elseif (isset($_POST['save_password'])) {
    // TODO: validate & update password
    $password_saved = true;
  } elseif (isset($_POST['save_notifications'])) {
    // TODO: persist notification prefs
    $notif_saved = true;
  }
}

// Notification prefs — read from DB/session; defaults off
$notif = [
  'low_balance'    => isset($_SESSION['notif_low_balance'])    ? (bool)$_SESSION['notif_low_balance']    : false,
  'session_start'  => isset($_SESSION['notif_session_start'])  ? (bool)$_SESSION['notif_session_start']  : false,
  'session_end'    => isset($_SESSION['notif_session_end'])    ? (bool)$_SESSION['notif_session_end']    : false,
  'weekly_report'  => isset($_SESSION['notif_weekly_report'])  ? (bool)$_SESSION['notif_weekly_report']  : false,
  'team_invite'    => isset($_SESSION['notif_team_invite'])    ? (bool)$_SESSION['notif_team_invite']    : false,
];

$active_tab = $_GET['tab'] ?? 'profile';
if (!in_array($active_tab, ['profile', 'notifications'])) $active_tab = 'profile';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DroneSimSaaS — Settings</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <style>
    /* ── TOKENS ── */
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

      --neu-out:  6px 6px 14px #080b12, -4px -4px 10px #222840;
      --neu-in:   inset 4px 4px 10px #080b12, inset -3px -3px 8px #222840;
      --neu-btn:  3px 3px 8px #080b12, -2px -2px 6px #222840;

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

    /* ── SIDEBAR ── */
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

    /* Settings nav item pinned just above bottom */
    .sidebar-spacer { flex: 1; }

    .sidebar-bottom {
      padding-top: 14px;
      border-top: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .user-chip {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 10px;
      background: var(--surface); box-shadow: var(--neu-in);
    }
    .user-actions { margin-left: auto; display: flex; gap: 4px; flex-shrink: 0; }
    .user-action-btn {
      width: 28px; height: 28px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      color: var(--text3); text-decoration: none;
      transition: background 0.18s, color 0.18s;
    }
    .user-action-btn:hover { background: var(--surface2); color: var(--text); }
    .user-action-btn.logout:hover { background: rgba(224,85,85,0.12); color: var(--red); }
    .avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .user-name { font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 11px; color: var(--text3); }

    /* ── MAIN ── */
    .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

    /* ── TOPBAR ── */
    .topbar {
      display: flex; align-items: center; justify-content: space-between;
      padding: 18px 32px;
      border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 10;
      background: var(--bg); transition: background 0.3s;
    }
    .topbar-title { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; }
    .topbar-right { display: flex; align-items: center; gap: 12px; }
    .theme-icon { font-size: 14px; line-height: 1; }
    .theme-toggle {
      width: 44px; height: 24px; border-radius: 12px;
      background: var(--surface); box-shadow: var(--neu-in);
      border: none; cursor: pointer; position: relative; transition: all 0.3s;
      flex-shrink: 0;
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

    /* ── CONTENT ── */
    .content { padding: 32px; flex: 1; display: flex; flex-direction: column; gap: 24px; }

    .page-title {
      font-family: 'Syne', sans-serif;
      font-size: 26px; font-weight: 800;
      letter-spacing: -0.03em;
    }

    /* ── TABS ── */
    .tabs {
      display: flex;
      gap: 2px;
      border-bottom: 1px solid var(--border);
      padding-bottom: 0;
    }
    .tab-btn {
      background: none; border: none; cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px; font-weight: 500;
      color: var(--text3);
      padding: 10px 20px;
      position: relative;
      transition: color 0.18s;
      border-bottom: 2px solid transparent;
      margin-bottom: -1px;
      text-decoration: none;
      display: inline-block;
    }
    .tab-btn:hover { color: var(--text2); }
    .tab-btn.active {
      color: var(--secondary);
      border-bottom-color: var(--secondary);
      font-weight: 600;
    }

    /* ── CARD ── */
    .settings-card {
      background: var(--surface);
      border-radius: var(--r);
      box-shadow: var(--neu-out);
      padding: 28px 32px;
      display: flex;
      flex-direction: column;
      gap: 24px;
      animation: fadeUp 0.35s ease both;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .card-section-title {
      font-family: 'Syne', sans-serif;
      font-size: 16px; font-weight: 700;
      letter-spacing: -0.01em;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--border);
    }

    /* ── PROFILE AVATAR ── */
    .profile-head {
      display: flex; align-items: center; gap: 18px;
    }
    .profile-avatar {
      width: 56px; height: 56px; border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; font-weight: 700; color: #fff;
      box-shadow: var(--neu-out);
      flex-shrink: 0;
    }
    .profile-head-info .profile-name { font-size: 15px; font-weight: 700; }
    .profile-head-info .profile-email { font-size: 12.5px; color: var(--text3); margin-top: 2px; }

    /* ── FORM ── */
    .form-grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
    }
    .form-group { display: flex; flex-direction: column; gap: 7px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-label {
      font-size: 12px; font-weight: 600; letter-spacing: 0.04em;
      color: var(--text3); text-transform: uppercase;
    }
    .form-input, .form-select {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 10px 14px;
      font-family: 'DM Sans', sans-serif;
      font-size: 13.5px;
      color: var(--text);
      outline: none;
      box-shadow: var(--neu-in);
      transition: border-color 0.18s, box-shadow 0.18s;
      width: 100%;
    }
    .form-input::placeholder { color: var(--text3); }
    .form-input:focus, .form-select:focus {
      border-color: var(--secondary);
      box-shadow: var(--neu-in), 0 0 0 2px rgba(238,147,70,0.12);
    }
    .form-select { cursor: pointer; appearance: none; -webkit-appearance: none; }
    .form-select option { background: var(--surface2); }

    /* ── DIVIDER ── */
    .card-divider { height: 1px; background: var(--border); margin: 4px 0; }

    /* ── BUTTONS ── */
    .btn-primary {
      display: inline-flex; align-items: center; gap: 8px;
      background: var(--secondary); color: #fff;
      border: none; border-radius: 10px;
      padding: 10px 22px; font-size: 13.5px; font-weight: 600;
      cursor: pointer; font-family: 'DM Sans', sans-serif;
      box-shadow: 0 4px 14px rgba(238,147,70,0.35);
      transition: opacity 0.18s, transform 0.15s;
      text-decoration: none;
    }
    .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }

    .btn-outline {
      display: inline-flex; align-items: center; gap: 8px;
      background: transparent; color: var(--secondary);
      border: 1px solid var(--secondary); border-radius: 10px;
      padding: 9px 20px; font-size: 13.5px; font-weight: 600;
      cursor: pointer; font-family: 'DM Sans', sans-serif;
      transition: background 0.18s;
    }
    .btn-outline:hover { background: rgba(238,147,70,0.08); }

    /* ── SUCCESS TOAST ── */
    .toast {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(40,200,64,0.1);
      border: 1px solid rgba(40,200,64,0.25);
      border-radius: 8px;
      padding: 8px 14px;
      font-size: 12.5px; font-weight: 500;
      color: var(--accent);
      animation: fadeUp 0.3s ease both;
    }

    /* ── NOTIFICATION ROWS ── */
    .notif-list { display: flex; flex-direction: column; gap: 0; }

    .notif-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 0;
      border-bottom: 1px solid var(--border);
    }
    .notif-row:last-child { border-bottom: none; }
    .notif-info { flex: 1; min-width: 0; }
    .notif-label { font-size: 13.5px; font-weight: 600; margin-bottom: 3px; }
    .notif-desc  { font-size: 12px; color: var(--text3); }

    /* Toggle switch */
    .toggle-wrap { flex-shrink: 0; margin-left: 20px; }
    .toggle-input { display: none; }
    .toggle-track {
      width: 42px; height: 23px;
      background: var(--surface2);
      box-shadow: var(--neu-in);
      border-radius: 12px;
      position: relative;
      cursor: pointer;
      transition: background 0.25s;
      display: block;
    }
    .toggle-track::after {
      content: '';
      position: absolute;
      top: 3px; left: 3px;
      width: 17px; height: 17px;
      border-radius: 50%;
      background: var(--text3);
      box-shadow: 1px 1px 4px rgba(0,0,0,0.35);
      transition: transform 0.25s, background 0.25s;
    }
    .toggle-input:checked + .toggle-track {
      background: rgba(238,147,70,0.2);
    }
    .toggle-input:checked + .toggle-track::after {
      transform: translateX(19px);
      background: var(--secondary);
    }

    /* ── PASSWORD STRENGTH ── */
    .pw-strength-bar {
      height: 3px; border-radius: 2px;
      background: var(--border);
      margin-top: 6px;
      overflow: hidden;
    }
    .pw-strength-fill {
      height: 100%; width: 0;
      border-radius: 2px;
      transition: width 0.3s, background 0.3s;
    }

    @media (max-width: 680px) {
      .form-grid-2 { grid-template-columns: 1fr; }
    }
  </style>
  <script>
    /* Apply saved theme before first paint */
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
      <rect x="6"  y="14" width="8" height="4" rx="2" fill="#10256D" opacity="0.7"/>
      <rect x="18" y="14" width="8" height="4" rx="2" fill="#10256D" opacity="0.7"/>
      <rect x="14" y="6"  width="4" height="8" rx="2" fill="#10256D" opacity="0.7"/>
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
  <a class="nav-item" href="new-session.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New Session
  </a>

  <div class="sidebar-sep">Account</div>
  <a class="nav-item" href="telemetry.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>
    Telemetry
  </a>
  <a class="nav-item" href="wallet.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    Wallet
  </a>
  <a class="nav-item" href="team.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Team
  </a>

  <!-- Spacer pushes settings to the bottom nav area -->
  <div class="sidebar-spacer"></div>

  <div class="sidebar-bottom">
    <!-- Settings pinned at bottom above user chip -->
    <a class="nav-item active" href="settings.php">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      Settings
    </a>

    <div class="user-chip">
      <div class="avatar"><?= $initials ?></div>
      <div style="flex:1;min-width:0;">
        <div class="user-name"><?= $name ?></div>
        <div class="user-role"><?= $plan ?> plan</div>
      </div>
      <div class="user-actions">
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
    <div class="topbar-title">Settings</div>
    <div class="topbar-right">
      <span class="theme-icon" id="themeIcon">🌙</span>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode"></button>
      <button class="icon-btn" aria-label="Notifications">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </button>
    </div>
  </header>

  <!-- CONTENT -->
  <div class="content">

    <div class="page-title">Settings</div>

    <!-- TABS -->
    <nav class="tabs">
      <a class="tab-btn <?= $active_tab === 'profile'       ? 'active' : '' ?>" href="?tab=profile">Profile</a>
      <a class="tab-btn <?= $active_tab === 'notifications' ? 'active' : '' ?>" href="?tab=notifications">Notifications</a>
    </nav>

    <!-- ══ PROFILE TAB ══ -->
    <?php if ($active_tab === 'profile'): ?>

      <!-- Profile Information -->
      <div class="settings-card">
        <div class="card-section-title">Profile Information</div>

        <div class="profile-head">
          <div class="profile-avatar"><?= $initials ?></div>
          <div class="profile-head-info">
            <div class="profile-name"><?= $name ?></div>
            <div class="profile-email"><?= $email ?></div>
          </div>
        </div>

        <?php if ($profile_saved): ?>
          <div class="toast">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20,6 9,17 4,12"/></svg>
            Profile saved successfully.
          </div>
        <?php endif; ?>

        <form method="POST" action="?tab=profile">
          <div class="form-grid-2" style="margin-bottom:20px;">
            <div class="form-group">
              <label class="form-label" for="full_name">Full Name</label>
              <input class="form-input" type="text" id="full_name" name="full_name"
                     value="<?= $name ?>" placeholder="Your full name" required />
            </div>
            <div class="form-group">
              <label class="form-label" for="email">Email</label>
              <input class="form-input" type="email" id="email" name="email"
                     value="<?= $email ?>" placeholder="you@example.com" required />
            </div>
            <div class="form-group full">
              <label class="form-label" for="timezone">Time Zone</label>
              <select class="form-select" id="timezone" name="timezone">
                <?php
                $zones = ['UTC-12:00','UTC-11:00','UTC-10:00','UTC-09:00','UTC-08:00','UTC-07:00',
                          'UTC-06:00','UTC-05:00','UTC-04:00','UTC-03:00','UTC-02:00','UTC-01:00',
                          'UTC+00:00','UTC+01:00','UTC+02:00','UTC+03:00','UTC+03:30','UTC+04:00',
                          'UTC+04:30','UTC+05:00','UTC+05:30','UTC+05:45','UTC+06:00','UTC+06:30',
                          'UTC+07:00','UTC+08:00','UTC+09:00','UTC+09:30','UTC+10:00','UTC+11:00',
                          'UTC+12:00','UTC+13:00','UTC+14:00'];
                $current_tz = $_SESSION['timezone'] ?? 'UTC+05:30';
                foreach ($zones as $z) {
                  $sel = ($z === $current_tz) ? 'selected' : '';
                  echo "<option value=\"$z\" $sel>$z</option>";
                }
                ?>
              </select>
            </div>
          </div>
          <button class="btn-primary" type="submit" name="save_profile">Save Changes</button>
        </form>

        <div class="card-divider"></div>

        <!-- Change Password -->
        <div class="card-section-title" style="border-bottom:none;padding-bottom:0;">Change Password</div>

        <?php if ($password_saved): ?>
          <div class="toast">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20,6 9,17 4,12"/></svg>
            Password updated successfully.
          </div>
        <?php endif; ?>

        <form method="POST" action="?tab=profile">
          <div class="form-grid-2" style="margin-bottom:20px;">
            <div class="form-group">
              <label class="form-label" for="current_password">Current Password</label>
              <input class="form-input" type="password" id="current_password" name="current_password"
                     placeholder="Current password" />
            </div>
            <div class="form-group">
              <label class="form-label" for="new_password">New Password</label>
              <input class="form-input" type="password" id="new_password" name="new_password"
                     placeholder="Min 8 characters" minlength="8" id="new_password" />
              <div class="pw-strength-bar"><div class="pw-strength-fill" id="pwStrengthFill"></div></div>
            </div>
          </div>
          <button class="btn-outline" type="submit" name="save_password">Update Password</button>
        </form>
      </div>

    <!-- ══ NOTIFICATIONS TAB ══ -->
    <?php elseif ($active_tab === 'notifications'): ?>

      <div class="settings-card">
        <div class="card-section-title">Notification Preferences</div>

        <?php if ($notif_saved): ?>
          <div class="toast">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20,6 9,17 4,12"/></svg>
            Preferences saved.
          </div>
        <?php endif; ?>

        <form method="POST" action="?tab=notifications">
          <div class="notif-list">

            <div class="notif-row">
              <div class="notif-info">
                <div class="notif-label">Low balance alert</div>
                <div class="notif-desc">Get notified when your wallet drops below threshold</div>
              </div>
              <div class="toggle-wrap">
                <input class="toggle-input" type="checkbox" id="n_low_balance" name="low_balance"
                       <?= $notif['low_balance'] ? 'checked' : '' ?> />
                <label class="toggle-track" for="n_low_balance"></label>
              </div>
            </div>

            <div class="notif-row">
              <div class="notif-info">
                <div class="notif-label">Session started</div>
                <div class="notif-desc">Confirmation when a session begins</div>
              </div>
              <div class="toggle-wrap">
                <input class="toggle-input" type="checkbox" id="n_session_start" name="session_start"
                       <?= $notif['session_start'] ? 'checked' : '' ?> />
                <label class="toggle-track" for="n_session_start"></label>
              </div>
            </div>

            <div class="notif-row">
              <div class="notif-info">
                <div class="notif-label">Session ended</div>
                <div class="notif-desc">Summary after each session ends</div>
              </div>
              <div class="toggle-wrap">
                <input class="toggle-input" type="checkbox" id="n_session_end" name="session_end"
                       <?= $notif['session_end'] ? 'checked' : '' ?> />
                <label class="toggle-track" for="n_session_end"></label>
              </div>
            </div>

            <div class="notif-row">
              <div class="notif-info">
                <div class="notif-label">Weekly usage report</div>
                <div class="notif-desc">Weekly email with usage analytics</div>
              </div>
              <div class="toggle-wrap">
                <input class="toggle-input" type="checkbox" id="n_weekly" name="weekly_report"
                       <?= $notif['weekly_report'] ? 'checked' : '' ?> />
                <label class="toggle-track" for="n_weekly"></label>
              </div>
            </div>

            <div class="notif-row">
              <div class="notif-info">
                <div class="notif-label">Team invitations</div>
                <div class="notif-desc">When someone invites you to a team</div>
              </div>
              <div class="toggle-wrap">
                <input class="toggle-input" type="checkbox" id="n_team" name="team_invite"
                       <?= $notif['team_invite'] ? 'checked' : '' ?> />
                <label class="toggle-track" for="n_team"></label>
              </div>
            </div>

          </div><!-- /notif-list -->

          <div style="margin-top:24px;">
            <button class="btn-primary" type="submit" name="save_notifications">Save Preferences</button>
          </div>
        </form>
      </div>

    <?php endif; ?>

  </div><!-- /content -->
</main>

<script>
/* ── THEME TOGGLE (syncs via localStorage across all pages) ── */
(function () {
  var html   = document.documentElement;
  var toggle = document.getElementById('themeToggle');
  var icon   = document.getElementById('themeIcon');

  function syncIcon() {
    icon.textContent = html.getAttribute('data-theme') === 'dark' ? '🌙' : '☀️';
  }
  syncIcon();

  toggle.addEventListener('click', function () {
    var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('sb_theme', next);
    syncIcon();
  });
})();

/* ── PASSWORD STRENGTH INDICATOR ── */
(function () {
  var pw   = document.getElementById('new_password');
  var fill = document.getElementById('pwStrengthFill');
  if (!pw || !fill) return;

  pw.addEventListener('input', function () {
    var v = pw.value;
    var score = 0;
    if (v.length >= 8)  score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;

    var pct   = Math.round((score / 5) * 100);
    var color = score <= 1 ? '#e05555' : score <= 3 ? '#EE9346' : '#28c840';
    fill.style.width      = pct + '%';
    fill.style.background = color;
  });
})();
</script>
</body>
</html>