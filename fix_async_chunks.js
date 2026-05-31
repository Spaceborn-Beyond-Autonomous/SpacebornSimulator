const fs = require('fs');

const files = ['simulator/max.php', 'simulator/pro.php', 'simulator/basic.php', 'simulator/replay.php'];

for (const f of files) {
  if (!fs.existsSync(f)) continue;
  let s = fs.readFileSync(f, 'utf8');

  // Fix 1: Make _buildChunk async and yield between expensive generation steps
  const oldBuild = `  function _buildChunk(cx, cz, lod) {
    const key = _chunkKey(cx, cz);
    const existing = _chunks.get(key);
    if (existing) {
      if (existing.lod <= lod) return;
      _disposeChunkData(existing);
      _chunks.delete(key);
    }
    const segs = lod === 0 ? CHUNK_SEGS : CHUNK_SEGS_L;
    const chunkData = { cx, cz, lod };

    // World-space origin of this chunk
    const worldX = cx * CHUNK_SIZE;
    const worldZ = cz * CHUNK_SIZE;
    // Render-space offset (kept small regardless of world position)
    const renderX = worldX - _renderOriginX;
    const renderZ = worldZ - _renderOriginZ;

    chunkData.mesh = buildChunkMesh(cx, cz, _envName, segs);
    // buildChunkMesh places geometry relative to its own origin,
    // then we position the mesh group in render space
    chunkData.mesh.position.set(renderX, 0, renderZ);
    scene.add(chunkData.mesh);

    // Vegetation only on full-detail inner chunks
    if (lod === 0 && _envName !== 'indoor' && _envName !== 'urban') {
      const veg = buildVegetation(cx, cz, _envName);
      if (veg)     { veg.position.set(renderX, 0, renderZ);     scene.add(veg);     chunkData.veg     = veg;     }
      const flowers = buildFlowers(cx, cz, _envName);
      if (flowers) { flowers.position.set(renderX, 0, renderZ); scene.add(flowers); chunkData.flowers = flowers; }
      const grass = buildGrassBlades(cx, cz, _envName);
      if (grass)   { grass.position.set(renderX, 0, renderZ);   scene.add(grass);   chunkData.grass   = grass;   }
      const rocks = buildRocks(cx, cz, _envName);
      if (rocks)   { rocks.position.set(renderX, 0, renderZ);   scene.add(rocks);   chunkData.rocks   = rocks;   }
    }
    _chunks.set(key, chunkData);
  }`;

  const newBuild = `  async function _buildChunk(cx, cz, lod) {
    const key = _chunkKey(cx, cz);
    const existing = _chunks.get(key);
    if (existing) {
      if (existing.lod <= lod) return;
      _disposeChunkData(existing);
      _chunks.delete(key);
    }
    const segs = lod === 0 ? CHUNK_SEGS : CHUNK_SEGS_L;
    const chunkData = { cx, cz, lod };
    _chunks.set(key, chunkData); // Set immediately to prevent duplicates

    const worldX = cx * CHUNK_SIZE;
    const worldZ = cz * CHUNK_SIZE;
    const renderX = worldX - _renderOriginX;
    const renderZ = worldZ - _renderOriginZ;

    chunkData.mesh = buildChunkMesh(cx, cz, _envName, segs);
    chunkData.mesh.position.set(renderX, 0, renderZ);
    scene.add(chunkData.mesh);
    
    await new Promise(r => setTimeout(r, 0)); // Yield to main thread

    if (lod === 0 && _envName !== 'indoor' && _envName !== 'urban') {
      const veg = buildVegetation(cx, cz, _envName);
      if (veg) { veg.position.set(renderX, 0, renderZ); scene.add(veg); chunkData.veg = veg; }
      await new Promise(r => setTimeout(r, 0)); // Yield

      const flowers = buildFlowers(cx, cz, _envName);
      if (flowers) { flowers.position.set(renderX, 0, renderZ); scene.add(flowers); chunkData.flowers = flowers; }
      await new Promise(r => setTimeout(r, 0)); // Yield

      const grass = buildGrassBlades(cx, cz, _envName);
      if (grass) { grass.position.set(renderX, 0, renderZ); scene.add(grass); chunkData.grass = grass; }
      await new Promise(r => setTimeout(r, 0)); // Yield

      const rocks = buildRocks(cx, cz, _envName);
      if (rocks) { rocks.position.set(renderX, 0, renderZ); scene.add(rocks); chunkData.rocks = rocks; }
    }
  }`;
  
  // Replace robustly
  s = s.replace(/function _buildChunk\(cx, cz, lod\) \{[\s\S]*?_chunks\.set\(key, chunkData\);\s*\}/, newBuild);

  // Fix 2: Modify _drainLoadQueue to only process one async chunk at a time
  const oldDrain = `  function _drainLoadQueue() {
    const t0 = performance.now();
    while (_loadQueue.length > 0) {
      if (performance.now() - t0 > CHUNK_BUDGET_MS) break; // time budget exhausted
      const { cx, cz, lod } = _loadQueue.shift();
      const key = _chunkKey(cx, cz);
      const ex  = _chunks.get(key);
      if (!ex || ex.lod > lod) {
        _buildChunk(cx, cz, lod);
      }
    }
  }`;

  const newDrain = `  let _isBuildingChunk = false;
  function _drainLoadQueue() {
    if (_isBuildingChunk || _loadQueue.length === 0) return;
    const { cx, cz, lod } = _loadQueue.shift();
    const key = _chunkKey(cx, cz);
    const ex  = _chunks.get(key);
    if (!ex || ex.lod > lod) {
      _isBuildingChunk = true;
      _buildChunk(cx, cz, lod).then(() => { _isBuildingChunk = false; }).catch(e => { console.error(e); _isBuildingChunk = false; });
    }
  }`;

  s = s.replace(/function _drainLoadQueue\(\) \{[\s\S]*?if \(!ex \|\| ex\.lod > lod\) \{\s*_buildChunk\(cx, cz, lod\);\s*\}\s*\}\s*\}/, newDrain);

  fs.writeFileSync(f, s, 'utf8');
  console.log('Applied async chunk logic to ' + f);
}

try { fs.copyFileSync('simulator/max.php', 'simulator/max_standalone (1).html'); } catch(e) {}
