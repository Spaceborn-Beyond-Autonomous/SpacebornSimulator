const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // Fix PID error bars
  s = s.replace(/const w = Math\.abs\(norm\) \* 50;\s*errEl\.style\.width = w\+'%';\s*errEl\.style\.left = \(norm >= 0 \? 50 : 50-w\)\+'%';/,
    `const w = Math.abs(norm) * 0.5;
          const start = norm >= 0 ? 0.5 : 0.5 - w;
          errEl.style.transform = 'translateX('+(start*100)+'%) scaleX('+w+')';`);

  // Fix Input UI bars
  s = s.replace(/const w = Math\.abs\(pct\) \* 50;\s*el\.style\.width = w \+ '%';\s*el\.style\.left\s*=\s*\(pct >= 0 \? 50 : 50 - w\) \+ '%';\s*\} else \{\s*el\.style\.width = pct \+ '%';\s*el\.style\.left\s*=\s*'0';/,
    `const w = Math.abs(pct) * 0.005;
      const start = pct >= 0 ? 0.5 : 0.5 - w;
      el.style.transform = 'translateX('+(start*100)+'%) scaleX('+w+')';
    } else {
      el.style.transform = 'scaleX('+(pct/100)+')';`);

  fs.writeFileSync(f, s, 'utf8');
  console.log('Fixed CSS reflow lag perfectly in ' + f);
}

try {
  fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html');
} catch(e) {}
