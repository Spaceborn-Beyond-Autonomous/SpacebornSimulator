<?php require_once __DIR__ . '/../auth/session_guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CERTANITY · Drone Simulator — PRO</title>
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
#app{width:100%;height:100%;display:grid;grid-template-rows:52px 1fr 190px;grid-template-columns:272px 1fr 272px}

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
#viewport{grid-row:2/3;grid-column:2/3;position:relative;overflow:hidden;background:#0a1020;min-width:0;min-height:0;width:100%;height:100%}
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
.cam-badge{position:absolute;top:14px;left:50%;transform:translateX(-50%);background:rgba(238,147,70,.9);color:white;padding:4px 12px;border-radius:20px;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;backdrop-filter:blur(4px)}
.crosshair{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:32px;height:32px}
.ch-h,.ch-v{position:absolute;background:rgba(238,147,70,.5)}
.ch-h{height:1px;width:100%;top:50%;transform:translateY(-50%)}
.ch-v{width:1px;height:100%;left:50%;transform:translateX(-50%)}
.vp-warn{position:absolute;bottom:14px;left:50%;transform:translateX(-50%);background:rgba(244,67,54,.85);color:white;padding:5px 14px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.5px;backdrop-filter:blur(4px);opacity:0;transition:opacity .3s;pointer-events:none}
.vp-warn.show{opacity:1}
#toast.show{opacity:1!important;}

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
.tval-num{font-family:var(--fh);font-size:17px;font-weight:600;color:var(--txt);line-height:1}
.tval-unit{font-size:10px;color:var(--txt3);margin-top:1px}
.tval.hi .tval-num{color:var(--s)}
.tval.warn .tval-num{color:#E53935}
.tval-row{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}
.tval-row2{display:grid;grid-template-columns:repeat(2,1fr);gap:7px}

/* ── Bar Gauge ── */
.bgauge-wrap{display:flex;flex-direction:column;gap:4px}
.bgauge-label{display:flex;justify-content:space-between;font-size:10px;color:var(--txt3)}
.bgauge-label span{font-family:var(--fh);font-weight:600}
.bgauge-track{height:8px;border-radius:4px;background:var(--n2);box-shadow:var(--sh-in-sm);overflow:hidden}
.bgauge-fill{height:100%;border-radius:4px;transition:width .2s}
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
.motor-bar{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--p),var(--s));transition:width .1s}

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
.obs-bar-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,#F44336,#EE9346,#4CAF50);transition:width .1s}

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
.pid-axis-live{font-size:8px;font-family:var(--fh);font-weight:600;color:var(--s)}
.pid-gains-row{display:flex;gap:3px;margin-bottom:5px}
.pid-gain{flex:1;text-align:center;padding:3px 2px;border-radius:5px;background:var(--n);box-shadow:var(--sh-in-sm)}
.pid-gain-lbl{font-size:8px;color:var(--txt4);font-weight:600}
.pid-gain-val{font-size:10px;font-family:var(--fh);font-weight:700;color:var(--p)}
.pid-err-track{height:4px;border-radius:2px;background:var(--n2);box-shadow:var(--sh-in-sm);overflow:hidden;position:relative}
.pid-err-track::before{content:'';position:absolute;left:50%;top:0;width:1px;height:100%;background:var(--n4);z-index:1}
.pid-err-fill{position:absolute;height:100%;border-radius:2px;background:var(--s);transition:width .06s,left .06s}

/* ── Rate Hz badges ── */
.hz-badge{display:inline-block;font-size:8px;font-weight:700;padding:1px 5px;border-radius:6px;background:var(--n);box-shadow:var(--sh-in-sm);color:var(--txt4);letter-spacing:.3px;margin-left:4px;vertical-align:middle}

</style>
<body>

<!-- Startup -->
<div id="startup">
  <div class="sl">CERTANITY</div>
  <div class="ss">Aerospace Drone Simulator · v2.0</div>
  <div class="sbw"><div class="sb" id="sbar"></div></div>
  <div class="st" id="sstat">Initializing physics engine…</div>
</div>

<!-- App -->
<div id="app" style="display:none">

  <!-- Topbar -->
  <div id="topbar">
    <a class="brand" href="#">
      <div class="brand-name">CERTANITY</div>
      <div class="brand-tag">SIM</div>
    </a>
    <div class="vsep"></div>
    <div class="nav-pills">
      <button class="npill on" id="nav-flight" onclick="showSection('flight')">Flight</button>
      <button class="npill" id="nav-mission" onclick="showSection('mission')">Mission</button>
      <button class="npill" id="nav-debug" onclick="showSection('debug')">Debug</button>
    </div>
    <div class="tsp"></div>
    <button class="nbtn sm accent" id="cloud-save-btn" onclick="triggerCloudSave()" title="Save Telemetry to Cloudflare">☁️ Cloud Save</button>
    <button class="nbtn sm accent" id="saved-telem-btn" style="display:none;background:var(--s);color:#fff;" onclick="openSavedTelemModal()">📥 Saved (0)</button>
    <button class="nbtn sm danger" id="exit-sim-btn" onclick="exitSimulation()" title="Exit and Save Flight">🚪 Exit</button>
    <button class="nbtn sm" id="pause-btn" onclick="toggleSimPause()" title="Pause/Resume Simulation (Space)">⏸ Pause</button>
    <div class="nfield" style="padding:3px 8px;gap:4px;">
      <label style="font-size:10px;color:var(--txt4)">Speed</label>
      <select id="sim-speed" onchange="setSimSpeed(this.value)" style="font-size:11px;font-weight:600;color:var(--txt);background:none;border:none;outline:none;cursor:pointer;">
        <option value="0.25">0.25×</option>
        <option value="0.5">0.5×</option>
        <option value="1" selected>1×</option>
        <option value="2">2×</option>
        <option value="4">4×</option>
      </select>
    </div>
    <div class="top-stat"><div class="sdot" id="sys-dot"></div><span id="sys-status">READY</span></div>
    <span id="arm-status">DISARMED</span>
    <div class="top-stat"><span>⚡</span><span id="batt-top">100%</span></div>
    <div class="top-stat"><span>🌡</span><span id="fps-val">60fps</span></div>
    <div class="top-clock" id="top-clock">00:00</div>
  </div>

  <!-- Left Panel -->
  <div id="lpanel">

    <!-- Flight Controls -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>FLIGHT CONTROLS</div>
      <div class="nbtn-row" style="margin-bottom:8px">
        <button class="nbtn accent" onclick="takeoff()">🚁 Takeoff</button>
        <button class="nbtn primary" onclick="doHover()">⏸ Hover</button>
        <button class="nbtn" onclick="returnHome()">🏠 RTH</button>
        <button class="nbtn danger" onclick="emergStop()">⛔ Stop</button>
        <button class="nbtn sm" onclick="resetDrone()">🔄 Reset</button>
      </div>
      <div class="nslider-wrap">
        <div class="nslider-label"><span>Throttle</span><span id="thr-val">0%</span></div>
        <input type="range" min="0" max="100" value="0" id="throttle-slider" class="accent-range"
          oninput="setThrottleSlider(this.value)">
      </div>
      <div style="margin-top:8px" class="ht-row">
        <span>Hover Thr:</span><span class="ht-val" id="hover-thr-val">50</span><span>%</span>
      </div>
    </div>

    <!-- Flight Modes -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>FLIGHT MODE</div>
      <div class="fmode-grid">
        <button class="fmode-btn on" data-mode="stabilized" onclick="setFlightMode('stabilized')"><span class="fm-icon">⚖️</span>Stabilized</button>
        <button class="fmode-btn" data-mode="angle" onclick="setFlightMode('angle')"><span class="fm-icon">📐</span>Angle</button>
        <button class="fmode-btn" data-mode="acro" onclick="setFlightMode('acro')"><span class="fm-icon">🎯</span>Acro</button>
        <button class="fmode-btn" data-mode="althold" onclick="setFlightMode('althold')"><span class="fm-icon">🔒</span>Alt Hold</button>
        <button class="fmode-btn" data-mode="gpshold" onclick="setFlightMode('gpshold')"><span class="fm-icon">📡</span>GPS Hold</button>
        <button class="fmode-btn" data-mode="rth" onclick="setFlightMode('rth')"><span class="fm-icon">🏠</span>RTH</button>
      </div>
    </div>

    <!-- Camera -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>CAMERA</div>
      <div class="cam-row">
        <button class="cam-btn on" data-cam="third" onclick="setCamera('third')">3rd</button>
        <button class="cam-btn" data-cam="fpv" onclick="setCamera('fpv')">FPV</button>
        <button class="cam-btn" data-cam="orbit" onclick="setCamera('orbit')">Orbit</button>
        <button class="cam-btn" data-cam="free" onclick="setCamera('free')">Free</button>
        <button class="cam-btn" data-cam="top" onclick="setCamera('top')">Top</button>
      </div>
    </div>

    <!-- Environment -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>ENVIRONMENT</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px">
        <button class="fmode-btn on" data-env="field" onclick="setEnvironment('field')"><span class="fm-icon">🌾</span>Field</button>
        <button class="fmode-btn" data-env="mountains" onclick="setEnvironment('mountains')"><span class="fm-icon">⛰️</span>Mountains</button>
        <button class="fmode-btn" data-env="urban" onclick="setEnvironment('urban')"><span class="fm-icon">🏙️</span>Urban</button>
        <button class="fmode-btn" data-env="indoor" onclick="setEnvironment('indoor')"><span class="fm-icon">🏭</span>Warehouse</button>
        <button class="fmode-btn" data-env="desert" onclick="setEnvironment('desert')"><span class="fm-icon">🏜️</span>Desert</button>
        <button class="fmode-btn" data-env="windy" onclick="setEnvironment('windy')"><span class="fm-icon">🌪️</span>Windy</button>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <div class="nslider-wrap">
          <div class="nslider-label"><span>Wind Speed</span><span id="wind-val">0 m/s</span></div>
          <input type="range" min="0" max="20" value="0" id="wind-speed" oninput="setWind(this.value)">
        </div>
        <div class="nslider-wrap">
          <div class="nslider-label"><span>Turbulence</span><span id="turb-val">0%</span></div>
          <input type="range" min="0" max="100" value="0" id="turbulence" oninput="setTurbulence(this.value)">
        </div>
        <div class="nslider-wrap">
          <div class="nslider-label"><span>Wind Dir</span><span id="wdir-val">N 0°</span></div>
          <input type="range" min="0" max="360" value="0" class="accent-range" id="wind-dir" oninput="setWindDir(this.value)">
        </div>
        <div style="display:flex;gap:7px;flex-wrap:wrap">
          <div class="ntoggle" onclick="toggleWeather('rain',this)">
            <div class="ntoggle-track" id="rain-track"><div class="ntoggle-thumb"></div></div>
            <span class="ntoggle-text">Rain</span>
          </div>
          <div class="ntoggle" onclick="toggleWeather('fog',this)">
            <div class="ntoggle-track" id="fog-track"><div class="ntoggle-thumb"></div></div>
            <span class="ntoggle-text">Fog</span>
          </div>
          <div class="ntoggle" onclick="toggleDayNight(this)">
            <div class="ntoggle-track" id="daynight-track"><div class="ntoggle-thumb"></div></div>
            <span class="ntoggle-text">Night</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Drone Profile -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>DRONE PROFILE</div>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <div class="nfield" style="margin-bottom:4px;">
          <label for="drone-profile-select">Profile</label>
          <select id="drone-profile-select" onchange="setDroneProfile(this.value)"></select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
          <div style="font-size:12px;color:var(--txt2);">Label</div><div id="drone-profile-label" style="font-size:12px;font-weight:700;">5" Racing Quad</div>
          <div style="font-size:12px;color:var(--txt2);">Mass</div><div id="drone-mass-val" style="font-size:12px;font-weight:700;">1.24 kg</div>
          <div style="font-size:12px;color:var(--txt2);">Battery</div><div id="drone-batt-val" style="font-size:12px;font-weight:700;">4S 1.65 Ah</div>
          <div style="font-size:12px;color:var(--txt2);">Max RPM</div><div id="drone-maxrpm-val" style="font-size:12px;font-weight:700;">14,000</div>
        </div>
        <!-- Action buttons -->
        <div class="nbtn-row" style="margin-top:2px">
          <button class="nbtn sm" id="customize-toggle-btn" onclick="toggleProfileCustomize()" style="flex:1">✏️ Customize</button>
          <button class="nbtn sm accent" onclick="openCustomProfileModal()" style="flex:1">＋ New Profile</button>
        </div>
        <!-- Inline customize panel -->
        <div class="profile-customize" id="profile-customize-panel">
          <div class="profile-section-title">⚙ Physics Parameters</div>
          <div class="profile-grid">
            <div class="profile-field"><label>Mass (kg)</label><input type="number" id="cust-mass" step="0.01" min="0.05" max="20" oninput="applyCustomize()"></div>
            <div class="profile-field"><label>Arm Length (m)</label><input type="number" id="cust-arm" step="0.01" min="0.05" max="1" oninput="applyCustomize()"></div>
            <div class="profile-field"><label>Max RPM</label><input type="number" id="cust-maxrpm" step="500" min="2000" max="50000" oninput="applyCustomize()"></div>
            <div class="profile-field"><label>Idle RPM</label><input type="number" id="cust-idlerpm" step="50" min="100" max="3000" oninput="applyCustomize()"></div>
            <div class="profile-field"><label>Thrust Coeff (kT)</label><input type="number" id="cust-kt" step="0.000001" min="0.000001" max="0.0001" oninput="applyCustomize()"></div>
            <div class="profile-field"><label>Torque Coeff (kQ)</label><input type="number" id="cust-kq" step="0.0000001" min="0.0000001" max="0.000005" oninput="applyCustomize()"></div>
            <div class="profile-field"><label>Battery Cells (S)</label><input type="number" id="cust-cells" step="1" min="1" max="12" oninput="applyCustomize()"></div>
            <div class="profile-field"><label>Battery (Ah)</label><input type="number" id="cust-batt" step="0.1" min="0.1" max="30" oninput="applyCustomize()"></div>
            <div class="profile-field"><label>Max Tilt (°)</label><input type="number" id="cust-tilt" step="1" min="10" max="85" oninput="applyCustomize()"></div>
            <div class="profile-field"><label>Drag Area (m²)</label><input type="number" id="cust-drag" step="0.001" min="0.001" max="0.5" oninput="applyCustomize()"></div>
          </div>
          <div class="profile-color-row">
            <label>Drone Color</label>
            <input type="color" id="cust-color" value="#1e88e5" oninput="applyCustomizeColor(this.value)">
          </div>
          <button class="nbtn sm primary" onclick="saveCustomizeAsProfile()" style="margin-top:4px;width:100%">💾 Save as Custom Profile</button>
        </div>
      </div>
    </div>

    <!-- PID -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>RATE PID TUNING</div>
      <div style="display:flex;flex-direction:column;gap:7px">
        <div class="nslider-wrap">
          <div class="nslider-label"><span>Rate P</span><span id="rp-val">0.12</span></div>
          <input type="range" min="0.01" max="0.40" step="0.005" value="0.12" id="pid-rp" oninput="setPID('rp',this.value)">
        </div>
        <div class="nslider-wrap">
          <div class="nslider-label"><span>Rate I</span><span id="ri-val">0.02</span></div>
          <input type="range" min="0" max="0.20" step="0.002" value="0.02" id="pid-ri" oninput="setPID('ri',this.value)">
        </div>
        <div class="nslider-wrap">
          <div class="nslider-label"><span>Rate D</span><span id="rd-val">0.006</span></div>
          <input type="range" min="0" max="0.05" step="0.001" value="0.006" id="pid-rd" oninput="setPID('rd',this.value)">
        </div>
        <div class="nslider-wrap">
          <div class="nslider-label"><span>Yaw Rate P</span><span id="yp-val">0.15</span></div>
          <input type="range" min="0.01" max="0.50" step="0.01" value="0.15" id="pid-yp" oninput="setPID('yp',this.value)">
        </div>
        <div class="nslider-wrap">
          <div class="nslider-label"><span>Alt P</span><span id="ap-val">6.0</span></div>
          <input type="range" min="1" max="15" step="0.1" value="6.0" id="pid-ap" oninput="setPID('ap',this.value)">
        </div>
        <div class="nslider-wrap">
          <div class="nslider-label"><span>Sensitivity</span><span id="sens-val">38%</span></div>
          <input type="range" min="10" max="100" value="38" oninput="setSensitivity(this.value)">
        </div>
      </div>
    </div>

    <!-- Controls Ref -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>CONTROLS · MODE 2</div>
      <div style="font-size:10px;color:var(--txt4);margin-bottom:8px">🎮 Gamepad supported — plug in for analog input</div>

      <!-- Two-stick diagram -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
        <!-- Left stick -->
        <div style="background:var(--n);border-radius:var(--r2);padding:8px;box-shadow:var(--sh-in-sm)">
          <div style="font-size:9px;font-weight:700;color:var(--s);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;text-align:center">LEFT STICK</div>
          <div style="display:flex;flex-direction:column;gap:3px">
            <div style="display:flex;justify-content:space-between;font-size:10px"><span style="color:var(--txt4)">↑ Throttle+</span><div class="kd" style="font-size:9px">W</div></div>
            <div style="display:flex;justify-content:space-between;font-size:10px"><span style="color:var(--txt4)">↓ Throttle−</span><div class="kd" style="font-size:9px">S</div></div>
            <div style="display:flex;justify-content:space-between;font-size:10px"><span style="color:var(--txt4)">← Yaw Left</span><div class="kd" style="font-size:9px">A</div></div>
            <div style="display:flex;justify-content:space-between;font-size:10px"><span style="color:var(--txt4)">→ Yaw Right</span><div class="kd" style="font-size:9px">D</div></div>
          </div>
        </div>
        <!-- Right stick -->
        <div style="background:var(--n);border-radius:var(--r2);padding:8px;box-shadow:var(--sh-in-sm)">
          <div style="font-size:9px;font-weight:700;color:var(--p);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;text-align:center">RIGHT STICK</div>
          <div style="display:flex;flex-direction:column;gap:3px">
            <div style="display:flex;justify-content:space-between;font-size:10px"><span style="color:var(--txt4)">↑ Pitch Fwd</span><div class="kd" style="font-size:9px">↑</div></div>
            <div style="display:flex;justify-content:space-between;font-size:10px"><span style="color:var(--txt4)">↓ Pitch Back</span><div class="kd" style="font-size:9px">↓</div></div>
            <div style="display:flex;justify-content:space-between;font-size:10px"><span style="color:var(--txt4)">← Roll Left</span><div class="kd" style="font-size:9px">←</div></div>
            <div style="display:flex;justify-content:space-between;font-size:10px"><span style="color:var(--txt4)">→ Roll Right</span><div class="kd" style="font-size:9px">→</div></div>
          </div>
        </div>
      </div>

      <!-- Hotkeys grid -->
      <div style="font-size:9px;font-weight:700;color:var(--txt4);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px">ACTION HOTKEYS</div>
      <div class="kbd-row">
        <div class="kbd"><div class="kd">␣</div>Arm</div>
        <div class="kbd"><div class="kd">T</div>Takeoff</div>
        <div class="kbd"><div class="kd">H</div>Hover</div>
        <div class="kbd"><div class="kd">G</div>GPS</div>
        <div class="kbd"><div class="kd">R</div>RTH</div>
        <div class="kbd"><div class="kd">X</div>Stop</div>
        <div class="kbd"><div class="kd">F</div>FPV</div>
        <div class="kbd"><div class="kd">C</div>Cam</div>
        <div class="kbd"><div class="kd">M</div>WP</div>
        <div class="kbd"><div class="kd">P</div>Pause</div>
        <div class="kbd"><div class="kd">[ ]</div>Speed</div>
      </div>

      <!-- Flight mode shortcuts -->
      <div style="font-size:9px;font-weight:700;color:var(--txt4);letter-spacing:1px;text-transform:uppercase;margin:8px 0 5px">FLIGHT MODE KEYS</div>
      <div class="kbd-row">
        <div class="kbd"><div class="kd">1</div>Stab</div>
        <div class="kbd"><div class="kd">2</div>Angle</div>
        <div class="kbd"><div class="kd">3</div>Acro</div>
        <div class="kbd"><div class="kd">4</div>Alt✦</div>
        <div class="kbd"><div class="kd">5</div>GPS✦</div>
      </div>
    </div>
  </div>

  <!-- 3D Viewport -->
  <div id="viewport">
    <canvas id="threeCanvas"></canvas>
    <div id="vp-grain"></div>
    <div id="vp-chroma"></div>
    <div class="vp-tl"></div><div class="vp-tr"></div>
    <div class="vp-bl"></div><div class="vp-br"></div>
    <div class="cam-badge" id="cam-badge">THIRD PERSON</div>
    <div class="crosshair"><div class="ch-h"></div><div class="ch-v"></div></div>
    <div class="vp-warn" id="vp-warn">⚠ LOW ALTITUDE</div>
  </div>

  <!-- Right Panel -->
  <div id="rpanel">

    <!-- Primary Telemetry -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>ATTITUDE <span class="hz-badge">50 Hz</span></div>
      <div class="tval-row" style="margin-bottom:7px">
        <div class="tval"><div class="tval-label">ALT</div><div class="tval-num" id="t-alt">0.0</div><div class="tval-unit">m AGL</div></div>
        <div class="tval"><div class="tval-label">VEL</div><div class="tval-num" id="t-vel">0.0</div><div class="tval-unit">m/s</div></div>
        <div class="tval"><div class="tval-label">HDNG</div><div class="tval-num" id="t-hdng">000</div><div class="tval-unit">deg</div></div>
      </div>
      <div class="tval-row">
        <div class="tval"><div class="tval-label">PITCH</div><div class="tval-num" id="t-pitch">0.0</div><div class="tval-unit">deg</div></div>
        <div class="tval"><div class="tval-label">ROLL</div><div class="tval-num" id="t-roll">0.0</div><div class="tval-unit">deg</div></div>
        <div class="tval"><div class="tval-label">YAW</div><div class="tval-num" id="t-yaw">0.0</div><div class="tval-unit">deg</div></div>
      </div>
    </div>

    <!-- Attitude Indicator -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>ATTITUDE</div>
      <div class="attitude-wrap">
        <canvas id="attCanvas" width="120" height="120"></canvas>
      </div>
    </div>

    <!-- Battery -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>POWER <span class="hz-badge">2 Hz</span></div>
      <div style="display:flex;flex-direction:column;gap:7px">
        <div class="bgauge-wrap">
          <div class="bgauge-label"><span>Battery</span><span id="batt-pct">100%</span></div>
          <div class="bgauge-track"><div class="bgauge-fill green" id="batt-bar" style="width:100%"></div></div>
        </div>
        <div class="tval-row">
          <div class="tval"><div class="tval-label">VOLT</div><div class="tval-num" id="t-volt">16.8</div><div class="tval-unit">V</div></div>
          <div class="tval"><div class="tval-label">CURR</div><div class="tval-num" id="t-curr">0.0</div><div class="tval-unit">A</div></div>
          <div class="tval"><div class="tval-label">ETA</div><div class="tval-num" id="t-batt-eta">--</div><div class="tval-unit">min</div></div>
        </div>
      </div>
    </div>

    <!-- GPS_RAW_INT -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>GPS_RAW_INT <span class="hz-badge">5 Hz</span></div>
      <div style="display:flex;flex-direction:column;gap:7px">
        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
          <span class="gps-fix-badge gps-fix-3d" id="gps-fix-badge">3D FIX</span>
          <span style="font-size:10px;color:var(--txt3)">SATS: <span class="gps-coord" id="gps-sat-count">14</span></span>
          <span style="font-size:10px;color:var(--txt3)">HDOP: <span class="gps-coord" id="gps-hdop">0.9</span></span>
        </div>
        <div class="gps-sat-row" id="gps-sat-row"></div>
        <div style="display:flex;flex-direction:column;gap:3px">
          <div style="font-size:9px;color:var(--txt4);font-weight:600;letter-spacing:.6px;text-transform:uppercase">Coordinates</div>
          <div style="display:flex;gap:4px">
            <div class="tval" style="flex:1"><div class="tval-label">LAT</div><div class="tval-num" style="font-size:12px" id="gps-lat">17.0005</div><div class="tval-unit">°N</div></div>
            <div class="tval" style="flex:1"><div class="tval-label">LON</div><div class="tval-num" style="font-size:12px" id="gps-lon">82.2458</div><div class="tval-unit">°E</div></div>
          </div>
          <div class="tval"><div class="tval-label">ALT MSL</div><div class="tval-num" id="gps-alt">12.0</div><div class="tval-unit">m</div></div>
        </div>
      </div>
    </div>

    <!-- Motors -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>MOTORS</div>
      <div class="motors-grid">
        <div class="motor-item"><div class="motor-header"><span class="motor-label">FR M1</span><span class="motor-rpm" id="m0-rpm">0</span></div><div class="motor-bar-wrap"><div class="motor-bar" id="m0-bar" style="width:0%"></div></div></div>
        <div class="motor-item"><div class="motor-header"><span class="motor-label">FL M2</span><span class="motor-rpm" id="m1-rpm">0</span></div><div class="motor-bar-wrap"><div class="motor-bar" id="m1-bar" style="width:0%"></div></div></div>
        <div class="motor-item"><div class="motor-header"><span class="motor-label">BL M3</span><span class="motor-rpm" id="m2-rpm">0</span></div><div class="motor-bar-wrap"><div class="motor-bar" id="m2-bar" style="width:0%"></div></div></div>
        <div class="motor-item"><div class="motor-header"><span class="motor-label">BR M4</span><span class="motor-rpm" id="m3-rpm">0</span></div><div class="motor-bar-wrap"><div class="motor-bar" id="m3-bar" style="width:0%"></div></div></div>
      </div>
    </div>

    <!-- VISION_POSITION (VSLAM) -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>VISION_POSITION <span class="hz-badge">30 Hz</span></div>
      <div>
        <span class="vslam-badge vslam-idle" id="vslam-badge">GPS ACTIVE</span>
        <div class="tval-row" style="margin-top:6px">
          <div class="tval"><div class="tval-label">V-X</div><div class="tval-num" id="vslam-x">0.00</div><div class="tval-unit">m</div></div>
          <div class="tval"><div class="tval-label">V-Y</div><div class="tval-num" id="vslam-y">0.00</div><div class="tval-unit">m</div></div>
          <div class="tval"><div class="tval-label">V-Z</div><div class="tval-num" id="vslam-z">0.00</div><div class="tval-unit">m</div></div>
        </div>
        <div style="margin-top:5px">
          <div style="display:flex;justify-content:space-between;font-size:9px;color:var(--txt4)"><span>VSLAM Quality</span><span id="vslam-quality-val">100%</span></div>
          <div class="vslam-quality"><div class="vslam-quality-fill" id="vslam-quality" style="width:100%"></div></div>
        </div>
      </div>
    </div>

    <!-- OBSTACLE_DISTANCE -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>OBSTACLE_DISTANCE <span class="hz-badge">10 Hz</span></div>
      <div style="display:flex;align-items:center;gap:10px">
        <!-- Radar SVG -->
        <svg id="obs-radar-svg" width="80" height="80" viewBox="-40 -40 80 80" style="flex-shrink:0">
          <circle class="obs-ring" cx="0" cy="0" r="35"/>
          <circle class="obs-ring" cx="0" cy="0" r="22"/>
          <circle class="obs-ring" cx="0" cy="0" r="10"/>
          <line x1="0" y1="-35" x2="0" y2="35" stroke="rgba(96,125,139,0.15)" stroke-width="1"/>
          <line x1="-35" y1="0" x2="35" y2="0" stroke="rgba(96,125,139,0.15)" stroke-width="1"/>
          <!-- Sector arcs (will be animated by JS) -->
          <g id="obs-sectors"></g>
          <!-- Drone dot -->
          <circle cx="0" cy="0" r="3" fill="var(--s)"/>
          <!-- Labels -->
          <text class="obs-sector-label" x="0" y="-37" text-anchor="middle">F</text>
          <text class="obs-sector-label" x="37" y="3" text-anchor="start">R</text>
          <text class="obs-sector-label" x="0" y="41" text-anchor="middle">B</text>
          <text class="obs-sector-label" x="-37" y="3" text-anchor="end">L</text>
        </svg>
        <!-- Sector bars -->
        <div style="flex:1;display:flex;flex-direction:column;gap:4px">
          <div class="obs-bar-row"><div class="obs-bar-lbl">FWD</div><div class="obs-bar-track"><div class="obs-bar-fill" id="obs-fwd" style="width:100%"></div></div><div class="obs-bar-lbl" id="obs-fwd-v">12m</div></div>
          <div class="obs-bar-row"><div class="obs-bar-lbl">RGT</div><div class="obs-bar-track"><div class="obs-bar-fill" id="obs-right" style="width:100%"></div></div><div class="obs-bar-lbl" id="obs-right-v">12m</div></div>
          <div class="obs-bar-row"><div class="obs-bar-lbl">BCK</div><div class="obs-bar-track"><div class="obs-bar-fill" id="obs-back" style="width:100%"></div></div><div class="obs-bar-lbl" id="obs-back-v">12m</div></div>
          <div class="obs-bar-row"><div class="obs-bar-lbl">LFT</div><div class="obs-bar-track"><div class="obs-bar-fill" id="obs-left" style="width:100%"></div></div><div class="obs-bar-lbl" id="obs-left-v">12m</div></div>
          <div class="obs-bar-row"><div class="obs-bar-lbl"> UP</div><div class="obs-bar-track"><div class="obs-bar-fill" id="obs-up" style="width:100%"></div></div><div class="obs-bar-lbl" id="obs-up-v">--m</div></div>
        </div>
      </div>
    </div>

    <!-- PID TELEMETRY -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>PID TELEMETRY <span class="hz-badge">Live</span></div>
      <div class="pid-telem-grid">
        <!-- Roll axis -->
        <div class="pid-axis-card">
          <div class="pid-axis-title">ROLL <span class="pid-axis-live" id="pid-roll-err-lbl">±0.000</span></div>
          <div class="pid-gains-row">
            <div class="pid-gain"><div class="pid-gain-lbl">Kp</div><div class="pid-gain-val" id="pid-roll-kp">0.042</div></div>
            <div class="pid-gain"><div class="pid-gain-lbl">Ki</div><div class="pid-gain-val" id="pid-roll-ki">0.000</div></div>
            <div class="pid-gain"><div class="pid-gain-lbl">Kd</div><div class="pid-gain-val" id="pid-roll-kd">0.002</div></div>
          </div>
          <div class="pid-err-track"><div class="pid-err-fill" id="pid-roll-err" style="width:0%;left:50%"></div></div>
        </div>
        <!-- Pitch axis -->
        <div class="pid-axis-card">
          <div class="pid-axis-title">PITCH <span class="pid-axis-live" id="pid-pitch-err-lbl">±0.000</span></div>
          <div class="pid-gains-row">
            <div class="pid-gain"><div class="pid-gain-lbl">Kp</div><div class="pid-gain-val" id="pid-pitch-kp">0.042</div></div>
            <div class="pid-gain"><div class="pid-gain-lbl">Ki</div><div class="pid-gain-val" id="pid-pitch-ki">0.000</div></div>
            <div class="pid-gain"><div class="pid-gain-lbl">Kd</div><div class="pid-gain-val" id="pid-pitch-kd">0.002</div></div>
          </div>
          <div class="pid-err-track"><div class="pid-err-fill" id="pid-pitch-err" style="width:0%;left:50%"></div></div>
        </div>
        <!-- Yaw axis -->
        <div class="pid-axis-card">
          <div class="pid-axis-title">YAW <span class="pid-axis-live" id="pid-yaw-err-lbl">±0.000</span></div>
          <div class="pid-gains-row">
            <div class="pid-gain"><div class="pid-gain-lbl">Kp</div><div class="pid-gain-val" id="pid-yaw-kp">0.065</div></div>
            <div class="pid-gain"><div class="pid-gain-lbl">Ki</div><div class="pid-gain-val" id="pid-yaw-ki">0.012</div></div>
            <div class="pid-gain"><div class="pid-gain-lbl">Kd</div><div class="pid-gain-val" id="pid-yaw-kd">0.000</div></div>
          </div>
          <div class="pid-err-track"><div class="pid-err-fill" id="pid-yaw-err" style="width:0%;left:50%"></div></div>
        </div>
        <!-- Throttle/Alt axis -->
        <div class="pid-axis-card">
          <div class="pid-axis-title">THR/ALT <span class="pid-axis-live" id="pid-thr-err-lbl">±0.000</span></div>
          <div class="pid-gains-row">
            <div class="pid-gain"><div class="pid-gain-lbl">Kp</div><div class="pid-gain-val" id="pid-thr-kp">1.6</div></div>
            <div class="pid-gain"><div class="pid-gain-lbl">Ki</div><div class="pid-gain-val" id="pid-thr-ki">0.10</div></div>
            <div class="pid-gain"><div class="pid-gain-lbl">Kd</div><div class="pid-gain-val" id="pid-thr-kd">0.06</div></div>
          </div>
          <div class="pid-err-track"><div class="pid-err-fill" id="pid-thr-err" style="width:0%;left:50%"></div></div>
        </div>
      </div>
      <div style="margin-top:6px;font-size:9px;color:var(--txt4);text-align:center">Error signal ← centre=0 →  |  copy Kp/Ki/Kd directly to Betaflight/ArduPilot/PX4</div>
    </div>

    <!-- Minimap -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>MINIMAP</div>
      <div class="minimap"><canvas id="miniCanvas"></canvas><div class="minimap-badge" id="minimap-badge">0,0</div></div>
    </div>

    <!-- Position -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>POSITION</div>
      <div class="tval-row">
        <div class="tval"><div class="tval-label">X</div><div class="tval-num" id="t-px">0.0</div><div class="tval-unit">m</div></div>
        <div class="tval"><div class="tval-label">Y</div><div class="tval-num" id="t-py">0.0</div><div class="tval-unit">m</div></div>
        <div class="tval"><div class="tval-label">Z</div><div class="tval-num" id="t-pz">0.0</div><div class="tval-unit">m</div></div>
      </div>
    </div>

    <!-- Wind -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>WIND</div>
      <div class="wind-wrap">
        <div class="wind-compass"><canvas id="windCanvas" width="52" height="52"></canvas></div>
        <div style="flex:1;display:flex;flex-direction:column;gap:4px">
          <div class="tval-row2">
            <div class="tval"><div class="tval-label">SPEED</div><div class="tval-num" id="t-wind">0.0</div><div class="tval-unit">m/s</div></div>
            <div class="tval"><div class="tval-label">GUST</div><div class="tval-num" id="t-gust">0.0</div><div class="tval-unit">m/s</div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Warnings -->
    <div class="card card-sm">
      <div class="card-title"><span class="ct-dot"></span>HEARTBEAT / SYSTEM <span class="hz-badge">1 Hz</span></div>
      <div class="warn-list" id="warn-list">
        <div class="warn-item"><div class="warn-dot ok"></div><span>Systems Nominal</span></div>
      </div>
      <div style="margin-top:8px">
        <div class="log-list" id="log-list"></div>
      </div>
    </div>

    <!-- Telemetry Graph -->
    <div class="card card-sm" id="tgraph-card">
      <div class="card-title"><span class="ct-dot"></span>LIVE TELEMETRY GRAPH</div>
      <div class="tgraph-legend">
        <div class="tgl-item on" id="tgl-alt" onclick="toggleTGraph('alt')" title="Altitude"><div class="tgl-dot" style="background:#10256D"></div>ALT</div>
        <div class="tgl-item on" id="tgl-vel" onclick="toggleTGraph('vel')" title="Speed"><div class="tgl-dot" style="background:#EE9346"></div>VEL</div>
        <div class="tgl-item" id="tgl-roll" onclick="toggleTGraph('roll')" title="Roll angle"><div class="tgl-dot" style="background:#43A047"></div>ROLL</div>
        <div class="tgl-item" id="tgl-pitch" onclick="toggleTGraph('pitch')" title="Pitch angle"><div class="tgl-dot" style="background:#E53935"></div>PITCH</div>
        <div class="tgl-item" id="tgl-batt" onclick="toggleTGraph('batt')" title="Battery %"><div class="tgl-dot" style="background:#9C27B0"></div>BATT</div>
      </div>
      <canvas class="graph-canvas" id="telemGraph" width="220" height="80"></canvas>
    </div>

    <!-- Export / Blackbox -->
    <div class="card card-sm" id="export-card">
      <div class="card-title"><span class="ct-dot"></span>FLIGHT LOG · BLACKBOX <span class="hz-badge">Physics tick</span></div>
      <div class="export-panel">
        <div class="nbtn-row">
          <button class="nbtn sm accent" id="rec-btn" onclick="toggleRecording()">⏺ Record</button>
          <button class="nbtn sm" onclick="BLACKBOX.stop();BLACKBOX.download();UI.toast('💾 CSV saved')">💾 CSV</button>
          <button class="nbtn sm primary" onclick="exportMAVLink()">📡 MAVLink</button>
          <button class="nbtn sm" onclick="exportJSON()">{ } JSON</button>
        </div>
        <div id="export-stats" class="export-stat-row" style="display:none">
          <div class="export-stat"><div class="es-val" id="es-dur">0s</div><div class="es-lbl">Duration</div></div>
          <div class="export-stat"><div class="es-val" id="es-samp">0</div><div class="es-lbl">Samples</div></div>
          <div class="export-stat"><div class="es-val" id="es-maxalt">0m</div><div class="es-lbl">Max Alt</div></div>
          <div class="export-stat"><div class="es-val" id="es-maxvel">0</div><div class="es-lbl">Max m/s</div></div>
        </div>
      </div>
    </div>

    <!-- Debug (hidden by default) -->
    <div class="card card-sm" id="debug-section" style="display:none">
      <div class="card-title"><span class="ct-dot"></span>DEBUG PID</div>
      <canvas class="graph-canvas" id="pidGraph" width="220" height="60"></canvas>
      <div style="margin-top:8px"></div>
      <canvas class="graph-canvas" id="gyroGraph" width="220" height="60"></canvas>
      <div style="margin-top:8px">
        <button class="nbtn sm" onclick="DEBUG.toggle();document.getElementById('debug-section').style.display=DEBUG.enabled?'block':'none'">🔬 Toggle Debug</button>
      </div>
    </div>
  </div>

  <!-- Bottom Bar — Virtual Joysticks + Stick Visualizer -->
  <div id="bottombar">

    <!-- Left Virtual Joystick (Throttle / Yaw) -->
    <div class="card card-sm" style="padding:10px;display:flex;flex-direction:column;align-items:center;gap:6px">
      <div class="card-title" style="margin-bottom:0;align-self:flex-start"><span class="ct-dot" style="background:var(--s)"></span>LEFT STICK · THR/YAW</div>
      <div style="display:flex;align-items:center;gap:10px;width:100%">
        <div id="vj-left" class="vj-pad" data-stick="left" style="touch-action:none">
          <div class="vj-center"></div>
          <div class="vj-knob" id="vj-left-knob"></div>
          <div class="vj-label-t">THR+</div>
          <div class="vj-label-b">THR−</div>
          <div class="vj-label-l">YAW L</div>
          <div class="vj-label-r">YAW R</div>
        </div>
        <div style="flex:1;display:flex;flex-direction:column;gap:5px">
          <div style="font-size:9px;color:var(--txt4);font-weight:600;text-transform:uppercase;letter-spacing:.8px">W/S = Throttle</div>
          <div style="font-size:9px;color:var(--txt4);font-weight:600;text-transform:uppercase;letter-spacing:.8px">A/D = Yaw</div>
          <div style="font-size:9px;color:var(--txt4);font-weight:600;text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Live Input</div>
          <div class="stick-meter-wrap">
            <div class="stick-meter-lbl">THR</div>
            <div class="stick-meter-track"><div class="stick-meter-fill accent" id="sm-thr" style="width:0%"></div></div>
          </div>
          <div class="stick-meter-wrap">
            <div class="stick-meter-lbl">YAW</div>
            <div class="stick-meter-track bidir"><div class="stick-meter-fill primary" id="sm-yaw" style="width:0%;left:50%"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Centre: Stick Visualizer + Mission -->
    <div class="card card-sm" style="padding:10px">
      <div class="card-title" style="margin-bottom:6px"><span class="ct-dot"></span>STICK MONITOR · NAVIGATION</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
        <!-- Left stick XY canvas -->
        <div style="display:flex;flex-direction:column;align-items:center;gap:3px">
          <canvas id="stick-viz-l" width="76" height="76" class="stick-viz-canvas"></canvas>
          <div style="font-size:9px;color:var(--txt4);font-weight:600">LEFT (THR/YAW)</div>
        </div>
        <!-- Right stick XY canvas -->
        <div style="display:flex;flex-direction:column;align-items:center;gap:3px">
          <canvas id="stick-viz-r" width="76" height="76" class="stick-viz-canvas"></canvas>
          <div style="font-size:9px;color:var(--txt4);font-weight:600">RIGHT (PITCH/ROLL)</div>
        </div>
      </div>
      <!-- Nav values -->
      <div class="tval-row">
        <div class="tval"><div class="tval-label">V-X</div><div class="tval-num" id="t-vx">0.0</div><div class="tval-unit">m/s</div></div>
        <div class="tval"><div class="tval-label">V-Y</div><div class="tval-num" id="t-vy">0.0</div><div class="tval-unit">m/s</div></div>
        <div class="tval"><div class="tval-label">V-Z</div><div class="tval-num" id="t-vz">0.0</div><div class="tval-unit">m/s</div></div>
      </div>
    </div>

    <!-- Right Virtual Joystick (Pitch / Roll) -->
    <div class="card card-sm" style="padding:10px;display:flex;flex-direction:column;align-items:center;gap:6px">
      <div class="card-title" style="margin-bottom:0;align-self:flex-start"><span class="ct-dot"></span>RIGHT STICK · PITCH/ROLL</div>
      <div style="display:flex;align-items:center;gap:10px;width:100%">
        <div style="flex:1;display:flex;flex-direction:column;gap:5px">
          <div style="font-size:9px;color:var(--txt4);font-weight:600;text-transform:uppercase;letter-spacing:.8px">↑↓ = Pitch</div>
          <div style="font-size:9px;color:var(--txt4);font-weight:600;text-transform:uppercase;letter-spacing:.8px">←→ = Roll</div>
          <div style="font-size:9px;color:var(--txt4);font-weight:600;text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Live Input</div>
          <div class="stick-meter-wrap">
            <div class="stick-meter-lbl">PCH</div>
            <div class="stick-meter-track bidir"><div class="stick-meter-fill accent" id="sm-pitch" style="width:0%;left:50%"></div></div>
          </div>
          <div class="stick-meter-wrap">
            <div class="stick-meter-lbl">RLL</div>
            <div class="stick-meter-track bidir"><div class="stick-meter-fill primary" id="sm-roll" style="width:0%;left:50%"></div></div>
          </div>
        </div>
        <div id="vj-right" class="vj-pad" data-stick="right" style="touch-action:none">
          <div class="vj-center"></div>
          <div class="vj-knob" id="vj-right-knob"></div>
          <div class="vj-label-t">PITCH↑</div>
          <div class="vj-label-b">PITCH↓</div>
          <div class="vj-label-l">ROLL L</div>
          <div class="vj-label-r">ROLL R</div>
        </div>
      </div>
    </div>

  </div><!-- end #bottombar -->

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
<script src="sim-engine.js"></script>

<!-- ══ TIER: PRO ══ -->
<script>
/* PLAN FLAGS — PRO tier
   Duration: 24h | 2 profiles | 3 envs | Basic HUD
   PID: view-only | MAVLink: read-only
   No: waypoints, export, GLTF, gamepad */
const PLAN = {
  tier: 'PRO',
  sessionMinutes: 1440,
  droneProfiles: ['racing5', 'micro2'],
  environments: ['field', 'mountains', 'urban'],
  waypointMissions: false,
  pidTuning: 'view',
  dataExport: false,
  mavlinkLogs: 'readonly',
  customGLTF: false,
  joystickGamepad: false,
  hudLevel: 'basic',
  nightMode: true,
  windScenario: true,
  support: 'community',
  tierLabel: 'PRO',
  tierColor: '#607D8B',
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

  // Chunk system
  const CHUNK_SIZE = 80;
  const CHUNK_SEGS = 48;
  const RENDER_DIST = 3; // chunks in each direction
  let _chunks = new Map(); // key -> {mesh, veg, rocks, x, z}
  let _lastChunkX = null, _lastChunkZ = null;

  // Mouse orbit
  let _mouse = { down:false, lx:0, ly:0 };

  // Bloom compositing
  let _bloomRT, _bloomScene, _bloomCamera, _bloomQuad;
  let _mainRT;

  // ── Terrain heightmap (chunk-aware) ──────────────────────────────
  function terrainHeight(x, z, envName) {
    const env = envName || _envName;
    switch(env) {
      case 'mountains': {
        const h = Noise.fbm(x*0.012, 0, z*0.012, 6, 0.55, 2.1) * 60
                + Noise.fbm(x*0.04,  0, z*0.04,  3, 0.45, 2.0) * 12
                + Noise.fbm(x*0.12,  0, z*0.12,  2, 0.4,  2.0) * 4;
        return Math.max(0, h);
      }
      case 'desert': {
        const dune = Noise.fbm(x*0.025, 0.3, z*0.025, 4, 0.45, 2.0) * 18
                   + Noise.fbm(x*0.08,  0.7, z*0.08,  3, 0.4, 2.0) * 5;
        return Math.max(0, dune);
      }
      case 'urban': return 0;
      case 'indoor': return 0;
      case 'field':
      case 'windy':
      default: {
        return Math.max(0,
          Noise.fbm(x*0.015, 0.5, z*0.015, 4, 0.5, 2.0) * 8
        + Noise.fbm(x*0.06,  1.2, z*0.06,  3, 0.4, 2.0) * 2.5
        + Noise.fbm(x*0.15,  2.3, z*0.15,  2, 0.35,2.0) * 0.8);
      }
    }
  }

  // ── Terrain colour helper ──────────────────────────────────────────
  function terrainColor(x, z, h, envName) {
    const env = envName || _envName;
    let r, g, b;
    if (env === 'desert') {
      const v = Noise.n(x*0.18, 0, z*0.18)*0.06;
      r = 0.80 + h*0.005 + v; g = 0.65 + h*0.003 + v; b = 0.32 + v;
    } else if (env === 'mountains') {
      if      (h < 2)  { r=0.32; g=0.52; b=0.20; }
      else if (h < 12) { const t=h/12; r=0.28+t*0.22; g=0.46+t*0.06; b=0.16+t*0.12; }
      else if (h < 30) { const t=(h-12)/18; r=0.50+t*0.18; g=0.44+t*0.04; b=0.38+t*0.08; }
      else if (h < 45) { const t=(h-30)/15; r=0.68+t*0.14; g=0.62+t*0.18; b=0.58+t*0.22; }
      else             { r=0.92; g=0.94; b=0.96; }
    } else if (env === 'urban') {
      r=0.36; g=0.36; b=0.36;
    } else {
      // Lush field — variation between meadow greens
      const v  = Noise.n(x*0.12, 0, z*0.12)*0.10;
      const v2 = Noise.n(x*0.35, 1, z*0.35)*0.04;
      const moisture = Noise.fbm(x*0.02, 3, z*0.02, 2, 0.5, 2)*0.5+0.5;
      r = 0.18 + v + v2 + h*0.008 - moisture*0.04;
      g = 0.48 + h*0.015 + v*0.5 + moisture*0.08;
      b = 0.14 + v2 - moisture*0.02;
    }
    return [Math.min(1,Math.max(0,r)), Math.min(1,Math.max(0,g)), Math.min(1,Math.max(0,b))];
  }

  // ── Single chunk terrain mesh ──────────────────────────────────────
  function buildChunkMesh(cx, cz, envName) {
    const geo = new THREE.PlaneGeometry(CHUNK_SIZE, CHUNK_SIZE, CHUNK_SEGS, CHUNK_SEGS);
    geo.rotateX(-Math.PI/2);
    const pos = geo.attributes.position;
    const colors = [];
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    for (let i = 0; i < pos.count; i++) {
      const wx = pos.getX(i) + worldOffX;
      const wz = pos.getZ(i) + worldOffZ;
      const h = terrainHeight(wx, wz, envName);
      pos.setY(i, h);
      const [r,g,b] = terrainColor(wx, wz, h, envName);
      colors.push(r, g, b);
    }
    geo.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
    geo.computeVertexNormals();
    const mat = new THREE.MeshLambertMaterial({ vertexColors: true });
    const mesh = new THREE.Mesh(geo, mat);
    mesh.position.set(worldOffX, 0, worldOffZ);
    mesh.receiveShadow = true;
    mesh.name = 'terrain_chunk';
    return mesh;
  }

  // ── Grass blade system (per-chunk) ────────────────────────────────
  let _grassTime = 0;
  function buildGrassBlades(cx, cz, envName) {
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor' || env === 'desert') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const count = env === 'mountains' ? 200 : 600;
    const positions = [], colors2 = [], indices2 = [];
    let vi = 0;
    // Each blade: 3 quads (6 verts)
    for (let i = 0; i < count; i++) {
      const lx = (Math.random()-0.5)*CHUNK_SIZE;
      const lz = (Math.random()-0.5)*CHUNK_SIZE;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, wz, env);
      const h = 0.18 + Math.random()*0.22;
      const ang = Math.random()*Math.PI*2;
      const bx = Math.cos(ang)*0.04, bz = Math.sin(ang)*0.04;
      // color variation
      const gv = 0.3 + Math.random()*0.3;
      const rc = 0.1+gv*0.3, gc = 0.4+gv*0.35, bc = 0.05+gv*0.1;
      // base L
      positions.push(wx-bx, hy, wz-bz, wx+bx, hy, wz+bz, wx, hy+h, wz);
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
  function buildFlowers(cx, cz, envName) {
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor' || env === 'desert' || env === 'mountains') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const group = new THREE.Group();
    const flowerColors = [0xff4466, 0xffcc22, 0xff8844, 0xee66ff, 0xffffff, 0x66ddff];
    const stemMat = new THREE.MeshLambertMaterial({ color: 0x2d7a1a });
    const count = 30 + Math.floor(Math.random()*40);
    for (let i = 0; i < count; i++) {
      const lx = (Math.random()-0.5)*CHUNK_SIZE;
      const lz = (Math.random()-0.5)*CHUNK_SIZE;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, wz, env);
      const h = 0.14 + Math.random()*0.12;
      // stem
      const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.006,0.008,h,4), stemMat);
      stem.position.set(wx, hy+h/2, wz);
      group.add(stem);
      // petals
      const col = flowerColors[Math.floor(Math.random()*flowerColors.length)];
      const petMat = new THREE.MeshLambertMaterial({ color: col, side: THREE.DoubleSide });
      const pCount = 4 + Math.floor(Math.random()*3);
      for (let p = 0; p < pCount; p++) {
        const pa = (p/pCount)*Math.PI*2;
        const pet = new THREE.Mesh(new THREE.PlaneGeometry(0.05,0.04), petMat);
        pet.position.set(wx+Math.cos(pa)*0.04, hy+h+0.02, wz+Math.sin(pa)*0.04);
        pet.rotation.y = pa; pet.rotation.x = -0.4;
        group.add(pet);
      }
      // center
      const cMat = new THREE.MeshBasicMaterial({ color: 0xffee00 });
      const cen = new THREE.Mesh(new THREE.SphereGeometry(0.018,6,4), cMat);
      cen.position.set(wx, hy+h+0.018, wz);
      group.add(cen);
    }
    return group;
  }

  // ── Rocks ──────────────────────────────────────────────────────────
  function buildRocks(cx, cz, envName) {
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const group = new THREE.Group();
    const count = env === 'mountains' ? 20 : env === 'desert' ? 12 : 8;
    const rockColors = [0x888880, 0x706a60, 0x999288, 0x7a7268];
    for (let i = 0; i < count; i++) {
      const lx = (Math.random()-0.5)*CHUNK_SIZE;
      const lz = (Math.random()-0.5)*CHUNK_SIZE;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, wz, env);
      const scale = 0.2 + Math.random()*0.8;
      const col = rockColors[Math.floor(Math.random()*rockColors.length)];
      const mat = new THREE.MeshStandardMaterial({ color: col, roughness: 0.9, metalness: 0.05 });
      // Irregular rock shape from scaled sphere
      const geo = new THREE.SphereGeometry(scale, 6, 5);
      const verts = geo.attributes.position;
      for (let v = 0; v < verts.count; v++) {
        const nx = verts.getX(v), ny = verts.getY(v), nz = verts.getZ(v);
        const bump = 1 + Noise.n(nx*2+wx*0.1, ny*2, nz*2+wz*0.1)*0.35;
        verts.setXYZ(v, nx*bump, ny*bump*(0.5+Math.random()*0.4), nz*bump);
      }
      geo.computeVertexNormals();
      const rock = new THREE.Mesh(geo, mat);
      rock.position.set(wx, hy + scale*0.35, wz);
      rock.rotation.y = Math.random()*Math.PI*2;
      rock.castShadow = true; rock.receiveShadow = true;
      group.add(rock);
    }
    return group;
  }

  // ── Trees (lush, varied) ──────────────────────────────────────────
  function buildVegetation(cx, cz, envName) {
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
      const lx = (Math.random()-0.5)*CHUNK_SIZE*0.85;
      const lz = (Math.random()-0.5)*CHUNK_SIZE*0.85;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, wz, env);
      if (env === 'mountains' && hy > 30) continue;
      const treeType = Math.floor(Math.random()*3);
      const leafCol = leafColors[Math.floor(Math.random()*leafColors.length)];
      const leafMat = new THREE.MeshStandardMaterial({ color: leafCol, roughness: 0.85, metalness: 0 });
      if (treeType === 0) {
        // Pine / conifer
        const tH = 3 + Math.random()*4;
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.18, tH, 6), trunkMat.clone());
        trunk.position.set(wx, hy + tH/2, wz);
        trunk.castShadow = true;
        group.add(trunk);
        // Stacked cones
        const tiers = 3 + Math.floor(Math.random()*2);
        for (let t = 0; t < tiers; t++) {
          const ty = hy + tH*0.4 + t*(tH*0.22);
          const r = 1.6 - t*0.3 + Math.random()*0.3;
          const cone = new THREE.Mesh(new THREE.ConeGeometry(r, tH*0.35, 7), leafMat.clone());
          cone.position.set(wx, ty, wz);
          cone.castShadow = true;
          group.add(cone);
        }
      } else if (treeType === 1) {
        // Broad deciduous
        const tH = 2.5 + Math.random()*3;
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.22, tH, 7), darkTrunk.clone());
        trunk.position.set(wx, hy + tH/2, wz);
        trunk.castShadow = true;
        group.add(trunk);
        // Multi-sphere canopy
        const cr = 1.8 + Math.random()*1.4;
        const canopy = new THREE.Mesh(new THREE.SphereGeometry(cr, 8, 7), leafMat.clone());
        canopy.position.set(wx, hy + tH + cr*0.6, wz);
        canopy.scale.y = 0.72 + Math.random()*0.2;
        canopy.castShadow = true;
        group.add(canopy);
        // Extra lobes
        for (let l = 0; l < 3; l++) {
          const la = (l/3)*Math.PI*2 + Math.random()*0.8;
          const lr = cr*0.55;
          const lobe = new THREE.Mesh(new THREE.SphereGeometry(lr, 6, 5), leafMat.clone());
          lobe.position.set(wx+Math.cos(la)*cr*0.55, hy+tH+cr*0.3+Math.random()*0.5, wz+Math.sin(la)*cr*0.55);
          lobe.castShadow = true;
          group.add(lobe);
        }
      } else {
        // Tall slender birch
        const tH = 4 + Math.random()*5;
        const birchMat = new THREE.MeshStandardMaterial({ color: 0xddd8cc, roughness: 0.8 });
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.14, tH, 6), birchMat);
        trunk.position.set(wx, hy + tH/2, wz);
        trunk.castShadow = true;
        group.add(trunk);
        const brightLeaf = new THREE.MeshStandardMaterial({ color: 0x8ab840, roughness: 0.8 });
        const cr = 1.2 + Math.random()*0.8;
        const canopy = new THREE.Mesh(new THREE.SphereGeometry(cr, 7, 6), brightLeaf);
        canopy.position.set(wx, hy + tH + cr*0.5, wz);
        canopy.scale.y = 1.1;
        canopy.castShadow = true;
        group.add(canopy);
      }
    }
    return group;
  }

  // ── Buildings (urban) ─────────────────────────────────────────────
  function buildUrban() {
    const group = new THREE.Group();
    const bMats = [
      new THREE.MeshStandardMaterial({ color: 0x8090a0, roughness:0.7, metalness:0.2 }),
      new THREE.MeshStandardMaterial({ color: 0x607080, roughness:0.65, metalness:0.15 }),
      new THREE.MeshStandardMaterial({ color: 0x9aabbb, roughness:0.6, metalness:0.25 }),
    ];
    for (let i = 0; i < 40; i++) {
      const x = (Math.random()-0.5)*200;
      const z = (Math.random()-0.5)*200;
      const dist = Math.hypot(x,z);
      if (dist < 12) continue;
      const w = 4 + Math.random()*14, d = 4 + Math.random()*14, hh = 5 + Math.random()*35;
      const geo = new THREE.BoxGeometry(w, hh, d);
      const mesh = new THREE.Mesh(geo, bMats[i%3]);
      mesh.position.set(x, hh/2, z);
      mesh.castShadow = true; mesh.receiveShadow = true;
      group.add(mesh);
      PHYS.colliders.push({
        min:{x:x-w/2, y:0, z:z-d/2}, max:{x:x+w/2, y:hh, z:z+d/2},
        normal:{x:0,y:1,z:0},
      });
    }
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
      { alt: 55, spread: 380, count: 32, minR: 5, maxR: 18, opacity: 0.78 },
      { alt: 95, spread: 300, count: 18, minR: 8, maxR: 25, opacity: 0.55 },
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
        const clumpCount = 4 + Math.floor(Math.random()*7);
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

  // ── Rain ────────────────────────────────────────────────────────────
  function buildRain() {
    const count = 5000;
    const geo = new THREE.BufferGeometry();
    const pos = new Float32Array(count * 3);
    for (let i = 0; i < count; i++) {
      pos[i*3  ] = (Math.random()-0.5)*90;
      pos[i*3+1] = Math.random()*65;
      pos[i*3+2] = (Math.random()-0.5)*90;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const mat = new THREE.PointsMaterial({ color:0x99ccff, size:0.10, transparent:true, opacity:0.45, depthWrite:false });
    return { pts: new THREE.Points(geo, mat), geo, pos };
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
    const p = PHYS.pos;
    const last = _trailPoints[_trailPoints.length-1] || {x:9999,y:9999,z:9999};
    if (V3.len(V3.sub(p, last)) > 0.3) {
      _trailPoints.push({ x:p.x, y:p.y, z:p.z });
      if (_trailPoints.length > 500) _trailPoints.shift();
    }
    const buf = _trailLine.geometry.attributes.position.array;
    for (let i = 0; i < _trailPoints.length; i++) {
      buf[i*3  ] = _trailPoints[i].x;
      buf[i*3+1] = _trailPoints[i].y;
      buf[i*3+2] = _trailPoints[i].z;
    }
    _trailLine.geometry.setDrawRange(0, _trailPoints.length);
    _trailLine.geometry.attributes.position.needsUpdate = true;
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

  function _loadChunk(cx, cz) {
    const key = _chunkKey(cx, cz);
    if (_chunks.has(key)) return;
    const chunkData = {};
    // Terrain
    const mesh = buildChunkMesh(cx, cz, _envName);
    scene.add(mesh);
    chunkData.mesh = mesh;
    // Vegetation
    if (_envName !== 'indoor') {
      const veg = buildVegetation(cx, cz, _envName);
      if (veg) { scene.add(veg); chunkData.veg = veg; }
      const flowers = buildFlowers(cx, cz, _envName);
      if (flowers) { scene.add(flowers); chunkData.flowers = flowers; }
      const grass = buildGrassBlades(cx, cz, _envName);
      if (grass)   { scene.add(grass);   chunkData.grass = grass; }
      const rocks = buildRocks(cx, cz, _envName);
      if (rocks)   { scene.add(rocks);   chunkData.rocks = rocks; }
    }
    chunkData.cx = cx; chunkData.cz = cz;
    _chunks.set(key, chunkData);
  }

  function _unloadChunk(key) {
    const cd = _chunks.get(key);
    if (!cd) return;
    ['mesh','veg','flowers','grass','rocks'].forEach(k => {
      if (!cd[k]) return;
      scene.remove(cd[k]);
      cd[k].traverse(o => {
        if (o.geometry) o.geometry.dispose();
        if (o.material) {
          if (Array.isArray(o.material)) o.material.forEach(m=>m.dispose());
          else o.material.dispose();
        }
      });
    });
    _chunks.delete(key);
  }

  function _updateChunks() {
    const p = PHYS.pos;
    const cx = Math.round(p.x / CHUNK_SIZE);
    const cz = Math.round(p.z / CHUNK_SIZE);
    if (cx === _lastChunkX && cz === _lastChunkZ) return;
    _lastChunkX = cx; _lastChunkZ = cz;
    // Load needed chunks
    const needed = new Set();
    for (let dx = -RENDER_DIST; dx <= RENDER_DIST; dx++) {
      for (let dz = -RENDER_DIST; dz <= RENDER_DIST; dz++) {
        const key = _chunkKey(cx+dx, cz+dz);
        needed.add(key);
        _loadChunk(cx+dx, cz+dz);
      }
    }
    // Unload far chunks
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
    for (let i = 0; i < 4; i++) {
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
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
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

    // Clear all chunks
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
      const wallMat = new THREE.MeshStandardMaterial({ color: 0xccccbb, roughness:0.9 });
      const floor = new THREE.Mesh(new THREE.PlaneGeometry(60, 60), wallMat.clone());
      floor.rotation.x = -Math.PI/2; floor.receiveShadow = true;
      scene.add(floor);
      const wallGeo = new THREE.BoxGeometry(60, 20, 0.5);
      [0,1,2,3].forEach(i => {
        const w = new THREE.Mesh(wallGeo, wallMat.clone());
        const a = i * Math.PI/2;
        w.rotation.y = a; w.position.set(Math.sin(a)*30, 10, Math.cos(a)*30);
        w.receiveShadow = true; w.castShadow = true;
        scene.add(w);
        PHYS.colliders.push({
          min:{x:w.position.x-30, y:0, z:w.position.z-0.5},
          max:{x:w.position.x+30, y:20, z:w.position.z+0.5},
          normal:{x:0,y:0,z:1},
        });
      });
      hemiLight.intensity = 0.95;
      shadowLight.intensity = 0.55;
    }

    // Volumetric fog planes
    if (_fogOn || envName === 'field' || envName === 'windy') {
      const fogLayers = buildFogLayers();
      scene.add(fogLayers);
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
      _updateChunks();
    }
  }

  // ── Camera update ────────────────────────────────────────────────
  function updateCamera() {
    const p = PHYS.pos;
    const quat = PHYS.quat;
    const yaw  = PHYS.euler.yaw;

    if (_camMode === 'third') {
      const dist = 4.5, height = 2.2;
      const tx = p.x - Math.sin(yaw)*dist;
      const tz = p.z - Math.cos(yaw)*dist;
      camera.position.lerp(new THREE.Vector3(tx, p.y+height, tz), 0.12);
      camera.lookAt(p.x, p.y+0.3, p.z);
    } else if (_camMode === 'fpv') {
      const fwd = Q.rotVec(quat, {x:0, y:0.05, z:0.15});
      camera.position.set(p.x+fwd.x, p.y+fwd.y, p.z+fwd.z);
      const aim = Q.rotVec(quat, {x:0, y:-0.1, z:1.0});
      camera.lookAt(p.x+aim.x, p.y+aim.y, p.z+aim.z);
    } else if (_camMode === 'orbit') {
      const ox = p.x + Math.sin(_orbitAngle)*_orbitDist;
      const oz = p.z + Math.cos(_orbitAngle)*_orbitDist;
      camera.position.lerp(new THREE.Vector3(ox, p.y+_orbitH, oz), 0.08);
      camera.lookAt(p.x, p.y, p.z);
    } else if (_camMode === 'free') {
      camera.position.lerp(new THREE.Vector3(_freeCam.x, _freeCam.y, _freeCam.z), 0.05);
      camera.lookAt(p.x, p.y, p.z);
    } else if (_camMode === 'top') {
      camera.position.lerp(new THREE.Vector3(p.x, p.y+22, p.z+0.001), 0.06);
      camera.lookAt(p.x, p.y, p.z);
    }
  }

  // ── Software bloom (additive overdraw) ───────────────────────────
  let _bloomCanvas = null, _bloomCtx = null, _bloomEnabled = true;
  function _initBloom() {
    _bloomCanvas = document.createElement('canvas');
    _bloomCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1;mix-blend-mode:screen;opacity:0.28;';
    const vp = document.getElementById('viewport');
    if (vp) vp.appendChild(_bloomCanvas);
    _bloomCtx = _bloomCanvas.getContext('2d');
  }

  function _drawBloom(W, H) {
    if (!_bloomCanvas || !_bloomCtx) return;
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
  function render() {
    requestAnimationFrame(render);
    const dt = Math.min(0.05, clock.getDelta());
    _frame++;
    _simTime += dt;

    // FPS counter
    const now = performance.now();
    const instantFps = 1/Math.max(dt, 0.001);
    _fps = _fps * 0.92 + instantFps * 0.08;
    if (now - _lastFPSTime > 500) { _fpsSmooth = Math.round(_fps); _lastFPSTime = now; }

    // Sky time uniform
    if (skyMesh && skyMesh.material.uniforms) {
      skyMesh.material.uniforms.time.value = _simTime;
    }

    // Drone mesh from physics
    const p = PHYS.pos;
    droneGroup.position.set(p.x, p.y, p.z);
    droneGroup.quaternion.set(PHYS.quat.x, PHYS.quat.y, PHYS.quat.z, PHYS.quat.w);

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

    // Cloud drift with wind
    if (cloudGroup) {
      cloudGroup.position.x += PHYS.windVec.x * dt * 0.12;
      cloudGroup.position.z += PHYS.windVec.z * dt * 0.12;
      // Gentle bob
      cloudGroup.position.y = Math.sin(_simTime * 0.06) * 1.2;
    }

    // Rain animation
    if (_rainOn && _rainPositions) {
      for (let i = 0; i < _rainPositions.length/3; i++) {
        _rainPositions[i*3+1] -= (14 + Math.random()*6) * dt;
        _rainPositions[i*3  ] += PHYS.windVec.x * dt * 0.5;
        _rainPositions[i*3+2] += PHYS.windVec.z * dt * 0.5;
        if (_rainPositions[i*3+1] < -5) {
          _rainPositions[i*3+1] = 60;
          _rainPositions[i*3  ] = p.x + (Math.random()-0.5)*85;
          _rainPositions[i*3+2] = p.z + (Math.random()-0.5)*85;
        }
      }
      _rainGeo.attributes.position.needsUpdate = true;
      if (_rainParticles) _rainParticles.position.set(p.x, 0, p.z);
    }

    // Day cycle
    _dayTime += dt * 0.00055;
    if (_dayTime > 1) _dayTime -= 1;
    if (!_nightMode) _updateSunFromTime(_dayTime);

    // Shadow frustum follows drone
    if (shadowLight) {
      shadowLight.shadow.camera.position.copy(shadowLight.position).add(new THREE.Vector3(p.x, 0, p.z));
      shadowLight.target.position.set(p.x, 0, p.z);
      shadowLight.target.updateMatrixWorld();
    }

    // Chunk streaming
    _updateChunks();

    updateCamera();
    updateTrail();
    renderer.render(scene, camera);

    // Software bloom (every 2nd frame for perf)
    if (_bloomEnabled && _frame % 2 === 0) {
      const vp = document.getElementById('viewport');
      if (vp) _drawBloom(vp.clientWidth, vp.clientHeight);
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
    getTerrainHeight(x, z) { return terrainHeight(x, z, _envName); },
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
    const W = canvas.width || 220, H = canvas.height || 120;
    const ctx = canvas.getContext('2d');
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

    // Badge
    const badge = document.getElementById('minimap-badge');
    if (badge) badge.textContent = `${PHYS.pos.x.toFixed(1)}, ${PHYS.pos.z.toFixed(1)}`;

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
  const ctx = canvas.getContext('2d');
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
  const ctx = canvas.getContext('2d');
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
    // After rebuild, sample terrain at origin so drone sits on surface
    const gY = THREE_ENV.getTerrainHeight(0, 0);
    PHYS.groundY = gY;
    if (PHYS.grounded || PHYS.pos.y < gY + 0.5) {
      PHYS.pos.y = gY + 0.15;
      PHYS.vel = {x:0, y:0, z:0};
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
  _last: 0, _running: false, _paused: false, _speed: 1.0,
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
    const dt = rawDt * this._speed;

    // [FIX-Bug-26c] Absolute sim time always advances (not only when armed)
    _simClock.t += dt;

    // Sub-step for higher speeds to keep physics stable
    const substeps = this._speed > 1 ? Math.ceil(this._speed) : 1;
    const subDt = dt / substeps;
    INPUT.update(dt);
    const inp = INPUT.get();
    for (let s = 0; s < substeps; s++) {
      // Update groundY to actual terrain height each substep
      PHYS.groundY = THREE_ENV.getTerrainHeight(PHYS.pos.x, PHYS.pos.z);
      // [FIX-2.1] FC.update() runs the OUTER angle loop only (stores rate cmd).
      // The inner rate PID runs inside PHYS._substep() at full substep rate.
      FC.update(subDt, inp);
      PHYS.step(subDt);
    }
    MISSION.update();

    if (State.armed) State.flightTime += rawDt;  // [FIX-Bug-26b] use real time for clock

    // Update telemetry systems
    GPS_SIM.update(dt);
    VISION_POS.update(dt);
    OBSTACLE_DIST.update();
    PID_TELEM.capture();

    // [FIX-Bug-26b] Pass rawDt to _updateUI so wall clock runs at real time
    this._updateUI(rawDt);
    // [FIX-Bug-26c] Use shared sim clock (same reference as sim-engine.js)
    BLACKBOX.tick(_simClock.t);
    TELEM_GRAPH.push(PHYS);
    TELEM_GRAPH.draw();
    DEBUG.draw();
    MINIMAP.draw();
    drawAttitude();
    drawWindCompass();
    updateRecordingUI();
  },

  _updateUI(dt) {
    const p = PHYS, e = p.euler;
    const R2D = 180/Math.PI;
    const alt = Math.max(0, p.pos.y - p.groundY);
    const vel = V3.len(p.vel);

    // Telemetry
    const $ = id => document.getElementById(id);
    const set = (id,v) => { const el=$(id); if(el) el.textContent=v; };
    set('t-alt', alt.toFixed(1));
    set('t-vel', vel.toFixed(1));
    set('t-hdng', (((e.yaw*R2D+360)%360)|0).toString().padStart(3,'0'));
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
    set('batt-pct', battPct.toFixed(0)+'%');
    set('batt-top', battPct.toFixed(0)+'%');
    set('t-volt', p.battVoltage.toFixed(2));
    set('t-curr', p.currentDraw.toFixed(1));
    const bbar = $('batt-bar');
    if (bbar) {
      bbar.style.width = battPct+'%';
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
      const rpmEl = $(`m${i}-rpm`); const barEl = $(`m${i}-bar`);
      const rpm = Math.round(p.motorRPM[i]);
      if (rpmEl) rpmEl.textContent = rpm;
      if (barEl) {
        const pct = (rpm/p.maxRPM*100).toFixed(1);
        barEl.style.width = pct+'%';
        const dmg = State.motorDamage[i]||0;
        barEl.className = 'motor-bar' + (dmg>0.5?' red':dmg>0.2?' orange':'');
      }
    }

    // Clock — [FIX-Bug-26b] flightTime increments at real-time rate when armed
    // Wall clock shows State.flightTime which is incremented by rawDt (unscaled) in _loop
    const ft = State.flightTime;
    const mm = Math.floor(ft/60), ss = Math.floor(ft%60);
    const clk = $('top-clock');
    if (clk) clk.textContent = mm.toString().padStart(2,'0')+':'+ss.toString().padStart(2,'0');

    // Battery ETA
    const etaSec = getBattEstimatedFlightTime();
    const etaMin = etaSec < 9999 ? (etaSec/60).toFixed(1) : '--';
    set('t-batt-eta', etaMin);

    // ── GPS_RAW_INT (5 Hz) ────────────────────────────────────────────
    const gps = GPS_SIM;
    const fixType = gps.getFixType();
    const satCount = gps.getSatCount();
    const hdop = gps.getHdop();
    set('gps-lat', gps.getLat().toFixed(5));
    set('gps-lon', gps.getLon().toFixed(5));
    set('gps-alt', gps.getAltMSL().toFixed(1));
    set('gps-sat-count', satCount);
    set('gps-hdop', hdop.toFixed(2));
    const fixBadge = $('gps-fix-badge');
    if (fixBadge) {
      const fixLabels = { 0:'NO FIX', 1:'NO FIX', 2:'2D FIX', 3:'3D FIX', 4:'DGPS', 5:'RTK' };
      const fixClasses= { 0:'gps-fix-none', 1:'gps-fix-none', 2:'gps-fix-2d', 3:'gps-fix-3d', 4:'gps-fix-3d', 5:'gps-fix-3d' };
      fixBadge.textContent = fixLabels[fixType]||'NO FIX';
      fixBadge.className = 'gps-fix-badge '+(fixClasses[fixType]||'gps-fix-none');
    }
    // Satellite dots
    const satRow = $('gps-sat-row');
    if (satRow && satRow.children.length !== 16) {
      satRow.innerHTML = Array(16).fill(0).map((_,i)=>`<div class="gps-sat-dot" id="sat-dot-${i}"></div>`).join('');
    }
    for (let i = 0; i < 16; i++) {
      const dot = $('sat-dot-'+i);
      if (dot) dot.className = 'gps-sat-dot'+(i<satCount?' on':i<satCount+2?' dim':'');
    }

    // ── VISION_POSITION (30 Hz VSLAM) ────────────────────────────────
    const vp = VISION_POS.get();
    set('vslam-x', vp.x);
    set('vslam-y', vp.y);
    set('vslam-z', vp.z);
    set('vslam-quality-val', vp.quality+'%');
    const vslamBadge = $('vslam-badge');
    if (vslamBadge) {
      vslamBadge.textContent = vp.active ? 'VSLAM ACTIVE' : 'GPS ACTIVE';
      vslamBadge.className = 'vslam-badge '+(vp.active ? 'vslam-active' : 'vslam-idle');
    }
    const vslamQ = $('vslam-quality');
    if (vslamQ) vslamQ.style.width = vp.quality+'%';

    // ── OBSTACLE_DISTANCE (10 Hz) ─────────────────────────────────────
    const obs = OBSTACLE_DIST.get();
    const obsMax = OBSTACLE_DIST.SENSOR_RANGE;
    const obsIds = ['fwd','right','back','left','up'];
    const obsSectors = ['FWD','RIGHT','BACK','LEFT','UP'];
    obs.forEach((d, i) => {
      const pct = Math.min(100, (d/obsMax)*100);
      const barEl = $(('obs-'+obsIds[i]));
      const valEl = $(('obs-'+obsIds[i]+'-v'));
      if (barEl) barEl.style.width = pct+'%';
      if (valEl) valEl.textContent = d.toFixed(1)+'m';
    });
    // Update radar SVG sectors
    _updateObstacleRadar(obs, obsMax);

    // ── PID TELEMETRY (Live) ─────────────────────────────────────────
    const pt = PID_TELEM.axes;
    const pidAxes = [
      { key:'roll',  id:'roll' },
      { key:'pitch', id:'pitch' },
      { key:'yaw',   id:'yaw' },
      { key:'throttle', id:'thr' },
    ];
    const errScale = { roll:5, pitch:5, yaw:3, throttle:20 };
    pidAxes.forEach(({ key, id }) => {
      const ax = pt[key];
      set(`pid-${id}-kp`, ax.kp.toFixed(3));
      set(`pid-${id}-ki`, ax.ki.toFixed(3));
      set(`pid-${id}-kd`, ax.kd.toFixed(3));
      set(`pid-${id}-err-lbl`, (ax.error >= 0 ? '+' : '') + ax.error.toFixed(3));
      const errEl = $(`pid-${id}-err`);
      if (errEl) {
        const scale = errScale[key] || 5;
        const norm = Math.max(-1, Math.min(1, ax.error / scale));
        const w = Math.abs(norm) * 50;
        errEl.style.width = w+'%';
        errEl.style.left = (norm >= 0 ? 50 : 50-w)+'%';
        errEl.style.background = w > 35 ? 'var(--s)' : 'var(--p)';
      }
    });

    // FPS
    const fpsEl = $('fps-val');
    if (fpsEl) fpsEl.textContent = THREE_ENV.getFPS()+'fps';

    // System status
    const sysDot = $('sys-dot'), sysStat = $('sys-status');
    if (p.crashed) {
      if (sysDot) { sysDot.className='sdot e'; }
      if (sysStat) sysStat.textContent='CRASHED';
    } else if (State.armed) {
      if (sysDot) sysDot.className='sdot w';
      if (sysStat) sysStat.textContent='ARMED';
    } else {
      if (sysDot) sysDot.className='sdot';
      if (sysStat) sysStat.textContent='READY';
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
    FC.altTarget = null; FC.posTarget = null;
    FC.resetPIDs();
  } else {
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
  const groundY = THREE_ENV.getTerrainHeight(0, 0);
  PHYS.groundY = groundY;
  PHYS.reset({x:0, y:groundY + 0.15, z:0});
  State.armed=false; State.flightMode='stabilized';
  State.motorDamage=[0,0,0,0];
  FC.altTarget=null; FC.posTarget=null; FC.rthPhase=0;
  INPUT._thrRaw=0;
  setFlightModeUI('stabilized');
  updateArmUI();
  UI.toast('🔄 Drone reset');
  UI.log('Drone reset','ok');
}

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
let _recording = false;
function toggleRecording() {
  _recording = !_recording;
  const btn = document.getElementById('rec-btn');
  if (_recording) {
    BLACKBOX.start();
    if (btn) { btn.textContent = '⏹ Stop'; btn.classList.add('active-btn'); }
    UI.toast('⏺ Recording started');
  } else {
    BLACKBOX.stop();
    if (btn) { btn.textContent = '⏺ Record'; btn.classList.remove('active-btn'); }
    UI.toast('⏹ Recording stopped — ' + BLACKBOX.getLog().length + ' frames');
    updateExportStats();
  }
}

function updateRecordingUI() {
  if (!_recording) return;
  const n = BLACKBOX.getLog().length;
  const btn = document.getElementById('rec-btn');
  if (btn && n % 30 === 0) btn.textContent = '⏹ ' + n + 'f';
}

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
  const ctx = c.getContext('2d');
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
      const w = Math.abs(pct) * 50;
      el.style.width = w + '%';
      el.style.left  = (pct >= 0 ? 50 : 50 - w) + '%';
    } else {
      el.style.width = pct + '%';
      el.style.left  = '0';
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
  PHYS.reset({x:0, y:0.15, z:0});
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
      const speeds = [0.25, 0.5, 1, 2, 4];
      const idx = speeds.indexOf(SIM._speed);
      const next = speeds[Math.min(idx + 1, speeds.length - 1)];
      SIM.setSpeed(next);
      const sel = document.getElementById('sim-speed');
      if (sel) sel.value = next;
    }
    if (e.code === 'BracketLeft') {
      const speeds = [0.25, 0.5, 1, 2, 4];
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
  // [PLAN-SESSION] Enforce time-limited access (BASIC=1h, PRO=24h, MAX=∞)
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
      setTimeout(tick, 100);
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
    // [PLAN-PID-BASIC] Hide entirely
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

  /* ─ 10. NIGHT MODE RESTRICTION (BASIC) ─────────────────────── */
  // [PLAN-NIGHT] Disable night toggle for BASIC tier
  if (!PLAN.nightMode) {
    const nightToggle = document.querySelector('[onclick*="toggleDayNight"]');
    if (nightToggle) {
      _lockEl(nightToggle, 'Night mode requires PRO or MAX plan');
      const lbl = nightToggle.querySelector('.ntoggle-text');
      if (lbl) lbl.textContent = 'Night 🔒';
    }
  }

  /* ─ 11. WIND CONTROLS RESTRICTION (BASIC) ───────────────────── */
  // [PLAN-WIND] Lock wind/weather controls for BASIC tier
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

  fetch('../api/get_sim_limits.php' + window.location.search)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        simPlanName = data.plan_name;
        simTimeRemaining = data.time_remaining_seconds;
        if (simTimeRemaining === 0) {
          showTimeLimitModal();
        } else if (simTimeRemaining > 0) {
          timerInterval = setInterval(() => {
            simTimeRemaining--;
            flightDurationSeconds++;
            if (simTimeRemaining <= 0) {
               showTimeLimitModal();
            }
          }, 1000);
        } else {
          timerInterval = setInterval(() => {
            flightDurationSeconds++;
          }, 1000);
        }
      }
    });

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

  console.log(`[CERTANITY SIM] Plan: ${PLAN.tierLabel} | Session: ${isFinite(PLAN.sessionMinutes) ? PLAN.sessionMinutes+'min' : 'Unlimited'}`);
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
      <button class="nbtn danger" onclick="exitSimulation()">Exit Simulator</button>
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
</body>
</html>
