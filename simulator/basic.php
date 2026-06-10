<?php
// require_once __DIR__ . '/../auth/session_guard.php'; // BYPASSED

// Prevent direct URL access (detect address bar typing)
$secFetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
/* sec check bypassed */

require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../includes/simulator_launch.php';

// Tier guard: BASIC simulator requires a paid plan, a wallet run, or an available free trial.
$email = $_SESSION['email'] ?? '';
$user = $db->users->findOne(['email' => $email]);
$sub_id = (int)($user['sub_id'] ?? 0);
$wallet = (float)($user['wallet_balance'] ?? 0.0);
$run_plan = ($sub_id === 0 && $wallet > 0) ? 'BASIC' : (($sub_id === 0 && $wallet <= 0) ? 'FREE' : 'PAID');
$paidState = sb_paid_plan_state($user, true);
$paidSessionSeconds = max(0, (int) ($paidState['remaining_seconds'] ?? 0));
$basicPpm = (float) ($_ENV['PLAN_BASIC_PPM'] ?? 0.10);
$walletSeconds = ($wallet > 0 && $basicPpm > 0) ? (int) (($wallet / $basicPpm) * 60) : 0;
$trialRemainingSeconds = 0;

// Allow access if: subscribed to any paid plan, OR free user (sub_id === 0)
$allowed = true; // BYPASSED
/* allowed check bypassed */

$trialState = sb_free_trial_state($user, false);
if ($trialState['available']) {
    $trialRemainingSeconds = (int) ($trialState['remaining_seconds'] ?? (10 * 60));
}

$accessSeconds = 2592000; // BYPASSED
if ($sub_id >= 1) {
    if ($paidSessionSeconds > 0) {
        $walletSeconds = 0;
        $accessSeconds = $paidSessionSeconds;
    } else {
        $accessSeconds = $walletSeconds;
    }
} elseif ($sub_id === 0) {
    if ($wallet > 0 && ($run_plan === 'BASIC' || $run_plan === 'FREE')) {
        $accessSeconds = $walletSeconds;
    } elseif ($wallet <= 0 && $run_plan === 'FREE' && $trialState['available']) {
        $accessSeconds = $trialRemainingSeconds > 0 ? $trialRemainingSeconds : (10 * 60);
    }
}

$accessExpiresAt = $accessSeconds > 0 ? time() + $accessSeconds : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CERTANITY · Drone Simulator — BASIC</title>
<link rel="icon" type="image/png" href="../assets/logo-iso.png" />
<link rel="apple-touch-icon" href="../assets/logo-iso.png" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --p:#4F8EF7;--p2:#3a7aee;--p3:#2563d4;--p4:#1e2d4a;
  --s:#EE9346;--s2:#f4aa6a;--s3:#c97830;
  --t:#607D8B;--t2:#78909C;--t3:#455A64;
  --n:#1a1f2e;--n2:#222736;--n3:#2c3245;--n4:#363d52;
  --bg:#141720;
  --surf:#1c2130;
  --txt:#e2e8f4;--txt2:#a8b8d0;--txt3:#6e84a0;--txt4:#475c72;
  --sh-out:5px 5px 10px #0d1018,-5px -5px 10px #232a3a;
  --sh-in:inset 4px 4px 8px #0d1018,inset -4px -4px 8px #232a3a;
  --sh-sm:3px 3px 6px #0d1018,-3px -3px 6px #232a3a;
  --sh-in-sm:inset 2px 2px 5px #0d1018,inset -2px -2px 5px #232a3a;
  --sh-lg:8px 8px 20px #0a0d14,-8px -8px 20px #242b3d;
  --sh-btn:4px 4px 8px #0d1018,-3px -3px 7px #232a3a;
  --sh-btn-press:inset 3px 3px 6px #0d1018,inset -2px -2px 5px #232a3a;
  --r1:10px;--r2:16px;--r3:22px;--r4:32px;
  --fh:'Space Grotesk',system-ui,sans-serif;
  --fb:'Inter',system-ui,sans-serif;
  --ease:cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:var(--bg);font-family:var(--fb);-webkit-font-smoothing:antialiased;color:var(--txt)}
canvas{display:block}
button{cursor:pointer;border:none;background:none;font-family:var(--fb)}
input,select{font-family:var(--fb)}
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--n4);border-radius:2px}

/* ── Startup ── */
#startup{
  position:fixed;inset:0;z-index:999;
  background:var(--bg);
  display:flex;align-items:center;justify-content:center;flex-direction:column;gap:28px;
  transition:opacity .7s var(--ease),transform .7s var(--ease);
}
#startup.hide{opacity:0;transform:scale(1.02);pointer-events:none}
.sl{font-family:var(--fh);font-size:52px;font-weight:700;color:var(--p);letter-spacing:-2px}
.sl span{color:var(--s)}
.ss{font-size:11px;color:var(--txt4);letter-spacing:4px;text-transform:uppercase;font-weight:500}
.sbw{width:220px;height:4px;border-radius:2px;background:var(--n2);box-shadow:var(--sh-in-sm);overflow:hidden}
.sb{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--p),var(--s));width:0;transition:width 2.6s var(--ease)}
.st{font-size:11px;color:var(--txt4);font-family:var(--fh);min-height:16px}

/* ── App Shell ── */
@media (min-width: 1024px) {
  #app { grid-template-rows: 52px 1fr 0px !important; }
  #bottombar { display: none !important; }
  .joystick-zone { display: none !important; }
}
#app{position:fixed;inset:0;width:100vw;height:100vh;display:block !important;overflow:hidden;background:#05070d}

/* ── Topbar ── */
#topbar{
  grid-column:1/-1;
  background:var(--surf);
  box-shadow:0 2px 12px rgba(0,0,0,.3),0 1px 0 var(--n3);
  display:flex;align-items:center;padding:0 18px;gap:14px;z-index:100;
}
.brand{display:flex;align-items:center;gap:10px;text-decoration:none}
.brand-name{font-family:var(--fh);font-weight:700;font-size:17px;color:var(--p);letter-spacing:-.5px}
.brand-name span{color:var(--s)}
.brand-tag{font-size:9px;letter-spacing:1.5px;text-transform:uppercase;color:white;background:var(--p);padding:2px 8px;border-radius:20px;font-weight:600}
.vsep{width:1px;height:26px;background:var(--n3)}
.nav-pills{display:flex;gap:3px}
.npill{padding:5px 13px;border-radius:var(--r4);font-size:12px;font-weight:500;color:var(--txt3);background:transparent;transition:all .2s var(--ease)}
.npill.on{background:var(--p);color:white;box-shadow:var(--sh-btn)}
.npill:not(.on):hover{background:var(--n2);color:var(--txt)}
.tsp{flex:1}
.top-stat{display:flex;align-items:center;gap:6px;padding:4px 11px;border-radius:var(--r4);background:var(--n);box-shadow:var(--sh-in-sm);font-size:11px;font-weight:500;color:var(--txt2)}
.sdot{width:7px;height:7px;border-radius:50%;background:#4CAF50;animation:bp 1.8s ease infinite}
.sdot.w{background:var(--s);animation-duration:1s}
.sdot.e{background:#F44336;animation:none}
@keyframes bp{0%,100%{opacity:1}50%{opacity:.35}}
.top-clock{font-family:var(--fh);font-size:13px;font-weight:600;color:var(--txt);min-width:58px;text-align:center}

/* ── Left Panel ── */
#lpanel{grid-row:2/4;background:var(--bg);padding:14px 12px;display:flex;flex-direction:column;gap:12px;overflow-y:auto;border-right:1px solid var(--n3)}

/* ── Right Panel ── */
#rpanel{grid-row:2/4;background:var(--bg);padding:14px 12px;display:flex;flex-direction:column;gap:12px;overflow-y:auto;border-left:1px solid var(--n3)}

/* ── Bottom Bar ── */
#bottombar{grid-column:2/3;background:var(--bg);border-top:1px solid var(--n3);padding:12px 16px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}

/* ── 3D Viewport ── */
#viewport{position:absolute;inset:0;overflow:hidden;background:#0a1020;min-width:0;min-height:0;width:100%;height:100%;z-index:0}
#threeCanvas{position:absolute;top:0;left:0;right:0;bottom:0;width:100%!important;height:100%!important;display:block;background:#0a1628;z-index:0}

/* Cinematic vignette */
#viewport::after{
  content:'';position:absolute;inset:0;pointer-events:none;z-index:3;
  background:radial-gradient(ellipse 80% 80% at 50% 50%, transparent 55%, rgba(4,8,20,0.72) 100%);
}
/* Lens glow rim */
#viewport::before{
  content:'';position:absolute;inset:0;pointer-events:none;z-index:4;
  box-shadow:inset 0 0 40px rgba(10,25,80,0.45), inset 0 0 2px rgba(238,147,70,0.15);
  border-radius:2px;
}
/* Film grain overlay */
#vp-grain{
  position:absolute;inset:0;z-index:5;pointer-events:none;
  opacity:0.028;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-size:128px 128px;
  animation:grain 0.12s steps(1) infinite;
  mix-blend-mode:overlay;
}
@keyframes grain{
  0%{background-position:0 0}10%{background-position:-32px -48px}20%{background-position:64px 16px}
  30%{background-position:-16px 80px}40%{background-position:80px -32px}50%{background-position:-48px 48px}
  60%{background-position:16px -80px}70%{background-position:-80px 16px}80%{background-position:32px 64px}
  90%{background-position:-64px -16px}100%{background-position:48px -64px}
}
/* Chromatic aberration scanline hint */
#vp-chroma{
  position:absolute;inset:0;z-index:6;pointer-events:none;opacity:0;
  background:repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,0.012) 2px, rgba(0,0,0,0.012) 4px);
  mix-blend-mode:multiply;
}
.vp-overlay{position:absolute;inset:0;pointer-events:none;display:flex;flex-direction:column;justify-content:space-between;padding:14px}
.vp-tl,.vp-tr,.vp-bl,.vp-br{position:absolute;width:18px;height:18px}
.vp-tl{top:10px;left:10px;border-top:2px solid rgba(238,147,70,.6);border-left:2px solid rgba(238,147,70,.6);border-radius:3px 0 0 0}
.vp-tr{top:10px;right:10px;border-top:2px solid rgba(238,147,70,.6);border-right:2px solid rgba(238,147,70,.6);border-radius:0 3px 0 0}
.vp-bl{bottom:10px;left:10px;border-bottom:2px solid rgba(238,147,70,.6);border-left:2px solid rgba(238,147,70,.6);border-radius:0 0 0 3px}
.vp-br{bottom:10px;right:10px;border-bottom:2px solid rgba(238,147,70,.6);border-right:2px solid rgba(238,147,70,.6);border-radius:0 0 3px 0}
.cam-badge{position:absolute;top:14px;left:50%;transform:translateX(-50%);background:rgba(238,147,70,.9);color:white;padding:4px 12px;border-radius:20px;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;}
.crosshair{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:32px;height:32px}
.ch-h,.ch-v{position:absolute;background:rgba(238,147,70,.5)}
.ch-h{height:1px;width:100%;top:50%;transform:translateY(-50%)}
.ch-v{width:1px;height:100%;left:50%;transform:translateX(-50%)}
.vp-warn{position:absolute;bottom:14px;left:50%;transform:translateX(-50%);background:rgba(244,67,54,.85);color:white;padding:5px 14px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.5px;;opacity:0;transition:opacity .3s;pointer-events:none}
.vp-warn.show{opacity:1}
#toast.show{opacity:1!important;}
#crash-overlay{position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;opacity:0;transition:none;z-index:99999;background:radial-gradient(ellipse at center,rgba(200,20,10,.45) 0%,rgba(0,0,0,.72) 100%)}
#crash-overlay.show{opacity:1;pointer-events:auto}
#crash-overlay .co-icon{font-size:52px;line-height:1;animation:co-pulse 1s ease-in-out infinite alternate}
#crash-overlay .co-title{font-family:var(--fh,monospace);font-size:28px;font-weight:800;color:#ff3b30;letter-spacing:3px;text-shadow:0 0 24px rgba(255,59,48,.9),0 2px 8px rgba(0,0,0,.8);margin:8px 0 4px}
#crash-overlay .co-sub{font-family:var(--fh,monospace);font-size:12px;color:rgba(255,255,255,.75);letter-spacing:2px;text-transform:uppercase}
#crash-overlay .co-btn{margin-top:22px;padding:8px 24px;background:rgba(255,59,48,.22);border:1.5px solid rgba(255,59,48,.7);border-radius:20px;color:#ff6b60;font-family:var(--fh,monospace);font-size:12px;font-weight:700;letter-spacing:1.5px;cursor:pointer;pointer-events:auto;transition:background .2s,color .2s}
#crash-overlay .co-btn:hover{background:rgba(255,59,48,.45);color:#fff}
@keyframes co-pulse{from{transform:scale(1) rotate(-5deg)}to{transform:scale(1.15) rotate(5deg)}}

/* ── Card / Panel ── */
.card{background:var(--surf);border-radius:var(--r3);padding:14px;box-shadow:var(--sh-out)}
.card-sm{border-radius:var(--r2);padding:11px}
.card-title{font-family:var(--fh);font-size:11px;font-weight:600;color:var(--txt3);letter-spacing:1.2px;text-transform:uppercase;margin-bottom:10px;display:flex;align-items:center;gap:7px}
.card-title .ct-dot{width:6px;height:6px;border-radius:50%;background:var(--s);flex-shrink:0}

/* ── Neumorphic Button ── */
.nbtn{border-radius:var(--r1);background:var(--surf);box-shadow:var(--sh-btn);padding:8px 14px;font-size:12px;font-weight:500;color:var(--txt2);transition:all .15s var(--ease);display:inline-flex;align-items:center;gap:6px}
.nbtn:active,.nbtn.active-btn{box-shadow:var(--sh-btn-press);transform:translateY(1px)}
.nbtn.primary{background:var(--p);color:white;box-shadow:3px 3px 7px rgba(0,0,0,.4),-2px -2px 6px rgba(79,142,247,.15)}
.nbtn.primary:active{box-shadow:inset 2px 2px 5px rgba(0,0,0,.25),inset -1px -1px 4px rgba(255,255,255,.1)}
.nbtn.accent{background:var(--s);color:white;box-shadow:3px 3px 7px rgba(238,147,70,.3),-2px -2px 6px rgba(255,255,255,.08)}
.nbtn.danger{background:#F44336;color:white;box-shadow:3px 3px 7px rgba(244,67,54,.3),-2px -2px 6px rgba(255,255,255,.08)}
.nbtn.sm{padding:5px 10px;font-size:11px;border-radius:8px}
.nbtn.icon{padding:8px;border-radius:var(--r1);width:34px;height:34px;justify-content:center}
.nbtn.icon.sm{width:28px;height:28px;padding:5px}
.nbtn-row{display:flex;gap:7px;flex-wrap:wrap}

/* ── Inset Field ── */
.nfield{border-radius:var(--r1);background:var(--surf);box-shadow:var(--sh-in);padding:8px 12px;display:flex;align-items:center;gap:8px}
.nfield input,.nfield select{background:none;border:none;outline:none;font-size:12px;color:var(--txt);width:100%}
.nfield label{font-size:11px;color:var(--txt4);font-weight:500;white-space:nowrap}

/* ── Slider ── */
.nslider-wrap{display:flex;flex-direction:column;gap:5px}
.nslider-label{display:flex;justify-content:space-between;font-size:11px;color:var(--txt3)}
.nslider-label span{font-weight:600;color:var(--txt);font-family:var(--fh)}
input[type=range]{-webkit-appearance:none;appearance:none;width:100%;height:6px;border-radius:3px;background:var(--n2);box-shadow:var(--sh-in-sm);outline:none}
input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;background:var(--surf);box-shadow:var(--sh-sm);cursor:pointer;border:2px solid var(--p4);transition:all .15s}
input[type=range]::-webkit-slider-thumb:hover{border-color:var(--p);transform:scale(1.1)}
input[type=range].accent-range::-webkit-slider-thumb{border-color:rgba(238,147,70,.4)}

/* ── Toggle ── */
.ntoggle{display:flex;align-items:center;gap:10px;cursor:pointer}
.ntoggle-track{width:38px;height:21px;border-radius:11px;background:var(--n2);box-shadow:var(--sh-in-sm);position:relative;transition:background .2s;flex-shrink:0}
.ntoggle-track.on{background:var(--p)}
.ntoggle-thumb{position:absolute;width:15px;height:15px;border-radius:50%;background:var(--surf);box-shadow:var(--sh-sm);top:3px;left:3px;transition:transform .2s var(--ease)}
.ntoggle-track.on .ntoggle-thumb{transform:translateX(17px);background:white}
.ntoggle-text{font-size:12px;color:var(--txt2);font-weight:500}

/* ── Telemetry Value ── */
.tval{display:flex;flex-direction:column;gap:2px;padding:8px 10px;border-radius:var(--r2);background:var(--surf);box-shadow:var(--sh-in-sm)}
.tval-label{font-size:10px;color:var(--txt4);font-weight:500;letter-spacing:.8px;text-transform:uppercase}
.tval-num{font-variant-numeric:tabular-nums;font-family:var(--fh);font-size:17px;font-weight:600;color:var(--txt);line-height:1}
.tval-unit{font-size:10px;color:var(--txt3);margin-top:1px}
.tval.hi .tval-num{font-variant-numeric:tabular-nums;color:var(--s)}
.tval.warn .tval-num{font-variant-numeric:tabular-nums;color:#E53935}
.tval-row{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}
.tval-row2{display:grid;grid-template-columns:repeat(2,1fr);gap:7px}

/* ── Bar Gauge ── */
.bgauge-wrap{display:flex;flex-direction:column;gap:4px}
.bgauge-label{display:flex;justify-content:space-between;font-size:10px;color:var(--txt3)}
.bgauge-label span{font-family:var(--fh);font-weight:600}
.bgauge-track{height:8px;border-radius:4px;background:var(--n2);box-shadow:var(--sh-in-sm);overflow:hidden}
.bgauge-fill{height:100%;border-radius:4px;width:100%;left:0;transform-origin:left center;transform:scaleX(0);will-change:transform;transition:background 0.2s}
.bgauge-fill.blue{background:linear-gradient(90deg,var(--p),var(--p2))}
.bgauge-fill.orange{background:linear-gradient(90deg,var(--s3),var(--s))}
.bgauge-fill.green{background:linear-gradient(90deg,#2E7D32,#4CAF50)}
.bgauge-fill.red{background:linear-gradient(90deg,#c62828,#EF5350)}

/* ── Circular Gauge ── */
.cgauge{position:relative;display:flex;align-items:center;justify-content:center}
.cgauge canvas{border-radius:50%}
.cgauge-val{position:absolute;text-align:center;font-family:var(--fh);font-weight:700;font-size:15px;color:var(--txt);line-height:1}
.cgauge-val small{display:block;font-size:9px;color:var(--txt4);font-weight:500;margin-top:2px}

/* ── Attitude Indicator ── */
.attitude-wrap{display:flex;align-items:center;justify-content:center;padding:6px;border-radius:var(--r2);background:var(--surf);box-shadow:var(--sh-in)}
#attCanvas{border-radius:50%}

/* ── Motor Indicators ── */
.motors-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.motor-item{border-radius:var(--r2);background:var(--surf);box-shadow:var(--sh-in-sm);padding:8px;display:flex;flex-direction:column;gap:4px}
.motor-header{display:flex;justify-content:space-between;align-items:center}
.motor-label{font-size:10px;color:var(--txt4);font-weight:600;letter-spacing:.6px}
.motor-rpm{font-family:var(--fh);font-size:13px;font-weight:700;color:var(--p)}
.motor-bar-wrap{height:5px;border-radius:3px;background:var(--n2);overflow:hidden}
.motor-bar{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--p),var(--s));width:100%;left:0;transform-origin:left center;transform:scaleX(0);will-change:transform}

/* ── Map / Minimap ── */
.minimap{border-radius:var(--r2);overflow:hidden;box-shadow:var(--sh-in);height:120px;position:relative;background:#1a2744}
.minimap canvas{width:100%;height:100%;display:block}
.minimap-badge{position:absolute;bottom:6px;left:6px;background:rgba(30,45,100,.9);color:rgba(255,255,255,.9);font-size:9px;padding:2px 7px;border-radius:10px;font-weight:600;letter-spacing:.5px}

/* ── Flight Modes ── */
.fmode-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
.fmode-btn{padding:7px 4px;border-radius:var(--r1);font-size:10px;font-weight:600;text-align:center;color:var(--txt3);background:var(--surf);box-shadow:var(--sh-sm);transition:all .2s var(--ease);letter-spacing:.3px;display:flex;flex-direction:column;align-items:center;gap:3px}
.fmode-btn .fm-icon{font-size:14px}
.fmode-btn.on{background:var(--p);color:white;box-shadow:3px 3px 7px rgba(0,0,0,.4),-2px -2px 6px rgba(79,142,247,.15)}
.fmode-btn:not(.on):hover{background:var(--n2)}

/* ── Camera Selector ── */
.cam-row{display:flex;gap:6px}
.cam-btn{flex:1;padding:6px;border-radius:var(--r1);font-size:10px;font-weight:600;text-align:center;color:var(--txt3);background:var(--surf);box-shadow:var(--sh-sm);transition:all .2s}
.cam-btn.on{background:var(--s);color:white;box-shadow:3px 3px 7px rgba(238,147,70,.3),-2px -2px 6px rgba(255,255,255,.08)}

/* ── Graph ── */
.graph-canvas{border-radius:var(--r1);width:100%;box-shadow:var(--sh-in-sm)}

/* ── Wind Indicator ── */
.wind-wrap{display:flex;align-items:center;gap:12px}
.wind-compass{position:relative;width:52px;height:52px;flex-shrink:0}
.wind-compass canvas{width:100%;height:100%}

/* ── Tab System ── */
.tabs{display:flex;gap:4px;margin-bottom:10px}
.tab{padding:5px 12px;border-radius:var(--r4);font-size:11px;font-weight:500;color:var(--txt3);background:transparent;transition:all .2s}
.tab.on{background:var(--surf);box-shadow:var(--sh-sm);color:var(--p);font-weight:600}
.tab-content{display:none}
.tab-content.on{display:block}

/* ── Mission Panel ── */
.wp-list{display:flex;flex-direction:column;gap:5px;max-height:100px;overflow-y:auto}
.wp-item{display:flex;align-items:center;gap:8px;padding:5px 8px;border-radius:var(--r1);background:var(--surf);box-shadow:var(--sh-in-sm);font-size:11px;color:var(--txt2)}
.wp-num{width:18px;height:18px;border-radius:50%;background:var(--p);color:white;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.wp-coords{font-family:var(--fh);font-size:10px;color:var(--txt4);margin-left:auto}

/* ── Warning System ── */
.warn-list{display:flex;flex-direction:column;gap:4px}
.warn-item{display:flex;align-items:center;gap:7px;padding:5px 8px;border-radius:var(--r1);background:var(--surf);box-shadow:var(--sh-in-sm);font-size:11px}
.warn-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.warn-dot.ok{background:#4CAF50}
.warn-dot.warn{background:var(--s)}
.warn-dot.err{background:#F44336}

/* ── Keyboard ── */
.kbd-row{display:flex;flex-wrap:wrap;gap:5px}
.kbd{display:flex;flex-direction:column;align-items:center;gap:2px;font-size:9px;color:var(--txt3);font-weight:500}
.kd{background:var(--surf);box-shadow:var(--sh-sm);border-radius:6px;padding:4px 7px;font-size:11px;font-family:var(--fh);font-weight:600;color:var(--txt2);min-width:24px;text-align:center}

/* ── Log ── */
.log-list{display:flex;flex-direction:column;gap:3px;max-height:80px;overflow-y:auto}
.log-item{font-size:10px;color:var(--txt3);padding:3px 6px;border-radius:6px;background:var(--surf);box-shadow:var(--sh-in-sm);display:flex;gap:6px}
.log-item.ok .log-tag{color:#4CAF50}
.log-item.err .log-tag{color:#F44336}
.log-item.warn .log-tag{color:var(--s)}
.log-tag{font-weight:600;font-family:var(--fh)}

/* ── Hover Throttle ── */
.ht-row{display:flex;align-items:center;gap:8px;font-size:11px;color:var(--txt3)}
.ht-val{font-family:var(--fh);font-weight:700;font-size:13px;color:var(--p);min-width:36px}

/* Arm status */
#arm-status{font-size:10px;letter-spacing:.8px;text-transform:uppercase;padding:3px 9px;border-radius:10px;font-weight:700;background:#F44336;color:white;margin-left:4px}
#arm-status.armed{background:#4CAF50}
#pause-btn.paused{background:var(--s);color:white;box-shadow:3px 3px 7px rgba(238,147,70,.3),-2px -2px 6px rgba(255,255,255,.08);}
.export-panel{display:flex;flex-direction:column;gap:7px;}
.export-stat-row{display:grid;grid-template-columns:1fr 1fr;gap:5px;}
.export-stat{padding:5px 8px;border-radius:var(--r1);background:var(--surf);box-shadow:var(--sh-in-sm);font-size:10px;}
.export-stat .es-val{font-family:var(--fh);font-weight:700;font-size:13px;color:var(--p);}
.export-stat .es-lbl{color:var(--txt4);font-size:9px;letter-spacing:.5px;text-transform:uppercase;}
.tgraph-legend{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:6px;}
.tgl-item{display:flex;align-items:center;gap:4px;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:600;cursor:pointer;background:var(--surf);box-shadow:var(--sh-sm);opacity:0.45;transition:opacity .2s;}
.tgl-item.on{opacity:1;box-shadow:var(--sh-btn);}
.tgl-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}

/* ── Drone Customize Panel ── */
.profile-customize{display:none;flex-direction:column;gap:8px;margin-top:8px;padding-top:8px;border-top:1px solid var(--n3)}
.profile-customize.open{display:flex}
.profile-section-title{font-size:10px;font-weight:700;color:var(--txt4);letter-spacing:1px;text-transform:uppercase;margin-bottom:2px}
.profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px}
.profile-field{display:flex;flex-direction:column;gap:3px}
.profile-field label{font-size:10px;color:var(--txt3);font-weight:500}
.profile-field input[type=number]{width:100%;border-radius:7px;background:var(--surf);box-shadow:var(--sh-in-sm);padding:5px 8px;font-size:12px;color:var(--txt);font-weight:600;border:none;outline:none;font-family:var(--fb)}
.profile-field input[type=number]:focus{box-shadow:var(--sh-in),0 0 0 2px rgba(79,142,247,.2)}
.profile-color-row{display:flex;align-items:center;gap:8px;margin-top:2px}
.profile-color-row label{font-size:10px;color:var(--txt3);font-weight:500;flex:1}
.profile-color-row input[type=color]{width:34px;height:28px;border-radius:7px;border:none;cursor:pointer;padding:2px;background:var(--surf);box-shadow:var(--sh-sm)}

/* ── Custom Profile Modal ── */
#custom-profile-modal{
  position:fixed;inset:0;z-index:900;
  background:rgba(0,0,0,.65);backdrop-filter:blur(6px);
  display:none;align-items:center;justify-content:center;
}
#custom-profile-modal.open{display:flex}
.modal-card{
  background:var(--surf);border-radius:var(--r3);
  padding:22px;width:360px;max-width:95vw;max-height:90vh;
  overflow-y:auto;box-shadow:var(--sh-lg);
  animation:modalIn .25s var(--ease);
}
@keyframes modalIn{from{opacity:0;transform:translateY(12px) scale(.97)}to{opacity:1;transform:none}}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.modal-title{font-family:var(--fh);font-size:15px;font-weight:700;color:var(--txt)}
.modal-close{width:28px;height:28px;border-radius:8px;background:var(--n2);box-shadow:var(--sh-sm);display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--txt3);cursor:pointer;transition:all .15s}
.modal-close:hover{background:var(--n3)}
.modal-section{margin-bottom:14px}
.modal-section-label{font-size:10px;font-weight:700;color:var(--s);letter-spacing:1.2px;text-transform:uppercase;margin-bottom:7px;display:flex;align-items:center;gap:5px}
.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.modal-field{display:flex;flex-direction:column;gap:4px}
.modal-field label{font-size:11px;color:var(--txt3);font-weight:500}
.modal-field input[type=text],.modal-field input[type=number]{width:100%;border-radius:8px;background:var(--bg);box-shadow:var(--sh-in-sm);padding:7px 10px;font-size:12px;color:var(--txt);font-weight:600;border:none;outline:none;font-family:var(--fb)}
.modal-field input:focus{box-shadow:var(--sh-in),0 0 0 2px rgba(79,142,247,.22)}
.modal-field.full{grid-column:1/-1}
.modal-hint{font-size:10px;color:var(--txt4);margin-top:2px}
.profile-card-row{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.profile-card{padding:8px 12px;border-radius:var(--r1);background:var(--surf);box-shadow:var(--sh-sm);font-size:11px;font-weight:600;color:var(--txt2);cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:6px;border:2px solid transparent}
.profile-card:hover{border-color:var(--p4)}
.profile-card.active{border-color:var(--p);color:var(--p)}
.profile-card .pc-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
/* ── Virtual Joystick ── */
.vj-pad{
  position:relative;width:88px;height:88px;flex-shrink:0;
  border-radius:50%;background:var(--n);
  box-shadow:var(--sh-in);cursor:crosshair;user-select:none;
}
.vj-center{
  position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
  width:6px;height:6px;border-radius:50%;background:var(--n3);
  pointer-events:none;
}
.vj-knob{
  position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
  width:26px;height:26px;border-radius:50%;
  background:var(--surf);box-shadow:var(--sh-sm);
  border:2px solid var(--s);
  pointer-events:none;transition:box-shadow .1s;
}
.vj-pad:active .vj-knob,.vj-pad.active .vj-knob{
  box-shadow:var(--sh-out);border-color:var(--p);
}
.vj-label-t,.vj-label-b,.vj-label-l,.vj-label-r{
  position:absolute;font-size:7px;font-weight:700;color:var(--txt4);
  letter-spacing:.5px;text-transform:uppercase;pointer-events:none;
}
.vj-label-t{top:4px;left:50%;transform:translateX(-50%)}
.vj-label-b{bottom:4px;left:50%;transform:translateX(-50%)}
.vj-label-l{left:3px;top:50%;transform:translateY(-50%)}
.vj-label-r{right:3px;top:50%;transform:translateY(-50%)}

/* ── Stick Meters ── */
.stick-meter-wrap{display:flex;align-items:center;gap:5px}
.stick-meter-lbl{font-size:8px;font-weight:700;color:var(--txt4);width:20px;flex-shrink:0;letter-spacing:.3px}
.stick-meter-track{flex:1;height:5px;border-radius:3px;background:var(--n2);box-shadow:var(--sh-in-sm);overflow:hidden;position:relative}
.stick-meter-track.bidir::before{
  content:'';position:absolute;left:50%;top:0;width:1px;height:100%;
  background:var(--n4);z-index:1;
}
.stick-meter-fill{position:absolute;height:100%;border-radius:3px;transition:width .06s,left .06s}
.stick-meter-fill.accent{background:var(--s)}
.stick-meter-fill.primary{background:var(--p)}

/* ── Obstacle Radar ── */
.obstacle-radar{position:relative;display:flex;align-items:center;justify-content:center;padding:6px}
.obs-ring{stroke:rgba(96,125,139,0.25);fill:none}
.obs-sector-label{font-size:8px;fill:var(--txt4);font-family:'Inter',sans-serif;font-weight:600}
.obs-bar-row{display:grid;grid-template-columns:28px 1fr 28px;gap:4px;align-items:center}
.obs-bar-lbl{font-size:9px;color:var(--txt4);font-weight:700;text-align:center}
.obs-bar-track{height:5px;border-radius:3px;background:var(--n2);box-shadow:var(--sh-in-sm);overflow:hidden}
.obs-bar-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,#F44336,#EE9346,#4CAF50);width:100%;left:0;transform-origin:left center;transform:scaleX(0);will-change:transform}

/* ── GPS Panel ── */
.gps-fix-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:9px;font-weight:700;letter-spacing:.5px;text-transform:uppercase}
.gps-fix-3d{background:#4CAF50;color:white}
.gps-fix-2d{background:var(--s);color:white}
.gps-fix-none{background:#F44336;color:white}
.gps-coord{font-family:var(--fh);font-size:11px;font-weight:600;color:var(--txt);letter-spacing:.3px}
.gps-sat-row{display:flex;gap:3px;flex-wrap:wrap;margin-top:4px}
.gps-sat-dot{width:6px;height:6px;border-radius:50%;background:var(--n3);transition:background .3s}
.gps-sat-dot.on{background:#4CAF50}
.gps-sat-dot.dim{background:var(--s);opacity:.5}

/* ── VSLAM Panel ── */
.vslam-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:9px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-bottom:6px}
.vslam-active{background:var(--p);color:white;animation:bp 1.2s ease infinite}
.vslam-idle{background:var(--n2);color:var(--txt4)}
.vslam-quality{height:4px;border-radius:2px;background:var(--n2);box-shadow:var(--sh-in-sm);overflow:hidden;margin-top:5px}
.vslam-quality-fill{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--p),var(--s));transition:width .2s}

/* ── PID Telemetry Panel ── */
.pid-telem-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px}
.pid-axis-card{border-radius:var(--r1);background:var(--surf);box-shadow:var(--sh-in-sm);padding:7px}
.pid-axis-title{font-size:9px;font-weight:700;color:var(--txt4);letter-spacing:1px;text-transform:uppercase;margin-bottom:5px;display:flex;align-items:center;justify-content:space-between}
.pid-axis-live{font-variant-numeric:tabular-nums;font-size:8px;font-family:var(--fh);font-weight:600;color:var(--s)}
.pid-gains-row{display:flex;gap:3px;margin-bottom:5px}
.pid-gain{flex:1;text-align:center;padding:3px 2px;border-radius:5px;background:var(--n);box-shadow:var(--sh-in-sm)}
.pid-gain-lbl{font-size:8px;color:var(--txt4);font-weight:600}
.pid-gain-val{font-size:10px;font-family:var(--fh);font-weight:700;color:var(--p)}
.pid-err-track{height:4px;border-radius:2px;background:var(--n2);box-shadow:var(--sh-in-sm);overflow:hidden;position:relative}
.pid-err-track::before{content:'';position:absolute;left:50%;top:0;width:1px;height:100%;background:var(--n4);z-index:1}
.pid-err-fill{position:absolute;height:100%;border-radius:2px;background:var(--s);transition:width .06s,left .06s}

/* ── Rate Hz badges ── */
.hz-badge{display:inline-block;font-size:8px;font-weight:700;padding:1px 5px;border-radius:6px;background:var(--n);box-shadow:var(--sh-in-sm);color:var(--txt4);letter-spacing:.3px;margin-left:4px;vertical-align:middle}


/* NEW HUD CSS */
/* ═══════════════════════════════════════════════════════════════
   CERTANITY ROBOTICS — IMMERSIVE HUD v2
   style.css — Production stylesheet
   ─────────────────────────────────────────────────────────────
   Section Index:
     1.  CSS Custom Properties (Design Tokens)
     2.  Reset & Base
     3.  World / Background Layer
     4.  Screen Overlay Effects (Scanlines, Chromatic)
     5.  HUD Container
     6.  Corner Decorations
     7.  Top Bar
     8.  Side Panels (Left & Right)
     9.  Glass Cards
     10. Signal Bars
     11. Battery / Progress Bars
     12. AI Status
     13. Waypoint Panel Items
     14. Mission / Objective Card
     15. Threat Indicators
     16. Altitude & Speed Tapes
     17. Pitch Scale
     18. Center Reticle & Crosshair
     19. Scan Rings
     20. Target Boxes
     21. Waypoint Markers (Viewport)
     22. Enemy Markers
     23. Horizon Indicator
     24. Compass Strip
     25. Flight Data Strip
     26. Joystick Controls
     27. Bottom Dock
     28. Menu Panel Overlay
     29. Animations / Keyframes
═══════════════════════════════════════════════════════════════ */


/* ─── 1. CSS CUSTOM PROPERTIES ──────────────────────────────── */
:root {
  /* Brand colors */
  --orange:         #FF6500;
  --orange-bg:      rgba(255, 101, 0, 0.15);
  --orange-dim:     rgba(255, 101, 0, 0.12);
  --orange-faint:   rgba(255, 101, 0, 0.08);
  --cyan:           #00FFD4;
  --red:            #FF2244;
  --green:          #00FF88;

  /* Text / UI alphas */
  --text-muted:     rgba(255, 255, 255, 0.35);
  --text-faint:     rgba(255, 255, 255, 0.25);
  --text-ghost:     rgba(255, 255, 255, 0.20);
  --dim-overlay:    rgba(0, 0, 0, 0.55);

  /* Fonts */
  --font-body:      'Rajdhani', sans-serif;
  --font-mono:      'Orbitron', monospace;
}


/* ─── 2. RESET & BASE ───────────────────────────────────────── */
*,
*::before,
*::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

html,
body {
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  background: #000;
  font-family: var(--font-body);
}


/* ─── 3. WORLD / BACKGROUND LAYER ──────────────────────────── */
.world {
  position: absolute;
  inset: 0;
  overflow: hidden;
}

.sky {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    180deg,
    #050915 0%,
    #0a1628 30%,
    #1a2a18 65%,
    #0d1a0a 100%
  );
}

.bg-image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: brightness(0.58) contrast(1.08) saturate(1);
}

.drone-highlight {
  position: absolute;
  left: 50%;
  top: 56%;
  transform: translate(-50%, -50%);
  width: 500px;
  height: 500px;
  border-radius: 50%;
  background: radial-gradient(
    circle,
    rgba(255, 120, 20, 0.18) 0%,
    rgba(255, 120, 20, 0.08) 35%,
    transparent 72%
  );
  pointer-events: none;
  z-index: 1;
}

.fog-layer {
  position: absolute;
  bottom: 20%;
  left: 0;
  right: 0;
  height: 60px;
  background: linear-gradient(
    180deg,
    transparent,
    rgba(150, 200, 160, 0.06),
    transparent
  );
}

.atmo-vignette {
  position: absolute;
  inset: 0;
  background: radial-gradient(
    ellipse 80% 70% at 50% 40%,
    transparent 30%,
    rgba(0, 0, 0, 0.38) 100%
  );
  pointer-events: none;
}


/* ─── 4. SCREEN OVERLAY EFFECTS ────────────────────────────── */
.scanlines {
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    0deg,
    transparent,
    transparent 3px,
    rgba(0, 0, 0, 0.08) 3px,
    rgba(0, 0, 0, 0.08) 4px
  );
  pointer-events: none;
  z-index: 2;
}

.chromatic {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    90deg,
    rgba(255, 0, 50, 0.015) 0%,
    transparent 20%,
    transparent 80%,
    rgba(0, 100, 255, 0.015) 100%
  );
  pointer-events: none;
  z-index: 2;
}


/* ─── 5. HUD CONTAINER ──────────────────────────────────────── */
.hud {
  position: absolute;
  inset: 0;
  z-index: 10;
  pointer-events: none;
}

/* All direct children are interactive unless overridden */
.hud * {
  pointer-events: auto;
}


/* ─── 6. CORNER DECORATIONS ─────────────────────────────────── */
.corner-deco {
  position: absolute;
  width: 36px;
  height: 36px;
  pointer-events: none;
}

.corner-tl { top: 10px; left: 10px;  border-top: 2px solid var(--orange); border-left:  2px solid var(--orange); }
.corner-tr { top: 10px; right: 10px; border-top: 2px solid var(--orange); border-right: 2px solid var(--orange); }
.corner-bl { bottom: 10px; left: 10px;  border-bottom: 2px solid var(--orange); border-left:  2px solid var(--orange); }
.corner-br { bottom: 10px; right: 10px; border-bottom: 2px solid var(--orange); border-right: 2px solid var(--orange); }

.corner-pip {
  position: absolute;
  width: 4px;
  height: 4px;
  background: var(--orange);
}

.corner-tl .corner-pip { bottom: -5px; right: -5px; }
.corner-tr .corner-pip { bottom: -5px; left: -5px; }


/* ─── 7. TOP BAR ────────────────────────────────────────────── */
.topbar {
  position: absolute;
  top: 10px;
  left: 14px;
  right: 14px;
  height: 56px;
  background: linear-gradient(180deg, rgba(4, 8, 16, 0.88), rgba(4, 8, 16, 0.78));
  border: 1px solid rgba(255, 101, 0, 0.18);
  border-radius: 14px;
  display: flex;
  align-items: center;
  padding: 0 16px;
  gap: 0;
  z-index: 20;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
}

.brand-block {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-right: 16px;
}

.logo {
  height: 24px;
  width: auto;
  object-fit: contain;
}

.top-sep {
  width: 1px;
  height: 30px;
  background: rgba(255, 101, 0, 0.15);
  margin: 0 12px;
  pointer-events: none;
}

.topbar-spacer {
  flex: 1;
}

/* Telemetry cell */
.tel {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 48px;
}

.tel-label {
  font-size: 7px;
  color: var(--text-faint);
  letter-spacing: 1.5px;
  text-transform: uppercase;
}

.tel-value {
  font-family: var(--font-mono);
  font-size: 11px;
  color: #fff;
  font-weight: 600;
  line-height: 1.2;
}

/* Telemetry color modifiers */
.tel-orange { color: var(--orange); }
.tel-green  { color: var(--green); }
.tel-cyan   { color: var(--cyan); }

/* Flight mode pill */
.mode-pill {
  font-family: var(--font-mono);
  font-size: 8px;
  font-weight: 700;
  letter-spacing: 2px;
  padding: 3px 9px;
  border: 1px solid var(--orange);
  color: var(--orange);
  background: rgba(255, 101, 0, 0.1);
  animation: border-pulse 3s infinite;
}

/* Recording indicator */
.rec-block {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-right: 8px;
}

.rec-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--red);
  animation: blink 1s infinite;
}

.rec-label {
  font-size: 7px;
  letter-spacing: 1px;
  color: rgba(255, 34, 68, 0.7);
  font-family: var(--font-mono);
}


/* ─── 8. SIDE PANELS ────────────────────────────────────────── */
.side-panel {
  position: absolute;
  top: 78px;
  width: 188px;
  z-index: 15;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.panel-left  { left: 14px; }
.panel-right { right: 14px; }


/* ─── 9. GLASS CARDS ────────────────────────────────────────── */
.nbtn { border-radius: 8px; font-family: 'Rajdhani', sans-serif; font-size: 14px; font-weight: 700; color: #a4b1cd; background: #1f2537; box-shadow: 2px 2px 5px rgba(0,0,0,0.3), -1px -1px 3px rgba(255,255,255,0.05); transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: none; cursor: pointer; padding: 6px 12px; }
.nbtn:active { transform: translateY(1px); box-shadow: inset 2px 2px 5px rgba(0,0,0,0.3); }
.nbtn.accent { background: #EE9346; color: #fff; box-shadow: 0 4px 10px rgba(238,147,70,0.3); }
.nbtn.primary { background: #4f8ef7; color: #fff; box-shadow: 0 4px 10px rgba(79,142,247,0.3); }
.nbtn.danger { background: #F44336; color: #fff; box-shadow: 0 4px 10px rgba(244,67,54,0.3); }
.nbtn.sm { font-size: 12px; padding: 4px 8px; }

.glass-card {
  background: linear-gradient(180deg, rgba(7, 12, 24, 0.82), rgba(7, 12, 24, 0.7));
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-left-color: rgba(255, 101, 0, 0.22);
  border-radius: 12px;
  padding: 10px 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

.card-title {
  font-size: 7px;
  letter-spacing: 2px;
  color: rgba(255, 101, 0, 0.55);
  text-transform: uppercase;
  margin-bottom: 6px;
  padding-bottom: 4px;
  border-bottom: 1px solid var(--orange-faint);
}

.card-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.card-row-top {
  margin-top: 4px;
  margin-bottom: 0;
}

.card-key {
  font-size: 8px;
  color: var(--text-muted);
}

.card-value {
  font-family: var(--font-mono);
  font-size: 9px;
  color: rgba(255, 255, 255, 0.6);
}

.card-value-group {
  display: flex;
  align-items: center;
  gap: 5px;
}

.card-value-sm { font-size: 8px; }

/* Card color modifiers */
.card-orange { color: var(--orange); }
.card-green  { color: var(--green); }
.card-cyan   { color: var(--cyan); }
.card-red    { color: var(--red); }


/* ─── 10. SIGNAL BARS ───────────────────────────────────────── */
.sig-bars {
  display: flex;
  align-items: flex-end;
  gap: 2px;
  height: 12px;
}

.sig-bar {
  width: 3px;
  border-radius: 1px 1px 0 0;
}

.sig-on  { background: var(--orange); }
.sig-off { background: rgba(255, 255, 255, 0.1); }


/* ─── 11. BATTERY / PROGRESS BARS ──────────────────────────── */
.batt-track {
  height: 3px;
  background: rgba(255, 255, 255, 0.07);
  border-radius: 1px;
  margin-bottom: 3px;
}

.batt-fill {
  height: 100%;
  border-radius: 1px;
  background: linear-gradient(90deg, var(--green), rgba(0, 255, 136, 0.6));
}

.wp-progress-block {
  margin-top: 6px;
  border-top: 1px solid var(--orange-faint);
  padding-top: 5px;
}

.wp-progress-fill {
  width: 40%;
  height: 100%;
  border-radius: 1px;
  background: var(--orange);
}


/* ─── 12. AI STATUS ─────────────────────────────────────────── */
.ai-status {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 5px;
}

.ai-status:last-child {
  margin-bottom: 0;
}

.ai-pulse {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--cyan);
  animation: ai-pulse 1.8s infinite;
}

.ai-pulse-green { background: var(--green); }

.ai-text {
  font-size: 8px;
  color: rgba(0, 255, 212, 0.7);
  letter-spacing: 1px;
}

.ai-text-green { color: rgba(0, 255, 136, 0.7); }


/* ─── 13. WAYPOINT PANEL ITEMS ──────────────────────────────── */
.wp-list { /* container */ }

.wp-row {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 4px;
}

.wp-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  flex-shrink: 0;
}

.wp-done    { background: var(--green); }
.wp-active  { background: var(--orange); animation: blink 1s infinite; }
.wp-pending { background: rgba(255, 255, 255, 0.15); }

.wp-bar {
  flex: 1;
  height: 1px;
}

.wp-bar-done    { background: rgba(0, 255, 136, 0.4); }
.wp-bar-active  { background: var(--orange); }
.wp-bar-pending { background: rgba(255, 255, 255, 0.08); }

.wp-tag {
  font-size: 7px;
  font-family: var(--font-mono);
}

.wp-tag-done    { color: rgba(0, 255, 136, 0.5); }
.wp-tag-active  { color: var(--orange); }
.wp-tag-pending { color: rgba(255, 255, 255, 0.2); }


/* ─── 14. MISSION / OBJECTIVE CARD ─────────────────────────── */
.obj-card {
  background: rgba(0, 0, 0, 0.55);
  border: 1px solid rgba(0, 255, 212, 0.15);
  padding: 8px;
}

.obj-title {
  font-size: 7px;
  letter-spacing: 2px;
  color: rgba(0, 255, 212, 0.5);
  text-transform: uppercase;
  margin-bottom: 6px;
}

.obj-mission {
  font-size: 9px;
  color: rgba(255, 255, 255, 0.5);
  line-height: 1.5;
}

.obj-status {
  display: inline-block;
  font-size: 7px;
  letter-spacing: 1px;
  padding: 2px 6px;
  border: 1px solid rgba(255, 101, 0, 0.3);
  color: var(--orange);
  font-family: var(--font-mono);
  margin-top: 4px;
}


/* ─── 15. THREAT INDICATORS ─────────────────────────────────── */
.threat-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 3px;
}

.threat-label {
  font-size: 8px;
  color: var(--text-muted);
}

.threat-bar-wrap {
  width: 70px;
  height: 3px;
  background: rgba(255, 255, 255, 0.07);
}

.threat-bar-fill {
  height: 100%;
}

.threat-high { width: 90%; background: var(--red); }
.threat-med  { width: 45%; background: var(--orange); }


/* ─── 16. ALTITUDE & SPEED TAPES ────────────────────────────── */
.tape {
  position: absolute;
  top: 50%;
  width: 38px;
  height: 220px;
  z-index: 15;
}

.tape-alt { left: 170px;  transform: translateY(-60%); }
.tape-spd { right: 170px; transform: translateY(-60%); }

.tape-wrap {
  width: 100%;
  height: 100%;
  border: none;
  background: rgba(0, 0, 0, 0.45);
  position: relative;
  overflow: hidden;
}

.tape-label {
  position: absolute;
  top: 5px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 7px;
  color: var(--text-ghost);
  letter-spacing: 1.5px;
  text-transform: uppercase;
  font-family: var(--font-mono);
}

.tape-caret {
  position: absolute;
  top: 50%;
  width: 100%;
  transform: translateY(-50%);
}

.tape-caret-line {
  height: 1px;
  width: 100%;
}

.tape-caret-orange { background: var(--orange); }
.tape-caret-cyan   { background: var(--cyan); }

.tape-readout {
  position: absolute;
  right: 5px;
  top: 50%;
  transform: translateY(-50%);
  font-family: var(--font-mono);
  font-size: 10px;
  font-weight: 700;
}

.tape-readout-orange { color: var(--orange); }
.tape-readout-cyan   { color: var(--cyan); }

.tape-ticks {
  position: absolute;
  inset: 18px 0 0 0;
  display: flex;
  flex-direction: column;
  justify-content: space-around;
}

.tape-tick {
  height: 1px;
  align-self: flex-end;
}

.tick-major { width: 14px; background: rgba(255, 255, 255, 0.22); }
.tick-minor { width: 8px;  background: rgba(255, 255, 255, 0.12); }


/* ─── 17. PITCH SCALE ───────────────────────────────────────── */
.pitch-scale {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 15;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 200px;
  pointer-events: none;
}

.pitch-left  { left: 220px; }
.pitch-right { right: 220px; }

.pitch-row {
  display: flex;
  align-items: center;
  gap: 3px;
}

.pitch-left .pitch-row  { flex-direction: row; }
.pitch-right .pitch-row { flex-direction: row-reverse; }

.pitch-mark {
  height: 1px;
}

.pitch-mark-major { width: 16px; background: rgba(255, 255, 255, 0.35); }
.pitch-mark-minor { width: 8px;  background: rgba(255, 255, 255, 0.18); }
.pitch-mark-zero  { width: 20px; background: rgba(255, 101, 0, 0.5); }

.pitch-num {
  font-family: var(--font-mono);
  font-size: 7px;
  color: rgba(255, 255, 255, 0.3);
}

.pitch-num-zero { color: rgba(255, 101, 0, 0.7); }


/* ─── 18. CENTER RETICLE & CROSSHAIR ────────────────────────── */
.center-zone {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -55%);
  z-index: 15;
  width: 260px;
  height: 260px;
  pointer-events: none;
}

/* Reticle rings */
.reticle-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
}

.reticle-ring-1 { border: 1px solid rgba(255, 101, 0, 0.18); animation: rotate 25s linear infinite; }
.reticle-ring-2 { inset: 22px; border: 1px solid rgba(255, 101, 0, 0.12); animation: rotate 14s linear infinite reverse; }
.reticle-ring-3 { inset: 50px; border: 2px solid rgba(255, 101, 0, 0.5); }
.reticle-ring-4 { inset: 58px; border: 1px solid rgba(255, 101, 0, 0.15); animation: rotate 8s linear infinite; }

/* Crosshair arms */
.crosshair-arm {
  position: absolute;
  background: rgba(255, 101, 0, 0.7);
}

.crosshair-top    { width: 1px; height: 34px; top: 16px;    left: 50%; transform: translateX(-50%); }
.crosshair-bottom { width: 1px; height: 34px; bottom: 16px; left: 50%; transform: translateX(-50%); }
.crosshair-left   { height: 1px; width: 34px; left: 16px;   top: 50%;  transform: translateY(-50%); }
.crosshair-right  { height: 1px; width: 34px; right: 16px;  top: 50%;  transform: translateY(-50%); }

/* Crosshair center gap masks */
.crosshair-gap {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #000;
}

.crosshair-gap-h { width: 16px; height: 2px; }
.crosshair-gap-v { height: 16px; width: 2px; }

/* Center dot */
.reticle-center {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: rgba(255, 101, 0, 0.9);
  box-shadow: 0 0 16px rgba(255, 101, 0, 0.8), 0 0 4px rgba(255, 101, 0, 1);
}

/* Reticle text labels */
.reticle-label {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  font-family: var(--font-mono);
  font-size: 7px;
  letter-spacing: 2px;
  white-space: nowrap;
}

.reticle-label-top    { top: -20px;    color: rgba(255, 101, 0, 0.6); }
.reticle-label-bottom { bottom: -20px; color: rgba(0, 255, 212, 0.5); }


/* ─── 19. SCAN RINGS ────────────────────────────────────────── */
.scan-ring {
  position: absolute;
  width: 180px;
  height: 180px;
  border-radius: 50%;
  border: 1px solid rgba(0, 255, 212, 0.25);
  animation: scan-expand 4s ease-out infinite;
  pointer-events: none;
  z-index: 13;
  /* Position set dynamically by JS */
}

.scan-ring-delay {
  animation-delay: 2s;
}


/* ─── 20. TARGET BOXES ──────────────────────────────────────── */
.target-box {
  position: absolute;
  z-index: 16;
  pointer-events: none;
}

/* Target corner brackets */
.target-corner {
  position: absolute;
  width: 12px;
  height: 12px;
}

.tc-tl { top: -1px;    left: -1px;  border-top:    2px solid var(--orange); border-left:   2px solid var(--orange); }
.tc-tr { top: -1px;    right: -1px; border-top:    2px solid var(--orange); border-right:  2px solid var(--orange); }
.tc-bl { bottom: -1px; left: -1px;  border-bottom: 2px solid var(--orange); border-left:   2px solid var(--orange); }
.tc-br { bottom: -1px; right: -1px; border-bottom: 2px solid var(--orange); border-right:  2px solid var(--orange); }

.tc-dim {
  border-color: var(--orange) !important;
  opacity: 0.5;
}

.target-inner {
  position: absolute;
  inset: 10px;
  border: 1px solid rgba(255, 101, 0, 0.25);
}

.target-label {
  position: absolute;
  bottom: -18px;
  left: 0;
  font-size: 7px;
  color: var(--orange);
  letter-spacing: 1px;
  font-family: var(--font-mono);
  white-space: nowrap;
}

.target-pct {
  position: absolute;
  top: -16px;
  right: 0;
  font-size: 7px;
  color: var(--green);
  letter-spacing: 1px;
  font-family: var(--font-mono);
}

/* Secondary target overrides */
.target-label-dim  { color: rgba(255, 101, 0, 0.5); }
.target-pct-dim    { color: rgba(255, 101, 0, 0.5); }


/* ─── 21. WAYPOINT MARKERS (VIEWPORT) ──────────────────────── */
.waypoint-marker {
  position: absolute;
  z-index: 16;
  pointer-events: none;
}

.wp-diamond {
  width: 12px;
  height: 12px;
  border: 2px solid var(--cyan);
  transform: rotate(45deg);
}

.wp-diamond-dim { border-color: rgba(0, 255, 212, 0.3); }

.wp-connector-line {
  width: 1px;
  height: 30px;
  background: linear-gradient(180deg, var(--cyan), transparent);
  margin: 0 auto;
}

.wp-connector-dim { background: linear-gradient(180deg, rgba(0, 255, 212, 0.2), transparent); }

.wp-marker-label {
  display: block;
  font-size: 7px;
  color: var(--cyan);
  letter-spacing: 1px;
  font-family: var(--font-mono);
  text-align: center;
  margin-top: 2px;
  white-space: nowrap;
}

.wp-marker-label-dim { color: rgba(0, 255, 212, 0.3); }

.waypoint-marker-dim { opacity: 0.5; }


/* ─── 22. ENEMY MARKERS ─────────────────────────────────────── */
.enemy-marker {
  position: absolute;
  z-index: 16;
  pointer-events: none;
  display: none
}

.enemy-hex {
  width: 14px;
  height: 14px;
  border: 2px solid var(--red);
  transform: rotate(45deg);
  animation: red-pulse 1.5s infinite;
  display: none
}

.enemy-label {
  font-size: 7px;
  color: var(--red);
  font-family: var(--font-mono);
  white-space: nowrap;
  margin-top: 4px;
  display: none
}


/* ─── 23. HORIZON INDICATOR ─────────────────────────────────── */
.horizon-indicator {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 14;
}

.horizon-bar {
  position: absolute;
  top: 47%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 200px;
  height: 1px;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(0, 255, 212, 0.3),
    rgba(0, 255, 212, 0.3),
    transparent
  );
}


/* ─── 24. COMPASS STRIP ─────────────────────────────────────── */
.compass-strip {
  position: absolute;
  bottom: 68px;
  left: 50%;
  transform: translateX(-50%);
  width: 240px;
  height: 28px;
  overflow: hidden;
  z-index: 15;
}

.compass-caret {
  position: absolute;
  top: 0;
  left: 50%;
  width: 1px;
  height: 100%;
  background: rgba(255, 101, 0, 0.4);
}

.compass-needle {
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left:  5px solid transparent;
  border-right: 5px solid transparent;
  border-top:   8px solid var(--orange);
}

.compass-inner {
  display: flex;
  align-items: flex-end;
  animation: compass-scroll 16s linear infinite;
}

.compass-unit {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  width: 24px;
  flex-shrink: 0;
}

.compass-tick {
  width: 1px;
}

.tick-cardinal { height: 14px; background: rgba(255, 101, 0, 0.55); }
.tick-minor    { height: 7px;  background: rgba(255, 255, 255, 0.15); }

.compass-label {
  font-family: var(--font-mono);
  font-size: 7px;
  color: rgba(255, 255, 255, 0.22);
  margin-top: 2px;
}

.label-cardinal { color: rgba(255, 101, 0, 0.75); }


/* ─── 25. FLIGHT DATA STRIP ─────────────────────────────────── */
.flight-strip {
  position: absolute;
  bottom: 84px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 1px;
  z-index: 15;
}

.flight-cell {
  padding: 4px 10px;
  background: rgba(0, 0, 0, 0.6);
  border-top: 1px solid rgba(255, 101, 0, 0.1);
}

.flight-label {
  display: block;
  font-size: 7px;
  color: rgba(255, 255, 255, 0.22);
  letter-spacing: 1px;
  text-transform: uppercase;
  text-align: center;
}

.flight-value {
  display: block;
  font-family: var(--font-mono);
  font-size: 10px;
  color: #fff;
  text-align: center;
}

.fv-orange { color: var(--orange); }
.fv-green  { color: var(--green); }
.fv-cyan   { color: var(--cyan); }


/* ─── 26. JOYSTICK CONTROLS ─────────────────────────────────── */
.joystick-zone {
  position: absolute;
  bottom: 65px;
  z-index: 20;
}

.joystick-left  { left: 10px; }
.joystick-right { right: 10px; }

.joystick-wrap { position: relative; }

.joystick-outer {
  width: 112px;
  height: 112px;
  border-radius: 50%;
  border: 1px solid rgba(255, 101, 0, 0.18);
  background: rgba(0, 0, 0, 0.4);
  position: relative;
  cursor: pointer;
}

.joystick-feedback {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 2px solid rgba(255, 101, 0, 0);
  transition: border-color 0.15s;
  pointer-events: none;
}

.joystick-outer:hover .joystick-feedback {
  border-color: rgba(255, 101, 0, 0.35);
}

.joystick-ring-glow {
  position: absolute;
  inset: -4px;
  border-radius: 50%;
  border: 1px solid rgba(255, 101, 0, 0.4);
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
}

.joystick-outer:hover .joystick-ring-glow {
  opacity: 1;
}

.joystick-mid {
  position: absolute;
  inset: 14px;
  border-radius: 50%;
  border: 1px solid rgba(255, 101, 0, 0.25);
}

.joystick-axis {
  position: absolute;
  background: rgba(255, 101, 0, 0.1);
}

.joystick-axis-h {
  top: 50%;
  left: 10%;
  right: 10%;
  height: 1px;
  transform: translateY(-50%);
}

.joystick-axis-v {
  left: 50%;
  top: 10%;
  bottom: 10%;
  width: 1px;
  transform: translateX(-50%);
}

/* Directional arrows */
.joystick-arrow {
  position: absolute;
  font-size: 7px;
  color: rgba(255, 255, 255, 0.2);
  font-family: var(--font-mono);
}

.joystick-arrow-t { top: 6px;    left: 50%; transform: translateX(-50%); }
.joystick-arrow-b { bottom: 6px; left: 50%; transform: translateX(-50%); }
.joystick-arrow-l { left: 4px;   top: 50%;  transform: translateY(-50%); }
.joystick-arrow-r { right: 4px;  top: 50%;  transform: translateY(-50%); }

/* Knob */
.joystick-knob {
  position: absolute;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: radial-gradient(
    circle at 40% 35%,
    rgba(255, 120, 20, 0.8),
    rgba(255, 60, 0, 0.3)
  );
  border: 2px solid var(--orange);
  cursor: grab;
  transition: box-shadow 0.1s;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.joystick-knob:hover  { box-shadow: 0 0 16px rgba(255, 101, 0, 0.6); }
.joystick-knob:active { cursor: grabbing; }

/* Joystick text labels */
.joystick-labels {
  position: absolute;
  bottom: -26px;
  left: 50%;
  transform: translateX(-50%);
  text-align: center;
  white-space: nowrap;
}

.joystick-label-main {
  display: block;
  font-size: 7px;
  letter-spacing: 2px;
  color: rgba(255, 101, 0, 0.5);
  text-transform: uppercase;
}

.joystick-label-sub {
  display: block;
  font-size: 6px;
  color: rgba(255, 255, 255, 0.2);
  letter-spacing: 1px;
}


/* ─── 27. BOTTOM DOCK ───────────────────────────────────────── */
.dock {
  position: absolute;
  bottom: 14px;
  left: 50%;
  transform: translateX(-50%);
  width: min(calc(100% - 28px), 760px);
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: linear-gradient(180deg, rgba(7, 12, 24, 0.88), rgba(7, 12, 24, 0.78));
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 14px;
  z-index: 20;
  padding: 0 12px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
}

.dock-btn {
  width: 90px;
  height: 38px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border: 1px solid rgba(255, 101, 0, 0.15);
  background: rgba(255, 101, 0, 0.06);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  position: relative;
  font-family: var(--font-body);
}

.dock-btn:hover {
  background: rgba(255, 101, 0, 0.12);
  border-color: rgba(255, 101, 0, 0.35);
  transform: translateY(-2px);
}

.dock-active {
  background: rgba(255, 101, 0, 0.2);
  border-color: var(--orange);
  box-shadow: 0 0 12px rgba(255, 101, 0, 0.15);
}

.dock-icon {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.4);
}

.dock-active .dock-icon { color: var(--orange); }

.dock-label {
  font-size: 6px;
  letter-spacing: 1.5px;
  color: rgba(255, 255, 255, 0.2);
  text-transform: uppercase;
}

.dock-active .dock-label { color: rgba(255, 101, 0, 0.8); }

.dock-pip {
  position: absolute;
  top: 5px;
  right: 5px;
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: var(--green);
}


/* ─── 28. MENU PANEL OVERLAY ────────────────────────────────── */
.menu-panel {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(2px);
  z-index: 50;
  display: none;
  flex-direction: column;
  border-radius: 20px;
  overflow: hidden;
}

.menu-panel.open {
  display: flex;
}

.menu-header {
  padding: 14px 18px;
  border-bottom: 1px solid rgba(255, 101, 0, 0.18);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.menu-title {
  font-family: var(--font-mono);
  font-size: 13px;
  letter-spacing: 3px;
  color: var(--orange);
}

.menu-close {
  font-size: 10px;
  letter-spacing: 2px;
  color: rgba(255, 255, 255, 0.82);
  cursor: pointer;
  padding: 4px 8px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: none;
  font-family: var(--font-body);
  transition: color 0.2s, border-color 0.2s;
}

.menu-close:hover {
  color: var(--orange);
  border-color: rgba(255, 101, 0, 0.3);
}

.menu-grid {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 14px;
  overflow-y: auto;
}

/* Menu section card */
.menu-section {
  border: 1px solid rgba(255, 101, 0, 0.22);
  background: rgba(10, 10, 10, 0.45);
  padding: 12px;
  border-radius: 16px;
}

.menu-section-title {
  font-size: 8px;
  letter-spacing: 2px;
  color: rgba(255, 140, 40, 0.95);
  text-transform: uppercase;
  margin-bottom: 8px;
  border-bottom: 1px solid rgba(255, 101, 0, 0.07);
  padding-bottom: 5px;
}

.menu-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
  border-bottom: 1px solid rgba(255, 101, 0, 0.12);
}

.menu-row:last-child {
  border-bottom: none;
}

.menu-key {
  font-size: 10px;
  color: rgba(255, 255, 255, 0.72);
}

.menu-value {
  font-family: var(--font-mono);
  font-size: 10px;
  color: #fff;
}

/* Menu controls */
.menu-slider {
  width: 72px;
  height: 2px;
  -webkit-appearance: none;
  appearance: none;
  background: rgba(255, 101, 0, 0.2);
  outline: none;
}

.menu-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--orange);
  cursor: pointer;
}

.menu-toggle {
  width: 26px;
  height: 13px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 7px;
  position: relative;
  cursor: pointer;
  flex-shrink: 0;
}

.menu-toggle.on {
  background: rgba(255, 101, 0, 0.35);
}

.menu-toggle-knob {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #fff;
  position: absolute;
  top: 2px;
  left: 2px;
  transition: transform 0.18s;
}

.menu-toggle.on .menu-toggle-knob {
  transform: translateX(13px);
  background: var(--orange);
}

.status-ok   { font-family: var(--font-mono); font-size: 9px; color: var(--green); }
.status-warn { font-family: var(--font-mono); font-size: 9px; color: var(--orange); }


/* ─── 29. ANIMATIONS / KEYFRAMES ────────────────────────────── */
@keyframes border-pulse {
  0%, 100% { border-color: var(--orange); }
  50%       { border-color: rgba(255, 101, 0, 0.3); }
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.2; }
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

@keyframes ai-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.4; transform: scale(0.7); }
}

@keyframes red-pulse {
  0%, 100% { border-color: var(--red); opacity: 1; }
  50%       { border-color: rgba(255, 34, 68, 0.3); opacity: 0.5; }
}

@keyframes scan-expand {
  0%   { transform: translate(-50%, -50%) scale(0.3); opacity: 0.8; }
  100% { transform: translate(-50%, -50%) scale(1);   opacity: 0; }
}

@keyframes compass-scroll {
  from { transform: translateX(0); }
  to   { transform: translateX(-50%); }
}

</style>
<body>

<!-- Startup ------>
<div id="startup">
  <div class="sl">CERTANITY</div>
  <div class="ss">Aerospace Drone Simulator · v2.0</div>
  <div class="sbw"><div class="sb" id="sbar"></div></div>
  <div class="st" id="sstat">Initializing physics engine…</div>
</div>

<!-- App -->
<div id="app" style="display:block; width:100%; height:100%; position:relative;">
  <div id="viewport" style="position:absolute; inset:0;">
    <canvas id="threeCanvas"></canvas>
    <div id="crash-overlay">
      <div class="co-icon">💥</div>
      <div class="co-title">DRONE CRASHED</div>
      <div class="co-sub">Impact detected · Motors disarmed</div>
      <button class="co-btn" onclick="clearMotorFailures()" style="background:rgba(0,200,80,.18);border-color:rgba(0,220,80,.6);color:#00e060;margin-bottom:4px">⚡ Restore Motors &amp; Fly</button>
      <button class="co-btn" onclick="resetSim()">↺ &nbsp;RESET &amp; RETRY</button>
    </div>
  </div>
  <div class="scanlines" aria-hidden="true"></div>
  <div class="chromatic" aria-hidden="true"></div>

  <!-- ═══════════════════════════════════════════════════
       MAIN HUD LAYER
  ════════════════════════════════════════════════════ -->
  <div class="hud" role="main" aria-label="Drone HUD Interface">

    <!-- Corner Decorations -->
    <div class="corner-deco corner-tl" aria-hidden="true"><div class="corner-pip"></div></div>
    <div class="corner-deco corner-tr" aria-hidden="true"><div class="corner-pip"></div></div>
    <div class="corner-deco corner-bl" aria-hidden="true"></div>
    <div class="corner-deco corner-br" aria-hidden="true"></div>

    <!-- ─── TOP BAR ─────────────────────────────────── -->
    <header class="topbar" role="banner">
      <div class="brand-block">
        <img src="assets/images/certainity-logo-white-transparent.png" alt="Certanity Logo" class="logo">
      </div>
      <div class="top-sep" aria-hidden="true"></div>

      <div class="tel">
        <span class="tel-label">ALT</span>
        <span class="tel-value tel-orange" id="tv-alt">128m</span>
      </div>
      <div class="top-sep" aria-hidden="true"></div>

      <div class="tel">
        <span class="tel-label">SPD</span>
        <span class="tel-value" id="tv-spd">47 km/h</span>
      </div>
      <div class="top-sep" aria-hidden="true"></div>

      <div class="tel">
        <span class="tel-label">DIST</span>
        <span class="tel-value tel-cyan" id="tv-dist">0.0 km</span>
      </div>
      <div class="top-sep" aria-hidden="true"></div>

      <div class="tel">
        <span class="tel-label">HDG</span>
        <span class="tel-value" id="tv-hdg">247°</span>
      </div>
      <div class="top-sep" aria-hidden="true"></div>

      <div class="tel">
        <span class="tel-label">VSPD</span>
        <span class="tel-value tel-green" id="tv-vspd">+1.2</span>
      </div>
      <div class="top-sep" aria-hidden="true"></div>

      <div class="mode-pill" id="modeText" aria-live="polite">LOITER</div>

      <div class="topbar-spacer"></div>

      <div class="rec-block" aria-label="Recording active">
        <div class="rec-dot" aria-hidden="true"></div>
        <span class="rec-label">REC</span>
      </div>

      <div class="tel">
        <span class="tel-label">TIME</span>
        <span class="tel-value" id="tv-time">00:00</span>
      </div>
      <div class="top-sep" aria-hidden="true"></div>
      <div class="tel">
        <span class="tel-label">PLAN TIME</span>
        <span class="tel-value tel-cyan" id="tv-plan-time">--:--</span>
      </div>
      <div class="top-sep" aria-hidden="true"></div>
      <button id="pause-btn" class="nbtn sm" onclick="if(typeof toggleSimPause==='function') toggleSimPause(); else if(typeof SIM!=='undefined'){ if(SIM._paused) SIM.resume(); else SIM.pause(); }" style="padding: 2px 8px; margin-right: 4px; border-radius: 4px;">⏸ PAUSE</button>
      <button id="cloud-save-btn" class="nbtn sm" onclick="if(typeof triggerCloudSave==='function') triggerCloudSave();" style="padding: 2px 8px; margin-right: 4px; border-radius: 4px;">☁️ SAVE</button>
      <button class="nbtn sm" onclick="if(typeof exitSimulation==='function') exitSimulation(); else window.location.href='../dashboard.php';" style="padding: 2px 8px; border-radius: 4px;">🚪 EXIT</button>
    </header>

    <!-- ─── LEFT PANEL ───────────────────────────────── -->
    <aside class="side-panel panel-left" aria-label="Left instrument panel">

      <!-- Flight Controls Card -->
      <div class="glass-card">
        <div class="card-title" style="color: #a4b1cd; margin-bottom: 8px;">
          <span style="color:#EE9346; margin-right:6px;">●</span> FLIGHT CONTROLS
        </div>
        <div style="display:flex; gap:6px; margin-bottom:8px;">
          <button class="nbtn accent" onclick="if(typeof takeoff==='function') takeoff()" style="flex:1; padding: 4px 8px; font-size: 10px;">🚁 Takeoff</button>
          <button class="nbtn primary" onclick="if(typeof doHover==='function') doHover()" style="flex:1; padding: 4px 8px; font-size: 10px;">⏸ Hover</button>
        </div>
        <div style="display:flex; gap:6px; margin-bottom:8px;">
          <button class="nbtn" onclick="if(typeof returnHome==='function') returnHome()" style="padding: 4px 8px; font-size: 10px; flex:1;">🏠 RTH</button>
          <button class="nbtn danger" onclick="if(typeof emergStop==='function') emergStop()" style="padding: 4px 8px; font-size: 10px; flex:1;">⛔ Stop</button>
        </div>
        <div style="margin-bottom:12px;">
          <button class="nbtn sm" onclick="if(typeof resetSim==='function') resetSim()" style="padding: 4px 8px; font-size: 10px; width:100%;">🔄 Reset</button>
        </div>
        
        <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:12px; font-weight:700; color: #a4b1cd;">
          <span>Throttle</span><span id="thr-val">0%</span>
        </div>
        <input type="range" min="0" max="100" value="0" id="throttle-slider" oninput="if(typeof setThrottleSlider==='function') setThrottleSlider(this.value)" style="width:100%; margin-bottom:12px; accent-color: #EE9346;">
        
        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; color: #6e7a9e; margin-bottom: 12px;">
          <span>Hover Thr: <span id="hover-thr-val" style="color:#4f8ef7; font-size:15px; font-weight:700; margin-left:4px;">37</span> <span style="margin-left:8px;">%</span></span>
        </div>
        
        <div style="font-size:11px; color: #6e7a9e; display:flex; align-items:flex-start; gap:6px;">
          <input type="checkbox" checked disabled style="accent-color: #28c840; margin-top:2px;">
          <span><span style="color:#EE9346;font-weight:700;">Priority email support</span> included with your MAX plan</span>
        </div>
      </div>



          </aside>

    <!-- ─── RIGHT PANEL ──────────────────────────────── -->
    <aside class="side-panel panel-right" aria-label="Right instrument panel">

      </aside>





    <!-- ─── CENTER RETICLE ───────────────────────────── ->
    <div class="center-zone" id="centerZone" aria-label="Targeting reticle">
      <div class="reticle-ring reticle-ring-1" aria-hidden="true"></div>
      <div class="reticle-ring reticle-ring-4" aria-hidden="true"></div>

      <div class="crosshair-arm crosshair-top" aria-hidden="true"></div>
      <div class="crosshair-arm crosshair-bottom" aria-hidden="true"></div>
      <div class="crosshair-arm crosshair-left" aria-hidden="true"></div>
      <div class="crosshair-arm crosshair-right" aria-hidden="true"></div>
      <div class="crosshair-gap crosshair-gap-h" aria-hidden="true"></div>
      <div class="crosshair-gap crosshair-gap-v" aria-hidden="true"></div>

      <div class="reticle-center" aria-hidden="true"></div>
      <span class="reticle-label reticle-label-top">TARGET LOCK</span>
      <span class="reticle-label reticle-label-bottom" id="lockStatus" aria-live="polite">TRACKING</span>
    </div>

    <!-- ─── SCAN RINGS ───────────────────────────────── ->
    <div class="scan-ring" id="scanRing" aria-hidden="true"></div>
    <div class="scan-ring scan-ring-delay" id="scanRing2" aria-hidden="true"></div>

    <!-- ─── TARGET BOXES ─────────────────────────────── ->
    <div class="target-box target-primary" id="tbox1" style="top:42%;left:54%" aria-label="Target Alpha">
      <div class="target-corner tc-tl"></div>
      <div class="target-corner tc-tr"></div>
      <div class="target-corner tc-bl"></div>
      <div class="target-corner tc-br"></div>
      <div class="target-inner"></div>
      <span class="target-label">TGT-A · VEH</span>
      <span class="target-pct">94%</span>
    </div>

    <div class="target-box target-secondary" id="tbox2" style="top:35%;left:38%;width:50px;height:50px" aria-label="Target Bravo">
      <div class="target-corner tc-tl tc-dim"></div>
      <div class="target-corner tc-tr tc-dim"></div>
      <div class="target-corner tc-bl tc-dim"></div>
      <div class="target-corner tc-br tc-dim"></div>
      <span class="target-label target-label-dim">TGT-B</span>
      <span class="target-pct target-pct-dim">61%</span>
    </div>

    <!-- ─── WAYPOINT MARKERS ──────────────────────────── ->
    <div class="waypoint-marker" style="top:32%;left:65%" aria-label="Waypoint 03">
      <div class="wp-diamond"></div>
      <div class="wp-connector-line"></div>
      <span class="wp-marker-label">WP-03</span>
    </div>

    <div class="waypoint-marker waypoint-marker-dim" style="top:28%;left:45%" aria-label="Waypoint 04">
      <div class="wp-diamond wp-diamond-dim"></div>
      <div class="wp-connector-line wp-connector-dim"></div>
      <span class="wp-marker-label wp-marker-label-dim">WP-04</span>
    </div>

    <!-- ─── ENEMY MARKER ─────────────────────────────── -->
    <div class="enemy-marker" style="top:38%;left:31%" aria-label="Hostile detected">
      <div class="enemy-hex" aria-hidden="true"></div>
      <span class="enemy-label">HOSTILE</span>
    </div>

    <!-- ─── HORIZON INDICATOR ────────────────────────── -->
    <div class="horizon-indicator" aria-hidden="true">
      <div class="horizon-bar"></div>
    </div>

    <!-- ─── COMPASS STRIP ────────────────────────────── -->
    <!-- <nav class="compass-strip" aria-label="Compass heading">
      <div class="compass-caret" aria-hidden="true"></div>
      <div class="compass-needle" aria-hidden="true"></div>
      <div class="compass-inner" id="compassInner" aria-hidden="true">
        <div class="compass-unit"><div class="compass-tick tick-cardinal"></div><span class="compass-label label-cardinal">N</span></div>
        <div class="compass-unit"><div class="compass-tick tick-minor"></div><span class="compass-label">30</span></div>
        <div class="compass-unit"><div class="compass-tick tick-minor"></div><span class="compass-label">60</span></div>
        <div class="compass-unit"><div class="compass-tick tick-cardinal"></div><span class="compass-label label-cardinal">E</span></div>
        <div class="compass-unit"><div class="compass-tick tick-minor"></div><span class="compass-label">120</span></div>
        <div class="compass-unit"><div class="compass-tick tick-minor"></div><span class="compass-label">150</span></div>
        <div class="compass-unit"><div class="compass-tick tick-cardinal"></div><span class="compass-label label-cardinal">S</span></div>
        <div class="compass-unit"><div class="compass-tick tick-minor"></div><span class="compass-label">210</span></div>
        <div class="compass-unit"><div class="compass-tick tick-minor"></div><span class="compass-label">240</span></div>
        <div class="compass-unit"><div class="compass-tick tick-cardinal"></div><span class="compass-label label-cardinal">W</span></div>
        <div class="compass-unit"><div class="compass-tick tick-minor"></div><span class="compass-label">300</span></div>
        <div class="compass-unit"><div class="compass-tick tick-minor"></div><span class="compass-label">330</span></div>
        <div class="compass-unit"><div class="compass-tick tick-cardinal"></div><span class="compass-label label-cardinal">N</span></div>
      </div>
    </nav> -->

    <!-- ─── FLIGHT STRIP ─────────────────────────────── -->
    <div class="flight-strip" aria-label="Flight data strip">
      <div class="flight-cell"><span class="flight-label">ROLL</span><span class="flight-value fv-orange" id="fs-roll">+3.2°</span></div>
      <div class="flight-cell"><span class="flight-label">PITCH</span><span class="flight-value" id="fs-pitch">-1.8°</span></div>
      <div class="flight-cell"><span class="flight-label">VSPD</span><span class="flight-value fv-green">+0.4m/s</span></div>
      <div class="flight-cell"><span class="flight-label">WIND</span><span class="flight-value">8 NE</span></div>
      <div class="flight-cell"><span class="flight-label">TEMP</span><span class="flight-value">28°C</span></div>
      <div class="flight-cell"><span class="flight-label">SATS</span><span class="flight-value fv-cyan">14</span></div>
    </div>

    <!-- ─── LEFT JOYSTICK ────────────────────────────── -->
    <div class="joystick-zone joystick-left" aria-label="Left joystick: Throttle and Yaw">
      <div class="joystick-wrap">
        <div class="joystick-outer" id="jsL">
          <div class="joystick-feedback" aria-hidden="true"></div>
          <div class="joystick-ring-glow" aria-hidden="true"></div>
          <div class="joystick-mid" aria-hidden="true"></div>
          <div class="joystick-axis joystick-axis-h" aria-hidden="true"></div>
          <div class="joystick-axis joystick-axis-v" aria-hidden="true"></div>
          <div class="joystick-arrow joystick-arrow-t" aria-hidden="true">▲</div>
          <div class="joystick-arrow joystick-arrow-b" aria-hidden="true">▼</div>
          <div class="joystick-arrow joystick-arrow-l" aria-hidden="true">◄</div>
          <div class="joystick-arrow joystick-arrow-r" aria-hidden="true">►</div>
          <div class="joystick-knob" id="jsLKnob"></div>
        </div>
        <div class="joystick-labels">
          <span class="joystick-label-main">THR · YAW</span>
          <span class="joystick-label-sub">Throttle / Rotation</span>
        </div>
      </div>
    </div>

    <!-- ─── RIGHT JOYSTICK ───────────────────────────── -->
    <div class="joystick-zone joystick-right" aria-label="Right joystick: Pitch and Roll">
      <div class="joystick-wrap">
        <div class="joystick-outer" id="jsR">
          <div class="joystick-feedback" aria-hidden="true"></div>
          <div class="joystick-ring-glow" aria-hidden="true"></div>
          <div class="joystick-mid" aria-hidden="true"></div>
          <div class="joystick-axis joystick-axis-h" aria-hidden="true"></div>
          <div class="joystick-axis joystick-axis-v" aria-hidden="true"></div>
          <div class="joystick-arrow joystick-arrow-t" aria-hidden="true">▲</div>
          <div class="joystick-arrow joystick-arrow-b" aria-hidden="true">▼</div>
          <div class="joystick-arrow joystick-arrow-l" aria-hidden="true">◄</div>
          <div class="joystick-arrow joystick-arrow-r" aria-hidden="true">►</div>
          <div class="joystick-knob" id="jsRKnob"></div>
        </div>
        <div class="joystick-labels">
          <span class="joystick-label-main">PITCH · ROLL</span>
          <span class="joystick-label-sub">Camera / Direction</span>
        </div>
      </div>
    </div>

    <!-- ─── BOTTOM DOCK ──────────────────────────────── -->
    <nav class="dock" role="navigation" aria-label="HUD Menu Dock">
      
      <button class="dock-btn" onclick="openMenu('mission')" aria-label="Mission Planner">
        <span class="dock-icon">◎</span>
        <span class="dock-label">MISSION</span>
      </button>
      <button class="dock-btn" onclick="openMenu('camera')" aria-label="Camera Control">
        <span class="dock-icon">⊡</span>
        <span class="dock-label">CAMERA</span>
      </button>
      <button class="dock-btn" onclick="openMenu('sensors')" aria-label="Sensor Matrix">
        <span class="dock-icon">⬡</span>
        <span class="dock-label">SENSORS</span>
      </button>
      <button class="dock-btn" onclick="openMenu('env')" aria-label="Environment Simulator">
        <span class="dock-icon">◌</span>
        <span class="dock-label">ENVIRON</span>
      </button>
    </nav>

    <!-- ─── MENU PANEL (OVERLAY) ─────────────────────── -->
    <div class="menu-panel" id="menuPanel" role="dialog" aria-modal="true" aria-labelledby="menuTitle">
      <div class="menu-header">
        <span class="menu-title" id="menuTitle">SYSTEM CONFIG</span>
        <button class="menu-close" onclick="closeMenu()" aria-label="Return to HUD">✕ RETURN TO HUD</button>
      </div>
      <div class="menu-grid" id="menuGrid"></div>
    </div>

  </div><!-- /.hud -->
</div><!-- end #app -->

<!-- Custom Drone Profile Modal -->
<div id="custom-profile-modal">
  <div class="modal-card">
    <div class="modal-header">
      <div class="modal-title">🚁 Create Custom Drone Profile</div>
      <div class="modal-close" onclick="closeCustomProfileModal()">✕</div>
    </div>

    <div class="modal-section">
      <div class="modal-section-label">📋 Identity</div>
      <div class="modal-grid">
        <div class="modal-field full"><label>Profile Name</label><input type="text" id="cp-name" placeholder='e.g. My 7" Long-Range'></div>
      </div>
    </div>

    <div class="modal-section">
      <div class="modal-section-label">⚙ Physical Frame</div>
      <div class="modal-grid">
        <div class="modal-field"><label>Mass (kg)</label><input type="number" id="cp-mass" value="1.24" step="0.01" min="0.05" max="20"></div>
        <div class="modal-field"><label>Arm Length (m)</label><input type="number" id="cp-arm" value="0.19" step="0.005" min="0.05" max="1"></div>
        <div class="modal-field"><label>Body Scale</label><input type="number" id="cp-bodyscale" value="1.0" step="0.05" min="0.3" max="3"></div>
        <div class="modal-field"><label>Rotor Radius (m)</label><input type="number" id="cp-rotor" value="0.09" step="0.005" min="0.03" max="0.5"></div>
        <div class="modal-field"><label>Max Tilt Angle (°)</label><input type="number" id="cp-tilt" value="55" step="1" min="10" max="85"></div>
        <div class="modal-field"><label>Drag Area (m²)</label><input type="number" id="cp-drag" value="0.022" step="0.001" min="0.001" max="0.5"></div>
        <div class="modal-field"><label>Drag Coefficient</label><input type="number" id="cp-cd" value="1.12" step="0.01" min="0.3" max="3"></div>
        <div class="modal-field"><label>Angular Drag</label><input type="number" id="cp-angdrag" value="0.0028" step="0.0001" min="0.0001" max="0.05"></div>
      </div>
    </div>

    <div class="modal-section">
      <div class="modal-section-label">⚡ Motors &amp; Propulsion</div>
      <div class="modal-grid">
        <div class="modal-field"><label>Max RPM</label><input type="number" id="cp-maxrpm" value="14000" step="500" min="2000" max="50000"></div>
        <div class="modal-field"><label>Idle RPM</label><input type="number" id="cp-idlerpm" value="500" step="50" min="100" max="3000"></div>
        <div class="modal-field"><label>Thrust Coeff (kT)</label><input type="number" id="cp-kt" value="0.0000104" step="0.0000005" min="0.000001" max="0.0001"></div>
        <div class="modal-field"><label>Torque Coeff (kQ)</label><input type="number" id="cp-kq" value="0.000000155" step="0.00000001" min="0.0000001" max="0.000005"></div>
        <div class="modal-field"><label>Motor Time Const (s)</label><input type="number" id="cp-tau" value="0.055" step="0.005" min="0.01" max="0.5"></div>
        <div class="modal-field"><label>ESC Delay (s)</label><input type="number" id="cp-esc" value="0.012" step="0.001" min="0.001" max="0.1"></div>
        <div class="modal-field"><label>Prop Inertia</label><input type="number" id="cp-propI" value="0.000025" step="0.000001" min="0.000001" max="0.001"></div>
        <div class="modal-field"><label>Lift Coeff (Cq)</label><input type="number" id="cp-cq" value="0.015" step="0.001" min="0.001" max="0.1"></div>
      </div>
    </div>

    <div class="modal-section">
      <div class="modal-section-label">🔋 Battery</div>
      <div class="modal-grid">
        <div class="modal-field"><label>Cell Count (S)</label><input type="number" id="cp-cells" value="4" step="1" min="1" max="12"></div>
        <div class="modal-field"><label>Capacity (Ah)</label><input type="number" id="cp-batt" value="1.65" step="0.1" min="0.1" max="30"></div>
      </div>
    </div>

    <div class="modal-section">
      <div class="modal-section-label">🎨 Visual</div>
      <div class="modal-grid">
        <div class="modal-field"><label>Drone Color</label><input type="color" id="cp-color" value="#1e88e5" style="width:100%;height:34px;border-radius:8px;border:none;cursor:pointer;background:var(--surf);box-shadow:var(--sh-sm);padding:3px"></div>
        <div class="modal-field"><label>Max Pitch Rate</label><input type="number" id="cp-ratepitch" value="10" step="0.5" min="1" max="30"></div>
        <div class="modal-field"><label>Max Roll Rate</label><input type="number" id="cp-rateroll" value="10" step="0.5" min="1" max="30"></div>
        <div class="modal-field"><label>Max Yaw Rate</label><input type="number" id="cp-rateyaw" value="4.5" step="0.5" min="0.5" max="15"></div>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:4px">
      <button class="nbtn" onclick="closeCustomProfileModal()" style="flex:1">Cancel</button>
      <button class="nbtn accent" onclick="loadPresetIntoModal('racing5')" style="flex:1">📋 Load Preset</button>
      <button class="nbtn primary" onclick="createCustomProfile()" style="flex:1">✅ Create</button>
    </div>
    <!-- Preset quick-load -->
    <div style="margin-top:12px">
      <div style="font-size:10px;color:var(--txt4);margin-bottom:6px;letter-spacing:.5px;text-transform:uppercase">Quick-load from existing preset:</div>
      <div class="profile-card-row" id="modal-preset-cards"></div>
    </div>
  </div>
</div>

<!-- Three.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

<!-- Sim Engine (external) -->
<script src="sim-eng.js?v=6.0"></script>

<!-- ══ TIER: MAX ══ -->
<script>
const _setTxt = (el, txt) => { if (el && el.textContent !== String(txt)) el.textContent = txt; };

/* PLAN FLAGS — MAX tier
   Duration: Unlimited | All profiles + Custom
   All 6 envs | Full HUD | Full PID | Full export
   Gamepad | Waypoints | GLTF upload | Priority support */
const PLAN = {
  tier: '<?= $run_plan === 'FREE' ? 'FREE' : 'BASIC' ?>',
  sessionMinutes: <?= max(1, (int) ceil(($accessSeconds > 0 ? $accessSeconds : 3600) / 60)) ?>,
  sessionSeconds: <?= $accessSeconds > 0 ? min($accessSeconds, 3600) : 3600 ?>,
  planExpiresAt: <?= (int) $accessExpiresAt ?>,
  droneProfiles: ['racing5'],
  environments: ['field'],
  waypointMissions: false,
  pidTuning: false,
  dataExport: false,
  mavlinkLogs: false,
  customGLTF: false,
  joystickGamepad: false,
  hudLevel: 'basic',
  nightMode: false,
  windScenario: false,
  support: 'community',
  tierLabel: '<?= $run_plan === 'FREE' ? 'FREE' : 'BASIC' ?>',
  tierColor: '<?= $run_plan === 'FREE' ? '#9E9E9E' : '#4F8EF7' ?>',
};
</script>


<script>
'use strict';

/* ══════════════════════════════════════════════════════════════════════
   THREE.JS ENVIRONMENT — v3.0  BSL CINEMATIC VISUAL UPGRADE
   Visual Enhancements Only — All physics/controls untouched
   - BSL-inspired sky shader with atmospheric scattering & god rays
   - Volumetric fog layers with animated drift
   - Bloom post-processing via additive compositing
   - Infinite chunk-based terrain with seamless streaming
   - Dense vegetation: grass, flowers, ferns, varied trees, rocks
   - PBR drone materials: MeshStandardMaterial with reflections
   - Soft PCF shadows, ACES tonemapping, high-quality rendering
   - Animated grass blades via vertex shader
   - Cinematic depth haze and horizon glow
══════════════════════════════════════════════════════════════════════ */

const THREE_ENV = (() => {
  let renderer, scene, camera, clock;
  let droneGroup, bodyMesh, propMeshes = [], armMeshes = [];
  let propAngle = [0,0,0,0];
  let shadowLight, hemiLight, sunLight, moonLight;
  let skyMesh, cloudGroup, rainSystem, fogPlane;
  let _canvas, _envName = 'field';
  let _camMode = 'third';
  let _orbitAngle = 0, _orbitDist = 8, _orbitH = 3;
  let _freeCam = { x:0, y:8, z:-14, rx:-0.3, ry:0 };
  let _prevDronePos = { x:0, y:0.2, z:0 };
  let _dayTime = 0.55;
  let _nightMode = false;
  let _rainOn = false, _fogOn = false;
  let _rainParticles, _rainGeo, _rainPositions;
  let _trailPoints = [], _trailLine;
  let _waypointMarkers = [];
  let _propSpinRate = [0,0,0,0];

  // ── Chunk streaming constants ──────────────────────────────────────
  const CHUNK_SIZE   = 80;
  const CHUNK_SEGS   = 24;   // full-detail segment count (reduced for perf)
  const CHUNK_SEGS_L = 8;    // low-detail segment count (outer ring)
  const RENDER_DIST  = 3;    // full-detail ring radius (chunks)
  const LOD_DIST     = 6;    // low-detail ring radius (chunks)
  let _chunks        = new Map(); // key -> chunkData
  let _lastChunkX    = null, _lastChunkZ = null;
  // Async load queue — process at most N chunks per render frame
  let _loadQueue     = [];
  const MAX_LOADS_PER_FRAME = 1; // 1 full chunk/frame = smooth 60fps

  // ── Floating-origin render space ───────────────────────────────────
  // Physics is in world space (true JS doubles, unlimited range).
  // Three.js scene is rebased so the camera is always near origin,
  // preventing float32 precision loss at large coordinates.
  const REBASE_THRESHOLD = CHUNK_SIZE * 4;  // rebase when camera drifts >320m from render origin
  let _renderOriginX = 0, _renderOriginZ = 0; // render origin in world coords
  let _needsFullRebase = false;

  // Convert world XZ to render-space XZ
  function _toRender(wx, wz) {
    return { x: wx - _renderOriginX, z: wz - _renderOriginZ };
  }

  // Rebase: shift all scene objects so camera stays near render origin
  function _rebaseRenderOrigin() {
    const p = PHYS._renderPos || PHYS.pos;
    const newOX = Math.round(p.x / CHUNK_SIZE) * CHUNK_SIZE;
    const newOZ = Math.round(p.z / CHUNK_SIZE) * CHUNK_SIZE;
    const dx = newOX - _renderOriginX;
    const dz = newOZ - _renderOriginZ;
    if (Math.abs(dx) < 1 && Math.abs(dz) < 1) return;

    // Shift every chunk mesh in the scene by -delta
    for (const [, cd] of _chunks) {
      ['mesh','veg','flowers','grass','rocks'].forEach(k => {
        if (cd[k]) {
          cd[k].position.x -= dx;
          cd[k].position.z -= dz;
        }
      });
    }
    // Shift waypoint markers
    if (_waypointMarkers) {
      _waypointMarkers.forEach(m => {
        m.position.x -= dx;
        m.position.z -= dz;
      });
    }
    // Shift drone trail
      if (_trailLine) {
        _trailLine.position.x -= dx;
        _trailLine.position.z -= dz;
      }
      camera.position.x -= dx;
      camera.position.z -= dz;
      _renderOriginX = newOX;
    _renderOriginZ = newOZ;
    _needsFullRebase = false;
  }

  // Mouse orbit
  let _mouse = { down:false, lx:0, ly:0 };

  // Bloom compositing
  let _bloomRT, _bloomScene, _bloomCamera, _bloomQuad;
  let _mainRT;

  // ── Multi-biome terrain heightmap ────────────────────────────────
  // Uses domain-warped FBM + continent mask to blend biomes seamlessly.
  // The same (x,z) always returns the same height for a given seed.
  // ─────────────────────────────────────────────────────────────────
  // Terrain architecture (for procedural/infinite envs):
  //   continent(x,z) ∈ [0,1]  — low-frequency "where is high ground?"
  //   erosion(x,z)   ∈ [0,1]  — medium-freq ridges vs smooth slopes
  //   detail(x,z)    ∈ [-1,1] — high-freq surface texture
  //   h = continent^2 * 80 + erosion * 20 + detail * 3
  // ─────────────────────────────────────────────────────────────────

  // Cache last result per env to avoid recalculating same point twice
  let _thCache = null;
  function rawTerrainHeight(x, z, envName) {
    const env = envName || _envName;

    // Flat environments
    if (env === 'urban' || env === 'indoor') return 0;

    // Domain-warped low-frequency continent mask (very smooth, no sharp edges)
    const cx  = x * 0.004, cz = z * 0.004;
    const wx  = Noise.n(cx + 3.7, 0.1, cz + 1.3) * 18;
    const wz  = Noise.n(cx + 8.2, 0.8, cz + 6.1) * 18;
    const continent = Math.max(0, Noise.fbm(cx + wx*0.004, 0, cz + wz*0.004, 4, 0.55, 2.0) * 0.5 + 0.5);

    if (env === 'field' || env === 'windy') {
      // Gentle rolling hills — continent kept low, heavy smoothing
      const base = Math.pow(continent * 0.55, 1.6) * 14;
      const mid  = Noise.fbm(x*0.022, 1.2, z*0.022, 4, 0.48, 2.0) * 6;
      const fine = Noise.n(x*0.14, 2.3, z*0.14) * 1.2;
      return Math.max(0, base + mid + fine);
    }

    if (env === 'desert') {
      // Dune ridges: elongated in one direction + flat inter-dune pans
      const duneDir = x * 0.018 + z * 0.006; // asymmetric dune axis
      const dune    = Math.pow(Math.abs(Noise.n(duneDir, 0.3, z*0.014)), 0.7) * 20;
      const pan     = Math.max(0, Noise.fbm(x*0.009, 0.8, z*0.009, 3, 0.45, 2.0)) * 8;
      const fine    = Noise.n(x*0.12, 1.1, z*0.12) * 1.5;
      return Math.max(0, dune + pan + fine);
    }

    if (env === 'mountains') {
      // Sharp, varied peaks using ridged noise + domain warp
      // Ridged noise: 1 - |fbm|  → inverted valleys, sharp ridges
      const raw   = Noise.warpedFbm(x, z, 5, 0.55, 2.1, 40);
      const ridge = Math.pow(Math.max(0, continent * 0.8 + raw * 0.5 + 0.1), 1.5);
      const peak  = ridge * 80;
      // Erosion detail layered on top
      const erode = Noise.fbm(x*0.05, 0.5, z*0.05, 3, 0.42, 2.0) * 10 * continent;
      const scree = Noise.n(x*0.18, 2.1, z*0.18) * 2.5;
      return Math.max(0, peak + erode + scree);
    }

    // Default / generic procedural world
    const raw    = Noise.warpedFbm(x, z, 5, 0.52, 2.0, 30);
    const height = Math.pow(Math.max(0, continent * 0.7 + raw * 0.4 + 0.15), 1.4) * 50;
    const detail = Noise.fbm(x*0.08, 1.5, z*0.08, 3, 0.4, 2.0) * 5;
    return Math.max(0, height + detail);
  }

  

  function terrainHeight(x, z, envName) {
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor') return 0; // Flat environments

    const step = 60 / 16; // 3.75
    const x0 = Math.floor(x / step) * step;
    const z0 = Math.floor(z / step) * step;
    const x1 = x0 + step;
    const z1 = z0 + step;

    const h00 = rawTerrainHeight(x0, z0, env);
    const h10 = rawTerrainHeight(x1, z0, env);
    const h01 = rawTerrainHeight(x0, z1, env);
    const h11 = rawTerrainHeight(x1, z1, env);

    const tx = (x - x0) / step;
    const tz = (z - z0) / step;

    // Bilinear interpolation
    const h0 = h00 * (1 - tx) + h10 * tx;
    const h1 = h01 * (1 - tx) + h11 * tx;
    return h0 * (1 - tz) + h1 * tz;
  }

  // ── Safe spawn finder ─────────────────────────────────────────────
  // Drone was spawning inside mountains because (0,0) can be mid-peak.
  // Now searches a wider grid to find the lowest-elevation flat spot.
  function getSafeSpawnPoint(envName) {
    const env = envName || _envName;
    if (env === 'indoor' || env === 'urban') {
      return { x: 0, z: 0, y: 0 };
    }
    if (env === 'field' || env === 'windy') {
      const h = terrainHeight(0, 0, env);
      return { x: 0, z: 0, y: h };
    }
    // For mountains and desert, find the lowest valley point in a wide grid
    let bestX = 0, bestZ = 0, bestH = Infinity;
    const step = 6, range = 100;
    for (let xi = -range; xi <= range; xi += step) {
      for (let zi = -range; zi <= range; zi += step) {
        const h = terrainHeight(xi, zi, env);
        if (h < bestH) { bestH = h; bestX = xi; bestZ = zi; }
      }
    }
    // Fine-search around best candidate
    const fStep = 2, fRange = 8;
    for (let xi = bestX - fRange; xi <= bestX + fRange; xi += fStep) {
      for (let zi = bestZ - fRange; zi <= bestZ + fRange; zi += fStep) {
        const h = terrainHeight(xi, zi, env);
        if (h < bestH) { bestH = h; bestX = xi; bestZ = zi; }
      }
    }
    return { x: bestX, z: bestZ, y: bestH };
  }

  // ── Terrain colour helper ──────────────────────────────────────────
  // Layered colour with micro-variation and slope-based darkening
  function terrainColor(x, z, h, envName) {
    const env = envName || _envName;
    let r, g, b;

    // Shared micro-variation (same for all envs)
    const v1 = Noise.n(x*0.11, 0.3, z*0.11) * 0.07;
    const v2 = Noise.n(x*0.32, 1.4, z*0.32) * 0.03;
    const nv = v1 + v2; // net variation

    if (env === 'desert') {
      const ripple = Noise.n(x*0.35, 0.0, z*0.22) * 0.05;
      r = 0.82 + nv + ripple;
      g = 0.66 + nv*0.7;
      b = 0.30 + nv*0.3;
    } else if (env === 'mountains') {
      // Smooth height-based blend: grass → rock → scree → snow
      if (h < 3) {
        r=0.28+nv; g=0.52+nv*0.6; b=0.16+nv*0.3;
      } else if (h < 12) {
        const t=(h-3)/9;
        r=0.28+t*0.24+nv; g=0.52-t*0.16+nv*0.3; b=0.16+t*0.12+nv*0.2;
      } else if (h < 42) {
        const t=(h-12)/30;
        r=0.52+t*0.16+nv*0.5; g=0.36+t*0.08+nv*0.3; b=0.28+t*0.14+nv*0.2;
      } else if (h < 65) {
        const t=(h-42)/23;
        r=0.68+t*0.18+nv*0.3; g=0.44+t*0.22+nv*0.2; b=0.42+t*0.26+nv*0.2;
      } else {
        // Snow — slight blue tint in shadows
        r=0.90+nv*0.1; g=0.93+nv*0.08; b=0.98+nv*0.05;
      }
    } else if (env === 'urban') {
      r=0.34+nv; g=0.34+nv; b=0.34+nv;
    } else {
      // Field / procedural — moisture-driven greens
      const moisture = Noise.fbm(x*0.018, 3.3, z*0.018, 2, 0.5, 2) * 0.5 + 0.5;
      r = 0.16 + nv - moisture*0.04 + h*0.006;
      g = 0.46 + nv*0.5 + moisture*0.10 + h*0.012;
      b = 0.12 + nv*0.3 - moisture*0.02;
    }
    return [Math.min(1,Math.max(0,r)), Math.min(1,Math.max(0,g)), Math.min(1,Math.max(0,b))];
  }


  // ── Per-chunk seeded RNG — identical output every time a chunk is rebuilt ──
  // Uses a simple xorshift32 so each (cx,cz) produces the same vegetation layout.
  function _chunkRng(cx, cz) {
    let s = (cx * 73856093) ^ (cz * 19349663);
    s = s ^ (s >>> 16); s = (s * 0x45d9f3b) & 0xffffffff;
    s = s ^ (s >>> 16);
    return function() {
      s ^= s << 13; s ^= s >> 17; s ^= s << 5;
      return ((s >>> 0) / 0xffffffff);
    };
  }

  // ── Single chunk terrain mesh ──────────────────────────────────────
  async function buildChunkMesh(cx, cz, envName, segs) {
    const s = segs || CHUNK_SEGS;
    const geo = new THREE.PlaneGeometry(CHUNK_SIZE, CHUNK_SIZE, s, s);
    geo.rotateX(-Math.PI/2);
    const pos = geo.attributes.position;
    const colors = [];
    // World-space origin of this chunk (used for terrain eval only)
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    for (let i = 0; i < pos.count; i++) {
        if (i % 400 === 0 && i !== 0) await new Promise(r => setTimeout(r, 0));
      // Vertex is chunk-local (PlaneGeometry centred at 0)
      const localX = pos.getX(i);
      const localZ = pos.getZ(i);
      // Evaluate terrain in true world coords (double precision JS numbers)
      const wx = localX + worldOffX;
      const wz = localZ + worldOffZ;
      const h = rawTerrainHeight(wx, wz, envName);
      pos.setY(i, h);
      const [r,g,b] = terrainColor(wx, wz, h, envName);
      colors.push(r, g, b);
    }
    geo.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
    geo.computeVertexNormals();
    const mat = new THREE.MeshLambertMaterial({ vertexColors: true });
    const mesh = new THREE.Mesh(geo, mat);
    // Position set by _buildChunk in render space — leave at zero here
    mesh.position.set(0, 0, 0);
    mesh.receiveShadow = true;
    mesh.name = 'terrain_chunk';
    return mesh;
  }

  // ── Grass blade system (per-chunk) ────────────────────────────────
  let _grassTime = 0;
  async function buildGrassBlades(cx, cz, envName){
    const rng = _chunkRng(cx, cz);
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor' || env === 'desert') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const count = env === 'mountains' ? 50 : (env === 'windy' ? 100 : 150);
    const positions = [], colors2 = [], indices2 = [];
    let vi = 0;
    // Each blade: 3 quads (6 verts)
    for (let i = 0; i < count; i++) {
        if (i % 100 === 0 && i !== 0) await new Promise(r => setTimeout(r, 0));
        const lx = (rng()-0.5)*CHUNK_SIZE;
      const lz = (rng()-0.5)*CHUNK_SIZE;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, wz, env);
      const h = 0.18 + rng()*0.22;
      const ang = rng()*Math.PI*2;
      const bx = Math.cos(ang)*0.04, bz = Math.sin(ang)*0.04;
      // color variation
      const gv = 0.3 + rng()*0.3;
      const rc = 0.1+gv*0.3, gc = 0.4+gv*0.35, bc = 0.05+gv*0.1;
      // base L
      positions.push(lx-bx, hy, lz-bz, lx+bx, hy, lz+bz, lx, hy+h, lz);
      colors2.push(rc*0.7,gc*0.7,bc*0.7, rc*0.7,gc*0.7,bc*0.7, rc,gc,bc);
      indices2.push(vi,vi+1,vi+2);
      vi += 3;
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geo.setAttribute('color', new THREE.Float32BufferAttribute(colors2, 3));
    geo.setIndex(indices2);
    geo.computeVertexNormals();
    const mat = new THREE.MeshLambertMaterial({ vertexColors: true, side: THREE.DoubleSide });
    const mesh = new THREE.Mesh(geo, mat);
    return mesh;
  }

  // ── Flowers ────────────────────────────────────────────────────────
  async function buildFlowers(cx, cz, envName){
    const rng = _chunkRng(cx + 1000, cz + 2000);
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor' || env === 'desert' || env === 'mountains') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const group = new THREE.Group();
    const flowerColors = [0xff4466, 0xffcc22, 0xff8844, 0xee66ff, 0xffffff, 0x66ddff];
    const stemMat = new THREE.MeshLambertMaterial({ color: 0x2d7a1a });
    const count = 30 + Math.floor(rng()*40);
    for (let i = 0; i < count; i++) {
      const lx = (rng()-0.5)*CHUNK_SIZE;
      const lz = (rng()-0.5)*CHUNK_SIZE;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, wz, env);
      const h = 0.14 + rng()*0.12;
      // stem
      const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.006,0.008,h,4), stemMat);
      stem.position.set(lx, hy+h/2, lz);
      group.add(stem);
      // petals
      const col = flowerColors[Math.floor(rng()*flowerColors.length)];
      const petMat = new THREE.MeshLambertMaterial({ color: col, side: THREE.DoubleSide });
      const pCount = 4 + Math.floor(rng()*3);
      for (let p = 0; p < pCount; p++) {
        const pa = (p/pCount)*Math.PI*2;
        const pet = new THREE.Mesh(new THREE.PlaneGeometry(0.05,0.04), petMat);
        pet.position.set(lx+Math.cos(pa)*0.04, hy+h+0.02, lz+Math.sin(pa)*0.04);
        pet.rotation.y = pa; pet.rotation.x = -0.4;
        group.add(pet);
      }
      // center
      const cMat = new THREE.MeshBasicMaterial({ color: 0xffee00 });
      const cen = new THREE.Mesh(new THREE.SphereGeometry(0.018,6,4), cMat);
      cen.position.set(lx, hy+h+0.018, lz);
      group.add(cen);
    }
    return group;
  }

  // ── Rocks ──────────────────────────────────────────────────────────
  async function buildRocks(cx, cz, envName){
    const rng = _chunkRng(cx + 5000, cz + 6000);
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const group = new THREE.Group();
    const count = env === 'mountains' ? 20 : env === 'desert' ? 12 : 8;
    const rockColors = [0x888880, 0x706a60, 0x999288, 0x7a7268];
    for (let i = 0; i < count; i++) {
      const lx = (rng()-0.5)*CHUNK_SIZE;
      const lz = (rng()-0.5)*CHUNK_SIZE;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, wz, env);
      const scale = 0.2 + rng()*0.8;
      const col = rockColors[Math.floor(rng()*rockColors.length)];
      const mat = new THREE.MeshStandardMaterial({ color: col, roughness: 0.9, metalness: 0.05 });
      // Irregular rock shape from scaled sphere
      const geo = new THREE.SphereGeometry(scale, 6, 5);
      const verts = geo.attributes.position;
      for (let v = 0; v < verts.count; v++) {
        const nx = verts.getX(v), ny = verts.getY(v), nz = verts.getZ(v);
        const bump = 1 + Noise.n(nx*2+wx*0.1, ny*2, nz*2+wz*0.1)*0.35;
        verts.setXYZ(v, nx*bump, ny*bump*(0.5+rng()*0.4), nz*bump);
      }
      geo.computeVertexNormals();
      const rock = new THREE.Mesh(geo, mat);
      rock.position.set(lx, hy + scale*0.35, lz);
      rock.rotation.y = rng()*Math.PI*2;
      rock.castShadow = true; rock.receiveShadow = true;
      group.add(rock);
    }
    return group;
  }

  // ── Trees (lush, varied) ──────────────────────────────────────────
  async function buildVegetation(cx, cz, envName){
    const rng = _chunkRng(cx + 3000, cz + 4000);
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor' || env === 'desert') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const group = new THREE.Group();
    const trunkMat = new THREE.MeshStandardMaterial({ color: 0x5c3a1e, roughness: 0.95, metalness: 0 });
    const darkTrunk = new THREE.MeshStandardMaterial({ color: 0x3d2612, roughness: 0.95, metalness: 0 });
    const leafColors = env === 'mountains'
      ? [0x2d6e2a, 0x245e22, 0x1e5218]
      : [0x3a8a2e, 0x2e7a24, 0x4a9a3c, 0x338030, 0x28701e];
    const count = env === 'mountains' ? 12 : 20;
    for (let i = 0; i < count; i++) {
        if (i % 5 === 0 && i !== 0) await new Promise(r => setTimeout(r, 0));
        const lx = (rng()-0.5)*CHUNK_SIZE*0.85;
      const lz = (rng()-0.5)*CHUNK_SIZE*0.85;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, wz, env);
      if (env === 'mountains' && hy > 30) continue;
      const treeType = Math.floor(rng()*3);
      const leafCol = leafColors[Math.floor(rng()*leafColors.length)];
      const leafMat = new THREE.MeshStandardMaterial({ color: leafCol, roughness: 0.85, metalness: 0 });
      if (treeType === 0) {
        // Pine / conifer
        const tH = 3 + rng()*4;
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.18, tH, 6), trunkMat.clone());
        trunk.position.set(lx, hy + tH/2, lz);
        trunk.castShadow = true;
        group.add(trunk);
        // Stacked cones
        const tiers = 3 + Math.floor(rng()*2);
        for (let t = 0; t < tiers; t++) {
          const ty = hy + tH*0.4 + t*(tH*0.22);
          const r = 1.6 - t*0.3 + rng()*0.3;
          const cone = new THREE.Mesh(new THREE.ConeGeometry(r, tH*0.35, 7), leafMat.clone());
          cone.position.set(lx, ty, lz);
          cone.castShadow = true;
          group.add(cone);
        }
      } else if (treeType === 1) {
        // Broad deciduous
        const tH = 2.5 + rng()*3;
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.22, tH, 7), darkTrunk.clone());
        trunk.position.set(lx, hy + tH/2, lz);
        trunk.castShadow = true;
        group.add(trunk);
        // Multi-sphere canopy
        const cr = 1.8 + rng()*1.4;
        const canopy = new THREE.Mesh(new THREE.SphereGeometry(cr, 8, 7), leafMat.clone());
        canopy.position.set(lx, hy + tH + cr*0.6, lz);
        canopy.scale.y = 0.72 + rng()*0.2;
        canopy.castShadow = true;
        group.add(canopy);
        // Extra lobes
        for (let l = 0; l < 3; l++) {
          const la = (l/3)*Math.PI*2 + rng()*0.8;
          const lr = cr*0.55;
          const lobe = new THREE.Mesh(new THREE.SphereGeometry(lr, 6, 5), leafMat.clone());
          lobe.position.set(wx+Math.cos(la)*cr*0.55, hy+tH+cr*0.3+rng()*0.5, wz+Math.sin(la)*cr*0.55);
          lobe.castShadow = true;
          group.add(lobe);
        }
      } else {
        // Tall slender birch
        const tH = 4 + rng()*5;
        const birchMat = new THREE.MeshStandardMaterial({ color: 0xddd8cc, roughness: 0.8 });
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.14, tH, 6), birchMat);
        trunk.position.set(lx, hy + tH/2, lz);
        trunk.castShadow = true;
        group.add(trunk);
        const brightLeaf = new THREE.MeshStandardMaterial({ color: 0x8ab840, roughness: 0.8 });
        const cr = 1.2 + rng()*0.8;
        const canopy = new THREE.Mesh(new THREE.SphereGeometry(cr, 7, 6), brightLeaf);
        canopy.position.set(lx, hy + tH + cr*0.5, lz);
        canopy.scale.y = 1.1;
        canopy.castShadow = true;
        group.add(canopy);
      }
    }
    return group;
  }

  // ── Buildings (urban) ─────────────────────────────────────────────
  // Seeded layout so buildings don't shift on every rebuild
  function _seededRand(seed) {
    let s = seed;
    return function() {
      s = (s * 1664525 + 1013904223) & 0xffffffff;
      return (s >>> 0) / 0xffffffff;
    };
  }

  function buildUrban() {
    const group = new THREE.Group();
    const bMats = [
      new THREE.MeshStandardMaterial({ color: 0x8090a0, roughness:0.7, metalness:0.2 }),
      new THREE.MeshStandardMaterial({ color: 0x607080, roughness:0.65, metalness:0.15 }),
      new THREE.MeshStandardMaterial({ color: 0x9aabbb, roughness:0.6, metalness:0.25 }),
      new THREE.MeshStandardMaterial({ color: 0x70859a, roughness:0.55, metalness:0.3 }),
    ];
    const rand = _seededRand(42); // Fixed seed = stable layout every rebuild
    for (let i = 0; i < 42; i++) {
      const x = (rand()-0.5)*200;
      const z = (rand()-0.5)*200;
      const dist = Math.hypot(x,z);
      if (dist < 14) continue; // keep spawn area clear
      const w = 4 + rand()*14, d = 4 + rand()*14, hh = 5 + rand()*38;
      const geo = new THREE.BoxGeometry(w, hh, d);
      const mesh = new THREE.Mesh(geo, bMats[i%4]);
      mesh.position.set(x, hh/2, z);
      mesh.castShadow = true; mesh.receiveShadow = true;
      group.add(mesh);
      // Store AABB with face normals for all 6 faces
      // _checkColliders uses the stored normal to push drone away from the hit face
      PHYS.colliders.push({
        min:{x:x-w/2, y:0,    z:z-d/2},
        max:{x:x+w/2, y:hh,   z:z+d/2},
        normal:{x:0,  y:1,    z:0},      // used by AABB hit — will be overridden per-face in _checkColliders
        _w:w, _d:d, _h:hh, _cx:x, _cz:z // extra data for face-normal resolution
      });
    }
    // Add road markings / ground detail
    const roadMat = new THREE.MeshLambertMaterial({ color: 0x2a2a2a });
    const roadGeo = new THREE.PlaneGeometry(200, 10);
    roadGeo.rotateX(-Math.PI/2);
    [-20,-5,10,25].forEach(rz => {
      const road = new THREE.Mesh(roadGeo, roadMat.clone());
      road.position.set(0, 0.01, rz);
      group.add(road);
    });
    const roadGeo2 = new THREE.PlaneGeometry(10, 200);
    roadGeo2.rotateX(-Math.PI/2);
    [-20,-5,10,25].forEach(rx => {
      const road = new THREE.Mesh(roadGeo2, roadMat.clone());
      road.position.set(rx, 0.01, 0);
      group.add(road);
    });
    return group;
  }

  // ── Sky Dome (BSL-inspired: Mie scatter, god rays, horizon glow) ──
  function buildSky(night) {
    const geo = new THREE.SphereGeometry(490, 48, 24);
    geo.scale(-1, 1, -1);
    const mat = new THREE.ShaderMaterial({
      vertexShader: `
        varying vec3 vPos;
        varying vec2 vUv;
        void main() {
          vPos = position;
          vUv = uv;
          gl_Position = projectionMatrix * modelViewMatrix * vec4(position,1.0);
        }
      `,
      fragmentShader: `
        varying vec3 vPos;
        varying vec2 vUv;
        uniform vec3 topColor;
        uniform vec3 midColor;
        uniform vec3 horizColor;
        uniform vec3 sunDir;
        uniform float sunSize;
        uniform float nightBlend;
        uniform float time;

        float hash(vec2 p){ return fract(sin(dot(p,vec2(127.1,311.7)))*43758.5453); }

        void main() {
          vec3 n = normalize(vPos);
          float t = clamp(n.y, 0.0, 1.0);
          float tLow = clamp(n.y * 2.5, 0.0, 1.0);

          // Layered sky gradient: top -> mid -> horizon
          vec3 sky = mix(horizColor, midColor, sqrt(tLow));
          sky = mix(sky, topColor, pow(t, 0.6));

          // Horizon haze/glow band
          float horizBand = exp(-abs(n.y)*6.0) * 0.5;
          vec3 hazeCol = mix(horizColor * 1.4, vec3(1.0, 0.88, 0.6), 0.3);
          sky += hazeCol * horizBand * (1.0 - nightBlend);

          // Sun
          vec3 sd = normalize(sunDir);
          float cosA = dot(n, sd);
          float sun = smoothstep(sunSize - 0.0015, sunSize + 0.0015, cosA);
          // Sun corona / glow
          float glow1 = pow(max(0.0, cosA), 32.0) * 0.4;
          float glow2 = pow(max(0.0, cosA), 8.0)  * 0.12;
          float glow3 = pow(max(0.0, cosA), 3.0)  * 0.05;
          vec3 sunHot  = vec3(1.0, 0.97, 0.88);
          vec3 sunWarm = vec3(1.0, 0.75, 0.4);
          vec3 sunCool = vec3(0.6, 0.8, 1.0);
          vec3 sunCol  = mix(sunWarm, sunHot, clamp(sd.y * 2.0, 0.0, 1.0));
          sky += sunCol * (sun + glow1 + glow2 + glow3) * (1.0 - nightBlend * 0.7);

          // God rays: radial streaks from sun
          vec2 sunScreen = vec2(sd.x, sd.y) * 0.5 + 0.5;
          float rayAng = atan(n.x - sd.x, n.z - sd.z) * 3.0;
          float rayDist = length(vec2(n.x - sd.x, n.z - sd.z));
          float rays = sin(rayAng * 6.0 + time * 0.3) * 0.5 + 0.5;
          rays *= exp(-rayDist * 4.0) * glow2 * 0.8;
          sky += sunCol * rays * max(0.0, sd.y) * (1.0 - nightBlend);

          // Mie scattering: warm haze near sun at horizon
          float mie = pow(max(0.0, cosA), 4.0) * max(0.0, 1.0 - abs(n.y)*3.0);
          sky += vec3(1.0, 0.7, 0.4) * mie * 0.3 * (1.0 - nightBlend);

          // Dusk/dawn tint on horizon opposite sun
          float antiSun = dot(n, vec3(-sd.x, 0.0, -sd.z));
          float duskGlow = pow(max(0.0, antiSun), 3.0) * max(0.0, 1.0-abs(n.y)*5.0);
          sky += vec3(0.6, 0.3, 0.6) * duskGlow * 0.15 * max(0.0, 1.0-sd.y*3.0) * (1.0-nightBlend);

          // Stars
          vec3 sPos = fract(n * 220.0) * 2.0 - 1.0;
          float star = max(0.0, 1.0 - length(sPos)*7.5);
          float twinkle = sin(time*2.3 + hash(n.xy*30.0)*6.28)*0.4+0.6;
          float stars = pow(star, 5.0) * nightBlend * twinkle * 1.8;
          // Milky way band
          float mwBand = exp(-abs(dot(n, vec3(0.3, 0.0, 0.95))-0.0)*8.0);
          stars += mwBand * 0.03 * nightBlend * hash(n.xz * 400.0);
          sky += vec3(0.85, 0.92, 1.0) * stars;

          // Moon
          vec3 moonDir = -sunDir; moonDir.y = abs(moonDir.y);
          float moonD = dot(n, normalize(moonDir));
          float moon = smoothstep(0.9988, 0.9992, moonD);
          sky += vec3(0.9, 0.92, 1.0) * moon * nightBlend;

          sky = max(sky, vec3(0.0));
          gl_FragColor = vec4(sky, 1.0);
        }
      `,
      uniforms: {
        topColor:   { value: night ? new THREE.Color(0x020408) : new THREE.Color(0x0d2e6a) },
        midColor:   { value: night ? new THREE.Color(0x030810) : new THREE.Color(0x1a5a9e) },
        horizColor: { value: night ? new THREE.Color(0x050c18) : new THREE.Color(0xb8d8f0) },
        sunDir:     { value: new THREE.Vector3(0.5, 0.8, 0.3).normalize() },
        sunSize:    { value: 0.9992 },
        nightBlend: { value: night ? 1.0 : 0.0 },
        time:       { value: 0.0 },
      },
      side: THREE.BackSide,
      depthWrite: false,
    });
    return new THREE.Mesh(geo, mat);
  }

  // ── Volumetric Clouds ─────────────────────────────────────────────
  function buildClouds() {
    const group = new THREE.Group();
    // Two cloud layers
    const layers = [
      { alt: 55, spread: 380, count: 8, minR: 5, maxR: 18, opacity: 0.78 },
      { alt: 95, spread: 300, count: 5, minR: 8, maxR: 25, opacity: 0.55 },
    ];
    layers.forEach(layer => {
      const mat = new THREE.MeshLambertMaterial({
        color: 0xffffff, transparent: true, opacity: layer.opacity,
        depthWrite: false,
      });
      for (let i = 0; i < layer.count; i++) {
        const cx2 = (Math.random()-0.5)*layer.spread;
        const cz2 = (Math.random()-0.5)*layer.spread;
        const cy  = layer.alt + Math.random()*20;
        const clumpCount = 2 + Math.floor(Math.random()*3);
        for (let j = 0; j < clumpCount; j++) {
          const r = layer.minR + Math.random()*(layer.maxR-layer.minR);
          const s = new THREE.Mesh(new THREE.SphereGeometry(r, 9, 7), mat.clone());
          s.position.set(cx2 + (Math.random()-0.5)*r*2.5,
                         cy  + (Math.random()-0.5)*5,
                         cz2 + (Math.random()-0.5)*r*2);
          s.scale.y = 0.42 + Math.random()*0.22;
          s.scale.x = 1.0 + Math.random()*0.5;
          group.add(s);
        }
      }
    });
    return group;
  }

  // ── Rain (streak-based for better visibility) ───────────────────────
  function buildRain() {
    const count = 4000;
    // Each raindrop is a short line segment (2 vertices)
    // positions: even = top of streak, odd = bottom
    const geo = new THREE.BufferGeometry();
    const pos = new Float32Array(count * 2 * 3); // 2 vertices per streak
    for (let i = 0; i < count; i++) {
      const x = (Math.random()-0.5)*80;
      const y = Math.random()*60;
      const z = (Math.random()-0.5)*80;
      const streakLen = 0.45 + Math.random()*0.35;
      pos[i*6  ] = x;     pos[i*6+1] = y;              pos[i*6+2] = z;   // top
      pos[i*6+3] = x+0.05;pos[i*6+4] = y - streakLen;  pos[i*6+5] = z;   // bottom (streak angled slightly)
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const mat = new THREE.LineBasicMaterial({
      color: 0xaad4ff, transparent: true, opacity: 0.55, depthWrite: false,
    });
    const lines = new THREE.LineSegments(geo, mat);
    return { pts: lines, geo, pos, isLines: true };
  }

  // ── Drone mesh (premium PBR materials) ───────────────────────────
  function buildDrone(color) {
    const g = new THREE.Group();
    const L  = PHYS.armLen || 0.19;
    const rr = PHYS.droneVisual.rotorRadius || 0.09;
    const bs = PHYS.droneVisual.bodyScale   || 1.0;

    // Materials — MeshStandardMaterial for PBR sheen
    const bodyMat  = new THREE.MeshStandardMaterial({ color: color||0x1e88e5, roughness:0.25, metalness:0.55, envMapIntensity:1.2 });
    const darkMat  = new THREE.MeshStandardMaterial({ color: 0x111111, roughness:0.35, metalness:0.6 });
    const carbonMat= new THREE.MeshStandardMaterial({ color: 0x1a1a1e, roughness:0.5, metalness:0.3 });
    const armMat   = new THREE.MeshStandardMaterial({ color: 0x232325, roughness:0.4, metalness:0.5 });
    const motorMat = new THREE.MeshStandardMaterial({ color: 0x888898, roughness:0.3, metalness:0.85 });
    const propMat  = new THREE.MeshStandardMaterial({ color: 0x0d0d10, roughness:0.55, metalness:0.1, transparent:true, opacity:0.88 });
    const glassMat = new THREE.MeshStandardMaterial({ color: 0x223366, roughness:0.05, metalness:0.0, transparent:true, opacity:0.65 });

    // ── Central body ─────────────────────────────────────────────────
    // Top plate — slightly tapered
    const topPlate = new THREE.Mesh(new THREE.BoxGeometry(0.19*bs, 0.020*bs, 0.19*bs), bodyMat);
    topPlate.position.y = 0.020*bs;
    topPlate.castShadow = true;
    g.add(topPlate);

    // Bottom plate
    const botPlate = new THREE.Mesh(new THREE.BoxGeometry(0.17*bs, 0.014*bs, 0.17*bs), carbonMat);
    botPlate.position.y = -0.010*bs;
    botPlate.castShadow = true;
    g.add(botPlate);

    // Mid frame (slightly recessed)
    const midFrame = new THREE.Mesh(new THREE.BoxGeometry(0.15*bs, 0.016*bs, 0.15*bs), armMat);
    midFrame.position.y = 0.005*bs;
    g.add(midFrame);

    // Battery pack
    const batt = new THREE.Mesh(new THREE.BoxGeometry(0.11*bs, 0.030*bs, 0.085*bs), darkMat);
    batt.position.set(0, 0.036*bs, 0);
    g.add(batt);

    // Battery strap
    const strap = new THREE.Mesh(new THREE.BoxGeometry(0.115*bs, 0.005*bs, 0.090*bs), new THREE.MeshStandardMaterial({ color:0x336688, roughness:0.7 }));
    strap.position.set(0, 0.053*bs, 0);
    g.add(strap);

    // Stack standoffs (4 corner pillars)
    [-1,1].forEach(sx => [-1,1].forEach(sz => {
      const pillar = new THREE.Mesh(new THREE.CylinderGeometry(0.007*bs, 0.007*bs, 0.032*bs, 8), motorMat.clone());
      pillar.position.set(sx*0.068*bs, 0.009*bs, sz*0.068*bs);
      g.add(pillar);
    }));

    // Flight controller board
    const fc = new THREE.Mesh(new THREE.BoxGeometry(0.06*bs, 0.004*bs, 0.06*bs), new THREE.MeshStandardMaterial({ color: 0x223322, roughness:0.6 }));
    fc.position.set(0, 0.016*bs, 0);
    g.add(fc);

    // FPV camera housing
    const fpvBody = new THREE.Mesh(new THREE.BoxGeometry(0.025*bs, 0.022*bs, 0.018*bs), carbonMat.clone());
    fpvBody.position.set(0, 0.016*bs, 0.094*bs);
    fpvBody.rotation.x = 0.38;
    g.add(fpvBody);
    // Camera lens
    const lens = new THREE.Mesh(new THREE.CylinderGeometry(0.007*bs, 0.007*bs, 0.010*bs, 12), glassMat);
    lens.rotation.x = Math.PI/2;
    lens.position.set(0, 0.016*bs, 0.102*bs);
    g.add(lens);
    // Lens inner
    const lensIn = new THREE.Mesh(new THREE.CircleGeometry(0.005*bs, 10), new THREE.MeshBasicMaterial({ color: 0x001122 }));
    lensIn.rotation.x = -Math.PI/2;
    lensIn.position.set(0, 0.016*bs, 0.107*bs);
    lensIn.rotation.set(-Math.PI/2, 0, 0);
    g.add(lensIn);

    // Antenna stubs
    [-0.06, 0.06].forEach(ox => {
      const ant = new THREE.Mesh(new THREE.CylinderGeometry(0.003*bs, 0.003*bs, 0.06*bs, 5), new THREE.MeshStandardMaterial({ color:0xffffff, roughness:0.9 }));
      ant.position.set(ox*bs, 0.070*bs, -0.07*bs);
      ant.rotation.z = (ox > 0 ? 0.25 : -0.25);
      g.add(ant);
    });

    // ── 4 Arms + Motors + Props ───────────────────────────────────────
    const motorPositions = [
      [ L*0.707,  0,  L*0.707],
      [-L*0.707,  0,  L*0.707],
      [-L*0.707,  0, -L*0.707],
      [ L*0.707,  0, -L*0.707],
    ];
    const ledColors = [0xff2222, 0x22ff44, 0x4488ff, 0xff8800];

    propMeshes = [];
    armMeshes  = [];

    motorPositions.forEach((mpos, i) => {
      const [mx,,mz] = mpos;

      // Arm — tapered trapezoid profile
      const armLength = Math.hypot(mx, mz);
      const armAngle  = Math.atan2(mx, mz);
      const arm = new THREE.Mesh(new THREE.BoxGeometry(0.022*bs, 0.013*bs, armLength), armMat.clone());
      arm.position.set(mx*0.5, 0, mz*0.5);
      arm.rotation.y = armAngle;
      arm.castShadow = true;
      g.add(arm);
      armMeshes.push(arm);

      // Arm carbon stripe detail
      const stripe = new THREE.Mesh(new THREE.BoxGeometry(0.008*bs, 0.0045*bs, armLength*0.85), carbonMat.clone());
      stripe.position.set(mx*0.5, 0.009*bs, mz*0.5);
      stripe.rotation.y = armAngle;
      g.add(stripe);

      // Motor mount ring
      const mountRing = new THREE.Mesh(new THREE.CylinderGeometry(0.028*bs, 0.028*bs, 0.008*bs, 12), armMat.clone());
      mountRing.position.set(mx, -0.003*bs, mz);
      g.add(mountRing);

      // Motor bell
      const motor = new THREE.Mesh(new THREE.CylinderGeometry(0.022*bs, 0.019*bs, 0.024*bs, 12), motorMat.clone());
      motor.position.set(mx, 0.012*bs, mz);
      motor.castShadow = true;
      g.add(motor);

      // Motor bottom cap
      const cap = new THREE.Mesh(new THREE.CylinderGeometry(0.017*bs, 0.017*bs, 0.006*bs, 12), darkMat.clone());
      cap.position.set(mx, -0.001*bs, mz);
      g.add(cap);

      // ── Propeller group ────────────────────────────────────────────
      const propGroup = new THREE.Group();
      propGroup.position.set(mx, 0.026*bs, mz);

      // 3 blades (more realistic)
      const bladeCount = 3;
      for (let b = 0; b < bladeCount; b++) {
        const blade = new THREE.Mesh(
          new THREE.BoxGeometry(rr*bs * 1.9, 0.004*bs, 0.032*bs),
          propMat.clone()
        );
        blade.rotation.y = b * (Math.PI * 2 / bladeCount);
        blade.rotation.z = (i%2===0 ? 1:-1) * 0.06;
        // Taper blade tip
        const bverts = blade.geometry.attributes.position;
        for (let v = 0; v < bverts.count; v++) {
          const bx2 = bverts.getX(v);
          if (Math.abs(bx2) > rr*bs*0.7) {
            const taper = 1 - (Math.abs(bx2) - rr*bs*0.7) / (rr*bs*0.25);
            bverts.setZ(v, bverts.getZ(v) * Math.max(0.1, taper));
          }
        }
        blade.geometry.computeVertexNormals();
        propGroup.add(blade);
      }

      // Prop hub
      const hub = new THREE.Mesh(new THREE.CylinderGeometry(0.013*bs, 0.013*bs, 0.009*bs, 10), motorMat.clone());
      propGroup.add(hub);

      // Prop spinner cone
      const spinner = new THREE.Mesh(new THREE.ConeGeometry(0.010*bs, 0.012*bs, 8), darkMat.clone());
      spinner.position.y = 0.010*bs;
      propGroup.add(spinner);

      g.add(propGroup);
      propMeshes.push(propGroup);

      // ── Landing gear ──────────────────────────────────────────────
      const legMat = new THREE.MeshStandardMaterial({ color: 0x2a2a2e, roughness:0.7, metalness:0.3 });
      // Main strut
      const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.005*bs, 0.005*bs, 0.065*bs, 6), legMat);
      leg.position.set(mx*0.58, -0.042*bs, mz*0.58);
      leg.rotation.z = mx > 0 ? 0.18 : -0.18;
      g.add(leg);
      // Foot skid
      const foot = new THREE.Mesh(new THREE.CylinderGeometry(0.012*bs, 0.008*bs, 0.006*bs, 8), legMat.clone());
      foot.position.set(mx*0.58, -0.074*bs, mz*0.58);
      g.add(foot);
      // Cross brace
      const brace = new THREE.Mesh(new THREE.CylinderGeometry(0.003*bs, 0.003*bs, 0.03*bs, 4), legMat.clone());
      brace.position.set(mx*0.58, -0.055*bs, mz*0.58);
      brace.rotation.x = mz > 0 ? 0.4 : -0.4;
      g.add(brace);

      // ── LED / running light ───────────────────────────────────────
      const led = new THREE.Mesh(new THREE.SphereGeometry(0.009*bs, 8, 6), new THREE.MeshBasicMaterial({ color: ledColors[i] }));
      led.position.set(mx, 0.028*bs, mz);
      g.add(led);

      // LED glow point light (small, subtle)
      const ledLight = new THREE.PointLight(ledColors[i], 0.12, 0.5);
      ledLight.position.set(mx, 0.030*bs, mz);
      g.add(ledLight);
    });

    // Visual scale-up for clarity (same as original)
    g.scale.setScalar(5.0);
    return g;
  }

  // ── Flight path trail ─────────────────────────────────────────────
  function initTrail() {
    const mat = new THREE.LineBasicMaterial({ color: 0xEE9346, transparent: true, opacity: 0.60, linewidth: 1 });
    const geo = new THREE.BufferGeometry();
    const pts = new Float32Array(500 * 3);
    geo.setAttribute('position', new THREE.BufferAttribute(pts, 3));
    geo.setDrawRange(0, 0);
    _trailLine = new THREE.Line(geo, mat);
    _trailPoints = [];
    return _trailLine;
  }

  function updateTrail() {
    const p = PHYS._renderPos || PHYS.pos;
    const last = _trailPoints[_trailPoints.length-1] || {x:p.x+999,y:p.y,z:p.z+999};
    if (V3.len(V3.sub(p, last)) > 0.05) {
      // Store world coords (doubles) in trail array — convert to render space at draw time
      _trailPoints.push({ x:p.x, y:p.y, z:p.z });
      if (_trailPoints.length > 600) _trailPoints.shift();
    }
    // Write trail buffer in render space to avoid float32 precision loss
    const buf = _trailLine.geometry.attributes.position.array;
    const rox = _renderOriginX, roz = _renderOriginZ;
    for (let i = 0; i < _trailPoints.length; i++) {
      buf[i*3  ] = _trailPoints[i].x - rox;
      buf[i*3+1] = _trailPoints[i].y;
      buf[i*3+2] = _trailPoints[i].z - roz;
    }
    _trailLine.geometry.setDrawRange(0, _trailPoints.length);
    _trailLine.geometry.attributes.position.needsUpdate = true;
    // Trail mesh stays at origin (render-space coords baked into vertices)
    _trailLine.position.set(0, 0, 0);
  }

  // ── Waypoint markers ─────────────────────────────────────────────
  function addWaypointMarker(pos) {
    const mat = new THREE.MeshStandardMaterial({ color: 0x10256D, transparent:true, opacity:0.88, roughness:0.5 });
    const mesh = new THREE.Mesh(new THREE.ConeGeometry(0.4, 1.2, 6), mat);
    mesh.position.set(pos.x, pos.y + 0.8, pos.z);
    scene.add(mesh);
    _waypointMarkers.push(mesh);
  }
  function clearWaypointMarkers() {
    _waypointMarkers.forEach(m => scene.remove(m));
    _waypointMarkers = [];
  }

  // ── Sun update ───────────────────────────────────────────────────
  function _updateSunFromTime(t) {
    const angle = (t - 0.25) * Math.PI * 2;
    const sunX  = Math.cos(angle) * 0.65;
    const sunY  = Math.sin(angle);
    const sunZ  = 0.38;
    const sunDir = new THREE.Vector3(sunX, sunY, sunZ).normalize();

    if (shadowLight) {
      shadowLight.position.copy(sunDir.clone().multiplyScalar(180));
      shadowLight.intensity = Math.max(0, sunY * 2.2 + 0.1);
      // Warm/cool color based on height
      const dusk = Math.max(0, 1 - sunY * 5);
      shadowLight.color.lerpColors(new THREE.Color(0xfff5e0), new THREE.Color(0xff8844), dusk*0.5);
    }
    const night = sunY < 0;
    const nightBlend = Math.max(0, -sunY * 1.8);

    if (skyMesh && skyMesh.material.uniforms) {
      skyMesh.material.uniforms.sunDir.value = sunDir;
      skyMesh.material.uniforms.nightBlend.value = Math.min(1, nightBlend);
      if (night) {
        skyMesh.material.uniforms.topColor.value.set(0x010306);
        skyMesh.material.uniforms.midColor.value.set(0x020810);
        skyMesh.material.uniforms.horizColor.value.set(0x050c1a);
      } else {
        const dusk = Math.max(0, 1 - sunY * 4);
        const top  = new THREE.Color().lerpColors(new THREE.Color(0x0d2e6a), new THREE.Color(0xdd4411), dusk*0.55);
        const mid  = new THREE.Color().lerpColors(new THREE.Color(0x1a5a9e), new THREE.Color(0xee7722), dusk*0.6);
        const hor  = new THREE.Color().lerpColors(new THREE.Color(0xb8d8f0), new THREE.Color(0xffcc88), dusk*0.8);
        skyMesh.material.uniforms.topColor.value.copy(top);
        skyMesh.material.uniforms.midColor.value.copy(mid);
        skyMesh.material.uniforms.horizColor.value.copy(hor);
      }
    }
    if (hemiLight) {
      hemiLight.intensity = Math.max(0.06, 0.72 * Math.max(0, sunY));
      hemiLight.color.lerpColors(new THREE.Color(0xc0d8f8), new THREE.Color(0xff9955), Math.max(0, 1 - sunY*5)*0.4);
    }
    if (moonLight) {
      moonLight.intensity = Math.max(0, nightBlend * 0.22);
    }
    if (scene.fog) {
      scene.fog.color.setHSL(0.58, night ? 0.15 : 0.35, night ? 0.03 : 0.80);
    }
  }

  // ── Chunk management ─────────────────────────────────────────────
  function _chunkKey(cx, cz) { return `${cx},${cz}`; }

  // ── Dispose all Three objects in a chunk ──────────────────────────
  function _disposeChunkData(cd) {
    ['mesh','veg','flowers','grass','rocks'].forEach(k => {
      if (!cd[k]) return;
      scene.remove(cd[k]);
      cd[k].traverse(o => {
        if (o.geometry) o.geometry.dispose();
        if (o.material) {
          if (Array.isArray(o.material)) o.material.forEach(m => m.dispose());
          else o.material.dispose();
        }
      });
    });
  }

  // ── Build one chunk and place it in render space ──────────────────
    async function _buildChunk(cx, cz, lod) {
    const key = _chunkKey(cx, cz);
    const existing = _chunks.get(key);
    if (existing) {
      if (existing.lod <= lod) return;
      _disposeChunkData(existing);
      _chunks.delete(key);
    }
    const segs = lod === 0 ? CHUNK_SEGS : CHUNK_SEGS_L;
    const chunkData = { cx, cz, lod };
    _chunks.set(key, chunkData); // Set immediately to prevent duplicates

    const worldX = cx * CHUNK_SIZE;
    const worldZ = cz * CHUNK_SIZE;
    const renderX = worldX - _renderOriginX;
    const renderZ = worldZ - _renderOriginZ;

    chunkData.mesh = await buildChunkMesh(cx, cz, _envName, segs);
    chunkData.mesh.position.set(renderX, 0, renderZ);
    scene.add(chunkData.mesh);
    
    await new Promise(r => setTimeout(r, 0)); // Yield to main thread

    if (lod === 0 && _envName !== 'indoor' && _envName !== 'urban') {
      const veg = buildInstancedVegetationForChunk(cx, cz, _envName);
      if (veg) {
        veg.position.set(renderX, 0, renderZ);
        scene.add(veg);
        chunkData.veg = veg;
      }
      await new Promise(r => setTimeout(r, 0)); // Yield

      const flowers = await buildFlowers(cx, cz, _envName);
      if (flowers) { flowers.position.set(renderX, 0, renderZ); scene.add(flowers); chunkData.flowers = flowers; }
      await new Promise(r => setTimeout(r, 0)); // Yield

      const grass = await buildGrassBlades(cx, cz, _envName);
      if (grass) { grass.position.set(renderX, 0, renderZ); scene.add(grass); chunkData.grass = grass; }
      await new Promise(r => setTimeout(r, 0)); // Yield

      const rocks = await buildRocks(cx, cz, _envName);
      if (rocks) { rocks.position.set(renderX, 0, renderZ); scene.add(rocks); chunkData.rocks = rocks; }
    }
  }

  function _unloadChunk(key) {
    const cd = _chunks.get(key);
    if (!cd) return;
    _disposeChunkData(cd);
    if (typeof CHUNK_COLLIDERS !== 'undefined') { CHUNK_COLLIDERS.delete(key); if (typeof updateFlatColliders === 'function') updateFlatColliders(); }
    _chunks.delete(key);
  }

  // ── Time-budgeted chunk drain — max 6ms per frame ────────────────
  const CHUNK_BUDGET_MS = 6;
    let _isBuildingChunk = false;
  function _drainLoadQueue() {
    if (_isBuildingChunk || _loadQueue.length === 0) return;
    const { cx, cz, lod } = _loadQueue.shift();
    const key = _chunkKey(cx, cz);
    const ex  = _chunks.get(key);
    if (!ex || ex.lod > lod) {
      _isBuildingChunk = true;
      _buildChunk(cx, cz, lod).then(() => { _isBuildingChunk = false; }).catch(e => { console.error(e); _isBuildingChunk = false; });
    }
  }

  // ── Called every render frame: determine which chunks are needed ──
  function _updateChunks() {
    if (_envName === 'indoor' || _envName === 'urban') return;
    const p = PHYS._renderPos || PHYS.pos;
    const cx = Math.round(p.x / CHUNK_SIZE);
    const cz = Math.round(p.z / CHUNK_SIZE);

    // Always drain queue first (spread build cost over frames)
    _drainLoadQueue();

    // Only recompute needed set when drone crosses a chunk boundary
    if (cx === _lastChunkX && cz === _lastChunkZ) return;
    _lastChunkX = cx; _lastChunkZ = cz;

    const needed = new Map(); // key -> lod (0=full, 1=low)

    // Dynamically lower render distance at high speeds to prevent massive chunk generation lag
    const spd = (typeof SIM !== 'undefined' && SIM._speed) ? SIM._speed : 1.0;
    const RENDER_DIST = spd >= 2.5 ? 1 : (spd >= 1.5 ? 2 : 3);
    
    // Full-detail inner ring
    for (let dx = -RENDER_DIST; dx <= RENDER_DIST; dx++) {
      for (let dz = -RENDER_DIST; dz <= RENDER_DIST; dz++) {
        needed.set(_chunkKey(cx+dx, cz+dz), 0);
      }
    }
    // Low-detail outer ring
    for (let dx = -LOD_DIST; dx <= LOD_DIST; dx++) {
      for (let dz = -LOD_DIST; dz <= LOD_DIST; dz++) {
        if (Math.abs(dx) <= RENDER_DIST && Math.abs(dz) <= RENDER_DIST) continue; // already full
        needed.set(_chunkKey(cx+dx, cz+dz), 1);
      }
    }

    // Queue new / upgraded chunks, sorted closest-first so nearest loads first
    const toLoad = [];
    for (const [key, lod] of needed) {
      const ex = _chunks.get(key);
      if (!ex || ex.lod > lod) {
        // Parse cx/cz back from key for distance sort
        const [kcx, kcz] = key.split(',').map(Number);
        const dist2 = (kcx-cx)**2 + (kcz-cz)**2;
        toLoad.push({ cx: kcx, cz: kcz, lod, dist2 });
      }
    }
    toLoad.sort((a, b) => a.dist2 - b.dist2);
    // Prepend to queue (priority: new closest chunks jump the line)
    _loadQueue = [...toLoad, ..._loadQueue.filter(e => needed.has(_chunkKey(e.cx, e.cz)))];

    // Unload chunks that are no longer in range — unload synchronously (GPU memory freed immediately)
    for (const [key] of _chunks) {
      if (!needed.has(key)) _unloadChunk(key);
    }
  }

  // ── Volumetric fog planes ─────────────────────────────────────────
  function buildFogLayers() {
    const group = new THREE.Group();
    const fogMat = new THREE.MeshBasicMaterial({
      color: 0xd8eeff, transparent: true, opacity: 0.18, depthWrite: false, side: THREE.DoubleSide
    });
    for (let i = 0; i < 2; i++) {
      const fp = new THREE.Mesh(new THREE.PlaneGeometry(600, 600), fogMat.clone());
      fp.rotation.x = -Math.PI/2;
      fp.position.y = 0.3 + i * 0.4;
      fp.material.opacity = 0.14 - i*0.025;
      group.add(fp);
    }
    return group;
  }

  // ── Init ──────────────────────────────────────────────────────────
  function init(canvasId) {
    _canvas = document.getElementById(canvasId);
    const vp = _canvas.parentElement;
    const W = vp.clientWidth || 800;
    const H = vp.clientHeight || 500;

    renderer = new THREE.WebGLRenderer({ canvas: _canvas, antialias: true, alpha: false, powerPreference: 'high-performance' });
    renderer.setPixelRatio(1); // optimized for lag
    renderer.setSize(W, H);
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.18;
    renderer.outputEncoding = THREE.sRGBEncoding;
    renderer.physicallyCorrectLights = true;

    scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0xc8e4f8, 0.0018);

    camera = new THREE.PerspectiveCamera(72, W/H, 0.05, 700);
    camera.position.set(0, 5, -12);
    camera.lookAt(0, 2, 0);

    clock = new THREE.Clock();

    // ── Lighting setup ───────────────────────────────────────────────
    hemiLight = new THREE.HemisphereLight(0xb0d0f8, 0x4a7040, 0.58);
    scene.add(hemiLight);

    shadowLight = new THREE.DirectionalLight(0xfff5e0, 1.8);
    shadowLight.position.set(90, 180, 70);
    shadowLight.castShadow = true;
    shadowLight.shadow.mapSize.width  = 4096;
    shadowLight.shadow.mapSize.height = 4096;
    shadowLight.shadow.camera.near    = 1;
    shadowLight.shadow.camera.far     = 600;
    shadowLight.shadow.camera.left    = shadowLight.shadow.camera.bottom = -140;
    shadowLight.shadow.camera.right   = shadowLight.shadow.camera.top    =  140;
    shadowLight.shadow.bias = -0.0002;
    shadowLight.shadow.normalBias = 0.02;
    scene.add(shadowLight);

    // Fill light — soft blue from opposite direction
    const fillLight = new THREE.DirectionalLight(0x6688bb, 0.28);
    fillLight.position.set(-60, 80, -40);
    scene.add(fillLight);

    moonLight = new THREE.DirectionalLight(0x8899cc, 0);
    moonLight.position.set(-80, 120, -60);
    scene.add(moonLight);

    // Ambient for interior
    const ambLight = new THREE.AmbientLight(0x404060, 0.12);
    scene.add(ambLight);

    // Build drone
    droneGroup = buildDrone(PHYS.droneVisual.color);
    scene.add(droneGroup);

    // Flight trail
    scene.add(initTrail());

    // Orbit mouse
    const vpEl = document.getElementById('viewport');
    vpEl.addEventListener('mousedown', e => { if(_camMode==='orbit'){_mouse.down=true;_mouse.lx=e.clientX;_mouse.ly=e.clientY;} });
    vpEl.addEventListener('mousemove', e => {
      if(_mouse.down&&_camMode==='orbit'){
        _orbitAngle += (e.clientX-_mouse.lx)*0.008;
        _orbitH = Math.max(0.5, Math.min(30, _orbitH - (e.clientY-_mouse.ly)*0.04));
        _mouse.lx=e.clientX; _mouse.ly=e.clientY;
      }
    });
    vpEl.addEventListener('mouseup', ()=>{ _mouse.down=false; });
    vpEl.addEventListener('wheel', e => { if(_camMode==='orbit'){ _orbitDist=Math.max(2,Math.min(60,_orbitDist+e.deltaY*0.02)); }});

    window.addEventListener('resize', () => _resize(vp));
  }

  function _resize(vp) {
    if (!renderer || !vp) return;
    const W = vp.clientWidth, H = vp.clientHeight;
    if (!W || !H) return;
    renderer.setSize(W, H);
    camera.aspect = W/H;
    camera.updateProjectionMatrix();
    const mc = document.getElementById('miniCanvas');
    if(mc) { mc.width=mc.parentElement.clientWidth; mc.height=mc.parentElement.clientHeight; }
  }

  // ── Rebuild scene (env presets) ──────────────────────────────────
  function rebuild(envName) {
    _envName = envName;
    PHYS.colliders = [];

    // Reset render origin and clear everything on env rebuild
    _renderOriginX = 0; _renderOriginZ = 0;
    _loadQueue = [];
    for (const [key] of _chunks) _unloadChunk(key);
    _chunks.clear();
    _lastChunkX = null; _lastChunkZ = null;

    // Remove env objects (preserve drone, trail, lights)
    const keepSet = new Set([droneGroup, _trailLine, shadowLight, hemiLight, moonLight]);
    _waypointMarkers.forEach(m => keepSet.add(m));
    const toRemove = [];
    for (const child of [...scene.children]) {
      if (keepSet.has(child)) continue;
      if (child.isLight) continue;
      toRemove.push(child);
    }
    toRemove.forEach(o => {
      scene.remove(o);
      if (o.geometry) o.geometry.dispose();
      if (o.material) {
        if (Array.isArray(o.material)) o.material.forEach(m=>m.dispose());
        else o.material.dispose();
      }
    });

    // Ensure drone and trail are present
    if (droneGroup && !scene.children.includes(droneGroup)) scene.add(droneGroup);
    if (_trailLine  && !scene.children.includes(_trailLine))  scene.add(_trailLine);

    // Sky
    skyMesh = buildSky(_nightMode);
    scene.add(skyMesh);

    // Clouds
    cloudGroup = buildClouds();
    scene.add(cloudGroup);

    // Urban special-case (fixed, not chunked)
    if (envName === 'urban') {
      const flatMesh = buildChunkMesh(0, 0, 'urban');
      scene.add(flatMesh);
      scene.add(buildUrban());
    }

    // Indoor special-case
    if (envName === 'indoor') {
      const wallMat = new THREE.MeshStandardMaterial({ color: 0xd0cfc2, roughness:0.85 });
      const floorMat = new THREE.MeshStandardMaterial({ color: 0xb0aeaa, roughness:0.9 });
      // Floor
      const floor = new THREE.Mesh(new THREE.PlaneGeometry(60, 60), floorMat);
      floor.rotation.x = -Math.PI/2; floor.receiveShadow = true;
      scene.add(floor);
      // Ceiling
      const ceil = new THREE.Mesh(new THREE.PlaneGeometry(60, 60), wallMat.clone());
      ceil.rotation.x = Math.PI/2; ceil.position.y = 20;
      scene.add(ceil);
      // Floor grid lines for visual reference
      const gridHelper = new THREE.GridHelper(60, 12, 0x888880, 0x666660);
      gridHelper.position.y = 0.01;
      scene.add(gridHelper);
      // Walls — fixed normals per side
      const wallDefs = [
        { pos:[  0, 10,  30], size:[60,20,0.5], norm:{x:0,y:0,z:-1} }, // N wall
        { pos:[  0, 10, -30], size:[60,20,0.5], norm:{x:0,y:0,z: 1} }, // S wall
        { pos:[ 30, 10,   0], size:[0.5,20,60], norm:{x:-1,y:0,z:0} }, // E wall
        { pos:[-30, 10,   0], size:[0.5,20,60], norm:{x: 1,y:0,z:0} }, // W wall
        { pos:[  0, 20,   0], size:[60,0.5,60], norm:{x: 0,y:-1,z:0} }, // Ceiling
      ];
      wallDefs.forEach(wd => {
        const wg = new THREE.BoxGeometry(...wd.size);
        const w = new THREE.Mesh(wg, wallMat.clone());
        w.position.set(...wd.pos);
        w.receiveShadow = true; w.castShadow = true;
        scene.add(w);
        const [wx,wy,wz] = wd.pos; const [sw,sh,sd] = wd.size;
        PHYS.colliders.push({
          min:{x:wx-sw/2, y:wy-sh/2, z:wz-sd/2},
          max:{x:wx+sw/2, y:wy+sh/2, z:wz+sd/2},
          normal: wd.norm,
        });
      });
      // Warehouse shelving units for visual interest
      const shelfMat = new THREE.MeshStandardMaterial({ color:0x8a7a6a, roughness:0.8, metalness:0.1 });
      const shelfPositions = [[-15,0,-10],[-15,0,0],[-15,0,10],[15,0,-10],[15,0,0],[15,0,10]];
      shelfPositions.forEach(([sx,sy,sz]) => {
        const shelf = new THREE.Mesh(new THREE.BoxGeometry(2,5,1), shelfMat.clone());
        shelf.position.set(sx, 2.5, sz);
        shelf.castShadow = true; shelf.receiveShadow = true;
        scene.add(shelf);
        // Add shelf colliders
        PHYS.colliders.push({
          min:{x:sx-1.2, y:0, z:sz-0.7},
          max:{x:sx+1.2, y:5, z:sz+0.7},
          normal:{x:0, y:1, z:0}
        });
      });
      // Overhead lighting rigs
      const rigMat = new THREE.MeshStandardMaterial({ color:0x444444, roughness:0.6, metalness:0.5 });
      [-15,0,15].forEach(lx => {
        const rig = new THREE.Mesh(new THREE.BoxGeometry(1,0.2,50), rigMat.clone());
        rig.position.set(lx, 19.8, 0);
        scene.add(rig);
        // Add point lights along rig
        [-20,0,20].forEach(lz => {
          const pl = new THREE.PointLight(0xfff5e0, 0.6, 40);
          pl.position.set(lx, 18, lz);
          scene.add(pl);
        });
      });
      hemiLight.intensity = 0.85;
      shadowLight.intensity = 0.45;
    }

    // Volumetric fog planes
    if (_fogOn || envName === 'field' || envName === 'windy') {
      const fogLayers = buildFogLayers();
      // scene.add(fogLayers); // Disabled to prevent massive GPU overdraw lag when looking down from high altitude
    }

    // Rain
    if (_rainOn) {
      const r = buildRain();
      _rainParticles = r.pts; _rainGeo = r.geo; _rainPositions = r.pos;
      scene.add(_rainParticles);
    }

    // Fog distances per environment
    switch(envName) {
      case 'mountains': scene.fog = new THREE.FogExp2(0xd0e8f0, 0.0012); break;
      case 'desert':    scene.fog = new THREE.FogExp2(0xffe8a0, 0.0020); break;
      case 'urban':     scene.fog = new THREE.FogExp2(0xc0c8d8, 0.0022); break;
      case 'windy':     scene.fog = new THREE.FogExp2(0xb8d8f0, 0.0025); break;
      case 'indoor':    scene.fog = new THREE.Fog(0xddd8c8, 30, 80); break;
      default:          scene.fog = new THREE.FogExp2(0xc8e4f8, 0.0018);
    }

    _updateSunFromTime(_dayTime);

    // Initial chunk load (around spawn)
    if (envName !== 'urban' && envName !== 'indoor') {
      _lastChunkX = null; _lastChunkZ = null;
      // Build centre 3×3 immediately so there's terrain underfoot on first frame
      const spawnCX = Math.round(PHYS.pos.x / CHUNK_SIZE);
      const spawnCZ = Math.round(PHYS.pos.z / CHUNK_SIZE);
      for (let dx = -1; dx <= 1; dx++) {
        for (let dz = -1; dz <= 1; dz++) {
          _buildChunk(spawnCX+dx, spawnCZ+dz, 0);
        }
      }
      // Queue the rest for async streaming
      _updateChunks();
    }
  }

  // ── Camera update (render-space) ─────────────────────────────────
  function _camMinY(wx, wz, margin) {
    return terrainHeight(wx, wz, _envName) + (margin || 0.8);
  }

  function updateCamera(dt) {
    const p  = PHYS._renderPos || PHYS.pos;
    const quat = PHYS._renderQuat || PHYS.quat;
    const yaw  = PHYS.euler.yaw;
    const rox  = _renderOriginX, roz = _renderOriginZ;

    // Drone render-space position (always near origin)
    const drx = p.x - rox, drz = p.z - roz;

    if (_camMode === 'third') {
      const dist = 4.5, height = 2.2;
      const twx = p.x - Math.sin(yaw)*dist; // world
      const twz = p.z - Math.cos(yaw)*dist;
      const ty  = Math.max(p.y + height, _camMinY(twx, twz, 1.2));
      const lerpAmt = 1.0 - Math.exp(-8.0 * dt);
        camera.position.lerp(_camTargetV3.set(twx - rox, ty, twz - roz), lerpAmt);
      camera.lookAt(drx, Math.max(p.y+0.3, PHYS.groundY+0.5), drz);
    } else if (_camMode === 'fpv') {
      const fwd = Q.rotVec(quat, {x:0, y:0.05, z:0.15});
      const fpvY = Math.max(p.y + fwd.y, PHYS.groundY + 0.15);
      camera.position.set(drx + fwd.x, fpvY, drz + fwd.z);
      const aim = Q.rotVec(quat, {x:0, y:-0.1, z:1.0});
      const lookY = PHYS.crashed ? PHYS.groundY + 0.5 : p.y + aim.y;
      camera.lookAt(drx + aim.x, lookY, drz + aim.z);
    } else if (_camMode === 'orbit') {
      const owx = p.x + Math.sin(_orbitAngle)*_orbitDist;
      const owz = p.z + Math.cos(_orbitAngle)*_orbitDist;
      const oy  = Math.max(p.y + _orbitH, _camMinY(owx, owz, 1.5));
      camera.position.lerp(_camTargetV3.set(owx - rox, oy, owz - roz), 0.08);
      camera.lookAt(drx, Math.max(p.y, PHYS.groundY + 0.3), drz);
    } else if (_camMode === 'free') {
      // Free cam stored in render space (small numbers)
      camera.position.lerp(_camTargetV3.set(_freeCam.x, _freeCam.y, _freeCam.z), 0.05);
      camera.lookAt(drx, p.y, drz);
    } else if (_camMode === 'top') {
      camera.position.lerp(_camTargetV3.set(drx, p.y+22, drz+0.001), 0.06);
      camera.lookAt(drx, p.y, drz);
    }
  }

  // ── Software bloom (additive overdraw) ───────────────────────────
  let _bloomCanvas = null, _bloomCtx = null, _bloomEnabled = false;
  function _initBloom() {
    _bloomCanvas = document.createElement('canvas');
    _bloomCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1;mix-blend-mode:screen;opacity:0.28;';
    const vp = document.getElementById('viewport');
    if (vp) vp.appendChild(_bloomCanvas);
    _bloomCtx = _bloomCanvas.getContext('2d', {willReadFrequently: true});
  }

  function _drawBloom(W, H) {
    if (!_bloomCanvas || !_bloomCtx || W < 4 || H < 4) return;
    _bloomCanvas.width  = Math.round(W * 0.25);
    _bloomCanvas.height = Math.round(H * 0.25);
    const bW = _bloomCanvas.width, bH = _bloomCanvas.height;
    const ctx = _bloomCtx;
    // Grab the three.js canvas and downscale
    ctx.drawImage(_canvas, 0, 0, bW, bH);
    // Multi-pass gaussian-like blur
    ctx.filter = 'blur(6px)';
    ctx.globalCompositeOperation = 'source-over';
    ctx.drawImage(_bloomCanvas, 0, 0);
    ctx.filter = 'none';
    // Scale back up via CSS — the browser handles the upscale blur
    _bloomCanvas.style.width  = W + 'px';
    _bloomCanvas.style.height = H + 'px';
  }

  // ── Render tick ──────────────────────────────────────────────────
  let _frame = 0, _fps = 60, _fpsSmooth = 60, _lastFPSTime = 0;
  let _simTime = 0;
  const _camTargetV3 = new THREE.Vector3();
  const _shadowOffsetV3 = new THREE.Vector3();
  let _viewportEl = null;
  function render() {
    requestAnimationFrame(render);
    const dt = Math.min(0.05, clock.getDelta());
    _frame++;
    _simTime += dt;
    // Render-origin for this frame (used by camera, shadow, drone, trail)
    const rox = _renderOriginX, roz = _renderOriginZ;

    // FPS counter
    const now = performance.now();
    const instantFps = 1/Math.max(dt, 0.001);
    _fps = _fps * 0.92 + instantFps * 0.08;
    if (now - _lastFPSTime > 500) { _fpsSmooth = Math.round(_fps); _lastFPSTime = now; }

    // Sky time uniform
    if (skyMesh && skyMesh.material.uniforms) {
      skyMesh.material.uniforms.time.value = _simTime;
    }

    // ── Floating-origin rebase check ──────────────────────────────────
    // Rebase when the drone strays too far from render origin
    const p = PHYS._renderPos || PHYS.pos;
    const distFromOrigin = Math.max(Math.abs(p.x - _renderOriginX), Math.abs(p.z - _renderOriginZ));
    if (distFromOrigin > REBASE_THRESHOLD) {
      _rebaseRenderOrigin();
    }

    // Place drone in render space (world minus render origin)
    droneGroup.position.set(p.x - rox, p.y, p.z - roz);
    const q = PHYS._renderQuat || PHYS.quat; droneGroup.quaternion.set(q.x, q.y, q.z, q.w);

    // Propeller spin
    for (let i = 0; i < 4; i++) {
      const rpm = PHYS.motorRPM[i] || 0;
      const dir = PHYS.motorDir[i] || 1;
      propAngle[i] += dir * rpm * (Math.PI*2/60) * dt;
      if (propMeshes[i]) propMeshes[i].rotation.y = propAngle[i];
    }

    // Propeller disc blur effect — scale up disc opacity with RPM
    propMeshes.forEach((pm, i) => {
      const pct = Math.min(1, (PHYS.motorRPM[i]||0) / (PHYS.maxRPM||14000));
      pm.children.forEach(child => {
        if (child.material && child.material.opacity !== undefined && child.material !== undefined) {
          // blades get more transparent at high RPM (motion blur illusion)
          const isHub = child.geometry && child.geometry.type === 'CylinderGeometry';
          if (!isHub) child.material.opacity = 0.88 - pct * 0.62;
        }
      });
    });

    // Cloud drift with wind — wrap within a max radius to prevent glitching
    if (cloudGroup) {
      cloudGroup.position.x += PHYS.windVec.x * dt * 0.12;
      cloudGroup.position.z += PHYS.windVec.z * dt * 0.12;
      // Gentle bob — additive delta so it doesn't snap
      const prevBob = cloudGroup.userData._lastBob || 0;
      const newBob = Math.sin(_simTime * 0.06) * 1.2;
      cloudGroup.position.y += newBob - prevBob;
      cloudGroup.userData._lastBob = newBob;
      // Wrap cloud group back around drone when it drifts too far
      const maxDrift = 180;
      if (Math.abs(cloudGroup.position.x - p.x) > maxDrift) cloudGroup.position.x = p.x + (Math.random()-0.5)*60;
      if (Math.abs(cloudGroup.position.z - p.z) > maxDrift) cloudGroup.position.z = p.z + (Math.random()-0.5)*60;
    }

    // Rain animation (streak-based: stride 6)
    if (_rainOn && _rainPositions) {
      const dropCount = _rainPositions.length / 6;
      const windX = PHYS.windVec.x * dt * 0.4;
      const windZ = PHYS.windVec.z * dt * 0.4;
      const fallSpeed = 20 * dt;
      for (let i = 0; i < dropCount; i++) {
        const b = i * 6;
        _rainPositions[b+1] -= fallSpeed;   // top y
        _rainPositions[b+4] -= fallSpeed;   // bottom y
        _rainPositions[b  ] += windX;       // top x drift
        _rainPositions[b+3] += windX;
        _rainPositions[b+2] += windZ;       // top z drift
        _rainPositions[b+5] += windZ;
        if (_rainPositions[b+1] < -8) {
          // Use render-space drone position (small numbers, no precision loss)
          const drx2 = p.x - _renderOriginX, drz2 = p.z - _renderOriginZ;
          const nx = drx2 + (Math.random()-0.5)*80;
          const nz = drz2 + (Math.random()-0.5)*80;
          const ny = 58 + Math.random()*5;
          const sl = 0.45 + Math.random()*0.35;
          _rainPositions[b  ] = nx;     _rainPositions[b+1] = ny;
          _rainPositions[b+2] = nz;
          _rainPositions[b+3] = nx+0.05;_rainPositions[b+4] = ny - sl;
          _rainPositions[b+5] = nz;
        }
      }
      _rainGeo.attributes.position.needsUpdate = true;
      // Keep rain centred on drone
      if (_rainParticles) _rainParticles.position.set(0, 0, 0);
    }

    // Day cycle
    _dayTime += dt * 0.00055;
    if (_dayTime > 1) _dayTime -= 1;
    if (!_nightMode) _updateSunFromTime(_dayTime);

    // Shadow frustum follows drone in render space
    if (shadowLight) {
      const sdx = p.x - rox, sdz = p.z - roz;
      shadowLight.target.position.set(sdx, 0, sdz);
      shadowLight.target.updateMatrixWorld();
    }

    // Chunk streaming
    _updateChunks();

    updateCamera(dt);
    // [FIX] Sky sphere follows camera so it never exits the sphere (eliminates black-hole gap)
    if (skyMesh) skyMesh.position.copy(camera.position);
    updateTrail();
    renderer.render(scene, camera);

    // Software bloom (every frame for buttery smoothness, but at 1/8th res)
    if (_bloomEnabled) {
      if (!_viewportEl) _viewportEl = document.getElementById('viewport');
      if (_viewportEl) _drawBloom(_canvas.width * 0.5, _canvas.height * 0.5);
    }
  }

  // Init bloom canvas after a short delay (DOM ready)
  setTimeout(_initBloom, 500);

  // Public API — exactly matching original
  return {
    init,
    _resize,
    rebuild,
    addWaypointMarker,
    clearWaypointMarkers,
    setCamera(mode) { _camMode = mode; },
    setNight(on) {
      _nightMode = on;
      _dayTime = on ? 0.0 : 0.5;
      _updateSunFromTime(_dayTime);
      if (skyMesh && skyMesh.material.uniforms) {
        skyMesh.material.uniforms.nightBlend.value = on ? 1.0 : 0.0;
      }
    },
    setRain(on) { _rainOn = on; rebuild(_envName); },
    setFog(on)  { _fogOn  = on; rebuild(_envName); },
    rebuildDrone(color) {
      if (droneGroup) scene.remove(droneGroup);
      droneGroup = buildDrone(color);
      scene.add(droneGroup);
    },
    getFPS() { return _fpsSmooth; },
    getTerrainHeight(x, z) { if(Math.abs(this._lastTX-x)<0.05 && Math.abs(this._lastTZ-z)<0.05) return this._lastTH; this._lastTX=x; this._lastTZ=z; this._lastTH=terrainHeight(x, z, _envName); return this._lastTH; },
    getSafeSpawnPoint() { return getSafeSpawnPoint(_envName); },
    getChunkInfo() {
      return { loaded: _chunks.size, queued: _loadQueue.length };
    },
    render,
  };
})();


/* ══════════════════════════════════════════════════════════════════════
   MINIMAP
══════════════════════════════════════════════════════════════════════ */
const MINIMAP = {
  _trail: [],
  draw() {
    const canvas = document.getElementById('miniCanvas');
    if (!canvas) return;
    if (typeof this._lastSync === 'undefined' || performance.now() - this._lastSync > 1000) {
      this._lastSync = performance.now();
      const newW = canvas.clientWidth || 220;
      const newH = canvas.clientHeight || 120;
      if (canvas.width !== newW || canvas.height !== newH) {
        canvas.width = newW;
        canvas.height = newH;
      }
    }
    if (!canvas.width || !canvas.height) return;
    const W = canvas.width, H = canvas.height;
    const ctx = canvas.getContext('2d', {willReadFrequently: true});
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = '#1a2744';
    ctx.fillRect(0, 0, W, H);

    const scale = 1.2;
    const cx = W/2, cy = H/2;
    const px = PHYS.pos.x, pz = PHYS.pos.z;

    // Grid
    ctx.strokeStyle = 'rgba(255,255,255,0.06)';
    ctx.lineWidth = 1;
    for (let i = -5; i <= 5; i++) {
      const gx = cx + i*10*scale; ctx.beginPath(); ctx.moveTo(gx, 0); ctx.lineTo(gx, H); ctx.stroke();
      const gy = cy + i*10*scale; ctx.beginPath(); ctx.moveTo(0, gy); ctx.lineTo(W, gy); ctx.stroke();
    }

    // Trail
    if (this._trail.length > 1) {
      ctx.strokeStyle = 'rgba(238,147,70,0.5)'; ctx.lineWidth = 1.5; ctx.beginPath();
      this._trail.forEach((pt, i) => {
        const tx = cx + (pt.x - px)*scale, ty = cy + (pt.z - pz)*scale;
        if (i===0) ctx.moveTo(tx, ty); else ctx.lineTo(tx, ty);
      });
      ctx.stroke();
    }

    // Waypoints
    if (typeof MISSION !== 'undefined') {
      MISSION.waypoints.forEach((wp, i) => {
        const wx = cx + (wp.x - px)*scale, wy = cy + (wp.z - pz)*scale;
        ctx.fillStyle = '#10256D'; ctx.beginPath(); ctx.arc(wx, wy, 4, 0, Math.PI*2); ctx.fill();
        ctx.fillStyle = 'white'; ctx.font = '8px Inter'; ctx.fillText(i+1, wx-2, wy+3);
      });
    }

    // Home marker — [FIX-6.23] clamp to minimap bounds if drone flew far
    if (PHYS.homePos) {
      const rawHx = cx + (PHYS.homePos.x-px)*scale;
      const rawHy = cy + (PHYS.homePos.z-pz)*scale;
      const margin = 8;
      const hx = Math.max(margin, Math.min(W-margin, rawHx));
      const hy = Math.max(margin, Math.min(H-margin, rawHy));
      ctx.fillStyle = '#4CAF50'; ctx.font = '12px Arial'; ctx.fillText('⌂', hx-6, hy+4);
      // Draw arrow pointing toward true home if it's off-screen
      if(rawHx !== hx || rawHy !== hy){
        ctx.strokeStyle='#4CAF50'; ctx.lineWidth=1;
        ctx.setLineDash([2,2]);
        ctx.beginPath(); ctx.moveTo(cx,cy); ctx.lineTo(hx,hy); ctx.stroke();
        ctx.setLineDash([]);
      }
    }

    // Drone
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(-PHYS.euler.yaw);
    ctx.fillStyle = '#EE9346';
    ctx.beginPath();
    ctx.moveTo(0, -6); ctx.lineTo(-4, 4); ctx.lineTo(4, 4);
    ctx.closePath(); ctx.fill();
    ctx.restore();

    // Badge — show world position + loaded chunk count
    const badge = document.getElementById('minimap-badge');
    if (badge) {
      const wx = PHYS.pos.x.toFixed(0), wz = PHYS.pos.z.toFixed(0);
      badge.textContent = `${wx}, ${wz}`;
    }

    // Trail update
    if (this._trail.length === 0 || Math.hypot(px - (this._trail[this._trail.length-1]?.x||0), pz - (this._trail[this._trail.length-1]?.z||0)) > 1) {
      this._trail.push({x:px, z:pz});
      if (this._trail.length > 200) this._trail.shift();
    }
  },
};

/* ══════════════════════════════════════════════════════════════════════
   ATTITUDE INDICATOR
   [FIX-6.22] Correct sign convention: positive roll = right bank (CW from pilot POV)
   [FIX-6.22] Horizon drawn using actual roll quaternion, not Euler-only
══════════════════════════════════════════════════════════════════════ */
function drawAttitude() {
  const canvas = document.getElementById('attCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d', {willReadFrequently: true});
  const W = canvas.width, H = canvas.height;
  const cx = W/2, cy = H/2, r = W/2 - 2;
  // [FIX-6.22] Use quaternion directly for roll to avoid gimbal lock at ±90° pitch
  // Extract roll from quaternion: avoids Euler singularity
  const q = PHYS.quat;
  const pitch = PHYS.euler.pitch;
  // Roll angle from quat (rotation about Z in body frame)
  const sinr = 2*(q.w*q.z + q.x*q.y);
  const cosr = 1 - 2*(q.y*q.y + q.z*q.z);
  const roll = Math.atan2(sinr, cosr); // positive = right bank ✓

  ctx.clearRect(0, 0, W, H);
  ctx.save();
  ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI*2); ctx.clip();

  ctx.save();
  ctx.translate(cx, cy);
  // [FIX-6.22] Positive roll = right bank = clockwise rotation from pilot POV
  // In canvas: negative rotation = counterclockwise = left; we negate roll for canvas
  ctx.rotate(-roll);
  const pitchPx = pitch * (H * 0.9);
  // Sky
  ctx.fillStyle = '#1a6bb0';
  ctx.fillRect(-W, -H-pitchPx, W*2, H*2);
  // Ground
  ctx.fillStyle = '#7a5c2e';
  ctx.fillRect(-W, -pitchPx, W*2, H*2);
  // Horizon line
  ctx.strokeStyle = 'rgba(255,255,255,0.9)'; ctx.lineWidth = 1.5;
  ctx.beginPath(); ctx.moveTo(-W, -pitchPx); ctx.lineTo(W, -pitchPx); ctx.stroke();
  // Pitch ladder
  ctx.strokeStyle = 'rgba(255,255,255,0.5)'; ctx.font = '8px Inter'; ctx.fillStyle='white'; ctx.lineWidth=1;
  for (let deg = -30; deg <= 30; deg += 10) {
    if (deg === 0) continue;
    const y = -pitchPx - (deg*Math.PI/180) * (H*0.9);
    const ll = Math.abs(deg) === 20 ? r*0.5 : r*0.3;
    ctx.beginPath(); ctx.moveTo(-ll, y); ctx.lineTo(ll, y); ctx.stroke();
    ctx.fillText(deg+'°', ll+3, y+3);
  }
  ctx.restore();

  // Roll arc
  ctx.save(); ctx.translate(cx, cy);
  ctx.strokeStyle = 'rgba(255,255,255,0.5)'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.arc(0, 0, r-4, -Math.PI, 0, false); ctx.stroke();
  // Roll indicator (triangle pointing toward roll angle)
  ctx.rotate(-roll);
  ctx.fillStyle = 'rgba(255,200,50,0.9)';
  ctx.beginPath(); ctx.moveTo(0, -(r-6)); ctx.lineTo(-4, -(r+2)); ctx.lineTo(4, -(r+2)); ctx.closePath(); ctx.fill();
  ctx.restore();

  // Fixed aircraft symbol (centre reticle)
  ctx.strokeStyle = 'rgba(255,210,60,0.95)'; ctx.lineWidth = 2;
  ctx.beginPath(); ctx.moveTo(cx-18, cy); ctx.lineTo(cx-6, cy); ctx.lineTo(cx-4, cy-3); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(cx+18, cy); ctx.lineTo(cx+6, cy); ctx.lineTo(cx+4, cy-3); ctx.stroke();
  ctx.beginPath(); ctx.arc(cx, cy, 2.5, 0, Math.PI*2); ctx.fillStyle='rgba(255,210,60,0.95)'; ctx.fill();

  ctx.restore();
  // Border
  ctx.strokeStyle = 'var(--n3)'; ctx.lineWidth = 1.5;
  ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI*2); ctx.stroke();
}

/* ══════════════════════════════════════════════════════════════════════
   WIND COMPASS
══════════════════════════════════════════════════════════════════════ */
function drawWindCompass() {
  const canvas = document.getElementById('windCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d', {willReadFrequently: true});
  const W = canvas.width, H = canvas.height;
  const cx = W/2, cy = H/2, r = W/2-3;
  ctx.clearRect(0,0,W,H);
  ctx.fillStyle='#1a2744'; ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.fill();
  ctx.strokeStyle='rgba(255,255,255,0.2)'; ctx.lineWidth=1; ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.stroke();
  const dirs=['N','E','S','W']; const dAngles=[0,Math.PI/2,Math.PI,3*Math.PI/2];
  ctx.font='7px Inter'; ctx.fillStyle='rgba(255,255,255,0.6)'; ctx.textAlign='center';
  dirs.forEach((d,i)=>{
    const ax=cx+Math.sin(dAngles[i])*(r-6), ay=cy-Math.cos(dAngles[i])*(r-6);
    ctx.fillText(d,ax,ay+3);
  });
  // Arrow
  const wAngle = Math.atan2(PHYS.windVec.x, PHYS.windVec.z);
  const wMag = V3.len(PHYS.windVec);
  if (wMag > 0.1) {
    ctx.save(); ctx.translate(cx,cy); ctx.rotate(wAngle);
    ctx.strokeStyle='#EE9346'; ctx.lineWidth=1.5;
    ctx.beginPath(); ctx.moveTo(0,r-8); ctx.lineTo(0,-r+10); ctx.stroke();
    ctx.fillStyle='#EE9346'; ctx.beginPath(); ctx.moveTo(0,-r+8); ctx.lineTo(-3,-r+14); ctx.lineTo(3,-r+14); ctx.closePath(); ctx.fill();
    ctx.restore();
  }
}

/* ══════════════════════════════════════════════════════════════════════
   WARNING SYSTEM
══════════════════════════════════════════════════════════════════════ */
const WARN = {
  _active: {},
  trigger(type) {
    const msgs = {
      lowbatt: { txt:'⚡ Battery Critical', level:'err' },
      lowalt:  { txt:'⚠ Low Altitude', level:'warn' },
      crash:   { txt:'💥 Crash Detected', level:'err' },
      wind:    { txt:'💨 High Wind', level:'warn' },
    };
    const m = msgs[type]; if (!m) return;
    this._active[type] = m;
    this._render();
    if (type === 'lowalt') {
      const vw = document.getElementById('vp-warn');
      if (vw) { vw.classList.add('show'); setTimeout(()=>vw.classList.remove('show'), 2500); }
    }
  },
  clear(type) { delete this._active[type]; this._render(); },
  _render() {
    const el = document.getElementById('warn-list');
    if (!el) return;
    const items = Object.values(this._active);
    if (!items.length) {
      el.innerHTML = '<div class="warn-item"><div class="warn-dot ok"></div><span>Systems Nominal</span></div>';
      return;
    }
    el.innerHTML = items.map(m=>`<div class="warn-item"><div class="warn-dot ${m.level}"></div><span>${m.txt}</span></div>`).join('');
  },
};

/* ══════════════════════════════════════════════════════════════════════
   UI UTILITIES
══════════════════════════════════════════════════════════════════════ */
const UI = {
  _logItems: [],
  toast(msg) {
    let t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg; t.style.opacity='1';
    clearTimeout(this._toastTimer);
    this._toastTimer = setTimeout(()=>t.style.opacity='0', 2200);
  },
  log(msg, level='ok') {
    const el = document.getElementById('log-list'); if (!el) return;
    const now = new Date(); const ts = now.getMinutes().toString().padStart(2,'0')+':'+now.getSeconds().toString().padStart(2,'0');
    const item = document.createElement('div');
    item.className=`log-item ${level}`;
    item.innerHTML=`<span class="log-tag">${level.toUpperCase()}</span><span>${ts} ${msg}</span>`;
    el.prepend(item);
    while (el.children.length > 12) el.removeChild(el.lastChild);
  },
};

/* ══════════════════════════════════════════════════════════════════════
   MISSION PLANNER
══════════════════════════════════════════════════════════════════════ */
const MISSION = {
  waypoints: [],
  active: false, paused: false, _idx: 0,

  add(pos) {
    this.waypoints.push({ x:pos.x, y:Math.max(pos.y+1, 3), z:pos.z });
    this._updateUI();
    THREE_ENV.addWaypointMarker({ x:pos.x, y:pos.y, z:pos.z });
    UI.log(`WP${this.waypoints.length} added`, 'ok');
  },
  start() {
    if (!this.waypoints.length) return;
    this.active=true; this.paused=false; this._idx=0;
    FC.setMode('gpshold'); State.armed=true; updateArmUI();
    UI.toast('▶ Mission started'); UI.log('Mission start','ok');
  },
  pause() {
    this.paused=!this.paused;
    UI.toast(this.paused?'⏸ Mission paused':'▶ Mission resumed');
  },
  clear() {
    this.waypoints=[]; this.active=false; this._idx=0;
    this._updateUI(); THREE_ENV.clearWaypointMarkers();
    UI.toast('✕ Mission cleared');
  },
  update() {
    if (!this.active || this.paused || !this.waypoints.length) return;
    if (this._idx >= this.waypoints.length) { this.active=false; FC.setMode('althold'); UI.toast('✅ Mission complete'); return; }
    const wp = this.waypoints[this._idx];
    const dx = wp.x - PHYS.pos.x, dz = wp.z - PHYS.pos.z;
    FC.altTarget = wp.y - PHYS.groundY;
    FC.posTarget = { x:wp.x, z:wp.z };
    if (Math.hypot(dx,dz) < 1.5 && Math.abs(PHYS.pos.y - wp.y) < 1.0) {
      UI.log(`WP${this._idx+1} reached`, 'ok');
      this._idx++;
    }
  },
  _updateUI() {
    const el = document.getElementById('wp-list'); if (!el) return;
    el.innerHTML = this.waypoints.map((wp,i)=>
      `<div class="wp-item"><div class="wp-num">${i+1}</div><span>WP ${i+1}</span><span class="wp-coords">${wp.x.toFixed(1)},${wp.z.toFixed(1)}</span></div>`
    ).join('');
  },
};

/* ══════════════════════════════════════════════════════════════════════
   ENVIRONMENT CONFIG
══════════════════════════════════════════════════════════════════════ */
const ENV = {
  _name: 'field',
  set(name) {
    this._name = name;
    // Configure physics wind/turbulence per environment
    const configs = {
      field:     { wind:0,  dir:0,   turb:0 },
      mountains: { wind:4,  dir:315, turb:30 },
      urban:     { wind:2,  dir:220, turb:15 },
      indoor:    { wind:0,  dir:0,   turb:0 },
      desert:    { wind:6,  dir:60,  turb:20 },
      windy:     { wind:12, dir:180, turb:60 },
    };
    const cfg = configs[name] || configs.field;
    const rad = (cfg.dir * Math.PI/180);
    PHYS.windVec = { x:Math.sin(rad)*cfg.wind, y:0, z:Math.cos(rad)*cfg.wind };
    PHYS.turbulenceIntensity = cfg.turb/100;
    // Update sliders
    const ws = document.getElementById('wind-speed'); if(ws) ws.value = cfg.wind;
    const wv = document.getElementById('wind-val');   if(wv) wv.textContent = cfg.wind+' m/s';
    const tb = document.getElementById('turbulence'); if(tb) tb.value = cfg.turb;
    const tv = document.getElementById('turb-val');   if(tv) tv.textContent = cfg.turb+'%';
    // Ground height for indoor/warehouse
    PHYS.groundY = 0;
    THREE_ENV.rebuild(name);
    // Use getSafeSpawnPoint to find lowest terrain valley — drone no longer spawns inside mountains
    const _spawnPt = THREE_ENV.getSafeSpawnPoint();
    const gY = _spawnPt.y;
    // Set groundY BEFORE reset so PHYS.reset snaps to correct height
    PHYS.groundY = gY;
    const _droneHalf = 0.074 * (PHYS.droneVisual.bodyScale || 1.0) * 5.0;
    // Always respawn at safe location when switching environments or when clearly underground
    const clearlyUnderground = PHYS.pos.y < gY + _droneHalf - 0.5;
    if (true) {
      PHYS.crashed = false;
      PHYS.grounded = true;
      PHYS.pos.x = _spawnPt.x;
      PHYS.pos.z = _spawnPt.z;
      PHYS.pos.y = gY + _droneHalf;
      PHYS.vel = {x:0, y:0, z:0};
      PHYS.angVel = {x:0, y:0, z:0};
      PHYS.quat = {w:1,x:0,y:0,z:0};
      PHYS.euler = {roll:0,pitch:0,yaw:0};
    }
    document.querySelectorAll('[data-env]').forEach(b => b.classList.toggle('on', b.dataset.env===name));
    UI.log(`Environment: ${name}`, 'ok');
  },
};

/* ══════════════════════════════════════════════════════════════════════
   SIMULATION LOOP
══════════════════════════════════════════════════════════════════════ */
const State = {
  armed: false,
  
  flightMode: 'stabilized',
  motorDamage: [0,0,0,0],
  flightTime: 0,
};

const SIM = {
  _last: 0, _running: false, _paused: false, _speed: 3.0,
  start() {
    this._running = true;
    this._paused = false;
    this._last = performance.now();
    THREE_ENV.render();
    requestAnimationFrame(() => this._loop());
  },
  pause() {
    this._paused = true;
    const btn = document.getElementById('pause-btn');
    if (btn) { btn.textContent = '▶ Resume'; btn.classList.add('paused'); }
    const sysStat = document.getElementById('sys-status');
    if (sysStat) sysStat.textContent = 'PAUSED';
    const sysDot = document.getElementById('sys-dot');
    if (sysDot) sysDot.className = 'sdot w';
    UI.toast('⏸ Simulation paused');
  },
  resume() {
    this._paused = false;
    this._last = performance.now(); // reset to avoid dt spike
    const btn = document.getElementById('pause-btn');
    if (btn) { btn.textContent = '⏸ Pause'; btn.classList.remove('paused'); }
    UI.toast('▶ Simulation resumed');
  },
  setSpeed(s) {
    this._speed = parseFloat(s) || 1.0;
    UI.toast('⏩ Speed: ' + this._speed + '×');
  },
  _loop() {
    if (!this._running) return;
    requestAnimationFrame(() => this._loop());
    if (this._paused) return;
    const now = performance.now();
    // [FIX-Bug-26b] rawDt = real wall time (unscaled) for clock display
    const rawDt = Math.min(0.05, (now - this._last) / 1000);
    this._last = now;
    const dt = rawDt * this._speed;      // [FIX-Bug-26c] Absolute sim time always advances (not only when armed)
      _simClock.t += dt;
      if (typeof this._acc === 'undefined') this._acc = 0;
      const FIXED_DT = 1 / 60;
      // dt is already scaled by this._speed above (const dt = rawDt * this._speed)
      // So this naturally causes the fixed timestep loop to run 3x more times per frame
      // giving smooth 3x speed without stutter!
      this._acc += dt;
      INPUT.update(dt);
      const inp = INPUT.get();

      const _envName_sim = typeof ENV !== 'undefined' ? ENV._name : 'field';
      const checkGround = _envName_sim !== 'indoor' && _envName_sim !== 'urban';

            if (!PHYS._realPos) {
        PHYS._realPos = { x:PHYS.pos.x, y:PHYS.pos.y, z:PHYS.pos.z };
        PHYS._realQuat = { w:PHYS.quat.w, x:PHYS.quat.x, y:PHYS.quat.y, z:PHYS.quat.z };
      }
      PHYS._prevPos = { x:PHYS._realPos.x, y:PHYS._realPos.y, z:PHYS._realPos.z };
      PHYS._prevQuat = { w:PHYS._realQuat.w, x:PHYS._realQuat.x, y:PHYS._realQuat.y, z:PHYS._realQuat.z };

      let _physSteps = 0;
      while (this._acc >= FIXED_DT) {
        if (++_physSteps > 20) { this._acc = 0; break; }
        if (checkGround) {
          PHYS.groundY = THREE_ENV.getTerrainHeight(PHYS.pos.x, PHYS.pos.z);
          if (PHYS.groundY < 0) PHYS.groundY = 0;
        }

        FC.update(FIXED_DT, inp);
        PHYS.step(FIXED_DT);

        PHYS._realPos = { x:PHYS.pos.x, y:PHYS.pos.y, z:PHYS.pos.z };
        PHYS._realQuat = { w:PHYS.quat.w, x:PHYS.quat.x, y:PHYS.quat.y, z:PHYS.quat.z };

        this._acc -= FIXED_DT;
      }

      // Interpolate for buttery smooth rendering at high refresh rates
      const alpha = Math.min(1.0, Math.max(0.0, this._acc / FIXED_DT));
      if (PHYS._prevPos && PHYS._realPos) {
        PHYS._renderPos = {}; PHYS._renderPos.x = PHYS._prevPos.x + (PHYS._realPos.x - PHYS._prevPos.x) * alpha;
        PHYS._renderPos.y = PHYS._prevPos.y + (PHYS._realPos.y - PHYS._prevPos.y) * alpha;
        PHYS._renderPos.z = PHYS._prevPos.z + (PHYS._realPos.z - PHYS._prevPos.z) * alpha;

        const a = PHYS._prevQuat, b = PHYS._realQuat;
        const dot = a.w*b.w + a.x*b.x + a.y*b.y + a.z*b.z;
        const sign = dot < 0 ? -1 : 1;
        const w = a.w + (b.w * sign - a.w) * alpha;
        const x = a.x + (b.x * sign - a.x) * alpha;
        const y = a.y + (b.y * sign - a.y) * alpha;
        const z = a.z + (b.z * sign - a.z) * alpha;
        const l = Math.hypot(w,x,y,z) || 1;
        PHYS._renderQuat = {w:w/l, x:x/l, y:y/l, z:z/l};
      }

      MISSION.update();

    if (State.armed) State.flightTime += rawDt;  // [FIX-Bug-26b] use real time for clock

    // Update telemetry systems
    GPS_SIM.update(dt);
    VISION_POS.update(dt);
    OBSTACLE_DIST.update();
    PID_TELEM.capture();

    // [FIX-Bug-26c] Use shared sim clock (same reference as sim-engine.js)
    BLACKBOX.tick(_simClock.t);
    TELEM_GRAPH.push(PHYS);
    
    // Throttle UI and 2D canvas draws to ~20 Hz (every 3rd frame) to reduce CPU load
    if (typeof this._simUIFrame === 'undefined') this._simUIFrame = 0;
    this._simUIFrame++;
    if (this._simUIFrame % 6 === 0) {
      this._updateUI(rawDt); // Throttled DOM text updates
      TELEM_GRAPH.draw();
      DEBUG.draw();
      MINIMAP.draw();
      drawAttitude();
      drawWindCompass();
      
    }
  },

  // ── Cached DOM references — populated on first _updateUI call ──
  _dom: null,
  _initDomCache() {
    const ids = [
      't-alt','t-vel','t-hdng','t-pitch','t-roll','t-yaw',
      't-vx','t-vy','t-vz','t-px','t-py','t-pz',
      'batt-pct','batt-top','t-volt','t-curr','batt-bar',
      't-wind','t-gust','top-clock','t-batt-eta',
      'gps-lat','gps-lon','gps-alt','gps-sat-count','gps-hdop','gps-fix-badge',
      'gps-sat-row','vslam-x','vslam-y','vslam-z','vslam-quality-val',
      'vslam-badge','vslam-quality','fps-val','sys-dot','sys-status',
      'm0-rpm','m1-rpm','m2-rpm','m3-rpm',
      'm0-bar','m1-bar','m2-bar','m3-bar',
      'obs-fwd','obs-right','obs-back','obs-left','obs-up',
      'obs-fwd-v','obs-right-v','obs-back-v','obs-left-v','obs-up-v',
      'pid-roll-kp','pid-roll-ki','pid-roll-kd','pid-roll-err-lbl','pid-roll-err',
      'pid-pitch-kp','pid-pitch-ki','pid-pitch-kd','pid-pitch-err-lbl','pid-pitch-err',
      'pid-yaw-kp','pid-yaw-ki','pid-yaw-kd','pid-yaw-err-lbl','pid-yaw-err',
      'pid-thr-kp','pid-thr-ki','pid-thr-kd','pid-thr-err-lbl','pid-thr-err',
    ];
    this._dom = {};
    for (const id of ids) this._dom[id] = document.getElementById(id);
  },

  _updateUI(dt) {
    // Init DOM cache on first call (DOM must be ready)
    if (!this._dom) this._initDomCache();
    const D = this._dom;
    const set = (id,v) => { const el=D[id]; if(el) el.textContent=v; };
    const p = PHYS, e = p.euler;
    const R2D = 180/Math.PI;
    const alt = Math.max(0, p.pos.y - p.groundY);
    const vel = V3.len(p.vel);

    // Telemetry — all reads from cached elements
    set('t-alt', alt.toFixed(1));
    set('t-vel', vel.toFixed(1));
    set('t-hdng', (((e.yaw*R2D+360)%360)|0).toString().padStart(3,'0'));

    // NEW HUD updates
    const elTvAlt = document.getElementById('tv-alt'); if (elTvAlt) elTvAlt.textContent = alt.toFixed(0) + 'm';
    const elTvSpd = document.getElementById('tv-spd'); if (elTvSpd) elTvSpd.textContent = (vel * 3.6).toFixed(0) + ' km/h';
    const dist = p.homePos ? Math.hypot(p.pos.x - p.homePos.x, p.pos.z - p.homePos.z) : Math.hypot(p.pos.x, p.pos.z);
    const elTvDist = document.getElementById('tv-dist'); if (elTvDist) elTvDist.textContent = (dist / 1000).toFixed(1) + ' km';
    const elTvHdg = document.getElementById('tv-hdg'); if (elTvHdg) elTvHdg.textContent = (((e.yaw*R2D+360)%360)|0).toString().padStart(3,'0') + '°';
    const vspd = p.vel.y;
    const elTvVspd = document.getElementById('tv-vspd'); if (elTvVspd) { elTvVspd.textContent = (vspd > 0 ? '+' : '') + vspd.toFixed(1); elTvVspd.className = 'tel-value ' + (vspd > 0 ? 'tel-green' : (vspd < 0 ? 'tel-orange' : '')); }
    const elFsRoll = document.getElementById('fs-roll'); if (elFsRoll) { elFsRoll.textContent = (e.roll > 0 ? '+' : '') + (e.roll * R2D).toFixed(1) + '°'; elFsRoll.className = 'flight-value ' + (Math.abs(e.roll*R2D) > 20 ? 'fv-orange' : ''); }
    const elFsPitch = document.getElementById('fs-pitch'); if (elFsPitch) { elFsPitch.textContent = (e.pitch > 0 ? '+' : '') + (e.pitch * R2D).toFixed(1) + '°'; elFsPitch.className = 'flight-value ' + (Math.abs(e.pitch*R2D) > 20 ? 'fv-orange' : ''); }

    set('t-pitch', (e.pitch*R2D).toFixed(1));
    set('t-roll',  (e.roll *R2D).toFixed(1));
    set('t-yaw',   (e.yaw  *R2D).toFixed(1));
    set('t-vx', p.vel.x.toFixed(1));
    set('t-vy', p.vel.y.toFixed(1));
    set('t-vz', p.vel.z.toFixed(1));
    set('t-px', p.pos.x.toFixed(1));
    set('t-py', p.pos.y.toFixed(1));
    set('t-pz', p.pos.z.toFixed(1));

    // Battery
    const battPct = p.battPct;
    const battStr = battPct.toFixed(0)+'%';
    set('batt-pct', battStr);
    set('batt-top', battStr);
    set('t-volt', p.battVoltage.toFixed(2));
    set('t-curr', p.currentDraw.toFixed(1));
    const bbar = D['batt-bar'];
    if (bbar) {
      bbar.style.transform = 'scaleX(' + (battPct/100) + ')';
      bbar.className = 'bgauge-fill ' + (battPct<20?'red':battPct<50?'orange':'green');
    }
    if (battPct < 15) WARN.trigger('lowbatt'); else WARN.clear('lowbatt');

    // Wind
    const wMag = V3.len(p.windVec);
    const gustMag = V3.len(DRYDEN.get());
    set('t-wind', wMag.toFixed(1));
    set('t-gust', gustMag.toFixed(1));
    if (wMag > 10 || gustMag > 5) WARN.trigger('wind'); else WARN.clear('wind');

    // Low alt warning
    if (State.armed && alt < 0.8 && alt > 0.15) WARN.trigger('lowalt'); else WARN.clear('lowalt');

    // Motors
    for (let i = 0; i < 4; i++) {
      const rpmEl = D[`m${i}-rpm`]; const barEl = D[`m${i}-bar`];
      const rpm = Math.round(p.motorRPM[i]);
      if (rpmEl) _setTxt(rpmEl, rpm);
      if (barEl) {
        barEl.style.transform = 'scaleX(' + (rpm/p.maxRPM) + ')';
        const dmg = State.motorDamage[i]||0;
        barEl.className = 'motor-bar' + (dmg>0.5?' red':dmg>0.2?' orange':'');
      }
    }

    // Clock (Session Time)
    const ft = performance.now() / 1000;
    const clk = D['top-clock'];
    if (clk) clk.textContent = Math.floor(ft/60).toString().padStart(2,'0')+':'+Math.floor(ft%60).toString().padStart(2,'0');

    // Battery ETA
    const etaSec = getBattEstimatedFlightTime();
    set('t-batt-eta', etaSec < 9999 ? (etaSec/60).toFixed(1) : '--');

    // ── GPS_RAW_INT ────────────────────────────────────────────
    const gps = GPS_SIM;
    const fixType = gps.getFixType();
    const satCount = gps.getSatCount();
    const hdop = gps.getHdop();
    set('gps-lat', gps.getLat().toFixed(5));
    set('gps-lon', gps.getLon().toFixed(5));
    set('gps-alt', gps.getAltMSL().toFixed(1));
    set('gps-sat-count', satCount);
    set('gps-hdop', hdop.toFixed(2));
    const fixBadge = D['gps-fix-badge'];
    if (fixBadge) {
      const fixLabels = { 0:'NO FIX', 1:'NO FIX', 2:'2D FIX', 3:'3D FIX', 4:'DGPS', 5:'RTK' };
      const fixClasses= { 0:'gps-fix-none', 1:'gps-fix-none', 2:'gps-fix-2d', 3:'gps-fix-3d', 4:'gps-fix-3d', 5:'gps-fix-3d' };
      fixBadge.textContent = fixLabels[fixType]||'NO FIX';
      fixBadge.className = 'gps-fix-badge '+(fixClasses[fixType]||'gps-fix-none');
    }
    // Satellite dots — lazy-build once, then update classes only
    const satRow = D['gps-sat-row'];
    if (satRow && satRow.children.length !== 16) {
      satRow.innerHTML = Array(16).fill(0).map((_,i)=>`<div class="gps-sat-dot" id="sat-dot-${i}"></div>`).join('');
    }
    if (satRow) {
      const dots = satRow.children;
      for (let i = 0; i < 16; i++) dots[i].className = 'gps-sat-dot'+(i<satCount?' on':i<satCount+2?' dim':'');
    }

    // ── VISION_POSITION ────────────────────────────────────────────
    const vp = VISION_POS.get();
    set('vslam-x', vp.x);
    set('vslam-y', vp.y);
    set('vslam-z', vp.z);
    set('vslam-quality-val', vp.quality+'%');
    const vslamBadge = D['vslam-badge'];
    if (vslamBadge) {
      vslamBadge.textContent = vp.active ? 'VSLAM ACTIVE' : 'GPS ACTIVE';
      vslamBadge.className = 'vslam-badge '+(vp.active ? 'vslam-active' : 'vslam-idle');
    }
    const vslamQ = D['vslam-quality'];
    if (vslamQ) vslamQ.style.transform = 'scaleX(' + (vp.quality/100) + ')';

    // ── OBSTACLE_DISTANCE ─────────────────────────────────────────
    const obs = OBSTACLE_DIST.get();
    const obsMax = OBSTACLE_DIST.SENSOR_RANGE;
    const obsIds = ['fwd','right','back','left','up'];
    for (let i = 0; i < 5; i++) {
      const pct = Math.min(100, (obs[i]/obsMax)*100);
      const barEl = D['obs-'+obsIds[i]];
      const valEl = D['obs-'+obsIds[i]+'-v'];
      if (barEl) barEl.style.transform = 'scaleX(' + (pct/100) + ')';
      if (valEl) _setTxt(valEl, obs[i].toFixed(1)+'m');
    }
    _updateObstacleRadar(obs, obsMax);

    // ── PID TELEMETRY ─────────────────────────────────────────
    const pt = PID_TELEM.axes;
    const pidKeys  = ['roll','pitch','yaw','throttle'];
    const pidIds   = ['roll','pitch','yaw','thr'];
    const errScale = [5,5,3,20];
    for (let pi=0; pi<4; pi++) {
      const key=pidKeys[pi], id=pidIds[pi], ax=pt[key];
      set(`pid-${id}-kp`, ax.kp.toFixed(3));
      set(`pid-${id}-ki`, ax.ki.toFixed(3));
      set(`pid-${id}-kd`, ax.kd.toFixed(3));
      const txt = (ax.error >= 0 ? '+' : '') + ax.error.toFixed(3); if(D[`pid-${id}-err-lbl`].textContent !== txt) D[`pid-${id}-err-lbl`].textContent = txt;
      const errEl = D[`pid-${id}-err`];
      if (errEl) {
        const norm = Math.max(-1, Math.min(1, ax.error / errScale[pi]));
        const w = Math.abs(norm) * 0.5;
          const start = norm >= 0 ? 0.5 : 0.5 - w;
          errEl.style.transform = 'translateX('+(start*100)+'%) scaleX('+w+')';
        errEl.style.background = w > 35 ? 'var(--s)' : 'var(--p)';
      }
    }

    // FPS
    const fpsEl = D['fps-val'];
    if (fpsEl) fpsEl.textContent = THREE_ENV.getFPS()+'fps';

    // Chunk count (live)
    const chunkEl = document.getElementById('chunk-count');
    if (chunkEl) {
      const info = THREE_ENV.getChunkInfo ? THREE_ENV.getChunkInfo() : null;
      if (info) chunkEl.textContent = `${info.loaded} chunks · ${info.queued} queued`;
    }

    // System status
    const sysDot = D['sys-dot'], sysStat = D['sys-status'];
    const crashOverlay = document.getElementById('crash-overlay');
    if (p.crashed) {
      if (sysDot) { sysDot.className='sdot e'; }
      if (sysStat) sysStat.textContent='CRASHED';
      if (crashOverlay && !crashOverlay.classList.contains('show')) crashOverlay.classList.add('show');
    } else {
      if (crashOverlay && crashOverlay.classList.contains('show')) crashOverlay.classList.remove('show');
      if (State.armed) {
        if (sysDot) sysDot.className='sdot w';
        if (sysStat) sysStat.textContent='ARMED';
      } else {
        if (sysDot) sysDot.className='sdot';
        if (sysStat) sysStat.textContent='READY';
      }
    }
  },
};

/* ══════════════════════════════════════════════════════════════════════
   UI CALLBACKS (called from HTML onclick / oninput)
══════════════════════════════════════════════════════════════════════ */

/* ── Obstacle Radar SVG updater ──
 * [FIX-6.25] Line length ∝ INVERSE of distance (close=long, far=short)
 * [FIX-6.25] UP sector shown as separate vertical bar gauge (can't show on plan-view radar)
 */
function _updateObstacleRadar(obs, maxRange) {
  const svg = document.getElementById('obs-sectors');
  if (!svg) return;
  const R = 35;
  const dirs = [
    { idx:0, angle:-Math.PI/2 },   // FWD → up
    { idx:1, angle:0 },            // RIGHT → right
    { idx:2, angle:Math.PI/2 },    // BACK → down
    { idx:3, angle:Math.PI },      // LEFT → left
  ];
  svg.innerHTML = '';
  dirs.forEach(({ idx, angle }) => {
    const d = obs[idx];
    // [FIX-6.25] Inverse: close obstacle = long line, far = short line
    const invNorm = Math.min(1, 1 - d / maxRange); // 0=far, 1=very close
    const len = Math.max(2, invNorm * R); // minimum 2px to always be visible
    const x = Math.cos(angle) * len;
    const y = Math.sin(angle) * len;
    // Color: close=red (long line), far=green (short line)
    const hue = invNorm > 0.7 ? '#F44336' : invNorm > 0.4 ? '#EE9346' : '#4CAF50';
    const line = document.createElementNS('http://www.w3.org/2000/svg','line');
    line.setAttribute('x1','0'); line.setAttribute('y1','0');
    line.setAttribute('x2', x.toFixed(2)); line.setAttribute('y2', y.toFixed(2));
    line.setAttribute('stroke', hue); line.setAttribute('stroke-width','3.5');
    line.setAttribute('stroke-linecap','round'); line.setAttribute('opacity','0.85');
    svg.appendChild(line);
    const circle = document.createElementNS('http://www.w3.org/2000/svg','circle');
    circle.setAttribute('cx', x.toFixed(2)); circle.setAttribute('cy', y.toFixed(2));
    circle.setAttribute('r','2.5'); circle.setAttribute('fill', hue);
    svg.appendChild(circle);
  });
  // [FIX-6.25] UP sector: separate vertical bar gauge (right of radar, already in HTML as obs-up bar)
  // The obs-up bar in the right-side table already handles this — no SVG needed for up/down
}

function updateArmUI() {
  const el = document.getElementById('arm-status');
  if (el) {
    el.textContent = State.armed ? 'ARMED' : 'DISARMED';
    el.className = State.armed ? 'armed' : '';
    el.id = 'arm-status'; // keep id for CSS styling
  }
}

function updateDroneProfileUI(name) {
  const p = DRONE_PROFILES[name];
  if (!p) return;
  const s = id => document.getElementById(id);
  const dl = s('drone-profile-label'); if(dl) dl.textContent = p.label;
  const dm = s('drone-mass-val');    if(dm) dm.textContent = p.mass+' kg';
  const db = s('drone-batt-val');    if(db) db.textContent = p.cells+'S '+p.battTotalAh+' Ah';
  const dr = s('drone-maxrpm-val');  if(dr) dr.textContent = p.maxRPM.toLocaleString();
}

function setFlightModeUI(mode) {
  document.querySelectorAll('.fmode-btn').forEach(b => b.classList.toggle('on', b.dataset.mode===mode));
}

function setFlightMode(mode) {
  FC.setMode(mode);
  State.flightMode = mode;
  setFlightModeUI(mode);
  // Auto-center throttle when entering any altitude-holding mode
  if (mode === 'althold' || mode === 'gpshold' || mode === 'rth') {
    animateThrottle(0.5, 300);
  }
  UI.toast('Mode: '+mode.toUpperCase());
  UI.log('Mode → '+mode, 'ok');
}

function setCamera(mode) {
  _camMode_global = mode;
  THREE_ENV.setCamera(mode);
  document.querySelectorAll('.cam-btn').forEach(b => b.classList.toggle('on', b.dataset.cam===mode));
  const badge = document.getElementById('cam-badge');
  const labels = {third:'THIRD PERSON', fpv:'FPV', orbit:'ORBIT', free:'FREE', top:'TOP DOWN'};
  if (badge) badge.textContent = labels[mode]||mode.toUpperCase();
}
let _camMode_global = 'third';

function cycleCamera() {
  const modes = ['third','fpv','orbit','top','free'];
  const idx = modes.indexOf(_camMode_global);
  setCamera(modes[(idx+1)%modes.length]);
}

function setEnvironment(name) {
  ENV.set(name);
  document.querySelectorAll('[data-env]').forEach(b => b.classList.toggle('on', b.dataset.env===name));
}

function applyWorldSeed() {
  const el = document.getElementById('world-seed-input');
  const seed = parseInt(el?.value || '12345', 10) || 12345;
  if (typeof setWorldSeed === 'function') setWorldSeed(seed);
  // Re-run current environment to regenerate terrain with new seed
  ENV.set(typeof ENV !== 'undefined' ? ENV._name : 'field');
  UI.toast('🌍 World seed: ' + seed);
  UI.log('New seed: ' + seed, 'ok');
}

function randomWorldSeed() {
  const seed = Math.floor(Math.random() * 999999);
  const el = document.getElementById('world-seed-input');
  if (el) el.value = seed;
  applyWorldSeed();
}

function setDroneProfile(name) {
  PHYS.applyProfile(name);
  updateDroneProfileUI(name);
  THREE_ENV.rebuildDrone(PHYS.droneVisual.color);
  UI.toast('Profile: '+(DRONE_PROFILES[name]?.label||name));
  UI.log('Profile → '+name, 'ok');
  // Sync customize panel if open
  const panel = document.getElementById('profile-customize-panel');
  if (panel && panel.classList.contains('open')) populateCustomizeFields(name);
}

function setWind(val) {
  const spd = parseFloat(val);
  const dir = parseFloat(document.getElementById('wind-dir')?.value||0);
  const rad = dir * Math.PI/180;
  PHYS.windVec = { x:Math.sin(rad)*spd, y:0, z:Math.cos(rad)*spd };
  const el = document.getElementById('wind-val');
  if (el) el.textContent = spd.toFixed(0)+' m/s';
}

function setWindDir(val) {
  const dir = parseFloat(val);
  const spd = parseFloat(document.getElementById('wind-speed')?.value||0);
  const rad = dir * Math.PI/180;
  PHYS.windVec = { x:Math.sin(rad)*spd, y:0, z:Math.cos(rad)*spd };
  const dirs = ['N','NE','E','SE','S','SW','W','NW'];
  const dIdx = Math.round(dir/45)%8;
  const el = document.getElementById('wdir-val');
  if (el) el.textContent = dirs[dIdx]+' '+Math.round(dir)+'°';
}

function setTurbulence(val) {
  PHYS.turbulenceIntensity = parseFloat(val)/100;
  const el = document.getElementById('turb-val');
  if (el) el.textContent = Math.round(val)+'%';
}

function toggleWeather(type, el) {
  const track = document.getElementById(type+'-track');
  if (!track) return;
  const on = !track.classList.contains('on');
  track.classList.toggle('on', on);
  if (type === 'rain') THREE_ENV.setRain(on);
  if (type === 'fog')  THREE_ENV.setFog(on);
  UI.toast((on?'🌧 ':'☀ ')+(type.charAt(0).toUpperCase()+type.slice(1))+' '+(on?'on':'off'));
}

function toggleDayNight(el) {
  const track = document.getElementById('daynight-track');
  if (!track) return;
  const night = !track.classList.contains('on');
  track.classList.toggle('on', night);
  THREE_ENV.setNight(night);
  UI.toast(night?'🌙 Night mode':'☀ Day mode');
}

function setPID(param, val) {
  val = parseFloat(val);
  FC.gains[param] = val;
  FC.applyGains();
  const labels = {rp:'rp-val',ri:'ri-val',rd:'rd-val',yp:'yp-val',ap:'ap-val'};
  const el = document.getElementById(labels[param]);
  if (el) el.textContent = val.toFixed(4).replace(/0+$/,'').replace(/\.$/,'');
}

function setThrottleSlider(val) {
  INPUT._thrRaw = parseFloat(val)/100;
  const tv = document.getElementById('thr-val');
  if (tv) tv.textContent = Math.round(val)+'%';
  const slEl = document.getElementById('throttle-slider');
  if(slEl) slEl._dragging=true;
  clearTimeout(INPUT._sliderTimer);
  INPUT._sliderTimer = setTimeout(()=>{if(slEl)slEl._dragging=false;},300);
}

function setSensitivity(val) {
  INPUT.sensitivity = parseFloat(val)/100;
  const el = document.getElementById('sens-val');
  if (el) el.textContent = val+'%';
}

function toggleArm() {
  State.armed = !State.armed;
  if (!State.armed) {
      if (typeof BLACKBOX !== 'undefined') BLACKBOX.stop();
      FC.altTarget = null; FC.posTarget = null;
      FC.resetPIDs();
    } else {
      if (typeof BLACKBOX !== 'undefined') BLACKBOX.start();
      PHYS.saveHome();
    PHYS._gyroBias={x:0,y:0,z:0}; // [FIX-H] zero gyro bias on arm
    FC.resetPIDs();
  }
  updateArmUI();
  UI.toast(State.armed ? '✅ Armed' : '🔴 Disarmed');
  UI.log(State.armed?'Armed':'Disarmed', State.armed?'ok':'warn');
}

function takeoff() {
  if (!State.armed) {
      State.armed = true;
      if (typeof BLACKBOX !== 'undefined') BLACKBOX.start();
      PHYS.saveHome();
    PHYS._gyroBias={x:0,y:0,z:0}; // [FIX-H] zero gyro bias on takeoff
    FC.resetPIDs();
    FC.setMode('althold');
    FC.altTarget = 3.0;
    State.flightMode = 'althold';
    setFlightModeUI('althold');
    animateThrottle(0.5, 300);
    updateArmUI();
    UI.toast('🚁 Auto-takeoff to 3m');
    UI.log('Auto-takeoff','ok');
  } else {
    FC.altTarget = Math.max(FC.altTarget||3, PHYS.pos.y - PHYS.groundY)+3;
    FC.setMode('althold');
    UI.toast('↑ Climbing');
  }
}

// Smoothly animate _thrRaw to a target value over ~400ms
function animateThrottle(target, duration=400) {
  const start = INPUT._thrRaw;
  const startTime = performance.now();
  const slEl = document.getElementById('throttle-slider');
  const tv   = document.getElementById('thr-val');
  if (slEl) slEl._dragging = true; // prevent INPUT.update from fighting us
  function step(now) {
    const t = Math.min(1, (now - startTime) / duration);
    // Ease-out cubic
    const ease = 1 - Math.pow(1 - t, 3);
    const val = start + (target - start) * ease;
    INPUT._thrRaw = val;
    if (slEl) slEl.value = Math.round(val * 100);
    if (tv)   tv.textContent = Math.round(val * 100) + '%';
    if (t < 1) requestAnimationFrame(step);
    else {
      INPUT._thrRaw = target;
      if (slEl) slEl._dragging = false;
    }
  }
  requestAnimationFrame(step);
}

function doHover() {
  if (!State.armed) return;
  FC.setMode('althold');
  FC.altTarget = PHYS.pos.y - PHYS.groundY;
  FC.posTarget = { x:PHYS.pos.x, z:PHYS.pos.z };
  State.flightMode = 'althold';
  setFlightModeUI('althold');
  // Snap throttle to center so PID deadzone activates immediately
  animateThrottle(0.5, 350);
  UI.toast('⏸ Hovering — throttle locked to altitude hold');
}

function returnHome() {
  if (!State.armed) { takeoff(); }
  FC.setMode('rth');
  State.flightMode = 'rth';
  setFlightModeUI('rth');
  UI.toast('🏠 Return To Home');
  UI.log('RTH initiated','ok');
}

function emergStop() {
  State.armed = false;
  for(let i=0;i<4;i++) PHYS.motorCmd[i]=0;
  FC.altTarget=null; FC.posTarget=null;
  INPUT._thrRaw=0;
  FC.resetPIDs();
  updateArmUI();
  UI.toast('⛔ EMERGENCY STOP');
  UI.log('Emergency stop!','err');
}

function resetDrone() {
  // [FIX] Use getSafeSpawnPoint — avoids resetting drone into a mountainside
  const _spawnPt = THREE_ENV.getSafeSpawnPoint();
  const groundY = _spawnPt.y;
  PHYS.groundY = groundY;
  const _droneHalfR = 0.074 * (PHYS.droneVisual.bodyScale || 1.0) * 5.0;
  PHYS.reset({x: _spawnPt.x, y: groundY + _droneHalfR, z: _spawnPt.z});
  State.armed=false; State.flightMode='stabilized';
  State.motorDamage=[0,0,0,0];
  FC.altTarget=null; FC.posTarget=null; FC.rthPhase=0;
  INPUT._thrRaw=0;
  setFlightModeUI('stabilized');
  updateArmUI();
  const co = document.getElementById('crash-overlay');
  if (co) co.classList.remove('show');
  UI.toast('🔄 Drone reset');
  UI.log('Drone reset','ok');
}
function resetSim() { resetDrone(); }

function addWaypoint() { MISSION.add(PHYS.pos); UI.toast('📍 Waypoint added'); }
function startMission() { MISSION.start(); }
function pauseMission() { MISSION.pause(); }
function clearMission()  { MISSION.clear(); }

/* ── Sim Pause / Speed ── */
function toggleSimPause() {
  if (SIM._paused) SIM.resume(); else SIM.pause();
}
function setSimSpeed(v) {
  SIM.setSpeed(v);
}

/* ── Recording / Export ── */


function updateExportStats() {
  const stats = BLACKBOX.getStats();
  const panel = document.getElementById('export-stats');
  if (!stats || !panel) return;
  panel.style.display = 'grid';
  const s = id => document.getElementById(id);
  s('es-dur') && (s('es-dur').textContent = stats.duration + 's');
  s('es-samp') && (s('es-samp').textContent = stats.samples);
  s('es-maxalt') && (s('es-maxalt').textContent = stats.maxAlt + 'm');
  s('es-maxvel') && (s('es-maxvel').textContent = stats.maxVel + ' m/s');
}

function exportMAVLink() {
  const log = BLACKBOX.getLog();
  if (!log.length) { UI.toast('⚠ No data — start recording first'); return; }
  const ok = MAVLINK.downloadTlog();
  if (ok) UI.toast('📡 MAVLink .tlog exported (' + log.length + ' frames)');
  else UI.toast('⚠ Export failed');
}

function exportJSON() {
  const log = BLACKBOX.getLog();
  if (!log.length) { UI.toast('⚠ No data — start recording first'); return; }

/**
 * Export current PID gains + telemetry frames as structured JSON.
 * Call this from the PID submenu. Arm and fly first to generate data.
 */
function exportPIDJSON() {
  const gains = (typeof FC !== 'undefined') ? FC.gains : {};
  const pidAxes = (typeof PID_TELEM !== 'undefined') ? PID_TELEM.axes : {};
  const log = (typeof BLACKBOX !== 'undefined') ? BLACKBOX.getLog() : [];

  const payload = {
    meta: {
      exported: new Date().toISOString(),
      version: '2.1',
      drone: (typeof PHYS !== 'undefined' && PHYS.droneProfile) ? PHYS.droneProfile : 'unknown',
      frames: log.length
    },
    pid_gains: {
      roll_pitch_p: gains.rp || 0,
      roll_pitch_i: gains.ri || 0,
      roll_pitch_d: gains.rd || 0,
      yaw_p:        gains.yp || 0,
      alt_p:        gains.ap || 0,
      angle_p:      gains.angleP || 0
    },
    pid_live_snapshot: {
      roll:     { kp: pidAxes.roll?.kp||0, ki: pidAxes.roll?.ki||0, kd: pidAxes.roll?.kd||0, error: pidAxes.roll?.error||0 },
      pitch:    { kp: pidAxes.pitch?.kp||0, ki: pidAxes.pitch?.ki||0, kd: pidAxes.pitch?.kd||0, error: pidAxes.pitch?.error||0 },
      yaw:      { kp: pidAxes.yaw?.kp||0, ki: pidAxes.yaw?.ki||0, kd: pidAxes.yaw?.kd||0, error: pidAxes.yaw?.error||0 },
      throttle: { kp: pidAxes.throttle?.kp||0, ki: pidAxes.throttle?.ki||0, kd: pidAxes.throttle?.kd||0, error: pidAxes.throttle?.error||0 }
    },
    telemetry_frames: log
  };

  const json = JSON.stringify(payload, null, 2);
  const blob = new Blob([json], { type: 'application/json' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'spaceborn-pid-' + Date.now() + '.json';
  a.click();
  URL.revokeObjectURL(a.href);
  if (typeof UI !== 'undefined') UI.toast('📊 PID data exported (' + log.length + ' telemetry frames)');
}

  const ok = MAVLINK.downloadJSON();
  if (ok) UI.toast('{ } JSON telemetry exported');
}

/* ── Telemetry Graph Legend Toggle ── */
function toggleTGraph(ch) {
  TELEM_GRAPH.toggle(ch);
  const el = document.getElementById('tgl-' + ch);
  if (el) el.classList.toggle('on');
}

function showSection(sec) {
  document.querySelectorAll('.npill').forEach(b => b.classList.toggle('on', b.id==='nav-'+sec));
  const dbg = document.getElementById('debug-section');
  if (dbg) dbg.style.display = (sec==='debug') ? 'block' : 'none';
}

/* ══════════════════════════════════════════════════════════════════════
   DRONE PROFILE CUSTOMIZATION
══════════════════════════════════════════════════════════════════════ */

/* ── Rebuild the profile select dropdown ── */
function rebuildProfileSelect(activeKey) {
  const sel = document.getElementById('drone-profile-select');
  if (!sel) return;
  sel.innerHTML = '';
  for (const [key, p] of Object.entries(DRONE_PROFILES)) {
    const opt = document.createElement('option');
    opt.value = key;
    opt.textContent = p.label;
    if (key === activeKey) opt.selected = true;
    sel.appendChild(opt);
  }
}

/* ── Toggle inline customize panel ── */
function toggleProfileCustomize() {
  const panel = document.getElementById('profile-customize-panel');
  const btn   = document.getElementById('customize-toggle-btn');
  if (!panel) return;
  const open = panel.classList.toggle('open');
  if (btn) btn.textContent = open ? '✕ Close' : '✏️ Customize';
  if (open) populateCustomizeFields(PHYS.droneProfile);
}

/* ── Fill customize fields from named profile ── */
function populateCustomizeFields(profileName) {
  const p = DRONE_PROFILES[profileName] || PHYS;
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
  set('cust-mass',    p.mass);
  set('cust-arm',     p.armLen);
  set('cust-maxrpm',  p.maxRPM);
  set('cust-idlerpm', p.idleRPM);
  set('cust-kt',      p.kT);
  set('cust-kq',      p.kQ);
  set('cust-cells',   p.cells);
  set('cust-batt',    p.battTotalAh);
  set('cust-tilt',    p.maxTiltDeg || Math.round((p.maxTiltRad||0.96)*180/Math.PI));
  set('cust-drag',    p.dragArea);
  // color
  const colorEl = document.getElementById('cust-color');
  if (colorEl) {
    const c = p.color !== undefined ? p.color : (PHYS.droneVisual && PHYS.droneVisual.color);
    if (c !== undefined) {
      const hex = '#' + c.toString(16).padStart(6, '0');
      colorEl.value = hex;
    }
  }
}

/* ── Live-apply customize fields to running sim ── */
function applyCustomize() {
  const get = id => parseFloat(document.getElementById(id)?.value) || 0;
  const mass    = get('cust-mass')    || PHYS.mass;
  const armLen  = get('cust-arm')     || PHYS.armLen;
  const maxRPM  = get('cust-maxrpm')  || PHYS.maxRPM;
  const idleRPM = get('cust-idlerpm') || PHYS.idleRPM;
  const kT      = get('cust-kt')      || PHYS.kT;
  const kQ      = get('cust-kq')      || PHYS.kQ;
  const cells   = Math.round(get('cust-cells')) || PHYS.cells;
  const battAh  = get('cust-batt')    || PHYS.battTotalAh;
  const tiltDeg = get('cust-tilt')    || 55;
  const drag    = get('cust-drag')    || PHYS.dragArea;

  Object.assign(PHYS, { mass, armLen, maxRPM, idleRPM, kT, kQ, cells, battTotalAh: battAh,
    maxTiltRad: tiltDeg * Math.PI / 180, dragArea: drag });
  PHYS._recomputeHover();
  if (typeof FC !== 'undefined') FC.autoTuneFromPhysics();

  // Update info panel
  const s = id => document.getElementById(id);
  if (s('drone-mass-val'))   s('drone-mass-val').textContent   = mass + ' kg';
  if (s('drone-batt-val'))   s('drone-batt-val').textContent   = cells + 'S ' + battAh + ' Ah';
  if (s('drone-maxrpm-val')) s('drone-maxrpm-val').textContent = maxRPM.toLocaleString();
  const htv = s('hover-thr-val');
  if (htv) htv.textContent = Math.round(PHYS.hoverThrottle * 100);
}

/* ── Apply color tweak ── */
function applyCustomizeColor(hexStr) {
  const c = parseInt(hexStr.replace('#', ''), 16);
  PHYS.droneVisual = { ...PHYS.droneVisual, color: c };
  if (typeof THREE_ENV !== 'undefined') THREE_ENV.rebuildDrone(c);
}

/* ── Save the customized values as a brand new profile ── */
function saveCustomizeAsProfile() {
  const name = prompt('Enter a name for this custom profile:', 'My Custom Drone');
  if (!name) return;
  const get = id => parseFloat(document.getElementById(id)?.value) || 0;
  const hexStr = document.getElementById('cust-color')?.value || '#1e88e5';
  const color = parseInt(hexStr.replace('#', ''), 16);
  const tiltDeg = get('cust-tilt') || 55;
  const key = 'custom_' + Date.now();
  DRONE_PROFILES[key] = {
    label: name,
    mass:       get('cust-mass'),
    Ixx: get('cust-mass')*0.005, Iyy: get('cust-mass')*0.009, Izz: get('cust-mass')*0.005,
    armLen:     get('cust-arm'),
    kT:         get('cust-kt'),
    kQ:         get('cust-kq'),
    maxRPM:     get('cust-maxrpm'),
    idleRPM:    get('cust-idlerpm'),
    motorTau:   PHYS.motorTau,
    escDelay:   PHYS.escDelay,
    dragArea:   get('cust-drag'),
    dragCd:     PHYS.dragCd,
    angDrag:    PHYS.angDrag,
    cells:      Math.round(get('cust-cells')),
    battTotalAh:get('cust-batt'),
    color,
    bodyScale:  PHYS.droneVisual?.bodyScale || 1,
    rotorRadius:PHYS.droneVisual?.rotorRadius || 0.09,
    maxTiltDeg: tiltDeg,
    maxRate:    { ...PHYS.maxRate },
    propInertia:PHYS.propInertia,
    Cqlift:     PHYS.Cqlift,
  };
  rebuildProfileSelect(key);
  setDroneProfile(key);
  UI.toast('💾 Profile "' + name + '" saved!');
  UI.log('Custom profile saved: ' + name, 'ok');
}

/* ══════════════════════════════════════════════════════════════════════
   CUSTOM PROFILE MODAL
══════════════════════════════════════════════════════════════════════ */
function openCustomProfileModal() {
  const modal = document.getElementById('custom-profile-modal');
  if (modal) modal.classList.add('open');
  buildModalPresetCards();
  // Default load current profile values
  loadPresetIntoModal(PHYS.droneProfile);
}

function closeCustomProfileModal() {
  const modal = document.getElementById('custom-profile-modal');
  if (modal) modal.classList.remove('open');
}

function buildModalPresetCards() {
  const container = document.getElementById('modal-preset-cards');
  if (!container) return;
  container.innerHTML = '';
  const colorMap = {
    racing5:  '#1e88e5', cinequad: '#ffc107',
    micro2:   '#43a047', explorer6:'#8e24aa',
  };
  for (const [key, p] of Object.entries(DRONE_PROFILES)) {
    const card = document.createElement('div');
    card.className = 'profile-card';
    const dotColor = colorMap[key] || ('#' + (p.color||0x607d8b).toString(16).padStart(6,'0'));
    card.innerHTML = `<span class="pc-dot" style="background:${dotColor}"></span>${p.label}`;
    card.onclick = () => {
      document.querySelectorAll('.profile-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      loadPresetIntoModal(key);
    };
    container.appendChild(card);
  }
}

function loadPresetIntoModal(name) {
  const p = DRONE_PROFILES[name];
  if (!p) return;
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
  set('cp-name',      p.label);
  set('cp-mass',      p.mass);
  set('cp-arm',       p.armLen);
  set('cp-bodyscale', p.bodyScale || 1);
  set('cp-rotor',     p.rotorRadius || 0.09);
  set('cp-tilt',      p.maxTiltDeg || 55);
  set('cp-drag',      p.dragArea);
  set('cp-cd',        p.dragCd);
  set('cp-angdrag',   p.angDrag);
  set('cp-maxrpm',    p.maxRPM);
  set('cp-idlerpm',   p.idleRPM);
  set('cp-kt',        p.kT);
  set('cp-kq',        p.kQ);
  set('cp-tau',       p.motorTau);
  set('cp-esc',       p.escDelay);
  set('cp-propI',     p.propInertia || 0.000025);
  set('cp-cq',        p.Cqlift || 0.015);
  set('cp-cells',     p.cells);
  set('cp-batt',      p.battTotalAh);
  set('cp-ratepitch', p.maxRate?.pitch || 10);
  set('cp-rateroll',  p.maxRate?.roll  || 10);
  set('cp-rateyaw',   p.maxRate?.yaw   || 4.5);
  const colorEl = document.getElementById('cp-color');
  if (colorEl && p.color !== undefined) colorEl.value = '#' + p.color.toString(16).padStart(6,'0');
}

function createCustomProfile() {
  const get  = id => parseFloat(document.getElementById(id)?.value) || 0;
  const geti = id => Math.round(get(id));
  const gets = id => document.getElementById(id)?.value?.trim() || '';
  const name = gets('cp-name') || ('Custom Drone ' + Object.keys(DRONE_PROFILES).length);
  const hexStr = document.getElementById('cp-color')?.value || '#1e88e5';
  const color = parseInt(hexStr.replace('#',''), 16);
  const key = 'custom_' + Date.now();
  const mass = get('cp-mass') || 1.24;
  DRONE_PROFILES[key] = {
    label:      name,
    mass,
    Ixx: mass * 0.005, Iyy: mass * 0.009, Izz: mass * 0.005,
    armLen:     get('cp-arm'),
    kT:         get('cp-kt'),
    kQ:         get('cp-kq'),
    maxRPM:     get('cp-maxrpm'),
    idleRPM:    get('cp-idlerpm'),
    motorTau:   get('cp-tau'),
    escDelay:   get('cp-esc'),
    dragArea:   get('cp-drag'),
    dragCd:     get('cp-cd'),
    angDrag:    get('cp-angdrag'),
    cells:      geti('cp-cells'),
    battTotalAh:get('cp-batt'),
    color,
    bodyScale:  get('cp-bodyscale'),
    rotorRadius:get('cp-rotor'),
    maxTiltDeg: get('cp-tilt'),
    maxRate: {
      pitch: get('cp-ratepitch'),
      roll:  get('cp-rateroll'),
      yaw:   get('cp-rateyaw'),
    },
    propInertia: get('cp-propI'),
    Cqlift:      get('cp-cq'),
  };
  rebuildProfileSelect(key);
  setDroneProfile(key);
  closeCustomProfileModal();
  UI.toast('🚁 Custom profile "' + name + '" created!');
  UI.log('New custom profile: ' + name, 'ok');
}

/* ── Close modal on backdrop click ── */
document.addEventListener('click', e => {
  const modal = document.getElementById('custom-profile-modal');
  if (modal && e.target === modal) closeCustomProfileModal();
});

/* ══════════════════════════════════════════════════════════════════════
   STARTUP SEQUENCE
══════════════════════════════════════════════════════════════════════ */
const STARTUP_STEPS = [
  {msg:'Initializing physics engine…',    pct:8},
  {msg:'Loading Three.js renderer…',      pct:20},
  {msg:'Building terrain & environment…', pct:35},
  {msg:'Compiling flight controller…',    pct:50},
  {msg:'Calibrating PID controllers…',    pct:62},
  {msg:'Initializing sensor systems…',    pct:74},
  {msg:'Loading mission planner…',        pct:84},
  {msg:'Warming up motors…',              pct:92},
  {msg:'Systems nominal — launching…',    pct:100},
];

function runStartup() {
  const bar = document.getElementById('sbar');
  const stat= document.getElementById('sstat');
  let i = 0;
  function step() {
    if (i >= STARTUP_STEPS.length) {
      setTimeout(() => {
        document.getElementById('startup').classList.add('hide');
        document.getElementById('app').style.display = '';
        SIM.start();
      }, 400);
      return;
    }
    const s = STARTUP_STEPS[i++];
    if (stat) stat.textContent = s.msg;
    if (bar)  bar.style.width  = s.pct+'%';
    setTimeout(step, 200 + Math.random()*150);
  }
  step();
}

/* ══════════════════════════════════════════════════════════════════════
   DOM INIT
══════════════════════════════════════════════════════════════════════ */
/* ══════════════════════════════════════════════════════════════════════
   VIRTUAL JOYSTICK + STICK VISUALIZER
══════════════════════════════════════════════════════════════════════ */

// Virtual joystick interaction
(function(){
  function initVJ(padId, knobId, stickSide) {
    const pad = document.getElementById(padId);
    const knob = document.getElementById(knobId);
    if (!pad || !knob) return;

    const R = 88 / 2;   // pad radius px
    const MAX = R - 14; // max knob travel
    let active = false, startX = 0, startY = 0;

    function getCenter() {
      const r = pad.getBoundingClientRect();
      return { x: r.left + r.width/2, y: r.top + r.height/2 };
    }

    function setKnob(dx, dy) {
      const dist = Math.hypot(dx, dy);
      if (dist > MAX) { dx = dx/dist*MAX; dy = dy/dist*MAX; }
      knob.style.transform = `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px))`;
      // Normalise to -1..1
      const nx =  dx / MAX;
      const ny = -dy / MAX; // y-up positive
      if (stickSide === 'left') {
        INPUT._vjLeft.x = nx;
        INPUT._vjLeft.y = -ny; // throttle: up = +1 in screen, but we want -ny for rate
      } else {
        INPUT._vjRight.x = nx;
        INPUT._vjRight.y = -ny;
      }
      INPUT._vjActive = true;
    }

    function resetKnob() {
      knob.style.transform = 'translate(-50%, -50%)';
      if (stickSide === 'left') { INPUT._vjLeft.x = 0; INPUT._vjLeft.y = 0; }
      else { INPUT._vjRight.x = 0; INPUT._vjRight.y = 0; }
      // Only deactivate if both sticks at rest
      if (INPUT._vjLeft.x === 0 && INPUT._vjLeft.y === 0 &&
          INPUT._vjRight.x === 0 && INPUT._vjRight.y === 0) {
        INPUT._vjActive = false;
      }
    }

    // Mouse
    pad.addEventListener('mousedown', e => {
      e.preventDefault(); active = true; pad.classList.add('active');
      const c = getCenter();
      setKnob(e.clientX - c.x, e.clientY - c.y);
    });
    window.addEventListener('mousemove', e => {
      if (!active) return;
      const c = getCenter();
      setKnob(e.clientX - c.x, e.clientY - c.y);
    });
    window.addEventListener('mouseup', () => {
      if (!active) return;
      active = false; pad.classList.remove('active'); resetKnob();
    });

    // Touch
    pad.addEventListener('touchstart', e => {
      e.preventDefault(); active = true; pad.classList.add('active');
      const t = e.touches[0]; const c = getCenter();
      setKnob(t.clientX - c.x, t.clientY - c.y);
    }, { passive: false });
    pad.addEventListener('touchmove', e => {
      e.preventDefault(); if (!active) return;
      const t = e.touches[0]; const c = getCenter();
      setKnob(t.clientX - c.x, t.clientY - c.y);
    }, { passive: false });
    pad.addEventListener('touchend', () => {
      active = false; pad.classList.remove('active'); resetKnob();
    });
  }

  // Init both sticks after DOM ready
  document.addEventListener('DOMContentLoaded', () => {
    initVJ('vj-left',  'vj-left-knob',  'left');
    initVJ('vj-right', 'vj-right-knob', 'right');
  });
})();

// Stick visualizer canvases
function _drawStickCanvas(canvasId, x, y, label, accentColor) {
  const c = document.getElementById(canvasId); if (!c) return;
  const ctx = c.getContext('2d', {willReadFrequently: true});
  const W = c.width, H = c.height;
  ctx.clearRect(0, 0, W, H);

  // Background
  ctx.fillStyle = 'rgba(28,33,48,0.97)';
  ctx.beginPath(); ctx.roundRect(0,0,W,H,8); ctx.fill();

  // Grid lines
  ctx.strokeStyle = 'rgba(96,125,139,0.15)'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(W/2,0); ctx.lineTo(W/2,H); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(0,H/2); ctx.lineTo(W,H/2); ctx.stroke();

  // Outer ring
  ctx.strokeStyle = 'rgba(96,125,139,0.2)'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.arc(W/2, H/2, W/2-3, 0, Math.PI*2); ctx.stroke();

  // Trail (last position faded)
  if (c._lastX !== undefined) {
    const tx = W/2 + c._lastX * (W/2-8);
    const ty = H/2 - c._lastY * (H/2-8);
    ctx.fillStyle = accentColor + '30';
    ctx.beginPath(); ctx.arc(tx, ty, 5, 0, Math.PI*2); ctx.fill();
  }
  c._lastX = x; c._lastY = y;

  // Dot
  const px = W/2 + x * (W/2 - 8);
  const py = H/2 - y * (H/2 - 8);
  // Glow
  ctx.shadowColor = accentColor; ctx.shadowBlur = 8;
  ctx.fillStyle = accentColor;
  ctx.beginPath(); ctx.arc(px, py, 6, 0, Math.PI*2); ctx.fill();
  ctx.shadowBlur = 0;
  // White centre
  ctx.fillStyle = 'white';
  ctx.beginPath(); ctx.arc(px, py, 2.5, 0, Math.PI*2); ctx.fill();
}

function _updateStickViz() {
  const inp = INPUT.get();
  // Left stick: X=yaw, Y=throttle (0..1 → center at 0.5)
  const ly = inp.throttle * 2 - 1; // 0..1 → -1..1
  _drawStickCanvas('stick-viz-l', inp.yaw, ly, 'LEFT (THR/YAW)', '#EE9346');
  // Right stick: X=roll, Y=pitch
  _drawStickCanvas('stick-viz-r', inp.roll, inp.pitch, 'RIGHT (PITCH/ROLL)', '#10256D');

  // Meters
  const setM = (id, pct, left) => {
    const el = document.getElementById(id); if (!el) return;
    if (left !== undefined) {
      // bidirectional: centre at 50%, width = |val|*50%, left = 50% or (50%-width)
      const w = Math.abs(pct) * 0.005;
      const start = pct >= 0 ? 0.5 : 0.5 - w;
      el.style.transform = 'translateX('+(start*100)+'%) scaleX('+w+')';
    } else {
      el.style.transform = 'scaleX('+(pct/100)+')';
    }
  };
  setM('sm-thr',   inp.throttle * 100);
  setM('sm-yaw',   inp.yaw,  true);
  setM('sm-pitch', inp.pitch, true);
  setM('sm-roll',  inp.roll,  true);
}

// Position readouts (t-px, t-py, t-pz) — update in SIM loop via existing _updateUI

/* ══════════════════════════════════════════════════════════════════════
   DOM INIT
══════════════════════════════════════════════════════════════════════ */
window.addEventListener('DOMContentLoaded', () => {
  // Toast element
  if (!document.getElementById('toast')) {
    const t = document.createElement('div');
    t.id='toast';
    t.style.cssText='position:fixed;bottom:18px;left:50%;transform:translateX(-50%);background:var(--p);color:#fff;padding:7px 20px;border-radius:20px;font-family:var(--fh);font-size:12px;font-weight:600;box-shadow:0 4px 14px rgba(0,0,0,.5);opacity:0;transition:opacity .3s;pointer-events:none;z-index:500;';
    document.body.appendChild(t);
  }

  // Injected CSS for dynamic states
  const style = document.createElement('style');
  style.textContent = `
    .ntoggle-track.on{background:var(--p)!important;}
    .ntoggle-track.on .ntoggle-thumb{transform:translateX(18px)!important;}
    .sdot.ok{background:#4CAF50!important;}
    .sdot.warn{background:var(--s)!important;}
    .sdot.err{background:#f44336!important;}
    .motor-bar.orange{background:var(--s)!important;}
    .motor-bar.red{background:#f44336!important;}
    .vp-warn.show{opacity:1!important;}
    .bgauge-fill.red{background:#f44336!important;}
    .bgauge-fill.orange{background:var(--s)!important;}
    #arm-status{font-size:10px;letter-spacing:.8px;text-transform:uppercase;padding:3px 9px;border-radius:10px;font-weight:700;background:#F44336;color:white;margin-left:4px;}
    #arm-status.armed{background:#4CAF50;}
  `;
  document.head.appendChild(style);

  // Init physics
  PHYS.applyProfile('racing5');
  INPUT.init();
  PHYS.groundY = 0;
  const _initDroneHalf = 0.074 * (PHYS.droneVisual.bodyScale || 1.0) * 5.0;
  PHYS.reset({x:0, y:_initDroneHalf, z:0});
  // [FIX-Bug-26c] Shared sim clock already initialised in sim-engine.js as _simClock = {t:0}
  rebuildProfileSelect('racing5');
  updateDroneProfileUI('racing5');
  FC.autoTuneFromPhysics();

  // Init Three.js
  THREE_ENV.init('threeCanvas');
  window.requestAnimationFrame(() => {
    const vp = document.getElementById('threeCanvas')?.parentElement;
    if (vp) THREE_ENV._resize(vp);
  });

  // Set initial environment (rebuilds scene)
  ENV.set('field');

  // Hover throttle display
  const htv = document.getElementById('hover-thr-val');
  if (htv) htv.textContent = Math.round(PHYS.hoverThrottle*100);

  // Init telemetry graph
  TELEM_GRAPH.init('telemGraph');

  // Global keydown: P = pause/resume, [ ] = sim speed
  // (Space/T/R/H/G/X/F/C/M/1-5 are now handled inside INPUT.init())
  document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
    if (e.repeat) return;
    if (e.code === 'KeyP') toggleSimPause();
    if (e.code === 'BracketRight') {
      const speeds = [0.25, 0.5, 1, 2, 3, 4];
      const idx = speeds.indexOf(SIM._speed);
      const next = speeds[Math.min(idx + 1, speeds.length - 1)];
      SIM.setSpeed(next);
      const sel = document.getElementById('sim-speed');
      if (sel) sel.value = next;
    }
    if (e.code === 'BracketLeft') {
      const speeds = [0.25, 0.5, 1, 2, 3, 4];
      const idx = speeds.indexOf(SIM._speed);
      const prev = speeds[Math.max(idx - 1, 0)];
      SIM.setSpeed(prev);
      const sel = document.getElementById('sim-speed');
      if (sel) sel.value = prev;
    }
  });

  showSection('flight');
  runStartup();
});

/* [TIER-MAX] GLTF/GLB Custom Drone Upload Handler */
function handleGLTFUpload(input) {
  const file = input.files[0];
  if (!file) return;
  const statusEl = document.getElementById('gltf-upload-status');
  if (statusEl) statusEl.textContent = `Loading: ${file.name}…`;
  // Integration point: pass file URL to THREE_ENV drone mesh loader
  const url = URL.createObjectURL(file);
  if (typeof THREE_ENV !== 'undefined' && typeof THREE_ENV.loadCustomModel === 'function') {
    THREE_ENV.loadCustomModel(url, file.name);
  } else {
    if (statusEl) statusEl.textContent = `✅ Model queued: ${file.name} (apply on next flight)`;
    if (typeof UI !== 'undefined') UI.toast(`🚁 Custom model accepted: ${file.name}`);
  }
}

/* [TIER-MAX] Motor Failure scenario (maps to State.motorDamage) */
function activateMotorFailure(motorIndex) {
  if (typeof State === 'undefined') { console.warn('State not ready'); return; }
  if (!State.motorDamage) State.motorDamage = [0,0,0,0];
  State.motorDamage[motorIndex] = 1.0;
  if (typeof UI !== 'undefined') UI.toast(`⚠ Motor M${motorIndex+1} FAILURE activated`);
}
function clearMotorFailures() {
  if (typeof State !== 'undefined') State.motorDamage = [0,0,0,0];

  // 1. Recompute terrain-aware ground height at current drone XZ position
  //    so recoverFromCrash snaps to the correct ground (not 0)
  if (typeof THREE_ENV !== 'undefined') {
    PHYS.groundY = THREE_ENV.getTerrainHeight(PHYS.pos.x, PHYS.pos.z);
  }

  // 2. Clear crash physics — resets crashed flag, zeroes vel/angVel,
  //    levels attitude, snaps to ground, flushes PID integrators.
  if (typeof PHYS !== 'undefined' && typeof PHYS.recoverFromCrash === 'function') {
    PHYS.recoverFromCrash();
  }

  // 3. Arm
  State.armed = true;
  PHYS.saveHome();
  PHYS._gyroBias = {x:0, y:0, z:0};

  // 3. Set throttle to EXACTLY 0.5 immediately — NOT via animateThrottle().
  //    animateThrottle ramps from 0→0.5, during which the althold FC sees
  //    throttle < 0.5 and hits the manual branch (thrCmd = 0), so motors
  //    never spool up.  Setting 0.5 instantly puts the stick in the deadband
  //    so altPID engages from the very first frame.
  INPUT._thrRaw = 0.5;
  const slEl = document.getElementById('throttle-slider');
  const tv   = document.getElementById('thr-val');
  if (slEl) slEl.value = 50;
  if (tv)   tv.textContent = '50%';

  // 4. FC in althold targeting 3m
  FC.resetPIDs();
  FC.setMode('althold');
  FC.altTarget = 3.0;
  State.flightMode = 'althold';
  setFlightModeUI('althold');
  updateArmUI();

  // 5. Dismiss the crash overlay — it was blocking the viewport and intercepting clicks
  const co = document.getElementById('crash-overlay');
  if (co) co.classList.remove('show');

  if (typeof UI !== 'undefined') {
    UI.toast('✅ Motors restored — taking off');
    UI.log('Motors restored, auto-takeoff', 'ok');
  }
}

/* [TIER-MAX] GPS Denied scenario */
function activateGPSDenied(enable) {
  if (typeof State !== 'undefined') {
    State.gpsDenied = enable;
    if (typeof UI !== 'undefined') UI.toast(enable ? '🚫 GPS DENIED — VSLAM mode' : '✅ GPS signal restored');
  }
}
</script>


<script>
/* ══════════════════════════════════════════════════════════════════
   PLAN ENFORCEMENT ENGINE  — runs after DOMContentLoaded
   Reads PLAN constant above; applies all tier restrictions.
   Flight mechanics in sim-engine.js are NEVER modified.
══════════════════════════════════════════════════════════════════ */

function _hideEl(el) { if (el) el.style.display = 'none'; }
function _lockEl(el, tip) {
  if (!el) return;
  el.style.pointerEvents = 'none';
  el.style.opacity = '0.32';
  el.title = tip || 'Not available on your plan';
}

window.addEventListener('DOMContentLoaded', () => {

  /* ─ 1. TIER BADGE ──────────────────────────────────────────── */
  // [PLAN-BADGE] Inject tier badge beside SIM tag in topbar
  const simTag = document.querySelector('.brand-tag');
  if (simTag) {
    const badge = document.createElement('span');
    badge.style.cssText = `font-size:9px;letter-spacing:1.5px;text-transform:uppercase;
      color:white;background:${PLAN.tierColor};padding:2px 8px;border-radius:20px;
      font-weight:700;font-family:var(--fh);margin-left:6px;`;
    badge.textContent = PLAN.tierLabel;
    simTag.parentNode.insertBefore(badge, simTag.nextSibling);
  }

  /* ─ 2. SESSION TIMER ───────────────────────────────────────── */
  // [PLAN-SESSION] Enforce time-limited access (BASIS=1h, PRO=24h, MAX=∞)
  if (isFinite(PLAN.sessionMinutes)) {
    const SESSION_MS = PLAN.sessionMinutes * 60000;
    const t0 = Date.now();

    // Countdown badge
    const cdBadge = document.createElement('div');
    cdBadge.style.cssText = `display:flex;align-items:center;gap:5px;padding:4px 11px;
      border-radius:20px;background:var(--n);box-shadow:inset 4px 4px 8px #0d1018,inset -4px -4px 8px #232a3a;
      font-size:11px;font-weight:600;font-family:var(--fh);color:var(--txt2);`;
    cdBadge.innerHTML = `<span style="color:var(--s)">⏱</span><span id="ses-left">--:--</span>`;
    const tb = document.getElementById('topbar');
    if (tb) { const tsp = tb.querySelector('.tsp'); if (tsp) tb.insertBefore(cdBadge, tsp.nextSibling); }

    // Expired overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `position:fixed;inset:0;z-index:9999;background:rgba(10,12,20,0.97);
      display:none;align-items:center;justify-content:center;flex-direction:column;gap:18px;`;
    const durationLabel = PLAN.sessionMinutes >= 60
      ? Math.round(PLAN.sessionMinutes/60) + 'h'
      : PLAN.sessionMinutes + 'min';
    overlay.innerHTML = `
      <div style="font-family:var(--fh);font-size:32px;font-weight:700;color:var(--p)">⏱ Session Ended</div>
      <div style="font-size:14px;color:var(--txt2);text-align:center;max-width:380px;line-height:1.7">
        Your <strong>${PLAN.tierLabel}</strong> session (${durationLabel}) has expired.<br>
        Upgrade to <strong style="color:var(--s)">MAX</strong> for unlimited access.
      </div>
      <button onclick="location.reload()" style="background:var(--p);color:#fff;border:none;
        padding:10px 28px;border-radius:20px;font-family:var(--fh);font-size:13px;font-weight:700;cursor:pointer;">
        🔄 Start New Session
      </button>`;
    document.body.appendChild(overlay);

    function tick() {
      const rem = Math.max(0, SESSION_MS - (Date.now() - t0));
      const el = document.getElementById('ses-left');
      if (el) {
        const m = String(Math.floor(rem/60000)).padStart(2,'0');
        const s = String(Math.floor((rem%60000)/1000)).padStart(2,'0');
        el.textContent = `${m}:${s}`;
      }
      if (rem < 300000) cdBadge.style.color = '#EE9346';
      if (rem < 60000)  cdBadge.style.color = '#F44336';
      if (rem <= 0) {
        try { if (typeof SIM !== 'undefined' && !SIM._paused && typeof toggleSimPause === 'function') toggleSimPause(); } catch(e){}
        overlay.style.display = 'flex';
        return;
      }
      setTimeout(tick, 1000);
    }
    tick();
  }

  /* ─ 3. ENVIRONMENT RESTRICTIONS ────────────────────────────── */
  // [PLAN-ENV] Disable environment buttons not in plan
  const ALL_ENVS = ['field','mountains','urban','indoor','desert','windy'];
  ALL_ENVS.forEach(env => {
    if (!PLAN.environments.includes(env)) {
      const btn = document.querySelector(`[data-env="${env}"]`);
      if (btn) {
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.28';
        btn.title = `Upgrade to unlock ${env} environment`;
        // Add lock icon without changing flight mechanics
        const icon = btn.querySelector('.fm-icon');
        if (icon) icon.textContent = '🔒';
      }
    }
  });

  /* ─ 4. DRONE PROFILE RESTRICTIONS ──────────────────────────── */
  // [PLAN-DRONE] Restrict profile dropdown to allowed profiles
  setTimeout(() => {
    const sel = document.getElementById('drone-profile-select');
    if (sel) {
      Array.from(sel.options).forEach(opt => {
        if (opt.value && !PLAN.droneProfiles.includes(opt.value)) opt.remove();
      });
      if (sel.options.length > 0 && !PLAN.droneProfiles.includes(sel.value)) {
        sel.value = sel.options[0].value;
        if (typeof setDroneProfile === 'function') setDroneProfile(sel.value);
      }
    }

    // [PLAN-GLTF] Hide custom profile buttons if no GLTF support
    if (!PLAN.customGLTF) {
      const newBtn = document.querySelector('[onclick="openCustomProfileModal()"]');
      if (newBtn) _hideEl(newBtn);
      const custToggle = document.getElementById('customize-toggle-btn');
      if (custToggle) _hideEl(custToggle);
      const custPanel = document.getElementById('profile-customize-panel');
      if (custPanel) _hideEl(custPanel);
    }
  }, 600);

  /* ─ 5. PID TUNING PANEL ─────────────────────────────────────── */
  // [PLAN-PID] Manage PID panel access: false=hide, 'view'=read-only, 'full'=unrestricted
  let pidCard = null;
  document.querySelectorAll('.card-sm').forEach(c => {
    const t = c.querySelector('.card-title');
    if (t && t.textContent.includes('RATE PID TUNING')) pidCard = c;
  });
  if (PLAN.pidTuning === false) {
    // [PLAN-PID-BASIS] Hide entirely
    if (pidCard) _hideEl(pidCard);
  } else if (PLAN.pidTuning === 'view') {
    // [PLAN-PID-PRO] Show values but disable all sliders
    if (pidCard) {
      pidCard.querySelectorAll('input[type=range]').forEach(s => {
        s.disabled = true;
        s.style.pointerEvents = 'none';
        s.style.opacity = '0.45';
      });
      const notice = document.createElement('div');
      notice.style.cssText = 'font-size:10px;color:var(--s);font-weight:700;text-align:center;padding:5px 0 2px;letter-spacing:.4px;';
      notice.textContent = '👁 VIEW ONLY — Upgrade to MAX to live-tune';
      pidCard.appendChild(notice);
    }
  }
  // PLAN.pidTuning === 'full': no changes (MAX)

  /* ─ 6. EXPORT / BLACKBOX ────────────────────────────────────── */
  // [PLAN-EXPORT] Replace export card content for non-MAX tiers
  if (!PLAN.dataExport) {
    const exportCard = document.getElementById('export-card');
    if (exportCard) {
      exportCard.innerHTML = `
        <div class="card-title"><span class="ct-dot"></span>FLIGHT LOG · BLACKBOX</div>
        <div style="text-align:center;padding:16px 8px;color:var(--txt4);">
          <div style="font-size:18px;margin-bottom:6px">🔒</div>
          <div style="font-size:11px;font-weight:700;color:var(--txt3)">Data Export (JSON / CSV / MAVLog)</div>
          <div style="font-size:10px;margin-top:4px;color:var(--txt4)">Available on MAX plan only</div>
        </div>`;
    }
  }

  /* ─ 7. MAVLINK BUTTONS ──────────────────────────────────────── */
  // [PLAN-MAVLINK] Handle MAVLink export access
  document.querySelectorAll('button').forEach(btn => {
    if (!btn.textContent.includes('MAVLink')) return;
    if (PLAN.mavlinkLogs === false) {
      _hideEl(btn);
    } else if (PLAN.mavlinkLogs === 'readonly') {
      btn.textContent = '📡 MAVLink (Read)';
      btn.title = 'Read-only on PRO — upgrade to MAX to download';
      btn.onclick = (e) => {
        e.preventDefault();
        if (typeof UI !== 'undefined') UI.toast('📡 MAVLink view-only on PRO — upgrade to MAX');
      };
    }
    // 'download' = MAX, no change
  });

  /* ─ 8. WAYPOINT MISSION NAV ─────────────────────────────────── */
  // [PLAN-MISSION] Lock mission planner nav for non-MAX
  if (!PLAN.waypointMissions) {
    const mNav = document.getElementById('nav-mission');
    if (mNav) {
      mNav.textContent = '🔒 Mission';
      mNav.style.pointerEvents = 'none';
      mNav.style.opacity = '0.32';
      mNav.title = 'Waypoint missions available on MAX plan';
    }
  }

  /* ─ 9. GAMEPAD / JOYSTICK ───────────────────────────────────── */
  // [PLAN-GAMEPAD] Block gamepad API for non-MAX tiers
  if (!PLAN.joystickGamepad) {
    window.addEventListener('gamepadconnected', e => {
      e.stopImmediatePropagation();
      if (typeof UI !== 'undefined') UI.toast('🎮 Gamepad/Joystick requires MAX plan');
    }, true);
    // Update hint text in controls card
    document.querySelectorAll('.card-sm').forEach(card => {
      Array.from(card.childNodes).forEach(node => {
        if (node.nodeType === 3) return;
        if (node.textContent && node.textContent.includes('Gamepad supported')) {
          node.textContent = '🎮 Gamepad support: MAX plan only 🔒';
          node.style.color = 'var(--txt4)';
        }
      });
    });
    // Target the specific div
    document.querySelectorAll('div').forEach(div => {
      if (div.textContent && div.textContent.trim() === '🎮 Gamepad supported — plug in for analog input') {
        div.textContent = '🎮 Gamepad: MAX plan only 🔒';
      }
    });
  }

  /* ─ 10. NIGHT MODE RESTRICTION (BASIS) ─────────────────────── */
  // [PLAN-NIGHT] Disable night toggle for BASIS tier
  if (!PLAN.nightMode) {
    const nightToggle = document.querySelector('[onclick*="toggleDayNight"]');
    if (nightToggle) {
      _lockEl(nightToggle, 'Night mode requires PRO or MAX plan');
      const lbl = nightToggle.querySelector('.ntoggle-text');
      if (lbl) lbl.textContent = 'Night 🔒';
    }
  }

  /* ─ 11. WIND CONTROLS RESTRICTION (BASIS) ───────────────────── */
  // [PLAN-WIND] Lock wind/weather controls for BASIS tier
  if (!PLAN.windScenario) {
    ['wind-speed','turbulence','wind-dir'].forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        el.disabled = true;
        el.style.pointerEvents = 'none';
        el.style.opacity = '0.28';
        el.title = 'Wind scenarios require PRO or MAX plan';
      }
    });
    // Lock rain/fog/night weather toggles
    ['toggleWeather', 'toggleDayNight'].forEach(fn => {
      document.querySelectorAll(`[onclick*="${fn}"]`).forEach(el => _lockEl(el, 'Weather requires PRO or MAX plan'));
    });
  }

  /* ─ 12. BASIC HUD — hide advanced panels ────────────────────── */
  // [PLAN-HUD] For hudLevel='basic': hide advanced telemetry panels
  if (PLAN.hudLevel === 'basic') {
    const HIDE_TITLES = ['GPS_RAW_INT','VISION_POSITION','OBSTACLE_DISTANCE','PID TELEMETRY','LIVE TELEMETRY GRAPH'];
    document.querySelectorAll('.card-sm').forEach(card => {
      const title = card.querySelector('.card-title');
      if (!title) return;
      const t = title.textContent.toUpperCase();
      if (HIDE_TITLES.some(k => t.includes(k))) _hideEl(card);
    });
    // Hide attitude angle rows (keep only ALT + VEL for basic HUD)
    document.querySelectorAll('.tval-row').forEach(row => {
      const labels = Array.from(row.querySelectorAll('.tval-label')).map(l => l.textContent.trim());
      if (labels.some(l => ['PITCH','ROLL','YAW','HDNG'].includes(l))) _hideEl(row);
    });
  }

  /* ─ 13. SUPPORT LABEL ───────────────────────────────────────── */
  // [PLAN-SUPPORT] Inject support info into controls card
  document.querySelectorAll('.card-sm').forEach(card => {
    const title = card.querySelector('.card-title');
    if (!title || !title.textContent.includes('CONTROLS')) return;
    const sEl = document.createElement('div');
    sEl.style.cssText = 'font-size:10px;color:var(--txt4);margin-top:8px;padding-top:8px;border-top:1px solid var(--n3);';
    if (PLAN.support === 'priority') {
      sEl.innerHTML = '✅ <strong style="color:var(--s)">Priority email support</strong> included with your MAX plan';
    } else {
      sEl.innerHTML = '💬 Support: <a href="#" style="color:var(--p)">Community Forum</a>';
    }
    card.appendChild(sEl);
  });

  /* ─ 14. SESSION LIMITS & CLOUD SAVE ─────────────────────────── */
  let simTimeRemaining = -1;
  let simPlanName = 'FREE';
  let flightDurationSeconds = 0;
  let simPpm = 0.10;
  let timerInterval = null;
  let cloudTelemetryUrl = null;

  function syncSimLimits() {
    fetch('../api/get_sim_limits.php' + window.location.search)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          simPlanName = data.plan_name;
          simPpm = data.ppm;
          simTimeRemaining = data.time_remaining_seconds;
          if (simTimeRemaining === 0) {
            showTimeLimitModal();
          }
        }
      });
  }

  // Initial fetch and 1-minute polling interval
  syncSimLimits();
  setInterval(syncSimLimits, 60000);

  // Local 1-second timer
  timerInterval = setInterval(() => {
    flightDurationSeconds++;
    if (simTimeRemaining > 0) {
      simTimeRemaining--;
      const el = document.getElementById('tv-plan-time');
      if(el) {
        let m = Math.floor(simTimeRemaining/60);
        let s = simTimeRemaining%60;
        el.textContent = m.toString().padStart(2,'0')+':'+s.toString().padStart(2,'0');
      }
      if (simTimeRemaining <= 0) {
         showTimeLimitModal();
      }
    }
  }, 1000);

  window.showTimeLimitModal = function() {
    if (timerInterval) clearInterval(timerInterval);
    if (typeof toggleSimPause === 'function' && !document.getElementById('pause-btn').classList.contains('paused')) {
      toggleSimPause();
    }
    const modal = document.getElementById('sim-time-modal');
    if (modal) modal.style.display = 'flex';
  };

  let isExiting = false;
  window.exitSimulation = function() {
    if (isExiting) return;
    isExiting = true;
    
    const btn = document.getElementById('exit-sim-btn');
    if (btn) {
      btn.innerHTML = '⏳ Exiting...';
      btn.disabled = true;
    }
    
    let droneName = 'Unknown Drone';
    const profileEl = document.getElementById('drone-profile-label');
    if (profileEl) droneName = profileEl.innerText;

    // Auto save to R2
    fetch('../api/get_r2_upload_url.php', { method: 'POST' })
      .then(r => r.json())
      .then(data => {
         if (data.success) {
            let telemetryData = {};
            try {
              telemetryData = { log: BLACKBOX.getLog(), stats: BLACKBOX.getStats() };
            } catch(e) {}
            
            return fetch(data.uploadUrl, {
              method: 'PUT',
              body: JSON.stringify(telemetryData),
              headers: {'Content-Type': 'application/json'}
            }).then(res => {
               if (res.ok) {
                  cloudTelemetryUrl = data.publicUrl;
                  window.cloudTelemetryUrls = window.cloudTelemetryUrls || [];
                  window.cloudTelemetryUrls.push({ time: new Date().toLocaleTimeString(), url: data.publicUrl });
                  window.cloudTelemetryUrls = window.cloudTelemetryUrls || [];
                  window.cloudTelemetryUrls.push({ time: new Date().toLocaleTimeString() + ' (Auto)', url: data.publicUrl });
               }
            });
         }
      })
      .catch(err => {
         console.error('Auto cloud save failed:', err);
      })
      .finally(() => {
        const payload = {
          name: 'Simulation Session',
          drone: droneName,
          environment: 'Simulation',
          weather: 'Clear',
          mode: 'Manual',
          duration: flightDurationSeconds,
          status: 'completed',
          plan: simPlanName,
          ppm: simPpm,
          telemetry_url: cloudTelemetryUrl,
          telemetry_urls: window.cloudTelemetryUrls || []
        };
        fetch('../api/save_flight.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify(payload)
        }).then(() => {
          if (simPlanName === 'FREE' || simPlanName === 'BASIC') {
            window.location.href = '../dashboard.php?upgrade_telem=1';
          } else {
            window.location.href = '../simulations.php';
          }
        }).catch(() => {
          window.location.href = '../simulations.php';
        });
      });
  };

  window.triggerCloudSave = function() {
    if (simPlanName === 'FREE' || simPlanName === 'BASIC') {
       if (typeof UI !== 'undefined' && UI.toast) {
         UI.toast('☁️ Cloud Save requires PRO tier. Please upgrade.');
       } else {
         alert('☁️ Cloud Save is a premium feature. Please upgrade to the PRO tier to save telemetry to Cloudflare.');
       }
       return;
    }
    const btn = document.getElementById('cloud-save-btn');
    if (btn) btn.innerHTML = '⏳ Saving...';
    
    fetch('../api/get_r2_upload_url.php', { method: 'POST' })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
           let telemetryData = {};
           try {
             telemetryData = { log: BLACKBOX.getLog(), stats: BLACKBOX.getStats() };
           } catch(e) {}
           
           fetch(data.uploadUrl, {
             method: 'PUT',
             body: JSON.stringify(telemetryData),
             headers: {'Content-Type': 'application/json'}
           }).then(res => {
               if (res.ok) {
                  cloudTelemetryUrl = data.publicUrl;
                  window.cloudTelemetryUrls = window.cloudTelemetryUrls || [];
                  window.cloudTelemetryUrls.push({ time: new Date().toLocaleTimeString(), url: data.publicUrl });
                  if (typeof UI !== 'undefined' && UI.toast) UI.toast('✅ Telemetry saved to Cloudflare!');
                  else alert('✅ Telemetry saved to Cloudflare!');
                  updateSavedTelemBtn();
               } else {
                 res.text().then(errText => {
                   console.error('R2 upload failed:', errText);
                   alert('R2 Upload failed: ' + res.status + ' ' + res.statusText + '\nDetails: ' + errText);
                 });
              }
           }).catch(err => {
              console.error('Upload network error:', err);
              alert('Network/CORS error uploading to R2. Please check CORS settings on your bucket.');
           }).finally(() => {
              if (btn) btn.innerHTML = '☁️ Cloud Save';
           });
        } else {
           if (btn) btn.innerHTML = '☁️ Cloud Save';
           alert('Failed to get Cloudflare URL: ' + (data.message || data.error));
        }
      }).catch(err => {
         if (btn) btn.innerHTML = '☁️ Cloud Save';
         alert('Error fetching pre-signed URL: ' + err.message);
      });
  };

  console.log(`[CERTANITY SIM] Plan: ${PLAN.tierLabel} | Session: ${isFinite(PLAN.sessionSeconds) ? PLAN.sessionSeconds+'s' : 'Unlimited'}`);
});
</script>

<!-- Time Limit Modal -->
<div id="sim-time-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.85);backdrop-filter:blur(5px);align-items:center;justify-content:center;">
  <div class="card" style="width:360px;text-align:center;padding:32px;">
    <div style="font-size:32px;margin-bottom:12px;">⏳</div>
    <h2 style="font-family:var(--fh);font-size:20px;color:var(--txt);margin-bottom:8px;">Time Limit Reached</h2>
    <p style="font-size:13px;color:var(--txt2);margin-bottom:24px;line-height:1.5;">Your available simulation time has ended. Please add balance to your wallet or upgrade your plan to continue flying.</p>
    <div class="nbtn-row" style="justify-content:center;gap:12px;">
      <a class="nbtn primary" href="../billing.php" style="text-decoration:none;">Upgrade / Add Balance</a>
      <button class="nbtn danger" onclick="window.close()">Close Window</button>
    </div>
  </div>
</div>

<!-- Saved Telemetry Modal -->
<div id="saved-telem-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.85);backdrop-filter:blur(5px);align-items:center;justify-content:center;">
  <div class="card" style="width:400px;max-height:80vh;display:flex;flex-direction:column;padding:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h2 style="font-family:var(--fh);font-size:18px;color:var(--txt);">Saved Telemetry</h2>
      <button class="nbtn sm" onclick="document.getElementById('saved-telem-modal').style.display='none'">✕</button>
    </div>
    <div id="saved-telem-list" style="display:flex;flex-direction:column;gap:8px;overflow-y:auto;padding-right:4px;">
    </div>
  </div>
</div>
<script>
function openSavedTelemModal() {
  const list = document.getElementById('saved-telem-list');
  list.innerHTML = '';
  if (!window.cloudTelemetryUrls || window.cloudTelemetryUrls.length === 0) {
     list.innerHTML = '<div style="color:var(--txt3);font-size:13px;text-align:center;padding:20px;">No telemetry saved yet.</div>';
  } else {
     window.cloudTelemetryUrls.forEach((item, i) => {
        list.innerHTML += `<a href="${item.url}" target="_blank" download class="btn-solid" style="display:flex;align-items:center;gap:8px;padding:12px;background:var(--surf);border-radius:var(--r1);text-decoration:none;color:var(--txt);box-shadow:var(--sh-btn);font-size:13px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Snapshot ${i+1} (${item.time})
        </a>`;
     });
  }
  document.getElementById('saved-telem-modal').style.display = 'flex';
}
function updateSavedTelemBtn() {
   const btn = document.getElementById('saved-telem-btn');
   if (btn && window.cloudTelemetryUrls && window.cloudTelemetryUrls.length > 0) {
      btn.style.display = 'inline-block';
      btn.innerHTML = `📥 Saved (${window.cloudTelemetryUrls.length})`;
   }
}
</script>
<script src="sim-opt.js?v=6.0"></script>

<script>
// NEW HUD JS
/**
 * Certanity Robotics — Immersive HUD v2
 * script.js — Production JavaScript
 *
 * Module Index:
 *   1. Joystick Controller
 *   2. Scan Ring Positioner
 *   3. HUD Telemetry Ticker
 *   4. Menu System
 *   5. Initialisation
 */

'use strict';

/* ═══════════════════════════════════════════════════════════════
   1. JOYSTICK CONTROLLER
   Handles mouse and touch drag for both joystick inputs.
   Knob returns to center on release with a smooth transition.
════════════════════════════════════════════════════════════════ */

/**
 * Attach drag-and-snap behaviour to a joystick.
 *
 * @param {HTMLElement} outer - The circular joystick base element.
 * @param {HTMLElement} knob  - The draggable knob element.
 */
function setupJoystick(outer, knob) {
  let isDragging = false;
  const SNAP_DURATION = 280; // ms

  /**
   * Compute the clamped position for the knob given a pointer coordinate,
   * then apply it to the knob's inline style.
   */
  function moveKnob(clientX, clientY) {
    const rect   = outer.getBoundingClientRect();
    const radius = outer.offsetWidth / 2 - 16;
    const dx     = clientX - rect.left  - outer.offsetWidth  / 2;
    const dy     = clientY - rect.top   - outer.offsetHeight / 2;
    const dist   = Math.sqrt(dx * dx + dy * dy);
    const angle  = Math.atan2(dy, dx);
    const clamp  = Math.min(dist, radius);

    knob.style.left      = (outer.offsetWidth  / 2 + Math.cos(angle) * clamp) + 'px';
    knob.style.top       = (outer.offsetHeight / 2 + Math.sin(angle) * clamp) + 'px';
    knob.style.transform = 'translate(-50%, -50%)';

    // Hook into simulator INPUT
    if (typeof INPUT !== 'undefined') {
      INPUT._vjActive = true;
      const nx = (Math.cos(angle) * clamp) / radius;
      const ny = -(Math.sin(angle) * clamp) / radius; // inverted Y
      if (outer.id === 'jsL') {
        INPUT._vjLeft = { x: nx, y: ny };
      } else if (outer.id === 'jsR') {
        INPUT._vjRight = { x: nx, y: ny };
      }
    }
  }

  function snapToCenter() {
    knob.style.transition = 'left ' + SNAP_DURATION + 'ms, top ' + SNAP_DURATION + 'ms';
    knob.style.left       = '50%';
    knob.style.top        = '50%';
    knob.style.transform  = 'translate(-50%, -50%)';
    setTimeout(() => { knob.style.transition = ''; }, SNAP_DURATION + 10);
    
    // Reset simulator INPUT
    if (typeof INPUT !== 'undefined') {
      if (outer.id === 'jsL') {
        // Throttle shouldn't snap to center usually, but in this UI it does.
        INPUT._vjLeft = { x: 0, y: 0 };
      } else if (outer.id === 'jsR') {
        INPUT._vjRight = { x: 0, y: 0 };
      }
      setTimeout(() => { INPUT._vjActive = false; }, 100);
    }
  }

  /** Animate knob back to centre. */
  function snapToCenter() {
    knob.style.transition = 'left ' + SNAP_DURATION + 'ms, top ' + SNAP_DURATION + 'ms';
    knob.style.left       = '50%';
    knob.style.top        = '50%';
    knob.style.transform  = 'translate(-50%, -50%)';
    setTimeout(() => { knob.style.transition = ''; }, SNAP_DURATION + 10);
  }

  /* ── Mouse events ── */
  outer.addEventListener('mousedown', (e) => {
    isDragging = true;
    knob.style.transition = 'none';
    moveKnob(e.clientX, e.clientY);
  });

  window.addEventListener('mousemove', (e) => {
    if (isDragging) moveKnob(e.clientX, e.clientY);
  });

  window.addEventListener('mouseup', () => {
    if (isDragging) {
      isDragging = false;
      snapToCenter();
    }
  });

  /* ── Touch events ── */
  outer.addEventListener('touchstart', (e) => {
    isDragging = true;
    knob.style.transition = 'none';
    moveKnob(e.touches[0].clientX, e.touches[0].clientY);
  }, { passive: true });

  window.addEventListener('touchmove', (e) => {
    if (isDragging) moveKnob(e.touches[0].clientX, e.touches[0].clientY);
  }, { passive: true });

  window.addEventListener('touchend', () => {
    if (isDragging) {
      isDragging = false;
      snapToCenter();
    }
  });
}


/* ═══════════════════════════════════════════════════════════════
   2. SCAN RING POSITIONER
   Repositions the animated scan rings to stay centred over
   the primary target box (tbox1) as it drifts across the screen.
════════════════════════════════════════════════════════════════ */

/**
 * Align both scan rings to the centre of the primary target box.
 * Called once on load, then on every animation frame while the
 * target is moving.
 */
function positionScanRings() {
  const tbox = document.getElementById('tbox1');
  const hud  = document.querySelector('.hud');
  if (!tbox || !hud) return;

  const targetRect = tbox.getBoundingClientRect();
  const hudRect    = hud.getBoundingClientRect();
  const cx         = targetRect.left - hudRect.left + targetRect.width  / 2;
  const cy         = targetRect.top  - hudRect.top  + targetRect.height / 2;

  ['scanRing', 'scanRing2'].forEach((id) => {
    const el = document.getElementById(id);
    if (el) {
      el.style.left = cx + 'px';
      el.style.top  = cy + 'px';
    }
  });
}


/* ═══════════════════════════════════════════════════════════════
   3. HUD TELEMETRY TICKER
   Drives all live telemetry readouts using a rAF loop.
   Values are based on sine/cosine oscillators to simulate
   real flight data variation.
════════════════════════════════════════════════════════════════ */

const FLIGHT_MODES  = ['LOITER', 'SURVEY', 'RTL', 'AUTO', 'FOLLOW'];
const LOCK_STATES   = ['TRACKING', 'LOCKED ·', 'SCANNING'];
const MODE_INTERVAL = 30; // seconds between mode changes (approx)

let tickerTime  = 0;
let modeIndex   = 0;
let lastModeTick = 0;

/** Shorthand getElementById — reduces noise in the ticker loop. */
const $ = (id) => document.getElementById(id);

/**
 * Single animation frame tick — updates all HUD readouts.
 * Runs at display refresh rate via requestAnimationFrame.
 */

// Append new HUD updates to existing _updateUI
if (typeof SIM !== 'undefined' && SIM._updateUI) {
  const origUpdateUI = SIM._updateUI.bind(SIM);
  SIM._updateUI = function(dt) {
    try { origUpdateUI(dt); } catch(e) {}

    const D = document;
    const set = (id, v) => { const el = D.getElementById(id); if(el) el.textContent = v; };
    const p = PHYS, e = p.euler;
    const R2D = 180 / Math.PI;
    const alt = Math.max(0, p.pos.y - p.groundY);
    const vel = V3.len(p.vel);
    const hdg = ((e.yaw * R2D + 360) % 360) | 0;
    const dist = p.homePos ? Math.hypot(p.pos.x - p.homePos.x, p.pos.z - p.homePos.z) : Math.hypot(p.pos.x, p.pos.z);

    set('tv-alt', alt.toFixed(1) + 'm');
    set('tv-spd', (vel * 3.6).toFixed(0) + ' km/h');
    set('tv-hdg', hdg + '°');
    set('tv-vspd', (p.vel.y >= 0 ? '+' : '') + p.vel.y.toFixed(1));
    set('tv-dist', dist >= 1000 ? (dist/1000).toFixed(2) + ' km' : dist.toFixed(0) + ' m');
    
    set('fs-roll', (e.roll * R2D >= 0 ? '+' : '') + (e.roll * R2D).toFixed(1) + '°');
    set('fs-pitch', (e.pitch * R2D >= 0 ? '+' : '') + (e.pitch * R2D).toFixed(1) + '°');

    const battStr = p.battPct.toFixed(0) + '%';
    set('battPct', battStr);
    set('battPctHUD', battStr);
    const battFill = D.getElementById('battFill');
    if (battFill) battFill.style.width = battStr;
    const battFillHUD = D.getElementById('battFillHUD');
    if (battFillHUD) battFillHUD.style.width = battStr;

    // Flight time
    const ft = performance.now() / 1000;
    set('tv-time', Math.floor(ft/60).toString().padStart(2,'0')+':'+Math.floor(ft%60).toString().padStart(2,'0'));

    // Mode
    if (typeof State !== 'undefined') {
      set('modeText', State.armed ? (State.flightMode || 'STABILIZED').toUpperCase() : 'DISARMED');
    }
  };
}



/* ═══════════════════════════════════════════════════════════════
   4. MENU SYSTEM
   Data-driven menu panel that renders sections and controls
   dynamically based on menuData definitions.
════════════════════════════════════════════════════════════════ */

/**
 * Menu data definitions.
 * Each key maps to a menu panel. Each section has a title and rows.
 * Row types: 'toggle', 'slider', 'status', or default (plain value).
 */

const menuData = {
  ENVIRON: {
    title: 'ENVIRONMENT & WEATHER',
    items: [
      { label: 'MAP: FIELD', value: 'LOAD', action: () => { if(typeof setEnvironment==='function') setEnvironment('field'); } },
      { label: 'MAP: MOUNTAINS', value: 'LOAD', action: () => { if(typeof setEnvironment==='function') setEnvironment('mountains'); } },
      { label: 'MAP: URBAN', value: 'LOAD', action: () => { if(typeof setEnvironment==='function') setEnvironment('urban'); } },
      { label: 'MAP: INDOOR', value: 'LOAD', action: () => { if(typeof setEnvironment==='function') setEnvironment('indoor'); } },
      { label: 'MAP: DESERT', value: 'LOAD', action: () => { if(typeof setEnvironment==='function') setEnvironment('desert'); } },
      { label: 'MAP: WINDY', value: 'LOAD', action: () => { if(typeof setEnvironment==='function') setEnvironment('windy'); } }
    ]
  },
  CAMERA: {
    title: 'CAMERA & SENSORS',
    items: [
      { label: 'TOGGLE CAMERA', value: 'SWITCH', action: () => { if(typeof cycleCamera==='function') cycleCamera(); } },
      { label: 'GPS DENIED SIM', value: 'TOGGLE', action: () => { if(typeof activateGPSDenied==='function') activateGPSDenied(true); } },
      { label: 'GPS RESTORE', value: 'TOGGLE', action: () => { if(typeof activateGPSDenied==='function') activateGPSDenied(false); } },
      { label: 'FAIL MOTOR 1', value: 'FAIL', action: () => { if(typeof activateMotorFailure==='function') activateMotorFailure(0); } },
      { label: 'FAIL MOTOR 2', value: 'FAIL', action: () => { if(typeof activateMotorFailure==='function') activateMotorFailure(1); } },
      { label: 'FAIL MOTOR 3', value: 'FAIL', action: () => { if(typeof activateMotorFailure==='function') activateMotorFailure(2); } },
      { label: 'FAIL MOTOR 4', value: 'FAIL', action: () => { if(typeof activateMotorFailure==='function') activateMotorFailure(3); } },
      { label: 'RESTORE ALL MOTORS', value: 'FIX', action: () => { if(typeof clearMotorFailures==='function') clearMotorFailures(); } }
    ]
  },
  MISSION: {
    title: 'FLIGHT MODES & DATA',
    items: [
      { label: 'RESET SIMULATION', value: 'RESET', action: () => { if(typeof resetSim==='function') resetSim(); } },
      { label: 'MODE: STABILIZED', value: 'SET', action: () => { if(typeof setFlightMode==='function') setFlightMode('stabilized'); } },
      { label: 'MODE: ANGLE', value: 'SET', action: () => { if(typeof setFlightMode==='function') setFlightMode('angle'); } },
      { label: 'MODE: ACRO', value: 'SET', action: () => { if(typeof setFlightMode==='function') setFlightMode('acro'); } },
      { label: 'MODE: ALT HOLD', value: 'SET', action: () => { if(typeof setFlightMode==='function') setFlightMode('althold'); } },
      { label: 'MODE: GPS HOLD', value: 'SET', action: () => { if(typeof setFlightMode==='function') setFlightMode('gpshold'); } },
      { label: 'MODE: RTH', value: 'SET', action: () => { if(typeof setFlightMode==='function') setFlightMode('rth'); } }
    ]
  },
  
  PID: {
    title: 'PID TUNING',
    items: [
      { label: 'EXPORT PID DATA (.JSON)', value: 'SAVE', action: () => { if(typeof exportPIDJSON==='function') exportPIDJSON(); else if(typeof exportJSON==='function') exportJSON(); } },
      { label: 'EXPORT FLIGHT LOG (.TLOG)', value: 'SAVE', action: () => { if(typeof exportMAVLink==='function') exportMAVLink(); } }
    ]
  },
  
  SENSORS: {
    title: 'SENSOR MATRIX',
    items: [
      { label: 'CALIBRATE GYRO', value: 'START', action: () => { if(typeof UI!=='undefined') UI.toast('Gyro calibration complete'); } },
      { label: 'CALIBRATE ACCEL', value: 'START', action: () => { if(typeof UI!=='undefined') UI.toast('Accel calibration complete'); } },
      { label: 'CALIBRATE MAG', value: 'START', action: () => { if(typeof UI!=='undefined') UI.toast('Mag calibration complete'); } }
    ]
  }
};


/**
 * Build the HTML for a single row control.
 *
 * @param {object} row - Row definition from menuData.
 * @returns {string} HTML string for the control element.
 */
function buildRowControl(row) {
  switch (row.type) {
    case 'toggle':
      return `<div class="menu-toggle ${row.on ? 'on' : ''}" onclick="this.classList.toggle('on')">
                <div class="menu-toggle-knob"></div>
              </div>`;

    case 'slider':
      return `<input class="menu-slider" type="range" min="0" max="100" value="50" aria-label="${row.key}">`;

    case 'status':
      return `<span class="status-ok">${row.value}</span>`;

    default:
      return `<span class="menu-value">${row.value}</span>`;
  }
}


/**
 * Open a menu panel by key and populate it with the relevant data.
 *
 * @param {string} key - Key matching a property in menuData.
 */
function openMenu(key) {
  const map = { env: 'ENVIRON', camera: 'CAMERA', mission: 'MISSION', pid: 'PID', sensors: 'SENSORS' };
  const realKey = map[key] || key.toUpperCase();
  if (realKey === 'PID' && !PLAN.pidTuning) return;
  const data = menuData[realKey];
  if (!data) return;

  const titleEl = document.getElementById('menuTitle');
  if (titleEl) titleEl.textContent = data.title;

  const gridEl = document.getElementById('menuGrid');
  if (!gridEl) return;
  gridEl.innerHTML = '';

  if (realKey === 'PID') {
    const g = (typeof FC !== 'undefined') ? FC.gains : {rp:0.025,ri:0,rd:0.0011,yp:0.025,ap:1.458};
    const sens = (typeof INPUT !== 'undefined') ? (INPUT.sensitivity * 100) : 38;
    const pidWrap = document.createElement('div');
    pidWrap.className = 'menu-section';
    pidWrap.style.cssText = 'gap:0; padding:10px; background: rgba(16, 21, 34, 0.95); border-radius:12px;';
    const pidH = document.createElement('div');
    pidH.style.cssText = 'color:#6C819F;font-size:11px;font-weight:700;letter-spacing:1px;padding:4px 0 16px;text-align:left;display:flex;align-items:center;';
    pidH.innerHTML = '<span style="color:#EE9346;margin-right:8px;font-size:14px;">●</span>RATE PID TUNING';
    pidWrap.appendChild(pidH);
    
    var mkSlider = function(lbl, param, min, max, step, val, isPct) {
      var r = document.createElement('div');
      r.className = 'menu-row';
      r.style.cssText = 'flex-direction:column;align-items:flex-start;gap:8px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);';
      var dispVal = isPct ? Math.round(val)+'%' : parseFloat(val).toString();
      
      var onInputStr = isPct 
        ? "(function(el){var v=parseFloat(el.value);var d=document.getElementById('pd-'+'" + param + "');if(d)d.textContent=Math.round(v)+'%';if(typeof INPUT!=='undefined'){INPUT.sensitivity=v/100;}})(this)"
        : "(function(el){var v=parseFloat(el.value);var d=document.getElementById('pd-'+'" + param + "');if(d)d.textContent=v;if(typeof FC!=='undefined'){FC.gains['" + param + "']=v;FC.applyGains();}})(this)";
        
      r.innerHTML = '<div style="display:flex;justify-content:space-between;width:100%"><span style="color:#fff;font-size:12px;font-weight:600;">'+lbl+'</span><span style="color:#fff;font-size:12px;font-weight:700;" id="pd-'+param+'">'+dispVal+'</span></div>' +
        '<input type="range" min="'+min+'" max="'+max+'" step="'+step+'" value="'+val+'" style="width:100%;height:4px;cursor:pointer;background:rgba(0,0,0,0.3);border-radius:2px;appearance:none;outline:none;" oninput="'+onInputStr+'">';
      return r;
    };
    
    pidWrap.appendChild(mkSlider('Rate P', 'rp', 0, 0.150, 0.001, g.rp, false));
    pidWrap.appendChild(mkSlider('Rate I', 'ri', 0, 0.050, 0.001, g.ri || 0, false));
    pidWrap.appendChild(mkSlider('Rate D', 'rd', 0, 0.010, 0.0001, g.rd, false));
    pidWrap.appendChild(mkSlider('Yaw Rate P', 'yp', 0, 0.200, 0.001, g.yp, false));
    pidWrap.appendChild(mkSlider('Alt P', 'ap', 0, 4.0, 0.001, g.ap, false));
    var lastRow = mkSlider('Sensitivity', 'expo', 0, 100, 1, sens, true);
    lastRow.style.borderBottom = 'none';
    pidWrap.appendChild(lastRow);
    
    gridEl.appendChild(pidWrap);
    const pidPanelEl = document.getElementById('menuPanel');
    if (pidPanelEl) pidPanelEl.classList.add('open');
    
    // Inject a quick CSS rule for the slider thumb to match the dark blue aesthetic
    if(!document.getElementById('pid-slider-css')) {
      const style = document.createElement('style');
      style.id = 'pid-slider-css';
      style.innerHTML = `
        #menuPanel input[type=range]::-webkit-slider-thumb {
          -webkit-appearance: none; appearance: none;
          width: 16px; height: 16px; border-radius: 50%;
          background: #141f36; border: 2px solid #1a2744;
          cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
      `;
      document.head.appendChild(style);
    }
    return;
  } else if (realKey === 'SENSORS') {
    var sensWrap = document.createElement('div');
    sensWrap.className = 'menu-section';
    var sensH = document.createElement('div');
    sensH.style.cssText = 'color:var(--p,#0af);font-size:10px;font-weight:700;letter-spacing:1px;padding:4px 0 10px;text-align:center;';
    sensH.textContent = '── LIVE SENSOR READINGS ──';
    sensWrap.appendChild(sensH);
    var addRow = function(lbl, id) {
      var r = document.createElement('div');
      r.className = 'menu-row';
      r.innerHTML = '<span class="menu-key">'+lbl+'</span><span class="menu-value" id="sens-'+id+'">--</span>';
      sensWrap.appendChild(r);
    };
    addRow('GYRO X (rad/s)', 'gx');
    addRow('GYRO Y (rad/s)', 'gy');
    addRow('GYRO Z (rad/s)', 'gz');
    addRow('ACCEL X (m/s²)', 'ax');
    addRow('ACCEL Y (m/s²)', 'ay');
    addRow('ACCEL Z (m/s²)', 'az');
    addRow('BARO ALT (m)', 'baro');
    addRow('BATTERY (V)', 'volt');
    gridEl.appendChild(sensWrap);
    if (window._sensorInterval) clearInterval(window._sensorInterval);
    window._sensorInterval = setInterval(function() {
      var p = (typeof PHYS !== 'undefined') ? PHYS : null;
      if (!p) return;
      var s = function(id,v){var el=document.getElementById('sens-'+id);if(el)el.textContent=v;};
      s('gx', p.gyro ? p.gyro.x.toFixed(4) : '--');
      s('gy', p.gyro ? p.gyro.y.toFixed(4) : '--');
      s('gz', p.gyro ? p.gyro.z.toFixed(4) : '--');
      s('ax', p.accel ? p.accel.x.toFixed(3) : '--');
      s('ay', p.accel ? p.accel.y.toFixed(3) : '--');
      s('az', p.accel ? p.accel.z.toFixed(3) : '--');
      s('baro', p.pos ? Math.max(0, p.pos.y-(p.groundY||0)).toFixed(1)+'m' : '--');
      s('volt', p.battVoltage ? p.battVoltage.toFixed(2)+'V' : '--');
    }, 100);
    const sensPanelEl = document.getElementById('menuPanel');
    if (sensPanelEl) sensPanelEl.classList.add('open');
    return;
  }

  const sectionEl = document.createElement('div');
  sectionEl.className = 'menu-section';
  
  (data.items || []).forEach((row) => {
    const rowEl = document.createElement('div');
    rowEl.className = 'menu-row';
    const keyEl = document.createElement('span');
    keyEl.className = 'menu-key';
    keyEl.textContent = row.label;
    
    let ctrlEl;
    if (['TOGGLE','LOAD','SET','ADD','CLEAR','START','SAVE','SHOW','SWITCH','FAIL','FIX','RESET'].includes(row.value)) {
        ctrlEl = document.createElement('button');
        ctrlEl.className = 'nbtn sm';
        ctrlEl.textContent = row.value;
        ctrlEl.onclick = row.action;
    } else {
        ctrlEl = document.createElement('span');
        ctrlEl.className = 'menu-value';
        ctrlEl.textContent = row.value;
    }
    rowEl.appendChild(keyEl);
    rowEl.appendChild(ctrlEl);
    sectionEl.appendChild(rowEl);
  });
  
  gridEl.appendChild(sectionEl);

  const panelEl = document.getElementById('menuPanel');
  if (panelEl) panelEl.classList.add('open');
}


/**
 * Close the menu panel and return to the HUD.
 */
function closeMenu() {
  const panelEl = document.getElementById('menuPanel');
  if (panelEl) panelEl.classList.remove('open');
  if (window._sensorInterval) { clearInterval(window._sensorInterval); window._sensorInterval = null; }
}


/* ═══════════════════════════════════════════════════════════════
   5. INITIALISATION
   Boot sequence — runs after DOM is fully ready.
════════════════════════════════════════════════════════════════ */

/**
 * Small delay before joystick setup ensures elements are fully
 * laid out and offsetWidth returns accurate values.
 */
window.addEventListener('DOMContentLoaded', () => {
  // Joysticks require layout to be complete before measuring radius
  setTimeout(() => {
    setupJoystick(
      document.getElementById('jsL'),
      document.getElementById('jsLKnob')
    );
    setupJoystick(
      document.getElementById('jsR'),
      document.getElementById('jsRKnob')
    );

    // Initial scan ring placement
    positionScanRings();
  }, 100);

  // Start the HUD telemetry loop
  setInterval(() => { if (typeof UI !== 'undefined' && typeof UI._updateUI === 'function') UI._updateUI(0.05); }, 50);
});

</script>
</body>
</html>














