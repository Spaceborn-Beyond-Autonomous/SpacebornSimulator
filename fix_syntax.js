const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // Fix the syntax error left behind by the bad regex
  const regex = /UI\.toast\('.*?Recording started'\);\s*\} else \{\s*BLACKBOX\.stop\(\);\s*if \(btn\) \{ btn\.textContent = '.*?Record'; btn\.classList\.remove\('active-btn'\); \}\s*UI\.toast\('.*?Recording stopped.*?' \+ BLACKBOX\.getLog\(\)\.length \+ ' frames'\);\s*updateExportStats\(\);\s*\}\s*\}\s*function updateRecordingUI\(\) \{\s*if \(!_recording\) return;\s*const n = BLACKBOX\.getLog\(\)\.length;\s*const btn = document\.getElementById\('rec-btn'\);\s*if \(btn && n % 30 === 0\) btn\.textContent = '.*?' \+ n \+ 'f';\s*\}/;
  
  s = s.replace(regex, '');
  
  // Also _recording is used in _loop() maybe? No, only in updateRecordingUI() and toggleRecording().

  fs.writeFileSync(f, s, 'utf8');
  console.log('Fixed syntax error in ' + f);
}

try {
  fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html');
} catch(e) {}
