const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // We are going to replace the current _loop variable timestep logic with the ultra-smooth fixed accumulator + nlerp logic
  const oldRegex = /\s*\/\/\s*\[FIX-Bug-26c\] Absolute sim time always advances[\s\S]*?PHYS\.step\(dt\);\s*MISSION\.update\(\);/;

  const newCode = `      // [FIX-Bug-26c] Absolute sim time always advances (not only when armed)
      _simClock.t += dt;
      
      if (typeof this._acc === 'undefined') this._acc = 0;
      const FIXED_DT = 1 / 60;
      this._acc += dt;

      INPUT.update(dt);
      const inp = INPUT.get();

      const _envName_sim = typeof ENV !== 'undefined' ? ENV._name : 'field';
      const checkGround = _envName_sim !== 'indoor' && _envName_sim !== 'urban';

      while (this._acc >= FIXED_DT) {
        if (checkGround) {
          PHYS.groundY = THREE_ENV.getTerrainHeight(PHYS.pos.x, PHYS.pos.z);
          if (PHYS.groundY < 0) PHYS.groundY = 0;
        }
        
        PHYS._prevPos = { x:PHYS.pos.x, y:PHYS.pos.y, z:PHYS.pos.z };
        PHYS._prevQuat = { w:PHYS.quat.w, x:PHYS.quat.x, y:PHYS.quat.y, z:PHYS.quat.z };

        FC.update(FIXED_DT, inp);
        PHYS.step(FIXED_DT);

        PHYS._realPos = { x:PHYS.pos.x, y:PHYS.pos.y, z:PHYS.pos.z };
        PHYS._realQuat = { w:PHYS.quat.w, x:PHYS.quat.x, y:PHYS.quat.y, z:PHYS.quat.z };

        this._acc -= FIXED_DT;
      }

      // Interpolate for buttery smooth rendering at high refresh rates
      const alpha = Math.min(1.0, Math.max(0.0, this._acc / FIXED_DT));
      if (PHYS._prevPos && PHYS._realPos) {
        PHYS.pos.x = PHYS._prevPos.x + (PHYS._realPos.x - PHYS._prevPos.x) * alpha;
        PHYS.pos.y = PHYS._prevPos.y + (PHYS._realPos.y - PHYS._prevPos.y) * alpha;
        PHYS.pos.z = PHYS._prevPos.z + (PHYS._realPos.z - PHYS._prevPos.z) * alpha;

        const a = PHYS._prevQuat, b = PHYS._realQuat;
        const dot = a.w*b.w + a.x*b.x + a.y*b.y + a.z*b.z;
        const sign = dot < 0 ? -1 : 1;
        const w = a.w + (b.w * sign - a.w) * alpha;
        const x = a.x + (b.x * sign - a.x) * alpha;
        const y = a.y + (b.y * sign - a.y) * alpha;
        const z = a.z + (b.z * sign - a.z) * alpha;
        const l = Math.hypot(w,x,y,z) || 1;
        PHYS.quat.w = w/l; PHYS.quat.x = x/l; PHYS.quat.y = y/l; PHYS.quat.z = z/l;
      }

      MISSION.update();`;

  s = s.replace(oldRegex, newCode);

  // Now, we need to restore the true physics state right after THREE_ENV.updateCamera()
  // Search for: THREE_ENV.updateCamera();
  const restoreCode = `      THREE_ENV.updateCamera();
      
      // Restore true physics state for the next frame
      if (PHYS._realPos) {
         PHYS.pos.x = PHYS._realPos.x; PHYS.pos.y = PHYS._realPos.y; PHYS.pos.z = PHYS._realPos.z;
         PHYS.quat.w = PHYS._realQuat.w; PHYS.quat.x = PHYS._realQuat.x; PHYS.quat.y = PHYS._realQuat.y; PHYS.quat.z = PHYS._realQuat.z;
      }`;
      
  s = s.replace(/THREE_ENV\.updateCamera\(\);/, restoreCode);

  fs.writeFileSync(f, s, 'utf8');
  console.log('Applied buttery smooth fixed accumulator logic to ' + f);
}

try { fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html'); } catch(e) {}
