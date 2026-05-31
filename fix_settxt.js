const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  let s = fs.readFileSync(f, 'utf8');
  
  if (!s.includes('const _setTxt = ')) {
    // Inject right after the first <script> tag
    s = s.replace(/<script>/, "<script>\nconst _setTxt = (el, txt) => { if (el && el.textContent !== String(txt)) el.textContent = txt; };\n");
  }
  
  fs.writeFileSync(f, s, 'utf8');
  console.log('Fixed _setTxt in ' + f);
}

try {
  fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html');
} catch(e) {}
