<?php
require 'auth/session_guard.php';
require 'auth/db.php';
require_once 'includes/simulator_launch.php';

$name           = htmlspecialchars($_SESSION['name'] ?? 'User');
$email          = $_SESSION['email'] ?? '';
$sidebar_active = 'dashboard';

// Fetch user's flights
$flights = $db->flights->find(['email' => $email], ['sort' => ['created_at' => -1]])->toArray();

$total_seconds = 0;
$this_week_count = 0;
$seven_days_ago = new DateTime('-7 days');
$usage_seconds = [0,0,0,0,0,0,0]; // Mon to Sun

foreach($flights as $f) {
    $dur = $f['duration'] ?? 0;
    $total_seconds += $dur;
    
    if (isset($f['created_at'])) {
        $dt = $f['created_at']->toDateTime();
        if ($dt >= $seven_days_ago) {
            $this_week_count++;
            // 1 (Mon) - 7 (Sun)
            $day_idx = (int)$dt->format('N') - 1;
            $usage_seconds[$day_idx] += $dur;
        }
    }
}

$total_hours = round($total_seconds / 3600, 1);
$max_usage = max($usage_seconds);
$usage_pct = [];
foreach($usage_seconds as $s) {
    $usage_pct[] = $max_usage > 0 ? round(($s / $max_usage) * 100) : 0;
}

$user_row = $db->users->findOne(['email' => $email]);
$plan_map = [1 => 'BASIC', 2 => 'PRO', 3 => 'MAX'];
$live_plan_id = (int) ($user_row['sub_id'] ?? ($_SESSION['user_sub']['plan_id'] ?? 0));
$plan_name = $plan_map[$live_plan_id] ?? (string) ($_SESSION['user_sub']['plan_name'] ?? 'Free');
if ($plan_name === '' || $plan_name === '0') {
    $plan_name = 'Free';
}
$sim_launch = sb_simulator_launch_info($user_row ?: null);
$can_launch = sb_can_launch_simulator($user_row ?: null);
$simulator_url = htmlspecialchars($sim_launch['url'], ENT_QUOTES, 'UTF-8');

$trial_state = sb_free_trial_state($user_row, false);
$wallet_balance = (float) ($_SESSION['wallet_balance'] ?? 0);
$show_wallet = $live_plan_id > 0 || $wallet_balance > 0;
$trial_min = floor($trial_state['remaining_seconds'] / 60);
$trial_sec = $trial_state['remaining_seconds'] % 60;
$trial_text = sprintf("%d:%02d", $trial_min, $trial_sec);

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Certanity — Dashboard</title>
  <link rel="icon" type="image/png" href="assets/logo-iso.png" />
  <link rel="apple-touch-icon" href="assets/logo-iso.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    /* ── Design tokens ── */
    :root{--primary:#10256D;--secondary:#EE9346;--accent:#28c840;--bg:#0e1117;--bg2:#141820;--surface:#1a1f2e;--surface2:#212637;--border:rgba(255,255,255,0.06);--text:#e8eaf0;--text2:#8b92a8;--text3:#5a6078;--neu-out:6px 6px 14px #080b12,-4px -4px 10px #222840;--neu-in:inset 4px 4px 10px #080b12,inset -3px -3px 8px #222840;--neu-btn:3px 3px 8px #080b12,-2px -2px 6px #222840;--sidebar-w:220px;--r:14px;}
    [data-theme="light"]{--bg:#e8eaf0;--bg2:#dde0ea;--surface:#eaecf4;--surface2:#f0f2f8;--border:rgba(0,0,0,0.06);--text:#1a1f35;--text2:#5a6078;--text3:#9099b8;--neu-out:6px 6px 14px #c8cad4,-4px -4px 10px #ffffff;--neu-in:inset 4px 4px 10px #c8cad4,inset -3px -3px 8px #ffffff;--neu-btn:3px 3px 8px #c8cad4,-2px -2px 6px #ffffff;}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;transition:background .3s,color .3s;}

    /* ── Shared sidebar CSS (single copy) ── */
    .sidebar{width:var(--sidebar-w);background:var(--bg2);display:flex;flex-direction:column;padding:24px 16px;gap:4px;position:fixed;top:0;left:0;bottom:0;box-shadow:4px 0 20px rgba(0,0,0,.25);z-index:20;transition:background .3s;}
    .sidebar-logo{display:flex;align-items:center;gap:10px;padding:6px 12px 20px;border-bottom:1px solid var(--border);margin-bottom:6px;}
    .sidebar-logo-text{font-family:'Syne',sans-serif;font-size:12.5px;font-weight:700;letter-spacing:.05em;color:var(--primary);}
    .nav-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:var(--text2);font-size:13.5px;font-weight:500;cursor:pointer;transition:all .18s;text-decoration:none;border:none;background:transparent;width:100%;}
    .nav-item svg{flex-shrink:0;opacity:.65;transition:opacity .18s;}
    .nav-item:hover{background:var(--surface);color:var(--text);}
    .nav-item:hover svg{opacity:1;}
    .nav-item.active{box-shadow:var(--neu-out);color:var(--secondary);font-weight:600;}
    .nav-item.active svg{opacity:1;color:var(--secondary);}
    .sidebar-bottom{margin-top:auto;padding-top:14px;border-top:1px solid var(--border);}
    .user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:var(--surface);box-shadow:var(--neu-in);}
    .user-actions{margin-left:auto;display:flex;gap:4px;flex-shrink:0;}
    .user-action-btn{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--text3);text-decoration:none;transition:background .18s,color .18s;}
    .user-action-btn:hover{background:var(--surface2);color:var(--text);}
    .user-action-btn.logout:hover{background:rgba(224,85,85,.12);color:#e05555;}
    .user-action-btn.active-icon{color:var(--secondary);}
    .avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;}
    .user-info{flex:1;min-width:0;}
    .user-name{font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .user-role{font-size:11px;color:var(--text3);}

    /* ── Main layout ── */
    .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
    .topbar{display:flex;align-items:center;justify-content:space-between;padding:20px 32px;background:var(--bg);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:5;transition:background .3s;}
    .topbar-title{font-family:'Syne',sans-serif;font-size:20px;font-weight:700;letter-spacing:-.02em;}
    .topbar-right{display:flex;align-items:center;gap:12px;}
    .theme-toggle{width:44px;height:24px;background:var(--surface);box-shadow:var(--neu-in);border-radius:12px;position:relative;cursor:pointer;border:none;transition:all .3s;flex-shrink:0;}
    .theme-toggle::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:var(--secondary);box-shadow:2px 2px 5px rgba(0,0,0,.3);transition:transform .3s;}
    [data-theme="light"] .theme-toggle::after{transform:translateX(20px);}
    .topbar-icon-btn{width:36px;height:36px;border-radius:10px;background:var(--surface);box-shadow:var(--neu-btn);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text2);transition:all .2s;}
    .topbar-icon-btn:hover{color:var(--text);box-shadow:var(--neu-out);}
    .content{padding:28px 32px;display:flex;flex-direction:column;gap:24px;flex:1;}

    /* ── Stat cards ── */
    .stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
    .stat-card{background:var(--surface);border-radius:var(--r);box-shadow:var(--neu-out);padding:22px 24px;transition:box-shadow .2s,transform .2s;cursor:default;}
    .stat-card:hover{box-shadow:8px 8px 20px #050810,-5px -5px 14px #252d48;transform:translateY(-2px);}
    [data-theme="light"] .stat-card:hover{box-shadow:8px 8px 20px #b8bac6,-5px -5px 14px #ffffff;}
    .stat-label{font-size:10.5px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--text3);margin-bottom:10px;}
    .stat-value{font-family:'Syne',sans-serif;font-size:28px;font-weight:700;letter-spacing:-.03em;line-height:1;margin-bottom:6px;}
    .stat-value.green{color:var(--accent);}
    .stat-value.orange{color:var(--secondary);}
    .stat-value.blue{color:#5b8def;}
    .stat-sub{font-size:12px;color:var(--text3);}

    /* ── Banner ── */
    .banner{background:linear-gradient(135deg,var(--primary) 0%,#1a3a8f 100%);border-radius:var(--r);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;box-shadow:var(--neu-out);}
    .banner-text h3{font-family:'Syne',sans-serif;font-size:17px;font-weight:700;color:#fff;margin-bottom:4px;}
    .banner-text p{font-size:13px;color:rgba(255,255,255,.65);}
    .btn-primary{background:var(--secondary);color:#fff;border:none;border-radius:10px;padding:11px 22px;font-size:13.5px;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(238,147,70,.4);transition:opacity .2s,transform .15s;white-space:nowrap;font-family:'DM Sans',sans-serif;}
    .btn-primary:hover{opacity:.9;transform:translateY(-1px);}

    /* ── Lower row ── */
    .lower-row{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;}
    .panel{background:var(--surface);border-radius:var(--r);box-shadow:var(--neu-out);overflow:hidden;}
    .panel-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px 14px;border-bottom:1px solid var(--border);}
    .panel-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;}
    .view-all{font-size:12px;color:var(--secondary);text-decoration:none;font-weight:500;}
    .view-all:hover{text-decoration:underline;}
    .session-item{display:flex;align-items:center;justify-content:space-between;padding:14px 22px;border-bottom:1px solid var(--border);transition:background .15s;}
    .session-item:last-child{border-bottom:none;}
    .session-item:hover{background:var(--surface2);}
    .session-left{display:flex;align-items:center;gap:14px;}
    .session-icon{width:36px;height:36px;border-radius:10px;background:var(--bg);box-shadow:var(--neu-in);display:flex;align-items:center;justify-content:center;color:var(--secondary);flex-shrink:0;}
    .session-name{font-size:13.5px;font-weight:600;margin-bottom:2px;}
    .session-meta{font-size:11.5px;color:var(--text3);}
    .session-right{display:flex;align-items:center;gap:12px;}
    .session-time{font-size:12px;color:var(--text2);font-variant-numeric:tabular-nums;}
    .badge{font-size:10px;font-weight:700;letter-spacing:.05em;padding:4px 10px;border-radius:20px;text-transform:uppercase;}
    .badge-completed{background:rgba(40,200,64,.12);color:var(--accent);}
    .right-col{display:flex;flex-direction:column;gap:18px;}
    .checklist-item{display:flex;align-items:center;gap:10px;padding:9px 0;font-size:13px;border-bottom:1px solid var(--border);}
    .checklist-item:last-child{border-bottom:none;}
    .checklist-item.done{color:var(--text3);text-decoration:line-through;}
    .check-circle{width:18px;height:18px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;}
    .check-circle.done{background:rgba(40,200,64,.15);color:var(--accent);}
    .check-circle.todo{border:1.5px solid var(--border);}
    .progress-bar-wrap{background:var(--surface2);box-shadow:var(--neu-in);border-radius:99px;height:6px;overflow:hidden;margin:8px 0 4px;}
    .progress-bar-fill{height:100%;background:linear-gradient(90deg,var(--accent),#1be050);border-radius:99px;width:60%;transition:width .6s ease;}
    .panel-body{padding:16px 22px;}
    .progress-text{font-size:11px;color:var(--text3);}
    .usage-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;align-items:end;height:60px;}
    .usage-bar{border-radius:4px;background:var(--accent);opacity:.6;transition:opacity .2s;cursor:default;min-height:8px;}
    .usage-bar:hover{opacity:1;}
    .usage-labels{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-top:4px;}
    .usage-label{font-size:9.5px;color:var(--text3);text-align:center;}

    /* ── Animations ── */
    .fade-up{opacity:0;transform:translateY(16px);animation:fadeUp .45s ease forwards;}
    @keyframes fadeUp{to{opacity:1;transform:translateY(0);}}
    .delay-1{animation-delay:.05s}.delay-2{animation-delay:.12s}.delay-3{animation-delay:.19s}
    .delay-4{animation-delay:.26s}.delay-5{animation-delay:.33s}.delay-6{animation-delay:.40s}

    @media(max-width:1100px){.stat-row{grid-template-columns:repeat(2,1fr);}.lower-row{grid-template-columns:1fr;}}
    @media(max-width:720px){.sidebar{display:none;}.main{margin-left:0;}.content{padding:20px 16px;}.topbar{padding:14px 16px;}.stat-row{grid-template-columns:repeat(2,1fr);gap:12px;}}
  </style>
  <script>(function(){var t=localStorage.getItem('sb_theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main">
  <header class="topbar">
    <div class="topbar-title">Dashboard</div>
    <div class="topbar-right">
      <div class="wallet-chip" style="display:flex; align-items:center; gap:6px; background:var(--surface); padding:6px 12px; border-radius:12px; box-shadow:var(--neu-btn); font-size:13px; font-weight:600; color:var(--text); margin-right:4px;">
        <?php if ($show_wallet): ?>
            <span style="color:var(--accent);">💰</span> $<?= number_format($wallet_balance, 2) ?>
        <?php else: ?>
            <span style="color:var(--accent);">🕐</span> <?= $trial_text ?> free time
        <?php endif; ?>
      </div>
      <span id="themeIcon" style="font-size:13px">🌙</span>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode"></button>
      <button class="topbar-icon-btn" aria-label="Notifications">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
      </button>
    </div>
  </header>

  <div class="content">

    <?php if (isset($_GET['error']) && $_GET['error'] === 'tier_mismatch'): ?>
    <div style="background:rgba(224,85,85,.12);border:1px solid rgba(224,85,85,.3);border-radius:var(--r);padding:16px 22px;display:flex;align-items:center;gap:12px;color:#e05555;font-size:13.5px;font-weight:500;">
      <span style="font-size:18px;">⚠️</span>
      <span>You don't have the required plan to access that simulator tier. Please <a href="billing.php" style="color:var(--secondary);font-weight:600;">upgrade your plan</a> or select the correct simulator for your subscription.</span>
    </div>
    <?php endif; ?>

    <div class="stat-row">
      <div class="stat-card fade-up delay-1">
        <div class="stat-label">Active Sessions</div>
        <div class="stat-value green">0</div>
        <div class="stat-sub">Running right now</div>
      </div>
      <div class="stat-card fade-up delay-2">
        <div class="stat-label">Total Flight Hours</div>
        <div class="stat-value blue"><?= $total_hours ?>h</div>
        <div class="stat-sub">Across <?= count($flights) ?> sessions</div>
      </div>
      <div class="stat-card fade-up delay-3">
        <div class="stat-label">This Week</div>
        <div class="stat-value" style="color:var(--text)"><?= $this_week_count ?></div>
        <div class="stat-sub">Sessions launched</div>
      </div>
      <div class="stat-card fade-up delay-4">
        <div class="stat-label">Plan Status</div>
        <div class="stat-value green"><?= htmlspecialchars($plan_name) ?></div>
        <div class="stat-sub">Access level</div>
      </div>
    </div>

    <div class="banner fade-up delay-5">
      <div class="banner-text">
        <h3>Ready for your next flight?</h3>
        <p>Configure a new simulation session with your choice of drone, environment, and weather.</p>
      </div>
      <button class="btn-primary" <?= $can_launch ? 'onclick="window.open(\'' . $simulator_url . '\', \'_blank\')"' : 'style="opacity: 0.5; cursor: not-allowed;" onclick="alert(\'Please top up your wallet or upgrade your plan to start a new session.\')"' ?>>+ Start New Session</button>
    </div>

    <div class="lower-row fade-up delay-6">
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">Recent Sessions</div>
          <a class="view-all" href="simulations.php">View all →</a>
        </div>
        <?php
        $recent = [];
        $recent_flights = array_slice($flights, 0, 4);
        foreach ($recent_flights as $f) {
          $dur = gmdate("i\m s\s", $f['duration'] ?? 0);
          $date = $f['created_at'] ? (function($c){ $d = $c->toDateTime(); $d->setTimezone(new DateTimeZone('Asia/Kolkata')); return $d->format('M d, g:i A'); })($f['created_at']) : 'Unknown';
          $recent[] = [ $f['name'] ?? 'Simulation', $f['drone'] ?? 'Unknown Drone', $date, $dur ];
        }
        if (empty($recent)) {
            echo '<div style="padding: 20px 22px; font-size: 13px; color: var(--text3);">No recent sessions. Launch the simulator to get started!</div>';
        }
        foreach ($recent as $r): ?>
        <div class="session-item">
          <div class="session-left">
            <div class="session-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="2"/>
                <line x1="2" y1="12" x2="8" y2="12"/><line x1="16" y1="12" x2="22" y2="12"/>
                <line x1="12" y1="2" x2="12" y2="8"/><line x1="12" y1="16" x2="12" y2="22"/>
                <circle cx="4" cy="4" r="2"/><circle cx="20" cy="4" r="2"/>
                <circle cx="4" cy="20" r="2"/><circle cx="20" cy="20" r="2"/>
              </svg>
            </div>
            <div>
              <div class="session-name"><?= htmlspecialchars($r[0]) ?></div>
              <div class="session-meta"><?= htmlspecialchars($r[1]) ?> · <?= htmlspecialchars($r[2]) ?></div>
            </div>
          </div>
          <div class="session-right">
            <span class="session-time"><?= $r[3] ?></span>
            <span class="badge badge-completed">Completed</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="right-col">
        <div class="panel">
          <div class="panel-header"><div class="panel-title">Getting Started</div></div>
          <div class="panel-body">
            <div class="progress-bar-wrap"><div class="progress-bar-fill"></div></div>
            <div class="progress-text" style="margin-bottom:10px">3 / 5 complete</div>
            <?php
            $checks = [
              ['Create an account',        true],
              ['Configure drone profile',  true],
              ['Launch first simulation',  true],
              ['Invite a team member',     false],
              ['Export session data',      false],
            ];
            foreach ($checks as [$label, $done]): ?>
            <div class="checklist-item <?= $done ? 'done' : '' ?>">
              <div class="check-circle <?= $done ? 'done' : 'todo' ?>">
                <?php if ($done): ?>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                  <polyline points="20,6 9,17 4,12"/>
                </svg>
                <?php endif; ?>
              </div>
              <?= htmlspecialchars($label) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="panel">
          <div class="panel-header"><div class="panel-title">Weekly Usage</div></div>
          <div class="panel-body">
            <div class="usage-grid">
              <?php foreach ($usage_pct as $h): ?>
              <div class="usage-bar" style="height:<?= max(5, $h) ?>%;<?= $h===0?' opacity:.3':'' ?>"></div>
              <?php endforeach; ?>
            </div>
            <div class="usage-labels">
              <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
              <div class="usage-label"><?= $d ?></div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</main>

<script>
(function(){
  var h=document.documentElement,t=document.getElementById('themeToggle'),i=document.getElementById('themeIcon');
  function s(){i.textContent=h.getAttribute('data-theme')==='dark'?'🌙':'☀️';}s();
  t.addEventListener('click',function(){
    var n=h.getAttribute('data-theme')==='dark'?'light':'dark';
    h.setAttribute('data-theme',n);localStorage.setItem('sb_theme',n);s();
  });
})();
</script>
<?php if (isset($_GET['upgrade_telem'])): ?>
<div id="telem-upgrade-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(4px);">
  <div style="background:var(--surf);padding:30px;border-radius:12px;max-width:400px;text-align:center;box-shadow:0 10px 25px rgba(0,0,0,0.3);border:1px solid var(--n2);">
    <h3 style="margin-top:0;margin-bottom:15px;color:var(--txt);">Flight Data Saved ☁️</h3>
    <p style="color:var(--txt2);margin-bottom:20px;font-size:14px;line-height:1.5;">Your telemetry data has been initially saved to the cloud.<br><br>If you want to download this data, you need to upgrade to a higher tier like <strong>PRO</strong> or <strong>MAX</strong> within the next 15 minutes.<br><br>Otherwise, the data will be permanently deleted.</p>
    <div style="display:flex;gap:10px;justify-content:center;">
      <a href="billing.php" class="btn" style="background:var(--p);color:#fff;text-decoration:none;padding:8px 16px;border-radius:6px;font-weight:600;">Upgrade Now</a>
      <button onclick="document.getElementById('telem-upgrade-modal').style.display='none';" style="background:var(--n);color:var(--txt);border:1px solid var(--n2);padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:600;">Close</button>
    </div>
  </div>
</div>
<?php endif; ?>
</body>
</html>
