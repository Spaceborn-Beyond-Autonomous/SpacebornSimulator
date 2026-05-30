<?php
// Read the new standalone HTML
$html = file_get_contents('max_standalone (1).html');

// 1. Fix the "i hav aa" garbage at the start if it exists
$html = preg_replace('/^.*?<!DOCTYPE html>/s', '<!DOCTYPE html>', $html);

// 2. Replace the inlined sim-engine block with external script tag
$html = preg_replace(
    '/<!-- Sim Engine \(external\) -->\s*<script>.*?<\/script>\s*(?=<!-- ══ TIER: MAX ══ -->)/s',
    "<!-- Sim Engine (external) -->\n<script src=\"sim-engine.js?v=<?= time() ?>\"></script>\n\n",
    $html
);

// 3. Add the Cloud Save and Exit buttons into the top nav bar
$buttonsHtml = <<<HTML
    <div class="tsp"></div>
    <button class="nbtn sm accent" id="cloud-save-btn" onclick="triggerCloudSave()" title="Save Telemetry to Cloudflare">☁️ Cloud Save</button>
    <button class="nbtn sm accent" id="saved-telem-btn" style="display:none;background:var(--s);color:#fff;" onclick="openSavedTelemModal()">📥 Saved (0)</button>
    <button class="nbtn sm danger" id="exit-sim-btn" onclick="exitSimulation()" title="Exit and Save Flight">🚪 Exit</button>
    <button class="nbtn sm" id="pause-btn" onclick="toggleSimPause()" title="Pause/Resume Simulation (Space)">⏸ Pause</button>
HTML;

$html = preg_replace(
    '/<div class="tsp"><\/div>\s*<button class="nbtn sm" id="pause-btn".*?<\/button>/',
    $buttonsHtml,
    $html
);

// 4. (Removed redundant _simUIFrame string replacements)

// 5. Replace the hardcoded PLAN definition with PHP-echoed values
$oldPlan = <<<'PLAN'
const PLAN = {
  tier: 'MAX',
  sessionMinutes: Infinity,
  droneProfiles: ['racing5','cinequad','micro2','explorer6'],
  environments: ['field','mountains','urban','indoor','desert','windy'],
  waypointMissions: true,
  pidTuning: 'full',
  dataExport: true,
  mavlinkLogs: 'download',
  customGLTF: true,
  joystickGamepad: true,
  hudLevel: 'full',
  nightMode: true,
  windScenario: true,
  support: 'priority',
  tierLabel: 'MAX',
  tierColor: '#EE9346',
};
PLAN;

$newPlan = <<<'PLAN'
const PLAN = {
  tier: 'PRO',
  sessionMinutes: <?= max(1, (int) ceil(($accessSeconds > 0 ? $accessSeconds : 86400) / 60)) ?>,
  sessionSeconds: <?= $accessSeconds > 0 ? min($accessSeconds, 86400) : 86400 ?>,
  planExpiresAt: <?= (int) $accessExpiresAt ?>,
  droneProfiles: ['racing5', 'micro2'],
  environments: ['field', 'mountains', 'urban'],
  waypointMissions: false,
  pidTuning: 'view',
  dataExport: false,
  mavlinkLogs: 'readonly',
  customGLTF: false,
  joystickGamepad: false,
  hudLevel: 'basic',
  nightMode: true,
  windScenario: true,
  support: 'community',
  tierLabel: 'PRO',
  tierColor: '#607D8B',
};
PLAN;

$html = str_replace($oldPlan, $newPlan, $html);

// 6. Add favicon links after the <title> tag
$html = str_replace(
    '<title>CERTANITY · Drone Simulator — MAX</title>',
    '<title>CERTANITY · Drone Simulator — MAX</title>' . "\n" .
    '<link rel="icon" type="image/png" href="../assets/logo-iso.png" />' . "\n" .
    '<link rel="apple-touch-icon" href="../assets/logo-iso.png" />',
    $html
);

// 7. Replace the final console.log and closing section with the session limits + cloud save code
$oldEnding = <<<'END'
  console.log(`[CERTANITY SIM] Plan: ${PLAN.tierLabel} | Session: ${isFinite(PLAN.sessionMinutes) ? PLAN.sessionMinutes+'min' : 'Unlimited'}`);
});
</script>

</body>
</html>
END;

$sessionLimitsCode = <<<'SESSION'
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
</script>

<!-- Time Limit Modal -->
<div id="sim-time-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.85);backdrop-filter:blur(5px);align-items:center;justify-content:center;">
  <div class="card" style="width:360px;text-align:center;padding:32px;">
    <div style="font-size:32px;margin-bottom:12px;">⏳</div>
    <h2 style="font-family:var(--fh);font-size:20px;color:var(--txt);margin-bottom:8px;">Time Limit Reached</h2>
    <p style="font-size:13px;color:var(--txt2);margin-bottom:24px;line-height:1.5;">Your available simulation time has ended. Please add balance to your wallet or upgrade your plan to continue flying.</p>
    <div class="nbtn-row" style="justify-content:center;gap:12px;">
      <a class="nbtn primary" href="../billing.php" style="text-decoration:none;">Upgrade / Add Balance</a>
      <button class="nbtn danger" onclick="window.close()">Close Window</button>
    </div>
  </div>
</div>

<!-- Saved Telemetry Modal -->
<div id="saved-telem-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.85);backdrop-filter:blur(5px);align-items:center;justify-content:center;">
  <div class="card" style="width:400px;max-height:80vh;display:flex;flex-direction:column;padding:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h2 style="font-family:var(--fh);font-size:18px;color:var(--txt);">Saved Telemetry</h2>
      <button class="nbtn sm" onclick="document.getElementById('saved-telem-modal').style.display='none'">✕</button>
    </div>
    <div id="saved-telem-list" style="display:flex;flex-direction:column;gap:8px;overflow-y:auto;padding-right:4px;">
    </div>
  </div>
</div>
<script>
function openSavedTelemModal() {
  const list = document.getElementById('saved-telem-list');
  list.innerHTML = '';
  if (!window.cloudTelemetryUrls || window.cloudTelemetryUrls.length === 0) {
     list.innerHTML = '<div style="color:var(--txt3);font-size:13px;text-align:center;padding:20px;">No telemetry saved yet.</div>';
  } else {
     window.cloudTelemetryUrls.forEach((item, i) => {
        list.innerHTML += `<a href="${item.url}" target="_blank" download class="btn-solid" style="display:flex;align-items:center;gap:8px;padding:12px;background:var(--surf);border-radius:var(--r1);text-decoration:none;color:var(--txt);box-shadow:var(--sh-btn);font-size:13px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Snapshot ${i+1} (${item.time})
        </a>`;
     });
  }
  document.getElementById('saved-telem-modal').style.display = 'flex';
}
function updateSavedTelemBtn() {
   const btn = document.getElementById('saved-telem-btn');
   if (btn && window.cloudTelemetryUrls && window.cloudTelemetryUrls.length > 0) {
      btn.style.display = 'inline-block';
      btn.innerHTML = `📥 Saved (${window.cloudTelemetryUrls.length})`;
   }
}
</script>
</body>
</html>
SESSION;

$html = str_replace($oldEnding, $sessionLimitsCode, $html);

// 8. Prepend the PHP header
$phpHeader = <<<'PHP'
<?php
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../includes/simulator_launch.php';

// Tier guard: PRO simulator requires sub_id >= 2 or wallet-based run with run_plan=PRO
$email = $_SESSION['email'] ?? '';
$user = $db->users->findOne(['email' => $email]);
$sub_id = (int)($user['sub_id'] ?? 0);
$run_plan = strtoupper($_GET['run_plan'] ?? '');
$paidState = sb_paid_plan_state($user, true);
$paidSessionSeconds = max(0, (int) ($paidState['remaining_seconds'] ?? 0));
$proPpm = (float) ($_ENV['PLAN_PRO_PPM'] ?? 0.05);
$walletSeconds = ($user && (float) ($user['wallet_balance'] ?? 0.0) > 0 && $proPpm > 0)
    ? (int) (((float) ($user['wallet_balance'] ?? 0.0) / $proPpm) * 60)
    : 0;
$accessSeconds = 0;

// Allow access if: subscribed to PRO/MAX, OR running on wallet with run_plan=PRO
$allowed = ($sub_id >= 2) || ($sub_id === 0 && $run_plan === 'PRO');
if (!$allowed) {
    header('Location: ../dashboard.php?error=tier_mismatch');
    exit;
}

if ($sub_id >= 2) {
    if ($paidSessionSeconds > 0) {
        $walletSeconds = 0;
        $accessSeconds = $paidSessionSeconds;
    } else {
        $accessSeconds = $walletSeconds;
    }
} elseif ($sub_id === 0 && $run_plan === 'PRO') {
    $accessSeconds = $walletSeconds;
}

$accessExpiresAt = $accessSeconds > 0 ? time() + $accessSeconds : 0;
?>
PHP;

$html = $phpHeader . $html;

// Write the result
file_put_contents('pro.php', $html);
echo "pro.php rebuilt successfully from max_standalone (1).html!\n";
echo "Lines: " . substr_count($html, "\n") . "\n";
echo "Bytes: " . strlen($html) . "\n";
