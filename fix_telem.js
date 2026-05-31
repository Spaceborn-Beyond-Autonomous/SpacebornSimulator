const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // Fix toggleArm
  s = s.replace(/if \(!State\.armed\) \{\s*FC\.altTarget = null; FC\.posTarget = null;\s*FC\.resetPIDs\(\);\s*\} else \{\s*PHYS\.saveHome\(\);/,
    `if (!State.armed) {
      if (typeof BLACKBOX !== 'undefined') BLACKBOX.stop();
      FC.altTarget = null; FC.posTarget = null;
      FC.resetPIDs();
    } else {
      if (typeof BLACKBOX !== 'undefined') BLACKBOX.start();
      PHYS.saveHome();`);

  // Fix takeoff
  s = s.replace(/if \(!State\.armed\) \{\s*State\.armed = true;\s*PHYS\.saveHome\(\);/,
    `if (!State.armed) {
      State.armed = true;
      if (typeof BLACKBOX !== 'undefined') BLACKBOX.start();
      PHYS.saveHome();`);

  fs.writeFileSync(f, s, 'utf8');
  console.log('Fixed telemetry auto-record in ' + f);
}

try { fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html'); } catch(e) {}
