<?php
require 'auth/session_guard.php';

$sidebar_active = 'settings';
$name     = htmlspecialchars($_SESSION['name']  ?? 'Demo Pilot');
$email    = htmlspecialchars($_SESSION['email'] ?? 'pilot@example.com');
$initials = strtoupper(substr(trim($_SESSION['name'] ?? 'U'), 0, 1));
$plan     = htmlspecialchars($_SESSION['user_sub']['plan_name'] ?? 'Free');

$profile_saved = $password_saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_profile'])) {
        $name = htmlspecialchars($_POST['full_name'] ?? $name);
        $profile_saved = true;
    } elseif (isset($_POST['save_password'])) {
        $password_saved = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Certanity — Settings</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    /* ── Design tokens ── */
    :root{--primary:#10256D;--secondary:#EE9346;--accent:#28c840;--red:#e05555;--bg:#0e1117;--bg2:#141820;--surface:#1a1f2e;--surface2:#212637;--border:rgba(255,255,255,0.06);--text:#e8eaf0;--text2:#8b92a8;--text3:#5a6078;--neu-out:6px 6px 14px #080b12,-4px -4px 10px #222840;--neu-in:inset 4px 4px 10px #080b12,inset -3px -3px 8px #222840;--neu-btn:3px 3px 8px #080b12,-2px -2px 6px #222840;--sidebar-w:220px;--r:14px;}
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
    .topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 32px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;background:var(--bg);transition:background .3s;}
    .topbar-title{font-family:'Syne',sans-serif;font-size:20px;font-weight:700;letter-spacing:-.02em;}
    .topbar-right{display:flex;align-items:center;gap:12px;}
    .theme-icon{font-size:14px;line-height:1;}
    .theme-toggle{width:44px;height:24px;border-radius:12px;background:var(--surface);box-shadow:var(--neu-in);border:none;cursor:pointer;position:relative;transition:all .3s;flex-shrink:0;}
    .theme-toggle::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:var(--secondary);box-shadow:2px 2px 5px rgba(0,0,0,.3);transition:transform .3s;}
    [data-theme="light"] .theme-toggle::after{transform:translateX(20px);}
    .icon-btn{width:36px;height:36px;border-radius:10px;background:var(--surface);box-shadow:var(--neu-btn);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text2);transition:all .18s;}
    .icon-btn:hover{color:var(--text);box-shadow:var(--neu-out);}
    .content{padding:32px;flex:1;display:flex;flex-direction:column;gap:24px;}
    .page-title{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;letter-spacing:-.03em;}

    /* ── Settings card ── */
    .settings-card{background:var(--surface);border-radius:var(--r);box-shadow:var(--neu-out);padding:28px 32px;display:flex;flex-direction:column;gap:24px;animation:fadeUp .4s cubic-bezier(.22,1,.36,1) both;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);}}
    .card-section-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;letter-spacing:-.01em;padding-bottom:16px;border-bottom:1px solid var(--border);}
    .profile-head{display:flex;align-items:center;gap:18px;}
    .profile-avatar{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;box-shadow:var(--neu-out);flex-shrink:0;}
    .profile-name{font-size:15px;font-weight:700;}
    .profile-email{font-size:12.5px;color:var(--text3);margin-top:2px;}

    /* ── Form ── */
    .form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
    .form-group{display:flex;flex-direction:column;gap:7px;}
    .form-group.full{grid-column:1/-1;}
    .form-label{font-size:12px;font-weight:600;letter-spacing:.04em;color:var(--text3);text-transform:uppercase;}
    .form-input,.form-select{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:var(--text);outline:none;box-shadow:var(--neu-in);transition:border-color .18s,box-shadow .18s;width:100%;}
    .form-input::placeholder{color:var(--text3);}
    .form-input:focus,.form-select:focus{border-color:var(--secondary);box-shadow:var(--neu-in),0 0 0 2px rgba(238,147,70,.12);}
    .form-select{cursor:pointer;appearance:none;-webkit-appearance:none;}
    .form-select option{background:var(--surface2);}
    .card-divider{height:1px;background:var(--border);margin:4px 0;}

    /* ── Buttons ── */
    .btn-primary{display:inline-flex;align-items:center;gap:8px;background:var(--secondary);color:#fff;border:none;border-radius:10px;padding:10px 22px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:0 4px 14px rgba(238,147,70,.35);transition:opacity .18s,transform .15s;}
    .btn-primary:hover{opacity:.9;transform:translateY(-1px);}
    .btn-outline{display:inline-flex;align-items:center;gap:8px;background:transparent;color:var(--secondary);border:1px solid var(--secondary);border-radius:10px;padding:9px 20px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .18s;}
    .btn-outline:hover{background:rgba(238,147,70,.08);}

    /* ── Toast ── */
    .toast{display:inline-flex;align-items:center;gap:8px;background:rgba(40,200,64,.1);border:1px solid rgba(40,200,64,.25);border-radius:8px;padding:8px 14px;font-size:12.5px;font-weight:500;color:var(--accent);animation:fadeUp .3s ease both;}

    /* ── Password strength ── */
    .pw-strength-bar{height:3px;border-radius:2px;background:var(--border);margin-top:6px;overflow:hidden;}
    .pw-strength-fill{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}

    @media(max-width:680px){.form-grid-2{grid-template-columns:1fr;}}
    @media(max-width:720px){.sidebar{display:none;}.main{margin-left:0;}.content{padding:20px 16px;}.topbar{padding:14px 16px;}}
  </style>
  <script>(function(){var t=localStorage.getItem('sb_theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main">
  <header class="topbar">
    <div class="topbar-title">Settings</div>
    <div class="topbar-right">
      <span class="theme-icon" id="themeIcon">🌙</span>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode"></button>
      <button class="icon-btn" aria-label="Notifications">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
      </button>
    </div>
  </header>

  <div class="content">
    <div class="page-title">Settings</div>

    <div class="settings-card">
      <div class="card-section-title">Profile Information</div>
      <div class="profile-head">
        <div class="profile-avatar"><?= $initials ?></div>
        <div>
          <div class="profile-name"><?= $name ?></div>
          <div class="profile-email"><?= $email ?></div>
        </div>
      </div>
      <?php if ($profile_saved): ?>
      <div class="toast">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="20,6 9,17 4,12"/>
        </svg>
        Profile saved.
      </div>
      <?php endif; ?>
      <form method="POST">
        <div class="form-grid-2" style="margin-bottom:20px;">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input class="form-input" type="text" name="full_name" value="<?= $name ?>" placeholder="Your full name" required/>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input" type="email" name="email" value="<?= $email ?>" placeholder="you@example.com" required/>
          </div>
          <div class="form-group full">
            <label class="form-label">Time Zone</label>
            <select class="form-select" name="timezone">
              <?php
              $zones = ['UTC-12:00','UTC-11:00','UTC-10:00','UTC-09:00','UTC-08:00','UTC-07:00','UTC-06:00','UTC-05:00','UTC-04:00','UTC-03:00','UTC-02:00','UTC-01:00','UTC+00:00','UTC+01:00','UTC+02:00','UTC+03:00','UTC+03:30','UTC+04:00','UTC+04:30','UTC+05:00','UTC+05:30','UTC+05:45','UTC+06:00','UTC+06:30','UTC+07:00','UTC+08:00','UTC+09:00','UTC+09:30','UTC+10:00','UTC+11:00','UTC+12:00','UTC+13:00','UTC+14:00'];
              $ctz   = $_SESSION['timezone'] ?? 'UTC+05:30';
              foreach ($zones as $z) echo "<option value=\"{$z}\"" . ($z === $ctz ? ' selected' : '') . ">{$z}</option>";
              ?>
            </select>
          </div>
        </div>
        <button class="btn-primary" type="submit" name="save_profile">Save Changes</button>
      </form>

      <div class="card-divider"></div>
      <div class="card-section-title" style="border-bottom:none;padding-bottom:0;">Change Password</div>
      <?php if ($password_saved): ?>
      <div class="toast">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="20,6 9,17 4,12"/>
        </svg>
        Password updated.
      </div>
      <?php endif; ?>
      <form method="POST">
        <div class="form-grid-2" style="margin-bottom:20px;">
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input class="form-input" type="password" name="current_password" placeholder="Current password"/>
          </div>
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input class="form-input" type="password" id="new_password" name="new_password"
                   placeholder="Min 8 characters" minlength="8"/>
            <div class="pw-strength-bar"><div class="pw-strength-fill" id="pwFill"></div></div>
          </div>
        </div>
        <button class="btn-outline" type="submit" name="save_password">Update Password</button>
      </form>
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
(function(){
  var p=document.getElementById('new_password'),f=document.getElementById('pwFill');
  if(!p||!f)return;
  p.addEventListener('input',function(){
    var v=p.value,s=0;
    if(v.length>=8)s++;if(v.length>=12)s++;
    if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
    f.style.width=Math.round(s/5*100)+'%';
    f.style.background=s<=1?'#e05555':s<=3?'#EE9346':'#28c840';
  });
})();
</script>
</body>
</html>