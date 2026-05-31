const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // Fix the redeclaration of const dt
  s = s.replace(/const dt = rawDt \* this\._speed;\s*INPUT\.update\(dt\);/,
    `INPUT.update(dt);`);

  fs.writeFileSync(f, s, 'utf8');
  console.log('Fixed let/const redeclaration in ' + f);
}

// Copy to standalone html just in case
try { fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html'); } catch(e) {}
