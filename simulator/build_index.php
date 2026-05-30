<?php
// Rebuild standalone index.html from new max.php + new sim-engine.js

$content = file_get_contents('max.php');

// 1. Remove PHP tag at the start
$content = preg_replace('/<\?php.*?\?>\s*/s', '', $content, 1);

// 2. Replace PHP echo statements in PLAN definition with hardcoded MAX values
$content = preg_replace('/<\?= max.*? \?>/', '43200', $content); // 43200 minutes = 30 days
$content = preg_replace('/<\?= \$accessSeconds.*? \?>/', '2592000', $content); // 2592000 seconds
$content = preg_replace('/<\?= \(int\) \$accessExpiresAt \?>/', '0', $content);

// 3. Inline sim-engine.js into the HTML
$sim_engine_js = file_get_contents('sim-engine.js');
$content = str_replace(
    '<script src="sim-engine.js"></script>',
    "<script>\n" . $sim_engine_js . "\n</script>",
    $content
);

// 4. Remove favicon/assets references that would 404 offline
$content = str_replace('href="../assets/logo-iso.png"', 'href=""', $content);

// 5. Replace the session limits / cloud save / exit with offline-safe stubs
// Replace syncSimLimits and related server calls
$oldSessionCode = <<<'OLD'
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
OLD;

$newSessionCode = <<<'NEW'
  // Standalone mode: no server sync needed
  simPlanName = 'MAX';
  simPpm = 0.01;
  simTimeRemaining = 2592000; // 30 days
NEW;

$content = str_replace($oldSessionCode, $newSessionCode, $content);

// 6. Replace exitSimulation with offline stub
$content = preg_replace(
    '/let isExiting = false;\s*window\.exitSimulation = function\(\).*?};/s',
    'window.exitSimulation = function() { alert("Simulation exited. (Offline mode)"); };',
    $content
);

// 7. Replace triggerCloudSave with offline stub
$content = preg_replace(
    '/window\.triggerCloudSave = function\(\).*?};/s',
    'window.triggerCloudSave = function() { alert("Cloud save is disabled in offline mode."); };',
    $content
);

// 8. Remove billing/upgrade links in the time limit modal
$content = str_replace(
    '<a class="nbtn primary" href="../billing.php" style="text-decoration:none;">Upgrade / Add Balance</a>',
    '<button class="nbtn primary" onclick="location.reload()">Restart Simulator</button>',
    $content
);

file_put_contents('index.html', $content);
echo "index.html rebuilt successfully!\n";
echo "Size: " . strlen($content) . " bytes\n";
echo "Lines: " . substr_count($content, "\n") . "\n";
