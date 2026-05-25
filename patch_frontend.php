<?php
$files = [
    'c:/xampp/htdocs/SpaceBorn/SpaceBorn/simulator/pro.php',
    'c:/xampp/htdocs/SpaceBorn/SpaceBorn/simulator/max.php'
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // 1. Add the "Saved (0)" button next to Cloud Save in topbar
    $topbarSearch = 'title="Save Telemetry to Cloudflare">☁️ Cloud Save</button>';
    $topbarReplace = 'title="Save Telemetry to Cloudflare">☁️ Cloud Save</button>' . "\n" . '    <button class="nbtn sm accent" id="saved-telem-btn" style="display:none;background:var(--s);color:#fff;" onclick="openSavedTelemModal()">📥 Saved (0)</button>';
    
    if (strpos($content, 'id="saved-telem-btn"') === false) {
        $content = str_replace($topbarSearch, $topbarReplace, $content);
    }
    
    // 2. Add the Modal HTML at the bottom of the body
    $modalHtml = <<<HTML
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
        list.innerHTML += `<a href="\${item.url}" target="_blank" download class="btn-solid" style="display:flex;align-items:center;gap:8px;padding:12px;background:var(--surf);border-radius:var(--r1);text-decoration:none;color:var(--txt);box-shadow:var(--sh-btn);font-size:13px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Snapshot \${i+1} (\${item.time})
        </a>`;
     });
  }
  document.getElementById('saved-telem-modal').style.display = 'flex';
}
function updateSavedTelemBtn() {
   const btn = document.getElementById('saved-telem-btn');
   if (btn && window.cloudTelemetryUrls && window.cloudTelemetryUrls.length > 0) {
      btn.style.display = 'inline-block';
      btn.innerHTML = `📥 Saved (\${window.cloudTelemetryUrls.length})`;
   }
}
</script>
</body>
HTML;
    if (strpos($content, 'id="saved-telem-modal"') === false) {
        $content = str_replace('</body>', $modalHtml, $content);
    }
    
    // 3. Update triggerCloudSave to call updateSavedTelemBtn()
    $jsSearch = "window.cloudTelemetryUrls.push({ time: new Date().toLocaleTimeString(), url: data.publicUrl });";
    $jsReplace = "window.cloudTelemetryUrls.push({ time: new Date().toLocaleTimeString(), url: data.publicUrl });\n                  if(typeof updateSavedTelemBtn === 'function') updateSavedTelemBtn();";
    
    if (strpos($content, "updateSavedTelemBtn()") === false) {
        $content = str_replace($jsSearch, $jsReplace, $content);
    }
    
    file_put_contents($file, $content);
    echo "Patched $file\n";
}
