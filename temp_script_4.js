
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
