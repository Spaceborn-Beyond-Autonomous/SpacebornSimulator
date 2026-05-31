const fs = require('fs');
const html = fs.readFileSync('simulator/max.php', 'utf8');
const scriptRegex = /<script>([\s\S]*?)<\/script>/g;
let match;
let i = 0;
while ((match = scriptRegex.exec(html)) !== null) {
  fs.writeFileSync(`temp_script_${i}.js`, match[1]);
  i++;
}
