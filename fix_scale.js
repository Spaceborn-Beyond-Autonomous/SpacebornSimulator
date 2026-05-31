const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  let s = fs.readFileSync(f, 'utf8');
  
  // Fix scaleX bug in joystick input
  s = s.replace(/const w = Math\.abs\(pct\) \* 0\.5;/g, "const w = Math.abs(pct) * 0.005;");
  
  fs.writeFileSync(f, s, 'utf8');
  console.log('Fixed scaleX bug in ' + f);
}

try {
  fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html');
} catch(e) {}
