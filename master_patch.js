const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // Fix 1: CSS Reflows (Layout Thrashing)
  s = s.replace(/const w = Math\.abs\(norm\) \* 50;\s*errEl\.style\.width = w\+'%';\s*errEl\.style\.left = \(norm >= 0 \? 50 : 50-w\)\+'%';/,
    `const w = Math.abs(norm) * 0.5;
          const start = norm >= 0 ? 0.5 : 0.5 - w;
          errEl.style.transform = 'translateX('+(start*100)+'%) scaleX('+w+')';`);

  s = s.replace(/const w = Math\.abs\(pct\) \* 50;\s*el\.style\.width = w \+ '%';\s*el\.style\.left\s*=\s*\(pct >= 0 \? 50 : 50 - w\) \+ '%';\s*\} else \{\s*el\.style\.width = pct \+ '%';\s*el\.style\.left\s*=\s*'0';/,
    `const w = Math.abs(pct) * 0.005;
      const start = pct >= 0 ? 0.5 : 0.5 - w;
      el.style.transform = 'translateX('+(start*100)+'%) scaleX('+w+')';
    } else {
      el.style.transform = 'scaleX('+(pct/100)+')';`);

  // Fix 2: Auto-Telemetry (Patching takeoff and toggleArm)
  s = s.replace(/State\.armed = !State\.armed;\s*PHYS\.saveHome\(\);\s*PHYS\._gyroBias = \{x:0, y:0, z:0\};\s*if \(State\.armed\) \{\s*FC\.resetPIDs\(\);\s*FC\.setMode\(State\.flightMode\);\s*if \(typeof UI !== 'undefined'\) \{\s*UI\.toast\('? MOTORS ARMED'\);\s*UI\.log\('System armed', 'ok'\);\s*\}/, 
    `State.armed = !State.armed;
    PHYS.saveHome();
    PHYS._gyroBias = {x:0, y:0, z:0};
    if (State.armed) {
      BLACKBOX.start(); // Auto-start telemetry
      FC.resetPIDs();
      FC.setMode(State.flightMode);
      if (typeof UI !== 'undefined') {
        UI.toast('? MOTORS ARMED');
        UI.log('System armed', 'ok');
      }`);

  s = s.replace(/\} else \{\s*if \(typeof UI !== 'undefined'\) \{\s*UI\.toast\('?1 MOTORS DISARMED'\);\s*UI\.log\('System disarmed', 'warn'\);\s*\}/,
    `} else {
      BLACKBOX.stop(); // Auto-stop telemetry
      if (typeof UI !== 'undefined') {
        UI.toast('?1 MOTORS DISARMED');
        UI.log('System disarmed', 'warn');
      }`);

  s = s.replace(/if \(typeof UI !== 'undefined'\) \{\s*UI\.toast\('? AUTO-TAKEOFF'\);\s*UI\.log\('Auto-takeoff engaged', 'ok'\);\s*\}/,
    `if (typeof UI !== 'undefined') {
      BLACKBOX.start(); // Auto-start telemetry
      UI.toast('? AUTO-TAKEOFF');
      UI.log('Auto-takeoff engaged', 'ok');
    }`);

  // Fix 3: Remove manual Record button logic completely to avoid dangling } else { block
  // First remove toggleRecording and updateRecordingUI functions safely
  const recRegex = /let _recording = false;\s*function toggleRecording\(\) \{[\s\S]*?function updateRecordingUI\(\) \{[\s\S]*?\}\s*const RECORDING_LOG_RATE = 30;/;
  s = s.replace(recRegex, 'const RECORDING_LOG_RATE = 30;');

  // Remove the UI button for recording
  s = s.replace(/<button id="rec-btn" class="sm-btn" onclick="toggleRecording\(\)">? Record<\/button>/g, '');

  // Fix 4: Fix _setTxt reference error
  s = s.replace(/_setTxt\('sm-thr', inp\.throttle \* 100\);/, `setM('sm-thr', inp.throttle * 100);`); // Just in case it was there, it wasn't.

  // Fix 5: Remove Fixed Timestep Judder
  // Find the exact block safely using a specific literal string replacement
  const oldJudder = `        // Fixed Accumulator for perfect physics stability
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

  const newJudder = `        // Smooth variable timestep for perfect render synchronization
        const dt = rawDt * this._speed;
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
        
  // Handle inconsistent spaces safely
  const regexJudder = /\s*\/\/\s*Fixed Accumulator for perfect physics stability[\s\S]*?this\._acc -= FIXED_DT;\s*\}/;
  s = s.replace(regexJudder, newJudder);

  // Fix 6: Remove updateRecordingUI() call in _loop()
  s = s.replace(/updateRecordingUI\(\);/g, '');

  fs.writeFileSync(f, s, 'utf8');
  console.log('Applied master patch to ' + f);
}

// Copy to standalone html just in case
try { fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html'); } catch(e) {}
