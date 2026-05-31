const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // 1. buildChunkMesh
  s = s.replace(/function buildChunkMesh\(cx, cz, envName, segs\) \{/, `async function buildChunkMesh(cx, cz, envName, segs) {`);
  s = s.replace(/for \(let i = 0; i < pos\.count; i\+\+\) \{/, `for (let i = 0; i < pos.count; i++) {
        if (i % 400 === 0 && i !== 0) await new Promise(r => setTimeout(r, 0));`);

  // 2. buildGrassBlades
  s = s.replace(/function buildGrassBlades\(cx, cz, envName\)\{/, `async function buildGrassBlades(cx, cz, envName){`);
  s = s.replace(/for \(let i = 0; i < count; i\+\+\) \{\s*const lx = \(rng\(\)-0\.5\)\*CHUNK_SIZE;/, `for (let i = 0; i < count; i++) {
        if (i % 100 === 0 && i !== 0) await new Promise(r => setTimeout(r, 0));
        const lx = (rng()-0.5)*CHUNK_SIZE;`);

  // 3. buildVegetation
  s = s.replace(/function buildVegetation\(cx, cz, envName\)\{/, `async function buildVegetation(cx, cz, envName){`);
  s = s.replace(/for \(let i = 0; i < count; i\+\+\) \{\s*const lx = \(rng\(\)-0\.5\)\*CHUNK_SIZE\*0\.85;/, `for (let i = 0; i < count; i++) {
        if (i % 5 === 0 && i !== 0) await new Promise(r => setTimeout(r, 0));
        const lx = (rng()-0.5)*CHUNK_SIZE*0.85;`);

  // 4. buildFlowers
  s = s.replace(/function buildFlowers\(cx, cz, envName\)\{/, `async function buildFlowers(cx, cz, envName){`);
  s = s.replace(/for \(let i = 0; i < count; i\+\+\) \{\s*const lx = \(rng\(\)-0\.5\)\*CHUNK_SIZE\*0\.8;/, `for (let i = 0; i < count; i++) {
        if (i % 20 === 0 && i !== 0) await new Promise(r => setTimeout(r, 0));
        const lx = (rng()-0.5)*CHUNK_SIZE*0.8;`);

  // 5. buildRocks
  s = s.replace(/function buildRocks\(cx, cz, envName\)\{/, `async function buildRocks(cx, cz, envName){`);
  s = s.replace(/for \(let i = 0; i < count; i\+\+\) \{\s*const lx = \(rng\(\)-0\.5\)\*CHUNK_SIZE\*0\.9;/, `for (let i = 0; i < count; i++) {
        if (i % 5 === 0 && i !== 0) await new Promise(r => setTimeout(r, 0));
        const lx = (rng()-0.5)*CHUNK_SIZE*0.9;`);

  // 6. Update _buildChunk to await these functions!
  // Wait, in my previous fix_async_chunks.js, I already made _buildChunk async.
  // I just need to add 'await' before calling them!
  s = s.replace(/chunkData\.mesh = buildChunkMesh\(cx, cz, _envName, segs\);/, `chunkData.mesh = await buildChunkMesh(cx, cz, _envName, segs);`);
  s = s.replace(/const veg = buildVegetation\(cx, cz, _envName\);/, `const veg = await buildVegetation(cx, cz, _envName);`);
  s = s.replace(/const flowers = buildFlowers\(cx, cz, _envName\);/, `const flowers = await buildFlowers(cx, cz, _envName);`);
  s = s.replace(/const grass = buildGrassBlades\(cx, cz, _envName\);/, `const grass = await buildGrassBlades(cx, cz, _envName);`);
  s = s.replace(/const rocks = buildRocks\(cx, cz, _envName\);/, `const rocks = await buildRocks(cx, cz, _envName);`);

  fs.writeFileSync(f, s, 'utf8');
  console.log('Applied internal yielding to ' + f);
}

try { fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html'); } catch(e) {}
