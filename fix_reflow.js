const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // Fix PID error bars
  const pidOld = `const w = Math.abs(norm) * 50;
          errEl.style.width = w+'%';
          errEl.style.left = (norm >= 0 ? 50 : 50-w)+'%';`;
  const pidNew = `const w = Math.abs(norm) * 0.5;
          const start = norm >= 0 ? 0.5 : 0.5 - w;
          errEl.style.transform = 'translateX('+(start*100)+'%) scaleX('+w+')';`;
  s = s.replace(new RegExp(pidOld.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&').replace(/\\s\+/g, '\\s+'), 'g'), pidNew);

  // Fix Input UI bars
  const inputOld = `const w = Math.abs(pct) * 50;
        el.style.width = w + '%';
        el.style.left  = (pct >= 0 ? 50 : 50 - w) + '%';
      } else {
        el.style.width = pct + '%';
        el.style.left  = '0';`;
  const inputNew = `const w = Math.abs(pct) * 0.005;
        const start = pct >= 0 ? 0.5 : 0.5 - w;
        el.style.transform = 'translateX('+(start*100)+'%) scaleX('+w+')';
      } else {
        el.style.transform = 'scaleX('+(pct/100)+')';`;
  s = s.replace(new RegExp(inputOld.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&').replace(/\\s\+/g, '\\s+'), 'g'), inputNew);

  fs.writeFileSync(f, s, 'utf8');
  console.log('Fixed CSS reflow lag in ' + f);
}

try {
  fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html');
} catch(e) {}
