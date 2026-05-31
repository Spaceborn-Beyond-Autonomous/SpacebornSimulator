
/* [TIER-MAX] GLTF/GLB Custom Drone Upload Handler */
function handleGLTFUpload(input) {
  const file = input.files[0];
  if (!file) return;
  const statusEl = document.getElementById('gltf-upload-status');
  if (statusEl) statusEl.textContent = `Loading: ${file.name}…`;
  // Integration point: pass file URL to THREE_ENV drone mesh loader
  const url = URL.createObjectURL(file);
  if (typeof THREE_ENV !== 'undefined' && typeof THREE_ENV.loadCustomModel === 'function') {
    THREE_ENV.loadCustomModel(url, file.name);
  } else {
    if (statusEl) statusEl.textContent = `✅ Model queued: ${file.name} (apply on next flight)`;
    if (typeof UI !== 'undefined') UI.toast(`🚁 Custom model accepted: ${file.name}`);
  }
}

/* [TIER-MAX] Motor Failure scenario (maps to State.motorDamage) */
function activateMotorFailure(motorIndex) {
  if (typeof State === 'undefined') { console.warn('State not ready'); return; }
  if (!State.motorDamage) State.motorDamage = [0,0,0,0];
  State.motorDamage[motorIndex] = 1.0;
  if (typeof UI !== 'undefined') UI.toast(`⚠ Motor M${motorIndex+1} FAILURE activated`);
}
function clearMotorFailures() {
  if (typeof State !== 'undefined') State.motorDamage = [0,0,0,0];

  // 1. Recompute terrain-aware ground height at current drone XZ position
  //    so recoverFromCrash snaps to the correct ground (not 0)
  if (typeof THREE_ENV !== 'undefined') {
    PHYS.groundY = THREE_ENV.getTerrainHeight(PHYS.pos.x, PHYS.pos.z);
  }

  // 2. Clear crash physics — resets crashed flag, zeroes vel/angVel,
  //    levels attitude, snaps to ground, flushes PID integrators.
  if (typeof PHYS !== 'undefined' && typeof PHYS.recoverFromCrash === 'function') {
    PHYS.recoverFromCrash();
  }

  // 3. Arm
  State.armed = true;
  PHYS.saveHome();
  PHYS._gyroBias = {x:0, y:0, z:0};

  // 3. Set throttle to EXACTLY 0.5 immediately — NOT via animateThrottle().
  //    animateThrottle ramps from 0→0.5, during which the althold FC sees
  //    throttle < 0.5 and hits the manual branch (thrCmd = 0), so motors
  //    never spool up.  Setting 0.5 instantly puts the stick in the deadband
  //    so altPID engages from the very first frame.
  INPUT._thrRaw = 0.5;
  const slEl = document.getElementById('throttle-slider');
  const tv   = document.getElementById('thr-val');
  if (slEl) slEl.value = 50;
  if (tv)   tv.textContent = '50%';

  // 4. FC in althold targeting 3m
  FC.resetPIDs();
  FC.setMode('althold');
  FC.altTarget = 3.0;
  State.flightMode = 'althold';
  setFlightModeUI('althold');
  updateArmUI();

  // 5. Dismiss the crash overlay — it was blocking the viewport and intercepting clicks
  const co = document.getElementById('crash-overlay');
  if (co) co.classList.remove('show');

  if (typeof UI !== 'undefined') {
    UI.toast('✅ Motors restored — taking off');
    UI.log('Motors restored, auto-takeoff', 'ok');
  }
}

/* [TIER-MAX] GPS Denied scenario */
function activateGPSDenied(enable) {
  if (typeof State !== 'undefined') {
    State.gpsDenied = enable;
    if (typeof UI !== 'undefined') UI.toast(enable ? '🚫 GPS DENIED — VSLAM mode' : '✅ GPS signal restored');
  }
}
