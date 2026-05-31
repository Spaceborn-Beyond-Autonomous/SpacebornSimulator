const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // 1. Remove backdrop-filter from badges & overlay btn
  s = s.replace(/backdrop-filter:blur\(4px\)/g, '');
  s = s.replace(/#crash-overlay \.co-btn\{([\s\S]*?)backdrop-filter:blur\(6px\);?([\s\S]*?)\}/, '#crash-overlay .co-btn{$1$2}');
  
  // 2. Add willReadFrequently to 2d canvas getContext
  s = s.replace(/\.getContext\(['"]2d['"]\)/g, ".getContext('2d', {willReadFrequently: true})");

  // 3. CSS transforms for layout thrashing
  s = s.replace(/\.axis-bar\{position:absolute;height:100%;top:0;background:var\(--p\);border-radius:10px\}/g, 
    ".axis-bar{position:absolute;height:100%;top:0;background:var(--p);border-radius:10px;width:100%;left:0;transform-origin:left center;transform:scaleX(0);will-change:transform}");
  s = s.replace(/\.bgauge-fill\{height:100%;border-radius:4px;transition:width \.2s\}/g, 
    ".bgauge-fill{height:100%;border-radius:4px;width:100%;left:0;transform-origin:left center;transform:scaleX(0);will-change:transform;transition:background 0.2s}");
  s = s.replace(/\.motor-bar\{height:100%;border-radius:3px;background:linear-gradient\(90deg,var\(--p\),var\(--s\)\);transition:width \.1s\}/g, 
    ".motor-bar{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--p),var(--s));width:100%;left:0;transform-origin:left center;transform:scaleX(0);will-change:transform}");
  s = s.replace(/\.obs-bar-fill\{height:100%;border-radius:3px;background:linear-gradient\(90deg,#F44336,#EE9346,#4CAF50\);transition:width \.1s\}/g, 
    ".obs-bar-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,#F44336,#EE9346,#4CAF50);width:100%;left:0;transform-origin:left center;transform:scaleX(0);will-change:transform}");
  s = s.replace(/\.pid-err-fill\{position:absolute;top:0;height:100%;background:var\(--p\);border-radius:4px;transition:background 0\.1s\}/g, 
    ".pid-err-fill{position:absolute;top:0;height:100%;background:var(--p);border-radius:4px;width:100%;left:0;transform-origin:left center;transform:scaleX(0);will-change:transform;transition:background 0.1s}");

  // Also remove style="width:0%;left:50%" from inline HTML
  s = s.replace(/style="width:0%;left:50%"/g, "");

  // 4. Modify JS logic for animating bars
  
  // Battery bar
  s = s.replace(/bbar\.style\.width = battPct\+'%';/g, "bbar.style.transform = 'scaleX(' + (battPct/100) + ')';");
  
  // Motor bar
  s = s.replace(/barEl\.style\.width = \(rpm\/p\.maxRPM\*100\)\.toFixed\(1\)\+'%';/g, "barEl.style.transform = 'scaleX(' + (rpm/p.maxRPM) + ')';");
  
  // VSLAM quality
  s = s.replace(/vslamQ\.style\.width = vp\.quality\+'%';/g, "vslamQ.style.transform = 'scaleX(' + (vp.quality/100) + ')';");
  
  // Obstacles
  s = s.replace(/barEl\.style\.width = pct\+'%';/g, "barEl.style.transform = 'scaleX(' + (pct/100) + ')';");

  // PID Error
  const pidFind = `const w = Math.abs(norm) * 50;
          errEl.style.width = w+'%';
          errEl.style.left = (norm >= 0 ? 50 : 50-w)+'%';`;
  const pidRep = `const w = Math.abs(norm) * 0.5;
          const start = norm >= 0 ? 0.5 : 0.5 - w;
          errEl.style.transform = 'translateX('+(start*100)+'%) scaleX('+w+')';`;
  s = s.replace(pidFind, pidRep);
  
  // Joystick Input
  const joyFind = `const w = Math.abs(pct) * 50;
        el.style.width = w + '%';
        el.style.left  = (pct >= 0 ? 50 : 50 - w) + '%';
      } else {
        el.style.width = pct + '%';
        el.style.left  = '0';
      }`;
  const joyRep = `const w = Math.abs(pct) * 0.5;
        const start = pct >= 0 ? 0.5 : 0.5 - w;
        el.style.transform = 'translateX('+(start*100)+'%) scaleX('+w+')';
      } else {
        el.style.transform = 'scaleX('+(pct/100)+')';
      }`;
  s = s.replace(joyFind, joyRep);

  // 5. Global text caching injection
  if (!s.includes('const _setTxt')) {
    s = s.replace(/function updateUI\(\) \{/, "const _setTxt = (el, txt) => { if (el && el.textContent !== String(txt)) el.textContent = txt; };\n  function updateUI() {");
  }
  
  // We won't automatically regex-replace all textContents because there are too many variables,
  // but we can replace the most frequent ones in `updateUI` and `updateInputUI`.
  s = s.replace(/valEl\.textContent = obs\[i\]\.toFixed\(1\)\+'m';/g, "_setTxt(valEl, obs[i].toFixed(1)+'m');");
  s = s.replace(/rpmEl\.textContent = rpm;/g, "_setTxt(rpmEl, rpm);");
  s = s.replace(/set\(`pid-\$\{id\}-err-lbl`, \(ax\.error >= 0 \? '\+' : ''\) \+ ax\.error\.toFixed\(3\)\);/g, 
    "const txt = (ax.error >= 0 ? '+' : '') + ax.error.toFixed(3); if(D[`pid-${id}-err-lbl`].textContent !== txt) D[`pid-${id}-err-lbl`].textContent = txt;");
  
  fs.writeFileSync(f, s, 'utf8');
  console.log('Patched UI in ' + f);
}

try {
  fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html');
} catch(e) {}
