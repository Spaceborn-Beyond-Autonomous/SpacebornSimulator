<?php
require 'auth/session_guard.php';
require 'auth/db.php';

$sidebar_active = 'billing';
$name           = htmlspecialchars($_SESSION['name'] ?? 'User');

// ── Plan catalog — matches DB plan_id exactly ───────────────────────────
// plan_id 1 = BASIC  ($1 / 1 hr)
// plan_id 2 = PRO    ($5 / 1 day)
// plan_id 3 = MAX    ($20 / mo, recurring)
$plan_catalog = [
  1 => [
    'id'       => 1,
    'name'     => 'BASIC',
    'label'    => '1 Hour',
    'price'    => 1,
    'period'   => '1 hr',
    'color'    => 'blue',
    'best_for' => 'Quick test, first look',
    'recurring'=> false,
    'features' => [
      ['yes', '1-hour access window'],
      ['yes', '1 drone profile (Research F450)'],
      ['yes', 'Normal Flight only'],
      ['yes', 'Daytime environment'],
      ['yes', 'Basic HUD (Altitude + Speed)'],
      ['no',  'Waypoint Missions'],
      ['no',  'PID Tuning Panel'],
      ['no',  'Data Export'],
      ['no',  'MAVLink Stream'],
    ],
  ],
  2 => [
    'id'       => 2,
    'name'     => 'PRO',
    'label'    => '1 Day',
    'price'    => 5,
    'period'   => '1 day',
    'color'    => 'orange',
    'best_for' => 'A focused one-off session',
    'recurring'=> false,
    'features' => [
      ['yes', '24-hour access'],
      ['yes', '2 profiles (Research + Indoor)'],
      ['yes', '2 scenarios (Normal + Wind)'],
      ['yes', '3 environments'],
      ['yes', 'View-only PID panel'],
      ['yes', 'Read-only MAVLink'],
      ['no',  'Data Export'],
      ['no',  'Waypoint Missions'],
      ['no',  'Custom GLTF/GLB model'],
    ],
  ],
  3 => [
    'id'       => 3,
    'name'     => 'MAX',
    'label'    => '1 Month',
    'price'    => 20,
    'period'   => 'mo',
    'color'    => 'accent',
    'best_for' => 'Serious builders &amp; learners',
    'recurring'=> true,
    'features' => [
      ['yes', 'Unlimited 30-day access'],
      ['yes', 'ALL 4 profiles + Custom Mode'],
      ['yes', 'ALL 5 scenarios incl. Motor Failure, GPS Denied'],
      ['yes', 'ALL 6 environments'],
      ['yes', 'Full mission planner (Waypoints)'],
      ['yes', 'Full live PID tuning'],
      ['yes', 'Full export pipeline (JSON / CSV / MAVLog)'],
      ['yes', 'Downloadable MAVLink logs'],
      ['yes', 'Upload your own GLTF/GLB drone'],
      ['yes', 'Full GCS-style HUD'],
      ['yes', 'Joystick / Gamepad support'],
      ['yes', 'Priority email support'],
    ],
  ],
];

// ── Fetch user from MongoDB for real-time data ──────────────────────────
$user = $db->users->findOne(['email' => $_SESSION['email']]);
$wallet_balance = $user ? (float)($user['wallet_balance'] ?? 0.0) : 0.0;
$db_plan_id     = $user ? (int)($user['sub_id'] ?? 0) : 0;

$sub_started = $user && isset($user['sub_started']) ? (bool)$user['sub_started'] : false;
$sub_expires_at_ts = null;
if ($user && isset($user['sub_expires_at']) && $user['sub_expires_at'] instanceof MongoDB\BSON\UTCDateTime) {
    $sub_expires_at_ts = $user['sub_expires_at']->toDateTime()->getTimestamp();
}

// ── Resolve current plan from DB ────────────────────────────────────────
$current_plan_id = $db_plan_id;

if ($current_plan_id === 0) {
    $current_plan = [
        'id'       => 0,
        'name'     => 'FREE',
        'label'    => 'Pay-As-You-Go',
        'price'    => 0,
        'period'   => 'min',
        'color'    => 'gray',
        'best_for' => 'Wallet balance users',
        'recurring'=> false,
    ];
    $current_price = 0;
    $current_name  = 'FREE';
} else {
    if (!isset($plan_catalog[$current_plan_id])) $current_plan_id = 1;
    $current_plan  = $plan_catalog[$current_plan_id];
    $current_price = $current_plan['price'];
    $current_name  = $current_plan['name'];
}

$payment_status = $_GET['payment'] ?? '';
$payment_id     = $_GET['payment_id'] ?? '';
$msg            = $_GET['msg'] ?? '';

$razorpay_sub_id = $_SESSION['user_sub']['razorpay_subscription_id'] ?? null;

// ── Dynamic next billing / access window ────────────────────────────────
$next_billing = '—';
if ($sub_started && $sub_expires_at_ts) {
    $next_billing = date('j M Y, g:i A', $sub_expires_at_ts);
} elseif (!$sub_started && $current_plan_id > 0) {
    $next_billing = 'Starts on first flight';
} elseif ($current_plan_id === 0) {
    $next_billing = 'N/A';
}

// ── Billing history from MongoDB invoices collection ────────────────────
$invoices = [];
try {
    $inv_cursor = $db->invoices->find(
        ['email' => $_SESSION['email']],
        ['sort' => ['created_at' => -1], 'limit' => 50]
    );
    foreach ($inv_cursor as $inv) {
        $date = '';
        if (isset($inv['created_at']) && $inv['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $date = $inv['created_at']->toDateTime()->format('j M Y');
        }
        $amount = (float)($inv['amount'] ?? 0);
        $invoices[] = [
            'date'        => $date,
            'description' => (string)($inv['description'] ?? ''),
            'amount'      => ($amount < 0 ? '-' : '') . '$' . number_format(abs($amount), 2),
            'status'      => (string)($inv['status'] ?? 'paid'),
            'payment_id'  => (string)($inv['payment_id'] ?? ''),
        ];
    }
} catch (Exception $e) {
    $invoices = [];
}
$total_paid = count(array_filter($invoices, fn($i) => $i['status'] === 'paid'));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Certanity — My Plan &amp; Billing</title>
  <link rel="icon" type="image/png" href="assets/logo-iso.png" />
  <link rel="apple-touch-icon" href="assets/logo-iso.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    /* ── Design tokens ── */
    :root{--primary:#10256D;--secondary:#EE9346;--accent:#28c840;--red:#e05555;--blue:#5b8def;--bg:#0e1117;--bg2:#141820;--surface:#1a1f2e;--surface2:#212637;--border:rgba(255,255,255,0.06);--text:#e8eaf0;--text2:#8b92a8;--text3:#5a6078;--neu-out:6px 6px 14px #080b12,-4px -4px 10px #222840;--neu-in:inset 4px 4px 10px #080b12,inset -3px -3px 8px #222840;--neu-btn:3px 3px 8px #080b12,-2px -2px 6px #222840;--sidebar-w:220px;--r:14px;}
    [data-theme="light"]{--bg:#e8eaf0;--bg2:#dde0ea;--surface:#eaecf4;--surface2:#f0f2f8;--border:rgba(0,0,0,0.06);--text:#1a1f35;--text2:#5a6078;--text3:#9099b8;--neu-out:6px 6px 14px #c8cad4,-4px -4px 10px #ffffff;--neu-in:inset 4px 4px 10px #c8cad4,inset -3px -3px 8px #ffffff;--neu-btn:3px 3px 8px #c8cad4,-2px -2px 6px #ffffff;}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;transition:background .3s,color .3s;}

    /* ── Shared sidebar CSS ── */
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
    .content{padding:28px 32px;display:flex;flex-direction:column;gap:28px;flex:1;}

    /* ── Page header ── */
    .page-header h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;letter-spacing:-.02em;margin-bottom:4px;}
    .page-header p{font-size:13px;color:var(--text2);}
    .section-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;letter-spacing:-.01em;margin-bottom:14px;display:flex;align-items:center;gap:8px;}
    .section-title span{font-size:11px;font-weight:600;color:var(--text3);font-family:'DM Sans',sans-serif;letter-spacing:.04em;text-transform:uppercase;}

    /* ── Current plan banner ── */
    .plan-banner{background:var(--surface);border-radius:var(--r);box-shadow:var(--neu-out);padding:22px 26px;display:flex;align-items:center;justify-content:space-between;gap:20px;border-left:3px solid var(--secondary);}
    .plan-banner-left{display:flex;flex-direction:column;gap:4px;}
    .plan-banner-label{font-size:10.5px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--text3);}
    .plan-banner-name{font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:var(--secondary);}
    .plan-banner-sub{font-size:12.5px;color:var(--text2);margin-top:2px;}
    .badge-active{display:inline-flex;align-items:center;gap:5px;background:rgba(40,200,64,.12);color:var(--accent);border-radius:6px;padding:4px 10px;font-size:11.5px;font-weight:600;margin-top:6px;width:fit-content;}
    .badge-active::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--accent);animation:pulse-dot 1.5s ease-in-out infinite;}
    @keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.8)}}
    .plan-banner-right{display:flex;flex-direction:column;align-items:flex-end;gap:10px;}
    .renewal-info{text-align:right;}
    .plan-next-label{font-size:11px;color:var(--text3);}
    .plan-next-date{font-size:14px;font-weight:600;color:var(--text);margin-top:2px;}
    .plan-banner-actions{display:flex;gap:8px;}
    .btn{display:inline-flex;align-items:center;gap:6px;border-radius:9px;padding:8px 16px;font-size:12.5px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all .18s;text-decoration:none;border:none;}
    .btn-primary{background:var(--secondary);color:#fff;box-shadow:0 4px 14px rgba(238,147,70,.3);}
    .btn-primary:hover{opacity:.88;transform:translateY(-1px);}
    .btn-ghost{background:var(--surface2);color:var(--text2);box-shadow:var(--neu-btn);}
    .btn-ghost:hover{color:var(--text);}

    /* ── Summary cards ── */
    .summary-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
    .summary-card{background:var(--surface);border-radius:var(--r);box-shadow:var(--neu-out);padding:18px 20px;}
    .summary-label{font-size:11px;color:var(--text3);font-weight:600;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px;}
    .summary-value{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;}
    .summary-value.orange{color:var(--secondary);}
    .summary-value.blue{color:var(--blue);}
    .summary-value.green{color:var(--accent);}

    /* ── Plan cards ── */
    #available-plans{scroll-margin-top:88px;}
    .plans-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
    .plan-card{background:var(--surface);border-radius:var(--r);box-shadow:var(--neu-out);padding:22px;display:flex;flex-direction:column;gap:14px;position:relative;border:1.5px solid transparent;transition:border-color .2s,transform .2s;}
    .plan-card:hover{transform:translateY(-2px);}
    .plan-card.current{border-color:var(--secondary);}
    .plan-card.popular{border-color:var(--accent);}
    .plan-card-badge{position:absolute;top:-11px;left:50%;transform:translateX(-50%);font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:3px 12px;border-radius:20px;white-space:nowrap;}
    .plan-card-badge.badge-current{background:var(--secondary);color:#fff;}
    .plan-card-badge.badge-popular{background:var(--accent);color:#fff;}
    .plan-card-name{font-family:'Syne',sans-serif;font-size:17px;font-weight:800;letter-spacing:.02em;}
    .plan-card-duration{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin-top:-8px;}
    .plan-card-price{display:flex;align-items:baseline;gap:4px;}
    .plan-card-price .currency{font-size:16px;font-weight:600;color:var(--text2);margin-top:4px;}
    .plan-card-price .amount{font-family:'Syne',sans-serif;font-size:30px;font-weight:800;}
    .plan-card-price .period{font-size:12px;color:var(--text3);}
    .plan-card-best{font-size:12px;color:var(--text3);font-style:italic;}
    .plan-features{list-style:none;display:flex;flex-direction:column;gap:7px;flex:1;}
    .plan-features li{font-size:12px;line-height:1.5;display:flex;align-items:flex-start;gap:6px;}
    .plan-features li.yes{color:var(--text);}
    .plan-features li.no{color:var(--text3);opacity:.5;}
    .plan-features li::before{content:'✅';flex-shrink:0;}
    .plan-features li.no::before{content:'❌';}
    .plan-action{width:100%;text-align:center;justify-content:center;padding:10px;}
    .plan-action.upgrade{background:var(--secondary);color:#fff;box-shadow:0 4px 14px rgba(238,147,70,.25);}
    .plan-action.upgrade:hover{opacity:.88;}
    .plan-action.current-plan{background:var(--surface2);color:var(--text3);cursor:default;box-shadow:var(--neu-in);}
    .plan-action.lower-tier{background:transparent;color:var(--text3);border:1px solid var(--border);cursor:pointer;opacity:.7;transition:all .2s;}
    .plan-action.lower-tier:hover{opacity:1;border-color:var(--red);color:var(--red);}

    /* ── Payment method selector ── */
    .pay-methods{display:flex;flex-direction:column;gap:10px;margin-bottom:16px;}
    .pay-method{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface2);cursor:pointer;transition:all .18s;}
    .pay-method:hover:not(.disabled){border-color:var(--secondary);}
    .pay-method.selected{border-color:var(--secondary);box-shadow:0 0 0 2px rgba(238,147,70,.2);}
    .pay-method.disabled{opacity:.4;cursor:not-allowed;}
    .pay-method-radio{width:18px;height:18px;border-radius:50%;border:2px solid var(--text3);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .pay-method.selected .pay-method-radio{border-color:var(--secondary);}
    .pay-method.selected .pay-method-radio::after{content:'';width:10px;height:10px;border-radius:50%;background:var(--secondary);}
    .pay-method-info{flex:1;}
    .pay-method-name{font-size:13px;font-weight:600;}
    .pay-method-desc{font-size:11.5px;color:var(--text3);margin-top:2px;}
    .wallet-badge{font-size:11px;font-weight:700;padding:2px 8px;border-radius:5px;background:rgba(91,141,239,.12);color:var(--blue);}
    .topup-form{display:flex;gap:8px;align-items:center;}
    .topup-input{width:80px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:6px 10px;font-size:13px;color:var(--text);font-family:'DM Sans',sans-serif;box-shadow:var(--neu-in);outline:none;}
    .topup-input:focus{border-color:var(--secondary);}
    .alert-banner{padding:14px 20px;border-radius:var(--r);font-size:13px;font-weight:500;display:flex;align-items:center;gap:10px;}
    .alert-banner.success{background:rgba(40,200,64,.1);color:var(--accent);border:1px solid rgba(40,200,64,.2);}
    .alert-banner.error{background:rgba(224,85,85,.1);color:var(--red);border:1px solid rgba(224,85,85,.2);}
    .alert-banner.info{background:rgba(91,141,239,.1);color:var(--blue);border:1px solid rgba(91,141,239,.2);}

    /* ── Subscription details panel ── */
    .sub-panel{background:var(--surface);border-radius:var(--r);box-shadow:var(--neu-out);padding:22px 26px;display:flex;flex-direction:column;gap:16px;}
    .sub-row{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;}
    .sub-item{display:flex;flex-direction:column;gap:3px;}
    .sub-item-label{font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:.06em;}
    .sub-item-value{font-size:14px;font-weight:600;color:var(--text);}
    .sub-divider{height:1px;background:var(--border);}
    .sub-actions{display:flex;gap:10px;flex-wrap:wrap;}

    /* ── Payment history table ── */
    .inv-panel{background:var(--surface);border-radius:var(--r);box-shadow:var(--neu-out);overflow:hidden;}
    .inv-panel-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px 0;}
    .inv-panel-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;}
    .inv-search-wrap{padding:12px 22px;}
    .inv-search{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:9px;padding:8px 14px;font-size:13px;color:var(--text);font-family:'DM Sans',sans-serif;box-shadow:var(--neu-in);outline:none;transition:border-color .18s;}
    .inv-search:focus{border-color:var(--secondary);}
    .inv-search::placeholder{color:var(--text3);}
    .inv-table{width:100%;border-collapse:collapse;font-size:13px;}
    .inv-table th{text-align:left;padding:10px 22px;font-size:10.5px;font-weight:600;color:var(--text3);letter-spacing:.07em;text-transform:uppercase;border-bottom:1px solid var(--border);background:var(--surface2);}
    .inv-table td{padding:13px 22px;border-bottom:1px solid var(--border);vertical-align:middle;}
    .inv-table tr:last-child td{border-bottom:none;}
    .inv-table tr:hover td{background:rgba(255,255,255,.02);}
    .inv-id{font-family:'Syne',sans-serif;font-size:12px;font-weight:700;color:var(--text2);}
    .inv-date{color:var(--text2);font-size:12.5px;}
    .inv-desc{color:var(--text);font-weight:500;}
    .inv-amount{font-weight:700;font-size:13.5px;}
    .status-badge{display:inline-flex;align-items:center;gap:5px;border-radius:6px;padding:3px 9px;font-size:11.5px;font-weight:600;}
    .status-badge.paid{background:rgba(40,200,64,.12);color:var(--accent);}
    .status-badge.pending{background:rgba(238,147,70,.12);color:var(--secondary);}
    .status-badge.failed{background:rgba(224,85,85,.12);color:var(--red);}
    .rzp-link{font-size:11.5px;color:var(--blue);text-decoration:none;opacity:.8;}
    .rzp-link:hover{opacity:1;text-decoration:underline;}

    /* ── Animations ── */
    .fade-up{opacity:0;transform:translateY(14px);animation:fadeUp .4s ease forwards;}
    @keyframes fadeUp{to{opacity:1;transform:none;}}
    .d1{animation-delay:.05s}.d2{animation-delay:.1s}.d3{animation-delay:.15s}
    .d4{animation-delay:.2s}.d5{animation-delay:.25s}.d6{animation-delay:.3s}

    /* ── Upgrade modal ── */
    .modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:100;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
    .modal-bg.open{display:flex;}
    .modal{background:var(--surface);border-radius:16px;box-shadow:var(--neu-out);padding:28px;max-width:400px;width:90%;}
    .modal h3{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:10px;}
    .modal p{font-size:13px;color:var(--text2);line-height:1.6;margin-bottom:20px;}
    .modal-actions{display:flex;gap:10px;justify-content:flex-end;}

    @media(max-width:1100px){.plans-grid{grid-template-columns:1fr 1fr;}.summary-row{grid-template-columns:1fr 1fr;}}
    @media(max-width:720px){.sidebar{display:none;}.main{margin-left:0;}.content{padding:20px 16px;}.topbar{padding:14px 16px;}.plans-grid{grid-template-columns:1fr;}.summary-row{grid-template-columns:1fr 1fr;}}
  </style>
  <script>(function(){var t=localStorage.getItem('sb_theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main">
  <header class="topbar">
    <div class="topbar-title">My Plan &amp; Billing</div>
    <div class="topbar-right">
      <span id="themeIcon" style="font-size:13px">🌙</span>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
      <button class="topbar-icon-btn" aria-label="Notifications">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
      </button>
    </div>
  </header>

  <div class="content">

    <!-- Page header -->
    <div class="page-header fade-up d1">
      <h1>My Plan &amp; Billing</h1>
      <p>View your current plan, upgrade anytime, and track your payment history</p>
    </div>

    <?php if ($payment_status === 'success'): ?>
      <div class="summary-card fade-up d1" style="border-left:3px solid var(--accent)">
        <div class="summary-label">Payment Status</div>
        <div class="summary-value green">Payment Successful</div>
        <?php if ($payment_id !== ''): ?>
          <div class="plan-banner-sub">Transaction ID: <?= htmlspecialchars($payment_id) ?></div>
        <?php endif; ?>
      </div>
    <?php elseif ($payment_status === 'failed' || $payment_status === 'error' || $payment_status === 'missing'): ?>
      <div class="summary-card fade-up d1" style="border-left:3px solid var(--red)">
        <div class="summary-label">Payment Status</div>
        <div class="summary-value" style="color:var(--red)">Payment Failed</div>
        <div class="plan-banner-sub">Please try again.</div>
      </div>
    <?php endif; ?>

    <?php if ($msg === 'upgrade_success'): ?>
      <div class="alert-banner success fade-up d1">✅ Plan upgraded successfully!</div>
    <?php elseif ($msg === 'downgrade_success'): ?>
      <div class="alert-banner info fade-up d1">ℹ️ Plan downgraded. Prorated refund added to wallet.</div>
    <?php elseif ($msg === 'insufficient_funds'): ?>
      <div class="alert-banner error fade-up d1">⚠️ Insufficient wallet balance. Top up or use Razorpay.</div>
    <?php elseif ($msg === 'topup_success'): ?>
      <div class="alert-banner success fade-up d1">✅ Wallet topped up successfully!</div>
    <?php endif; ?>

    <!-- Current plan banner -->
    <div class="plan-banner fade-up d2">
      <div class="plan-banner-left">
        <div class="plan-banner-label">Current Plan</div>
        <div class="plan-banner-name"><?= $current_name ?></div>
        <div class="plan-banner-sub">
          $<?= $current_price ?> / <?= $current_plan['period'] ?>
          <?php if ($current_plan['recurring']): ?>&nbsp;· Renews automatically<?php endif; ?>
        </div>
        <div class="badge-active">Active</div>
      </div>
      <div class="plan-banner-right">
        <?php if ($current_plan['recurring']): ?>
        <div class="renewal-info">
          <div class="plan-next-label">Next billing date</div>
          <div class="plan-next-date"><?= $next_billing ?></div>
        </div>
        <?php endif; ?>
        <div class="plan-banner-actions">
          <button class="btn btn-primary" type="button" onclick="scrollToPlans()">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
            Pay / Manage
          </button>
        </div>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="summary-row fade-up d3">
      <div class="summary-card">
        <div class="summary-label">Active Plan</div>
        <div class="summary-value orange"><?= $current_name ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-label">Wallet Balance</div>
        <div class="summary-value blue">$<?= number_format($wallet_balance, 2) ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-label"><?= $current_plan['recurring'] ? 'Next Renewal' : 'Access Window' ?></div>
        <div class="summary-value green">
          <?= $current_plan['recurring'] ? $next_billing : $current_plan['label'] ?>
        </div>
      </div>
    </div>

    <!-- Plan cards — upgrade only, no downgrade/pause/cancel -->
    <div class="fade-up d4" id="available-plans">
      <div class="section-title">Available Plans <span>Upgrade anytime</span></div>
      <div class="plans-grid">
        <?php foreach ($plan_catalog as $pid => $plan):
          $is_current = ($pid === $current_plan_id);
          $is_upgrade = ($pid > $current_plan_id);
          $is_lower   = ($pid < $current_plan_id);
        ?>
        <div class="plan-card <?= $is_current ? 'current' : '' ?> <?= ($pid === 3 && !$is_current) ? 'popular' : '' ?>">

          <?php if ($is_current): ?>
            <div class="plan-card-badge badge-current">Your Plan</div>
          <?php elseif ($pid === 3 && !$is_current): ?>
            <div class="plan-card-badge badge-popular">Most Popular</div>
          <?php endif; ?>

          <div class="plan-card-name"><?= $plan['name'] ?></div>
          <div class="plan-card-duration"><?= $plan['label'] ?> access</div>

          <div class="plan-card-price">
            <span class="currency">$</span>
            <span class="amount"><?= $plan['price'] ?></span>
            <span class="period">/ <?= $plan['period'] ?></span>
          </div>

          <div class="plan-card-best"><?= $plan['best_for'] ?></div>

          <ul class="plan-features">
            <?php foreach ($plan['features'] as [$type, $text]): ?>
            <li class="<?= $type ?>"><?= htmlspecialchars($text) ?></li>
            <?php endforeach; ?>
          </ul>

          <?php if ($is_current): ?>
            <button class="btn plan-action current-plan" disabled>Current Plan</button>

          <?php elseif ($is_upgrade): ?>
            <button class="btn plan-action upgrade"
                    onclick="openUpgradeModal('<?= $plan['name'] ?>','<?= $plan['price'] ?>',<?= $pid ?>,'<?= $plan['period'] ?>')">
              Upgrade to <?= $plan['name'] ?> →
            </button>

          <?php else: /* lower tier — downgrade available */ ?>
            <button class="btn plan-action lower-tier"
                    onclick="openDowngradeModal('<?= $plan['name'] ?>', <?= $pid ?>)">
              Downgrade to <?= $plan['name'] ?>
            </button>
          <?php endif; ?>

        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Subscription details — no cancel / pause / downgrade links -->
    <div class="fade-up d5">
      <div class="section-title">Subscription Details</div>
      <div class="sub-panel">
        <div class="sub-row">
          <div class="sub-item">
            <div class="sub-item-label">Subscription ID</div>
            <div class="sub-item-value" style="font-family:monospace;font-size:12px;color:var(--text2);">
              <?= $razorpay_sub_id ? htmlspecialchars($razorpay_sub_id) : 'sub_XXXXXXXXXXXX' ?>
            </div>
          </div>
          <div class="sub-item">
            <div class="sub-item-label">Billing Cycle</div>
            <div class="sub-item-value"><?= $current_plan['recurring'] ? 'Monthly' : 'One-time' ?></div>
          </div>
          <div class="sub-item">
            <div class="sub-item-label"><?= $current_plan['recurring'] ? 'Next Charge' : 'Access Window' ?></div>
            <div class="sub-item-value">
              <?= $current_plan['recurring']
                  ? '$' . $current_price . '.00 on ' . $next_billing
                  : $current_plan['label'] . ' from activation' ?>
            </div>
          </div>
          <div class="sub-item">
            <div class="sub-item-label">Wallet Balance</div>
            <div class="sub-item-value" style="color:var(--blue)">$<?= number_format($wallet_balance, 2) ?></div>
          </div>
        </div>
        <div class="sub-divider"></div>
        <div class="sub-actions">
          <div class="topup-form">
            <input type="number" id="topupAmount" class="topup-input" placeholder="$" min="1" max="500" step="1" value="10">
            <button type="button" class="btn btn-ghost" onclick="startRazorpayTopUp(document.getElementById('topupAmount').value, 'billing')">💰 Top Up</button>
          </div>
          <button type="button" class="btn btn-ghost" onclick="startRazorpayCheckout(<?= (int) $current_plan_id ?>, 'billing')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="1" y="4" width="22" height="16" rx="2"/>
              <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
            Renew via Razorpay
          </button>
        </div>
      </div>
    </div>

    <!-- Payment history -->
    <div class="inv-panel fade-up d6">
      <div class="inv-panel-header">
        <div class="inv-panel-title">Payment History</div>
      </div>
      <div class="inv-search-wrap">
        <input class="inv-search" id="invSearch" type="text"
               placeholder="Search by date, amount, or payment ID…" autocomplete="off"/>
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
          <tr data-search="<?= strtolower(htmlspecialchars($inv['description'] . ' ' . $inv['payment_id'] . ' ' . $inv['date'] . ' ' . $inv['amount'])) ?>">
            <td><span class="inv-id"><?= htmlspecialchars($inv['payment_id']) ?></span></td>
            <td><span class="inv-date"><?= htmlspecialchars($inv['date']) ?></span></td>
            <td><span class="inv-desc"><?= htmlspecialchars($inv['description']) ?></span></td>
            <td><span class="inv-amount"><?= htmlspecialchars($inv['amount']) ?></span></td>
            <td><span class="status-badge <?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span></td>
            <td>
              <a class="rzp-link"
                 href="receipt.php?payment_id=<?= urlencode($inv['payment_id']) ?>"
                 target="_blank">View ↗</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div style="padding:40px;text-align:center;color:var(--text3);font-size:13px;">No payments yet.</div>
      <?php endif; ?>
    </div>

  </div><!-- /content -->
</main>

<!-- UPGRADE MODAL -->
<div class="modal-bg" id="upgradeModal">
  <div class="modal">
    <h3>Upgrade to <span id="upgradePlanName"></span></h3>
    <p>You'll be charged <strong id="upgradePrice"></strong>. Choose your payment method:</p>
    <div class="pay-methods">
      <div class="pay-method selected" id="payMethodRazorpay" onclick="selectPayMethod('razorpay')">
        <div class="pay-method-radio"></div>
        <div class="pay-method-info">
          <div class="pay-method-name">💳 Razorpay (Card / UPI)</div>
          <div class="pay-method-desc">Pay securely via Razorpay checkout</div>
        </div>
      </div>
      <div class="pay-method" id="payMethodWallet" onclick="selectPayMethod('wallet')">
        <div class="pay-method-radio"></div>
        <div class="pay-method-info">
          <div class="pay-method-name">👛 Wallet Balance</div>
          <div class="pay-method-desc">Current: <span class="wallet-badge" id="walletBadge">$0.00</span></div>
        </div>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('upgradeModal')">Cancel</button>
      <button type="button" class="btn btn-primary" id="confirmUpgradeBtn">Confirm Upgrade</button>
    </div>
  </div>
</div>

<!-- DOWNGRADE MODAL -->
<div class="modal-bg" id="downgradeModal">
  <div class="modal">
    <h3 style="color:var(--red)">Downgrade to <span id="downgradePlanName"></span></h3>
    <p>Your remaining subscription time will be prorated and refunded to your wallet balance.</p>
    <form method="POST" action="api/downgrade.php">
      <input type="hidden" name="plan_id" id="downgradePlanId" value="0">
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal('downgradeModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:var(--red);box-shadow:0 4px 14px rgba(224,85,85,.3)">Confirm Downgrade</button>
      </div>
    </form>
  </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script src="payments/razorpay-checkout.js"></script>

<script>
function scrollToPlans() {
  var el = document.getElementById('available-plans');
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Theme toggle
(function(){
  var h=document.documentElement,t=document.getElementById('themeToggle'),i=document.getElementById('themeIcon');
  function s(){i.textContent=h.getAttribute('data-theme')==='dark'?'🌙':'☀️';}s();
  t.addEventListener('click',function(){
    var n=h.getAttribute('data-theme')==='dark'?'light':'dark';
    h.setAttribute('data-theme',n);localStorage.setItem('sb_theme',n);s();
  });
})();

// Invoice search
document.getElementById('invSearch').addEventListener('input',function(){
  var q=this.value.toLowerCase();
  document.querySelectorAll('#invBody tr').forEach(function(r){
    r.style.display=(!q||r.dataset.search.includes(q))?'':'none';
  });
});

// Modal helpers
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-bg').forEach(function(bg){
  bg.addEventListener('click',function(e){ if(e.target===bg) bg.classList.remove('open'); });
});

// ── Upgrade Modal ──
var _upgradePlanId = 0;
var _upgradePlanPrice = 0;
var _selectedPayMethod = 'razorpay';
var _walletBalance = <?= json_encode($wallet_balance) ?>;

function openUpgradeModal(name, price, planId, period){
  document.getElementById('upgradePlanName').textContent = name;
  document.getElementById('upgradePrice').textContent = '$' + price + ' / ' + period;
  _upgradePlanId = Number(planId || 0);
  _upgradePlanPrice = Number(price || 0);
  document.getElementById('walletBadge').textContent = '$' + _walletBalance.toFixed(2);
  var walletEl = document.getElementById('payMethodWallet');
  if (_walletBalance < _upgradePlanPrice) {
    walletEl.classList.add('disabled');
    walletEl.classList.remove('selected');
  } else {
    walletEl.classList.remove('disabled');
  }
  selectPayMethod('razorpay');
  document.getElementById('upgradeModal').classList.add('open');
}

function selectPayMethod(method) {
  var walletEl = document.getElementById('payMethodWallet');
  if (method === 'wallet' && walletEl.classList.contains('disabled')) return;
  _selectedPayMethod = method;
  document.getElementById('payMethodRazorpay').classList.toggle('selected', method === 'razorpay');
  walletEl.classList.toggle('selected', method === 'wallet');
}

document.getElementById('confirmUpgradeBtn').addEventListener('click', function(){
  if (_upgradePlanId <= 0) return;
  closeModal('upgradeModal');
  if (_selectedPayMethod === 'wallet') {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'billing_action.php';
    [{n:'action',v:'upgrade'},{n:'plan_id',v:_upgradePlanId}].forEach(function(f){
      var i = document.createElement('input');
      i.type='hidden'; i.name=f.n; i.value=f.v;
      form.appendChild(i);
    });
    document.body.appendChild(form);
    form.submit();
  } else {
    startRazorpayCheckout(_upgradePlanId, 'billing');
  }
});

// ── Downgrade Modal ──
function openDowngradeModal(name, planId) {
  document.getElementById('downgradePlanName').textContent = name;
  document.getElementById('downgradePlanId').value = planId;
  document.getElementById('downgradeModal').classList.add('open');
}

// Auto-dismiss alerts
setTimeout(function(){
  document.querySelectorAll('.alert-banner').forEach(function(el){
    el.style.transition='opacity .3s'; el.style.opacity='0';
    setTimeout(function(){ el.style.display='none'; }, 300);
  });
}, 5000);
</script>
</body>
</html>