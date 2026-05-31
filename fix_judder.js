const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // Regex to match the fixed accumulator block
  const oldCode = `      // Fixed Accumulator for perfect physics stability
        if (typeof this._acc === 'undefined') this._acc = 0;
        const FIXED_DT = 1 / 60; // 60Hz physics base rate
        this._acc += rawDt * this._speed;
        
        // Update inputs once per visual frame
        INPUT.update(rawDt * this._speed);
        const inp = INPUT.get();
        
        // Environment check
        const _envName_sim = typeof ENV !== 'undefined' ? ENV._name : 'field';
        const checkGround = _envName_sim !== 'indoor' && _envName_sim !== 'urban';
        
        while (this._acc >= FIXED_DT) {
          if (checkGround) {
            PHYS.groundY = THREE_ENV.getTerrainHeight(PHYS.pos.x, PHYS.pos.z);
            if (PHYS.groundY < 0) PHYS.groundY = 0;
          }
          FC.update(FIXED_DT, inp);
          PHYS.step(FIXED_DT);
          this._acc -= FIXED_DT;
        }`;

  const newCode = `      // Smooth variable timestep for rendering stability (physics engine internally substeps)
      INPUT.update(dt);
      const inp = INPUT.get();
      
      const _envName_sim = typeof ENV !== 'undefined' ? ENV._name : 'field';
      const checkGround = _envName_sim !== 'indoor' && _envName_sim !== 'urban';
      
      if (checkGround) {
        PHYS.groundY = THREE_ENV.getTerrainHeight(PHYS.pos.x, PHYS.pos.z);
        if (PHYS.groundY < 0) PHYS.groundY = 0;
      }
      
      FC.update(dt, inp);
      PHYS.step(dt);`;

  // Create a robust regex
  const regex = /[\s\S]*Fixed Accumulator for perfect physics stability[\s\S]*?this\._acc -= FIXED_DT;\s*\}/;
  s = s.replace(regex, newCode);

  fs.writeFileSync(f, s, 'utf8');
  console.log('Removed fixed accumulator in ' + f);
}

try {
  fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html');
} catch(e) {}
