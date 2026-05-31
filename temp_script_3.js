
/* ══════════════════════════════════════════════════════════════════
   PLAN ENFORCEMENT ENGINE  — runs after DOMContentLoaded
   Reads PLAN constant above; applies all tier restrictions.
   Flight mechanics in sim-engine.js are NEVER modified.
══════════════════════════════════════════════════════════════════ */

function _hideEl(el) { if (el) el.style.display = 'none'; }
function _lockEl(el, tip) {
  if (!el) return;
  el.style.pointerEvents = 'none';
  el.style.opacity = '0.32';
  el.title = tip || 'Not available on your plan';
}

window.addEventListener('DOMContentLoaded', () => {

  /* ─ 1. TIER BADGE ──────────────────────────────────────────── */
  // [PLAN-BADGE] Inject tier badge beside SIM tag in topbar
  const simTag = document.querySelector('.brand-tag');
  if (simTag) {
    const badge = document.createElement('span');
    badge.style.cssText = `font-size:9px;letter-spacing:1.5px;text-transform:uppercase;
      color:white;background:${PLAN.tierColor};padding:2px 8px;border-radius:20px;
      font-weight:700;font-family:var(--fh);margin-left:6px;`;
    badge.textContent = PLAN.tierLabel;
    simTag.parentNode.insertBefore(badge, simTag.nextSibling);
  }

  /* ─ 2. SESSION TIMER ───────────────────────────────────────── */
  // [PLAN-SESSION] Enforce time-limited access (BASIS=1h, PRO=24h, MAX=∞)
  if (isFinite(PLAN.sessionMinutes)) {
    const SESSION_MS = PLAN.sessionMinutes * 60000;
    const t0 = Date.now();

    // Countdown badge
    const cdBadge = document.createElement('div');
    cdBadge.style.cssText = `display:flex;align-items:center;gap:5px;padding:4px 11px;
      border-radius:20px;background:var(--n);box-shadow:inset 4px 4px 8px #0d1018,inset -4px -4px 8px #232a3a;
      font-size:11px;font-weight:600;font-family:var(--fh);color:var(--txt2);`;
    cdBadge.innerHTML = `<span style="color:var(--s)">⏱</span><span id="ses-left">--:--</span>`;
    const tb = document.getElementById('topbar');
    if (tb) { const tsp = tb.querySelector('.tsp'); if (tsp) tb.insertBefore(cdBadge, tsp.nextSibling); }

    // Expired overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `position:fixed;inset:0;z-index:9999;background:rgba(10,12,20,0.97);
      display:none;align-items:center;justify-content:center;flex-direction:column;gap:18px;`;
    const durationLabel = PLAN.sessionMinutes >= 60
      ? Math.round(PLAN.sessionMinutes/60) + 'h'
      : PLAN.sessionMinutes + 'min';
    overlay.innerHTML = `
      <div style="font-family:var(--fh);font-size:32px;font-weight:700;color:var(--p)">⏱ Session Ended</div>
      <div style="font-size:14px;color:var(--txt2);text-align:center;max-width:380px;line-height:1.7">
        Your <strong>${PLAN.tierLabel}</strong> session (${durationLabel}) has expired.<br>
        Upgrade to <strong style="color:var(--s)">MAX</strong> for unlimited access.
      </div>
      <button onclick="location.reload()" style="background:var(--p);color:#fff;border:none;
        padding:10px 28px;border-radius:20px;font-family:var(--fh);font-size:13px;font-weight:700;cursor:pointer;">
        🔄 Start New Session
      </button>`;
    document.body.appendChild(overlay);

    function tick() {
      const rem = Math.max(0, SESSION_MS - (Date.now() - t0));
      const el = document.getElementById('ses-left');
      if (el) {
        const m = String(Math.floor(rem/60000)).padStart(2,'0');
        const s = String(Math.floor((rem%60000)/1000)).padStart(2,'0');
        el.textContent = `${m}:${s}`;
      }
      if (rem < 300000) cdBadge.style.color = '#EE9346';
      if (rem < 60000)  cdBadge.style.color = '#F44336';
      if (rem <= 0) {
        try { if (typeof SIM !== 'undefined' && !SIM._paused && typeof toggleSimPause === 'function') toggleSimPause(); } catch(e){}
        overlay.style.display = 'flex';
        return;
      }
      setTimeout(tick, 1000);
    }
    tick();
  }

  /* ─ 3. ENVIRONMENT RESTRICTIONS ────────────────────────────── */
  // [PLAN-ENV] Disable environment buttons not in plan
  const ALL_ENVS = ['field','mountains','urban','indoor','desert','windy'];
  ALL_ENVS.forEach(env => {
    if (!PLAN.environments.includes(env)) {
      const btn = document.querySelector(`[data-env="${env}"]`);
      if (btn) {
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.28';
        btn.title = `Upgrade to unlock ${env} environment`;
        // Add lock icon without changing flight mechanics
        const icon = btn.querySelector('.fm-icon');
        if (icon) icon.textContent = '🔒';
      }
    }
  });

  /* ─ 4. DRONE PROFILE RESTRICTIONS ──────────────────────────── */
  // [PLAN-DRONE] Restrict profile dropdown to allowed profiles
  setTimeout(() => {
    const sel = document.getElementById('drone-profile-select');
    if (sel) {
      Array.from(sel.options).forEach(opt => {
        if (opt.value && !PLAN.droneProfiles.includes(opt.value)) opt.remove();
      });
      if (sel.options.length > 0 && !PLAN.droneProfiles.includes(sel.value)) {
        sel.value = sel.options[0].value;
        if (typeof setDroneProfile === 'function') setDroneProfile(sel.value);
      }
    }

    // [PLAN-GLTF] Hide custom profile buttons if no GLTF support
    if (!PLAN.customGLTF) {
      const newBtn = document.querySelector('[onclick="openCustomProfileModal()"]');
      if (newBtn) _hideEl(newBtn);
      const custToggle = document.getElementById('customize-toggle-btn');
      if (custToggle) _hideEl(custToggle);
      const custPanel = document.getElementById('profile-customize-panel');
      if (custPanel) _hideEl(custPanel);
    }
  }, 600);

  /* ─ 5. PID TUNING PANEL ─────────────────────────────────────── */
  // [PLAN-PID] Manage PID panel access: false=hide, 'view'=read-only, 'full'=unrestricted
  let pidCard = null;
  document.querySelectorAll('.card-sm').forEach(c => {
    const t = c.querySelector('.card-title');
    if (t && t.textContent.includes('RATE PID TUNING')) pidCard = c;
  });
  if (PLAN.pidTuning === false) {
    // [PLAN-PID-BASIS] Hide entirely
    if (pidCard) _hideEl(pidCard);
  } else if (PLAN.pidTuning === 'view') {
    // [PLAN-PID-PRO] Show values but disable all sliders
    if (pidCard) {
      pidCard.querySelectorAll('input[type=range]').forEach(s => {
        s.disabled = true;
        s.style.pointerEvents = 'none';
        s.style.opacity = '0.45';
      });
      const notice = document.createElement('div');
      notice.style.cssText = 'font-size:10px;color:var(--s);font-weight:700;text-align:center;padding:5px 0 2px;letter-spacing:.4px;';
      notice.textContent = '👁 VIEW ONLY — Upgrade to MAX to live-tune';
      pidCard.appendChild(notice);
    }
  }
  // PLAN.pidTuning === 'full': no changes (MAX)

  /* ─ 6. EXPORT / BLACKBOX ────────────────────────────────────── */
  // [PLAN-EXPORT] Replace export card content for non-MAX tiers
  if (!PLAN.dataExport) {
    const exportCard = document.getElementById('export-card');
    if (exportCard) {
      exportCard.innerHTML = `
        <div class="card-title"><span class="ct-dot"></span>FLIGHT LOG · BLACKBOX</div>
        <div style="text-align:center;padding:16px 8px;color:var(--txt4);">
          <div style="font-size:18px;margin-bottom:6px">🔒</div>
          <div style="font-size:11px;font-weight:700;color:var(--txt3)">Data Export (JSON / CSV / MAVLog)</div>
          <div style="font-size:10px;margin-top:4px;color:var(--txt4)">Available on MAX plan only</div>
        </div>`;
    }
  }

  /* ─ 7. MAVLINK BUTTONS ──────────────────────────────────────── */
  // [PLAN-MAVLINK] Handle MAVLink export access
  document.querySelectorAll('button').forEach(btn => {
    if (!btn.textContent.includes('MAVLink')) return;
    if (PLAN.mavlinkLogs === false) {
      _hideEl(btn);
    } else if (PLAN.mavlinkLogs === 'readonly') {
      btn.textContent = '📡 MAVLink (Read)';
      btn.title = 'Read-only on PRO — upgrade to MAX to download';
      btn.onclick = (e) => {
        e.preventDefault();
        if (typeof UI !== 'undefined') UI.toast('📡 MAVLink view-only on PRO — upgrade to MAX');
      };
    }
    // 'download' = MAX, no change
  });

  /* ─ 8. WAYPOINT MISSION NAV ─────────────────────────────────── */
  // [PLAN-MISSION] Lock mission planner nav for non-MAX
  if (!PLAN.waypointMissions) {
    const mNav = document.getElementById('nav-mission');
    if (mNav) {
      mNav.textContent = '🔒 Mission';
      mNav.style.pointerEvents = 'none';
      mNav.style.opacity = '0.32';
      mNav.title = 'Waypoint missions available on MAX plan';
    }
  }

  /* ─ 9. GAMEPAD / JOYSTICK ───────────────────────────────────── */
  // [PLAN-GAMEPAD] Block gamepad API for non-MAX tiers
  if (!PLAN.joystickGamepad) {
    window.addEventListener('gamepadconnected', e => {
      e.stopImmediatePropagation();
      if (typeof UI !== 'undefined') UI.toast('🎮 Gamepad/Joystick requires MAX plan');
    }, true);
    // Update hint text in controls card
    document.querySelectorAll('.card-sm').forEach(card => {
      Array.from(card.childNodes).forEach(node => {
        if (node.nodeType === 3) return;
        if (node.textContent && node.textContent.includes('Gamepad supported')) {
          node.textContent = '🎮 Gamepad support: MAX plan only 🔒';
          node.style.color = 'var(--txt4)';
        }
      });
    });
    // Target the specific div
    document.querySelectorAll('div').forEach(div => {
      if (div.textContent && div.textContent.trim() === '🎮 Gamepad supported — plug in for analog input') {
        div.textContent = '🎮 Gamepad: MAX plan only 🔒';
      }
    });
  }

  /* ─ 10. NIGHT MODE RESTRICTION (BASIS) ─────────────────────── */
  // [PLAN-NIGHT] Disable night toggle for BASIS tier
  if (!PLAN.nightMode) {
    const nightToggle = document.querySelector('[onclick*="toggleDayNight"]');
    if (nightToggle) {
      _lockEl(nightToggle, 'Night mode requires PRO or MAX plan');
      const lbl = nightToggle.querySelector('.ntoggle-text');
      if (lbl) lbl.textContent = 'Night 🔒';
    }
  }

  /* ─ 11. WIND CONTROLS RESTRICTION (BASIS) ───────────────────── */
  // [PLAN-WIND] Lock wind/weather controls for BASIS tier
  if (!PLAN.windScenario) {
    ['wind-speed','turbulence','wind-dir'].forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        el.disabled = true;
        el.style.pointerEvents = 'none';
        el.style.opacity = '0.28';
        el.title = 'Wind scenarios require PRO or MAX plan';
      }
    });
    // Lock rain/fog/night weather toggles
    ['toggleWeather', 'toggleDayNight'].forEach(fn => {
      document.querySelectorAll(`[onclick*="${fn}"]`).forEach(el => _lockEl(el, 'Weather requires PRO or MAX plan'));
    });
  }

  /* ─ 12. BASIC HUD — hide advanced panels ────────────────────── */
  // [PLAN-HUD] For hudLevel='basic': hide advanced telemetry panels
  if (PLAN.hudLevel === 'basic') {
    const HIDE_TITLES = ['GPS_RAW_INT','VISION_POSITION','OBSTACLE_DISTANCE','PID TELEMETRY','LIVE TELEMETRY GRAPH'];
    document.querySelectorAll('.card-sm').forEach(card => {
      const title = card.querySelector('.card-title');
      if (!title) return;
      const t = title.textContent.toUpperCase();
      if (HIDE_TITLES.some(k => t.includes(k))) _hideEl(card);
    });
    // Hide attitude angle rows (keep only ALT + VEL for basic HUD)
    document.querySelectorAll('.tval-row').forEach(row => {
      const labels = Array.from(row.querySelectorAll('.tval-label')).map(l => l.textContent.trim());
      if (labels.some(l => ['PITCH','ROLL','YAW','HDNG'].includes(l))) _hideEl(row);
    });
  }

  /* ─ 13. SUPPORT LABEL ───────────────────────────────────────── */
  // [PLAN-SUPPORT] Inject support info into controls card
  document.querySelectorAll('.card-sm').forEach(card => {
    const title = card.querySelector('.card-title');
    if (!title || !title.textContent.includes('CONTROLS')) return;
    const sEl = document.createElement('div');
    sEl.style.cssText = 'font-size:10px;color:var(--txt4);margin-top:8px;padding-top:8px;border-top:1px solid var(--n3);';
    if (PLAN.support === 'priority') {
      sEl.innerHTML = '✅ <strong style="color:var(--s)">Priority email support</strong> included with your MAX plan';
    } else {
      sEl.innerHTML = '💬 Support: <a href="#" style="color:var(--p)">Community Forum</a>';
    }
    card.appendChild(sEl);
  });

  /* ─ 14. SESSION LIMITS & CLOUD SAVE ─────────────────────────── */
  let simTimeRemaining = -1;
  let simPlanName = 'FREE';
  let flightDurationSeconds = 0;
  let simPpm = 0.10;
  let timerInterval = null;
  let cloudTelemetryUrl = null;

  function syncSimLimits() {
    fetch('../api/get_sim_limits.php' + window.location.search)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          simPlanName = data.plan_name;
          simPpm = data.ppm;
          simTimeRemaining = data.time_remaining_seconds;
          if (simTimeRemaining === 0) {
            showTimeLimitModal();
          }
        }
      });
  }

  // Initial fetch and 1-minute polling interval
  syncSimLimits();
  setInterval(syncSimLimits, 60000);

  // Local 1-second timer
  timerInterval = setInterval(() => {
    flightDurationSeconds++;
    if (simTimeRemaining > 0) {
      simTimeRemaining--;
      if (simTimeRemaining <= 0) {
         showTimeLimitModal();
      }
    }
  }, 1000);

  window.showTimeLimitModal = function() {
    if (timerInterval) clearInterval(timerInterval);
    if (typeof toggleSimPause === 'function' && !document.getElementById('pause-btn').classList.contains('paused')) {
      toggleSimPause();
    }
    const modal = document.getElementById('sim-time-modal');
    if (modal) modal.style.display = 'flex';
  };

  let isExiting = false;
  window.exitSimulation = function() {
    if (isExiting) return;
    isExiting = true;
    
    const btn = document.getElementById('exit-sim-btn');
    if (btn) {
      btn.innerHTML = '⏳ Exiting...';
      btn.disabled = true;
    }
    
    let droneName = 'Unknown Drone';
    const profileEl = document.getElementById('drone-profile-label');
    if (profileEl) droneName = profileEl.innerText;

    // Auto save to R2
    fetch('../api/get_r2_upload_url.php', { method: 'POST' })
      .then(r => r.json())
      .then(data => {
         if (data.success) {
            let telemetryData = {};
            try {
              telemetryData = { log: BLACKBOX.getLog(), stats: BLACKBOX.getStats() };
            } catch(e) {}
            
            return fetch(data.uploadUrl, {
              method: 'PUT',
              body: JSON.stringify(telemetryData),
              headers: {'Content-Type': 'application/json'}
            }).then(res => {
               if (res.ok) {
                  cloudTelemetryUrl = data.publicUrl;
                  window.cloudTelemetryUrls = window.cloudTelemetryUrls || [];
                  window.cloudTelemetryUrls.push({ time: new Date().toLocaleTimeString(), url: data.publicUrl });
                  window.cloudTelemetryUrls = window.cloudTelemetryUrls || [];
                  window.cloudTelemetryUrls.push({ time: new Date().toLocaleTimeString() + ' (Auto)', url: data.publicUrl });
               }
            });
         }
      })
      .catch(err => {
         console.error('Auto cloud save failed:', err);
      })
      .finally(() => {
        const payload = {
          name: 'Simulation Session',
          drone: droneName,
          environment: 'Simulation',
          weather: 'Clear',
          mode: 'Manual',
          duration: flightDurationSeconds,
          status: 'completed',
          plan: simPlanName,
          ppm: simPpm,
          telemetry_url: cloudTelemetryUrl,
          telemetry_urls: window.cloudTelemetryUrls || []
        };
        fetch('../api/save_flight.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify(payload)
        }).then(() => {
          if (simPlanName === 'FREE' || simPlanName === 'BASIC') {
            window.location.href = '../dashboard.php?upgrade_telem=1';
          } else {
            window.location.href = '../simulations.php';
          }
        }).catch(() => {
          window.location.href = '../simulations.php';
        });
      });
  };

  window.triggerCloudSave = function() {
    if (simPlanName === 'FREE' || simPlanName === 'BASIC') {
       if (typeof UI !== 'undefined' && UI.toast) {
         UI.toast('☁️ Cloud Save requires PRO tier. Please upgrade.');
       } else {
         alert('☁️ Cloud Save is a premium feature. Please upgrade to the PRO tier to save telemetry to Cloudflare.');
       }
       return;
    }
    const btn = document.getElementById('cloud-save-btn');
    if (btn) btn.innerHTML = '⏳ Saving...';
    
    fetch('../api/get_r2_upload_url.php', { method: 'POST' })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
           let telemetryData = {};
           try {
             telemetryData = { log: BLACKBOX.getLog(), stats: BLACKBOX.getStats() };
           } catch(e) {}
           
           fetch(data.uploadUrl, {
             method: 'PUT',
             body: JSON.stringify(telemetryData),
             headers: {'Content-Type': 'application/json'}
           }).then(res => {
               if (res.ok) {
                  cloudTelemetryUrl = data.publicUrl;
                  window.cloudTelemetryUrls = window.cloudTelemetryUrls || [];
                  window.cloudTelemetryUrls.push({ time: new Date().toLocaleTimeString(), url: data.publicUrl });
                  if (typeof UI !== 'undefined' && UI.toast) UI.toast('✅ Telemetry saved to Cloudflare!');
                  else alert('✅ Telemetry saved to Cloudflare!');
                  updateSavedTelemBtn();
               } else {
                 res.text().then(errText => {
                   console.error('R2 upload failed:', errText);
                   alert('R2 Upload failed: ' + res.status + ' ' + res.statusText + '\nDetails: ' + errText);
                 });
              }
           }).catch(err => {
              console.error('Upload network error:', err);
              alert('Network/CORS error uploading to R2. Please check CORS settings on your bucket.');
           }).finally(() => {
              if (btn) btn.innerHTML = '☁️ Cloud Save';
           });
        } else {
           if (btn) btn.innerHTML = '☁️ Cloud Save';
           alert('Failed to get Cloudflare URL: ' + (data.message || data.error));
        }
      }).catch(err => {
         if (btn) btn.innerHTML = '☁️ Cloud Save';
         alert('Error fetching pre-signed URL: ' + err.message);
      });
  };

  console.log(`[CERTANITY SIM] Plan: ${PLAN.tierLabel} | Session: ${isFinite(PLAN.sessionSeconds) ? PLAN.sessionSeconds+'s' : 'Unlimited'}`);
});
