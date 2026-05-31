const fs = require('fs');

// 1. Patch sim-engine.js BLACKBOX
let eng = fs.readFileSync('simulator/sim-engine.js', 'utf8');
const bbStartOld = `start(){this._log=[];this.recording=true;}`;
const bbStartNew = `_lastTick:0,\n    start(){this._log=[];this.recording=true;this._lastTick=0;}`;
eng = eng.replace(bbStartOld, bbStartNew);

const bbTickOld = `    tick(t){
      if(!this.recording) return;`;
const bbTickNew = `    tick(t){
      if(!this.recording) return;
      if(t - this._lastTick < 0.1) return;
      this._lastTick = t;`;
eng = eng.replace(bbTickOld, bbTickNew);
fs.writeFileSync('simulator/sim-engine.js', eng, 'utf8');
console.log('Patched sim-engine.js');


// 2. Patch PHP UI files
const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // Remove the Record button HTML entirely
  s = s.replace(/<button class="co-btn" id="rec-btn" onclick="toggleRecording\(\)">.*?<\/button>/, '');
  
  // Also remove toggleRecording function
  s = s.replace(/let _recording = false;\s*function toggleRecording\(\) \{[\s\S]*?\}\s*/, '');

  // Add BLACKBOX.start() and stop() to toggleArm
  const armOld = `function toggleArm() {
    State.armed = !State.armed;
    if (!State.armed) {
      FC.altTarget = null; FC.posTarget = null;
      FC.resetPIDs();
    } else {
      PHYS.saveHome();
      PHYS._gyroBias={x:0,y:0,z:0}; // [FIX-H] zero gyro bias on arm
      FC.resetPIDs();
    }`;
  const armNew = `function toggleArm() {
    State.armed = !State.armed;
    if (!State.armed) {
      FC.altTarget = null; FC.posTarget = null;
      FC.resetPIDs();
      BLACKBOX.stop();
    } else {
      PHYS.saveHome();
      PHYS._gyroBias={x:0,y:0,z:0}; // [FIX-H] zero gyro bias on arm
      FC.resetPIDs();
      BLACKBOX.start();
    }`;
  s = s.replace(armOld, armNew);

  // Add BLACKBOX.start() to takeoff
  const takeoffOld = `function takeoff() {
    if (!State.armed) {
      State.armed = true;
      PHYS.saveHome();
      PHYS._gyroBias={x:0,y:0,z:0}; // [FIX-H] zero gyro bias on takeoff
      FC.resetPIDs();`;
  const takeoffNew = `function takeoff() {
    if (!State.armed) {
      State.armed = true;
      PHYS.saveHome();
      PHYS._gyroBias={x:0,y:0,z:0}; // [FIX-H] zero gyro bias on takeoff
      FC.resetPIDs();
      BLACKBOX.start();`;
  s = s.replace(takeoffOld, takeoffNew);

  fs.writeFileSync(f, s, 'utf8');
  console.log('Patched ' + f);
}

try {
  fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html');
} catch(e) {}
