
'use strict';

/* ══════════════════════════════════════════════════════════════════════
   THREE.JS ENVIRONMENT — v3.0  BSL CINEMATIC VISUAL UPGRADE
   Visual Enhancements Only — All physics/controls untouched
   - BSL-inspired sky shader with atmospheric scattering & god rays
   - Volumetric fog layers with animated drift
   - Bloom post-processing via additive compositing
   - Infinite chunk-based terrain with seamless streaming
   - Dense vegetation: grass, flowers, ferns, varied trees, rocks
   - PBR drone materials: MeshStandardMaterial with reflections
   - Soft PCF shadows, ACES tonemapping, high-quality rendering
   - Animated grass blades via vertex shader
   - Cinematic depth haze and horizon glow
══════════════════════════════════════════════════════════════════════ */

const THREE_ENV = (() => {
  let renderer, scene, camera, clock;
  let droneGroup, bodyMesh, propMeshes = [], armMeshes = [];
  let propAngle = [0,0,0,0];
  let shadowLight, hemiLight, sunLight, moonLight;
  let skyMesh, cloudGroup, rainSystem, fogPlane;
  let _canvas, _envName = 'field';
  let _camMode = 'third';
  let _orbitAngle = 0, _orbitDist = 8, _orbitH = 3;
  let _freeCam = { x:0, y:8, z:-14, rx:-0.3, ry:0 };
  let _prevDronePos = { x:0, y:0.2, z:0 };
  let _dayTime = 0.55;
  let _nightMode = false;
  let _rainOn = false, _fogOn = false;
  let _rainParticles, _rainGeo, _rainPositions;
  let _trailPoints = [], _trailLine;
  let _waypointMarkers = [];
  let _propSpinRate = [0,0,0,0];

  // ── Chunk streaming constants ──────────────────────────────────────
  const CHUNK_SIZE   = 80;
  const CHUNK_SEGS   = 24;   // full-detail segment count (reduced for perf)
  const CHUNK_SEGS_L = 8;    // low-detail segment count (outer ring)
  const RENDER_DIST  = 3;    // full-detail ring radius (chunks)
  const LOD_DIST     = 6;    // low-detail ring radius (chunks)
  let _chunks        = new Map(); // key -> chunkData
  let _lastChunkX    = null, _lastChunkZ = null;
  // Async load queue — process at most N chunks per render frame
  let _loadQueue     = [];
  const MAX_LOADS_PER_FRAME = 1; // 1 full chunk/frame = smooth 60fps

  // ── Floating-origin render space ───────────────────────────────────
  // Physics is in world space (true JS doubles, unlimited range).
  // Three.js scene is rebased so the camera is always near origin,
  // preventing float32 precision loss at large coordinates.
  const REBASE_THRESHOLD = CHUNK_SIZE * 4;  // rebase when camera drifts >320m from render origin
  let _renderOriginX = 0, _renderOriginZ = 0; // render origin in world coords
  let _needsFullRebase = false;

  // Convert world XZ to render-space XZ
  function _toRender(wx, wz) {
    return { x: wx - _renderOriginX, z: wz - _renderOriginZ };
  }

  // Rebase: shift all scene objects so camera stays near render origin
  function _rebaseRenderOrigin() {
    const p = PHYS.pos;
    const newOX = Math.round(p.x / CHUNK_SIZE) * CHUNK_SIZE;
    const newOZ = Math.round(p.z / CHUNK_SIZE) * CHUNK_SIZE;
    const dx = newOX - _renderOriginX;
    const dz = newOZ - _renderOriginZ;
    if (Math.abs(dx) < 1 && Math.abs(dz) < 1) return;

    // Shift every chunk mesh in the scene by -delta
    for (const [, cd] of _chunks) {
      ['mesh','veg','flowers','grass','rocks'].forEach(k => {
        if (cd[k]) {
          cd[k].position.x -= dx;
          cd[k].position.z -= dz;
        }
      });
    }
    // Shift waypoint markers
    if (_waypointMarkers) {
      _waypointMarkers.forEach(m => {
        m.position.x -= dx;
        m.position.z -= dz;
      });
    }
    // Shift drone trail
    if (_trailLine) {
      _trailLine.position.x -= dx;
      _trailLine.position.z -= dz;
    }
    _renderOriginX = newOX;
    _renderOriginZ = newOZ;
    _needsFullRebase = false;
  }

  // Mouse orbit
  let _mouse = { down:false, lx:0, ly:0 };

  // Bloom compositing
  let _bloomRT, _bloomScene, _bloomCamera, _bloomQuad;
  let _mainRT;

  // ── Multi-biome terrain heightmap ────────────────────────────────
  // Uses domain-warped FBM + continent mask to blend biomes seamlessly.
  // The same (x,z) always returns the same height for a given seed.
  // ─────────────────────────────────────────────────────────────────
  // Terrain architecture (for procedural/infinite envs):
  //   continent(x,z) ∈ [0,1]  — low-frequency "where is high ground?"
  //   erosion(x,z)   ∈ [0,1]  — medium-freq ridges vs smooth slopes
  //   detail(x,z)    ∈ [-1,1] — high-freq surface texture
  //   h = continent^2 * 80 + erosion * 20 + detail * 3
  // ─────────────────────────────────────────────────────────────────

  // Cache last result per env to avoid recalculating same point twice
  let _thCache = null;
  function terrainHeight(x, z, envName) {
    const env = envName || _envName;

    // Flat environments
    if (env === 'urban' || env === 'indoor') return 0;

    // Domain-warped low-frequency continent mask (very smooth, no sharp edges)
    const cx  = x * 0.004, cz = z * 0.004;
    const wx  = Noise.n(cx + 3.7, 0.1, cz + 1.3) * 18;
    const wz  = Noise.n(cx + 8.2, 0.8, cz + 6.1) * 18;
    const continent = Math.max(0, Noise.fbm(cx + wx*0.004, 0, cz + wz*0.004, 4, 0.55, 2.0) * 0.5 + 0.5);

    if (env === 'field' || env === 'windy') {
      // Gentle rolling hills — continent kept low, heavy smoothing
      const base = Math.pow(continent * 0.55, 1.6) * 14;
      const mid  = Noise.fbm(x*0.022, 1.2, z*0.022, 4, 0.48, 2.0) * 6;
      const fine = Noise.n(x*0.14, 2.3, z*0.14) * 1.2;
      return Math.max(0, base + mid + fine);
    }

    if (env === 'desert') {
      // Dune ridges: elongated in one direction + flat inter-dune pans
      const duneDir = x * 0.018 + z * 0.006; // asymmetric dune axis
      const dune    = Math.pow(Math.abs(Noise.n(duneDir, 0.3, z*0.014)), 0.7) * 20;
      const pan     = Math.max(0, Noise.fbm(x*0.009, 0.8, z*0.009, 3, 0.45, 2.0)) * 8;
      const fine    = Noise.n(x*0.12, 1.1, z*0.12) * 1.5;
      return Math.max(0, dune + pan + fine);
    }

    if (env === 'mountains') {
      // Sharp, varied peaks using ridged noise + domain warp
      // Ridged noise: 1 - |fbm|  → inverted valleys, sharp ridges
      const raw   = Noise.warpedFbm(x, z, 5, 0.55, 2.1, 40);
      const ridge = Math.pow(Math.max(0, continent * 0.8 + raw * 0.5 + 0.1), 1.5);
      const peak  = ridge * 80;
      // Erosion detail layered on top
      const erode = Noise.fbm(x*0.05, 0.5, z*0.05, 3, 0.42, 2.0) * 10 * continent;
      const scree = Noise.n(x*0.18, 2.1, z*0.18) * 2.5;
      return Math.max(0, peak + erode + scree);
    }

    // Default / generic procedural world
    const raw    = Noise.warpedFbm(x, z, 5, 0.52, 2.0, 30);
    const height = Math.pow(Math.max(0, continent * 0.7 + raw * 0.4 + 0.15), 1.4) * 50;
    const detail = Noise.fbm(x*0.08, 1.5, z*0.08, 3, 0.4, 2.0) * 5;
    return Math.max(0, height + detail);
  }

  // ── Safe spawn finder ─────────────────────────────────────────────
  // Drone was spawning inside mountains because (0,0) can be mid-peak.
  // Now searches a wider grid to find the lowest-elevation flat spot.
  function getSafeSpawnPoint(envName) {
    const env = envName || _envName;
    if (env === 'indoor' || env === 'urban') {
      return { x: 0, z: 0, y: 0 };
    }
    if (env === 'field' || env === 'windy') {
      const h = terrainHeight(0, 0, env);
      return { x: 0, z: 0, y: h };
    }
    // For mountains and desert, find the lowest valley point in a wide grid
    let bestX = 0, bestZ = 0, bestH = Infinity;
    const step = 6, range = 100;
    for (let xi = -range; xi <= range; xi += step) {
      for (let zi = -range; zi <= range; zi += step) {
        const h = terrainHeight(xi, zi, env);
        if (h < bestH) { bestH = h; bestX = xi; bestZ = zi; }
      }
    }
    // Fine-search around best candidate
    const fStep = 2, fRange = 8;
    for (let xi = bestX - fRange; xi <= bestX + fRange; xi += fStep) {
      for (let zi = bestZ - fRange; zi <= bestZ + fRange; zi += fStep) {
        const h = terrainHeight(xi, zi, env);
        if (h < bestH) { bestH = h; bestX = xi; bestZ = zi; }
      }
    }
    return { x: bestX, z: bestZ, y: bestH };
  }

  // ── Terrain colour helper ──────────────────────────────────────────
  // Layered colour with micro-variation and slope-based darkening
  function terrainColor(x, z, h, envName) {
    const env = envName || _envName;
    let r, g, b;

    // Shared micro-variation (same for all envs)
    const v1 = Noise.n(x*0.11, 0.3, z*0.11) * 0.07;
    const v2 = Noise.n(x*0.32, 1.4, z*0.32) * 0.03;
    const nv = v1 + v2; // net variation

    if (env === 'desert') {
      const ripple = Noise.n(x*0.35, 0.0, z*0.22) * 0.05;
      r = 0.82 + nv + ripple;
      g = 0.66 + nv*0.7;
      b = 0.30 + nv*0.3;
    } else if (env === 'mountains') {
      // Smooth height-based blend: grass → rock → scree → snow
      if (h < 3) {
        r=0.28+nv; g=0.52+nv*0.6; b=0.16+nv*0.3;
      } else if (h < 12) {
        const t=(h-3)/9;
        r=0.28+t*0.24+nv; g=0.52-t*0.16+nv*0.3; b=0.16+t*0.12+nv*0.2;
      } else if (h < 42) {
        const t=(h-12)/30;
        r=0.52+t*0.16+nv*0.5; g=0.36+t*0.08+nv*0.3; b=0.28+t*0.14+nv*0.2;
      } else if (h < 65) {
        const t=(h-42)/23;
        r=0.68+t*0.18+nv*0.3; g=0.44+t*0.22+nv*0.2; b=0.42+t*0.26+nv*0.2;
      } else {
        // Snow — slight blue tint in shadows
        r=0.90+nv*0.1; g=0.93+nv*0.08; b=0.98+nv*0.05;
      }
    } else if (env === 'urban') {
      r=0.34+nv; g=0.34+nv; b=0.34+nv;
    } else {
      // Field / procedural — moisture-driven greens
      const moisture = Noise.fbm(x*0.018, 3.3, z*0.018, 2, 0.5, 2) * 0.5 + 0.5;
      r = 0.16 + nv - moisture*0.04 + h*0.006;
      g = 0.46 + nv*0.5 + moisture*0.10 + h*0.012;
      b = 0.12 + nv*0.3 - moisture*0.02;
    }
    return [Math.min(1,Math.max(0,r)), Math.min(1,Math.max(0,g)), Math.min(1,Math.max(0,b))];
  }


  // ── Per-chunk seeded RNG — identical output every time a chunk is rebuilt ──
  // Uses a simple xorshift32 so each (cx,cz) produces the same vegetation layout.
  function _chunkRng(cx, cz) {
    let s = (cx * 73856093) ^ (cz * 19349663);
    s = s ^ (s >>> 16); s = (s * 0x45d9f3b) & 0xffffffff;
    s = s ^ (s >>> 16);
    return function() {
      s ^= s << 13; s ^= s >> 17; s ^= s << 5;
      return ((s >>> 0) / 0xffffffff);
    };
  }

  // ── Single chunk terrain mesh ──────────────────────────────────────
  function buildChunkMesh(cx, cz, envName, segs) {
    const s = segs || CHUNK_SEGS;
    const geo = new THREE.PlaneGeometry(CHUNK_SIZE, CHUNK_SIZE, s, s);
    geo.rotateX(-Math.PI/2);
    const pos = geo.attributes.position;
    const colors = [];
    // World-space origin of this chunk (used for terrain eval only)
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    for (let i = 0; i < pos.count; i++) {
      // Vertex is chunk-local (PlaneGeometry centred at 0)
      const localX = pos.getX(i);
      const localZ = pos.getZ(i);
      // Evaluate terrain in true world coords (double precision JS numbers)
      const wx = localX + worldOffX;
      const wz = localZ + worldOffZ;
      const h = terrainHeight(wx, wz, envName);
      pos.setY(i, h);
      const [r,g,b] = terrainColor(wx, wz, h, envName);
      colors.push(r, g, b);
    }
    geo.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
    geo.computeVertexNormals();
    const mat = new THREE.MeshLambertMaterial({ vertexColors: true });
    const mesh = new THREE.Mesh(geo, mat);
    // Position set by _buildChunk in render space — leave at zero here
    mesh.position.set(0, 0, 0);
    mesh.receiveShadow = true;
    mesh.name = 'terrain_chunk';
    return mesh;
  }

  // ── Grass blade system (per-chunk) ────────────────────────────────
  let _grassTime = 0;
  function buildGrassBlades(cx, cz, envName){
    const rng = _chunkRng(cx, cz);
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor' || env === 'desert') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const count = env === 'mountains' ? 200 : 600;
    const positions = [], colors2 = [], indices2 = [];
    let vi = 0;
    // Each blade: 3 quads (6 verts)
    for (let i = 0; i < count; i++) {
      const lx = (rng()-0.5)*CHUNK_SIZE;
      const lz = (rng()-0.5)*CHUNK_SIZE;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, lz, env);
      const h = 0.18 + rng()*0.22;
      const ang = rng()*Math.PI*2;
      const bx = Math.cos(ang)*0.04, bz = Math.sin(ang)*0.04;
      // color variation
      const gv = 0.3 + rng()*0.3;
      const rc = 0.1+gv*0.3, gc = 0.4+gv*0.35, bc = 0.05+gv*0.1;
      // base L
      positions.push(lx-bx, hy, wz-bz, wx+bx, hy, wz+bz, wx, hy+h, lz);
      colors2.push(rc*0.7,gc*0.7,bc*0.7, rc*0.7,gc*0.7,bc*0.7, rc,gc,bc);
      indices2.push(vi,vi+1,vi+2);
      vi += 3;
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geo.setAttribute('color', new THREE.Float32BufferAttribute(colors2, 3));
    geo.setIndex(indices2);
    geo.computeVertexNormals();
    const mat = new THREE.MeshLambertMaterial({ vertexColors: true, side: THREE.DoubleSide });
    const mesh = new THREE.Mesh(geo, mat);
    return mesh;
  }

  // ── Flowers ────────────────────────────────────────────────────────
  function buildFlowers(cx, cz, envName){
    const rng = _chunkRng(cx + 1000, cz + 2000);
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor' || env === 'desert' || env === 'mountains') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const group = new THREE.Group();
    const flowerColors = [0xff4466, 0xffcc22, 0xff8844, 0xee66ff, 0xffffff, 0x66ddff];
    const stemMat = new THREE.MeshLambertMaterial({ color: 0x2d7a1a });
    const count = 30 + Math.floor(rng()*40);
    for (let i = 0; i < count; i++) {
      const lx = (rng()-0.5)*CHUNK_SIZE;
      const lz = (rng()-0.5)*CHUNK_SIZE;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, lz, env);
      const h = 0.14 + rng()*0.12;
      // stem
      const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.006,0.008,h,4), stemMat);
      stem.position.set(lx, hy+h/2, lz);
      group.add(stem);
      // petals
      const col = flowerColors[Math.floor(rng()*flowerColors.length)];
      const petMat = new THREE.MeshLambertMaterial({ color: col, side: THREE.DoubleSide });
      const pCount = 4 + Math.floor(rng()*3);
      for (let p = 0; p < pCount; p++) {
        const pa = (p/pCount)*Math.PI*2;
        const pet = new THREE.Mesh(new THREE.PlaneGeometry(0.05,0.04), petMat);
        pet.position.set(wx+Math.cos(pa)*0.04, hy+h+0.02, wz+Math.sin(pa)*0.04);
        pet.rotation.y = pa; pet.rotation.x = -0.4;
        group.add(pet);
      }
      // center
      const cMat = new THREE.MeshBasicMaterial({ color: 0xffee00 });
      const cen = new THREE.Mesh(new THREE.SphereGeometry(0.018,6,4), cMat);
      cen.position.set(lx, hy+h+0.018, lz);
      group.add(cen);
    }
    return group;
  }

  // ── Rocks ──────────────────────────────────────────────────────────
  function buildRocks(cx, cz, envName){
    const rng = _chunkRng(cx + 5000, cz + 6000);
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const group = new THREE.Group();
    const count = env === 'mountains' ? 20 : env === 'desert' ? 12 : 8;
    const rockColors = [0x888880, 0x706a60, 0x999288, 0x7a7268];
    for (let i = 0; i < count; i++) {
      const lx = (rng()-0.5)*CHUNK_SIZE;
      const lz = (rng()-0.5)*CHUNK_SIZE;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, lz, env);
      const scale = 0.2 + rng()*0.8;
      const col = rockColors[Math.floor(rng()*rockColors.length)];
      const mat = new THREE.MeshStandardMaterial({ color: col, roughness: 0.9, metalness: 0.05 });
      // Irregular rock shape from scaled sphere
      const geo = new THREE.SphereGeometry(scale, 6, 5);
      const verts = geo.attributes.position;
      for (let v = 0; v < verts.count; v++) {
        const nx = verts.getX(v), ny = verts.getY(v), nz = verts.getZ(v);
        const bump = 1 + Noise.n(nx*2+wx*0.1, ny*2, nz*2+wz*0.1)*0.35;
        verts.setXYZ(v, nx*bump, ny*bump*(0.5+rng()*0.4), nz*bump);
      }
      geo.computeVertexNormals();
      const rock = new THREE.Mesh(geo, mat);
      rock.position.set(lx, hy + scale*0.35, lz);
      rock.rotation.y = rng()*Math.PI*2;
      rock.castShadow = true; rock.receiveShadow = true;
      group.add(rock);
    }
    return group;
  }

  // ── Trees (lush, varied) ──────────────────────────────────────────
  function buildVegetation(cx, cz, envName){
    const rng = _chunkRng(cx + 3000, cz + 4000);
    const env = envName || _envName;
    if (env === 'urban' || env === 'indoor' || env === 'desert') return null;
    const worldOffX = cx * CHUNK_SIZE;
    const worldOffZ = cz * CHUNK_SIZE;
    const group = new THREE.Group();
    const trunkMat = new THREE.MeshStandardMaterial({ color: 0x5c3a1e, roughness: 0.95, metalness: 0 });
    const darkTrunk = new THREE.MeshStandardMaterial({ color: 0x3d2612, roughness: 0.95, metalness: 0 });
    const leafColors = env === 'mountains'
      ? [0x2d6e2a, 0x245e22, 0x1e5218]
      : [0x3a8a2e, 0x2e7a24, 0x4a9a3c, 0x338030, 0x28701e];
    const count = env === 'mountains' ? 12 : 20;
    for (let i = 0; i < count; i++) {
      const lx = (rng()-0.5)*CHUNK_SIZE*0.85;
      const lz = (rng()-0.5)*CHUNK_SIZE*0.85;
      const wx = lx + worldOffX, wz = lz + worldOffZ;
      const hy = terrainHeight(wx, lz, env);
      if (env === 'mountains' && hy > 30) continue;
      const treeType = Math.floor(rng()*3);
      const leafCol = leafColors[Math.floor(rng()*leafColors.length)];
      const leafMat = new THREE.MeshStandardMaterial({ color: leafCol, roughness: 0.85, metalness: 0 });
      if (treeType === 0) {
        // Pine / conifer
        const tH = 3 + rng()*4;
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.18, tH, 6), trunkMat.clone());
        trunk.position.set(lx, hy + tH/2, lz);
        trunk.castShadow = true;
        group.add(trunk);
        // Stacked cones
        const tiers = 3 + Math.floor(rng()*2);
        for (let t = 0; t < tiers; t++) {
          const ty = hy + tH*0.4 + t*(tH*0.22);
          const r = 1.6 - t*0.3 + rng()*0.3;
          const cone = new THREE.Mesh(new THREE.ConeGeometry(r, tH*0.35, 7), leafMat.clone());
          cone.position.set(lx, ty, lz);
          cone.castShadow = true;
          group.add(cone);
        }
      } else if (treeType === 1) {
        // Broad deciduous
        const tH = 2.5 + rng()*3;
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.22, tH, 7), darkTrunk.clone());
        trunk.position.set(lx, hy + tH/2, lz);
        trunk.castShadow = true;
        group.add(trunk);
        // Multi-sphere canopy
        const cr = 1.8 + rng()*1.4;
        const canopy = new THREE.Mesh(new THREE.SphereGeometry(cr, 8, 7), leafMat.clone());
        canopy.position.set(lx, hy + tH + cr*0.6, lz);
        canopy.scale.y = 0.72 + rng()*0.2;
        canopy.castShadow = true;
        group.add(canopy);
        // Extra lobes
        for (let l = 0; l < 3; l++) {
          const la = (l/3)*Math.PI*2 + rng()*0.8;
          const lr = cr*0.55;
          const lobe = new THREE.Mesh(new THREE.SphereGeometry(lr, 6, 5), leafMat.clone());
          lobe.position.set(wx+Math.cos(la)*cr*0.55, hy+tH+cr*0.3+rng()*0.5, wz+Math.sin(la)*cr*0.55);
          lobe.castShadow = true;
          group.add(lobe);
        }
      } else {
        // Tall slender birch
        const tH = 4 + rng()*5;
        const birchMat = new THREE.MeshStandardMaterial({ color: 0xddd8cc, roughness: 0.8 });
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.14, tH, 6), birchMat);
        trunk.position.set(lx, hy + tH/2, lz);
        trunk.castShadow = true;
        group.add(trunk);
        const brightLeaf = new THREE.MeshStandardMaterial({ color: 0x8ab840, roughness: 0.8 });
        const cr = 1.2 + rng()*0.8;
        const canopy = new THREE.Mesh(new THREE.SphereGeometry(cr, 7, 6), brightLeaf);
        canopy.position.set(lx, hy + tH + cr*0.5, lz);
        canopy.scale.y = 1.1;
        canopy.castShadow = true;
        group.add(canopy);
      }
    }
    return group;
  }

  // ── Buildings (urban) ─────────────────────────────────────────────
  // Seeded layout so buildings don't shift on every rebuild
  function _seededRand(seed) {
    let s = seed;
    return function() {
      s = (s * 1664525 + 1013904223) & 0xffffffff;
      return (s >>> 0) / 0xffffffff;
    };
  }

  function buildUrban() {
    const group = new THREE.Group();
    const bMats = [
      new THREE.MeshStandardMaterial({ color: 0x8090a0, roughness:0.7, metalness:0.2 }),
      new THREE.MeshStandardMaterial({ color: 0x607080, roughness:0.65, metalness:0.15 }),
      new THREE.MeshStandardMaterial({ color: 0x9aabbb, roughness:0.6, metalness:0.25 }),
      new THREE.MeshStandardMaterial({ color: 0x70859a, roughness:0.55, metalness:0.3 }),
    ];
    const rand = _seededRand(42); // Fixed seed = stable layout every rebuild
    for (let i = 0; i < 42; i++) {
      const x = (rand()-0.5)*200;
      const z = (rand()-0.5)*200;
      const dist = Math.hypot(x,z);
      if (dist < 14) continue; // keep spawn area clear
      const w = 4 + rand()*14, d = 4 + rand()*14, hh = 5 + rand()*38;
      const geo = new THREE.BoxGeometry(w, hh, d);
      const mesh = new THREE.Mesh(geo, bMats[i%4]);
      mesh.position.set(x, hh/2, z);
      mesh.castShadow = true; mesh.receiveShadow = true;
      group.add(mesh);
      // Store AABB with face normals for all 6 faces
      // _checkColliders uses the stored normal to push drone away from the hit face
      PHYS.colliders.push({
        min:{x:x-w/2, y:0,    z:z-d/2},
        max:{x:x+w/2, y:hh,   z:z+d/2},
        normal:{x:0,  y:1,    z:0},      // used by AABB hit — will be overridden per-face in _checkColliders
        _w:w, _d:d, _h:hh, _cx:x, _cz:z // extra data for face-normal resolution
      });
    }
    // Add road markings / ground detail
    const roadMat = new THREE.MeshLambertMaterial({ color: 0x2a2a2a });
    const roadGeo = new THREE.PlaneGeometry(200, 10);
    roadGeo.rotateX(-Math.PI/2);
    [-20,-5,10,25].forEach(rz => {
      const road = new THREE.Mesh(roadGeo, roadMat.clone());
      road.position.set(0, 0.01, rz);
      group.add(road);
    });
    const roadGeo2 = new THREE.PlaneGeometry(10, 200);
    roadGeo2.rotateX(-Math.PI/2);
    [-20,-5,10,25].forEach(rx => {
      const road = new THREE.Mesh(roadGeo2, roadMat.clone());
      road.position.set(rx, 0.01, 0);
      group.add(road);
    });
    return group;
  }

  // ── Sky Dome (BSL-inspired: Mie scatter, god rays, horizon glow) ──
  function buildSky(night) {
    const geo = new THREE.SphereGeometry(490, 48, 24);
    geo.scale(-1, 1, -1);
    const mat = new THREE.ShaderMaterial({
      vertexShader: `
        varying vec3 vPos;
        varying vec2 vUv;
        void main() {
          vPos = position;
          vUv = uv;
          gl_Position = projectionMatrix * modelViewMatrix * vec4(position,1.0);
        }
      `,
      fragmentShader: `
        varying vec3 vPos;
        varying vec2 vUv;
        uniform vec3 topColor;
        uniform vec3 midColor;
        uniform vec3 horizColor;
        uniform vec3 sunDir;
        uniform float sunSize;
        uniform float nightBlend;
        uniform float time;

        float hash(vec2 p){ return fract(sin(dot(p,vec2(127.1,311.7)))*43758.5453); }

        void main() {
          vec3 n = normalize(vPos);
          float t = clamp(n.y, 0.0, 1.0);
          float tLow = clamp(n.y * 2.5, 0.0, 1.0);

          // Layered sky gradient: top -> mid -> horizon
          vec3 sky = mix(horizColor, midColor, sqrt(tLow));
          sky = mix(sky, topColor, pow(t, 0.6));

          // Horizon haze/glow band
          float horizBand = exp(-abs(n.y)*6.0) * 0.5;
          vec3 hazeCol = mix(horizColor * 1.4, vec3(1.0, 0.88, 0.6), 0.3);
          sky += hazeCol * horizBand * (1.0 - nightBlend);

          // Sun
          vec3 sd = normalize(sunDir);
          float cosA = dot(n, sd);
          float sun = smoothstep(sunSize - 0.0015, sunSize + 0.0015, cosA);
          // Sun corona / glow
          float glow1 = pow(max(0.0, cosA), 32.0) * 0.4;
          float glow2 = pow(max(0.0, cosA), 8.0)  * 0.12;
          float glow3 = pow(max(0.0, cosA), 3.0)  * 0.05;
          vec3 sunHot  = vec3(1.0, 0.97, 0.88);
          vec3 sunWarm = vec3(1.0, 0.75, 0.4);
          vec3 sunCool = vec3(0.6, 0.8, 1.0);
          vec3 sunCol  = mix(sunWarm, sunHot, clamp(sd.y * 2.0, 0.0, 1.0));
          sky += sunCol * (sun + glow1 + glow2 + glow3) * (1.0 - nightBlend * 0.7);

          // God rays: radial streaks from sun
          vec2 sunScreen = vec2(sd.x, sd.y) * 0.5 + 0.5;
          float rayAng = atan(n.x - sd.x, n.z - sd.z) * 3.0;
          float rayDist = length(vec2(n.x - sd.x, n.z - sd.z));
          float rays = sin(rayAng * 6.0 + time * 0.3) * 0.5 + 0.5;
          rays *= exp(-rayDist * 4.0) * glow2 * 0.8;
          sky += sunCol * rays * max(0.0, sd.y) * (1.0 - nightBlend);

          // Mie scattering: warm haze near sun at horizon
          float mie = pow(max(0.0, cosA), 4.0) * max(0.0, 1.0 - abs(n.y)*3.0);
          sky += vec3(1.0, 0.7, 0.4) * mie * 0.3 * (1.0 - nightBlend);

          // Dusk/dawn tint on horizon opposite sun
          float antiSun = dot(n, vec3(-sd.x, 0.0, -sd.z));
          float duskGlow = pow(max(0.0, antiSun), 3.0) * max(0.0, 1.0-abs(n.y)*5.0);
          sky += vec3(0.6, 0.3, 0.6) * duskGlow * 0.15 * max(0.0, 1.0-sd.y*3.0) * (1.0-nightBlend);

          // Stars
          vec3 sPos = fract(n * 220.0) * 2.0 - 1.0;
          float star = max(0.0, 1.0 - length(sPos)*7.5);
          float twinkle = sin(time*2.3 + hash(n.xy*30.0)*6.28)*0.4+0.6;
          float stars = pow(star, 5.0) * nightBlend * twinkle * 1.8;
          // Milky way band
          float mwBand = exp(-abs(dot(n, vec3(0.3, 0.0, 0.95))-0.0)*8.0);
          stars += mwBand * 0.03 * nightBlend * hash(n.xz * 400.0);
          sky += vec3(0.85, 0.92, 1.0) * stars;

          // Moon
          vec3 moonDir = -sunDir; moonDir.y = abs(moonDir.y);
          float moonD = dot(n, normalize(moonDir));
          float moon = smoothstep(0.9988, 0.9992, moonD);
          sky += vec3(0.9, 0.92, 1.0) * moon * nightBlend;

          sky = max(sky, vec3(0.0));
          gl_FragColor = vec4(sky, 1.0);
        }
      `,
      uniforms: {
        topColor:   { value: night ? new THREE.Color(0x020408) : new THREE.Color(0x0d2e6a) },
        midColor:   { value: night ? new THREE.Color(0x030810) : new THREE.Color(0x1a5a9e) },
        horizColor: { value: night ? new THREE.Color(0x050c18) : new THREE.Color(0xb8d8f0) },
        sunDir:     { value: new THREE.Vector3(0.5, 0.8, 0.3).normalize() },
        sunSize:    { value: 0.9992 },
        nightBlend: { value: night ? 1.0 : 0.0 },
        time:       { value: 0.0 },
      },
      side: THREE.BackSide,
      depthWrite: false,
    });
    return new THREE.Mesh(geo, mat);
  }

  // ── Volumetric Clouds ─────────────────────────────────────────────
  function buildClouds() {
    const group = new THREE.Group();
    // Two cloud layers
    const layers = [
      { alt: 55, spread: 380, count: 8, minR: 5, maxR: 18, opacity: 0.78 },
      { alt: 95, spread: 300, count: 5, minR: 8, maxR: 25, opacity: 0.55 },
    ];
    layers.forEach(layer => {
      const mat = new THREE.MeshLambertMaterial({
        color: 0xffffff, transparent: true, opacity: layer.opacity,
        depthWrite: false,
      });
      for (let i = 0; i < layer.count; i++) {
        const cx2 = (Math.random()-0.5)*layer.spread;
        const cz2 = (Math.random()-0.5)*layer.spread;
        const cy  = layer.alt + Math.random()*20;
        const clumpCount = 2 + Math.floor(Math.random()*3);
        for (let j = 0; j < clumpCount; j++) {
          const r = layer.minR + Math.random()*(layer.maxR-layer.minR);
          const s = new THREE.Mesh(new THREE.SphereGeometry(r, 9, 7), mat.clone());
          s.position.set(cx2 + (Math.random()-0.5)*r*2.5,
                         cy  + (Math.random()-0.5)*5,
                         cz2 + (Math.random()-0.5)*r*2);
          s.scale.y = 0.42 + Math.random()*0.22;
          s.scale.x = 1.0 + Math.random()*0.5;
          group.add(s);
        }
      }
    });
    return group;
  }

  // ── Rain (streak-based for better visibility) ───────────────────────
  function buildRain() {
    const count = 4000;
    // Each raindrop is a short line segment (2 vertices)
    // positions: even = top of streak, odd = bottom
    const geo = new THREE.BufferGeometry();
    const pos = new Float32Array(count * 2 * 3); // 2 vertices per streak
    for (let i = 0; i < count; i++) {
      const x = (Math.random()-0.5)*80;
      const y = Math.random()*60;
      const z = (Math.random()-0.5)*80;
      const streakLen = 0.45 + Math.random()*0.35;
      pos[i*6  ] = x;     pos[i*6+1] = y;              pos[i*6+2] = z;   // top
      pos[i*6+3] = x+0.05;pos[i*6+4] = y - streakLen;  pos[i*6+5] = z;   // bottom (streak angled slightly)
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const mat = new THREE.LineBasicMaterial({
      color: 0xaad4ff, transparent: true, opacity: 0.55, depthWrite: false,
    });
    const lines = new THREE.LineSegments(geo, mat);
    return { pts: lines, geo, pos, isLines: true };
  }

  // ── Drone mesh (premium PBR materials) ───────────────────────────
  function buildDrone(color) {
    const g = new THREE.Group();
    const L  = PHYS.armLen || 0.19;
    const rr = PHYS.droneVisual.rotorRadius || 0.09;
    const bs = PHYS.droneVisual.bodyScale   || 1.0;

    // Materials — MeshStandardMaterial for PBR sheen
    const bodyMat  = new THREE.MeshStandardMaterial({ color: color||0x1e88e5, roughness:0.25, metalness:0.55, envMapIntensity:1.2 });
    const darkMat  = new THREE.MeshStandardMaterial({ color: 0x111111, roughness:0.35, metalness:0.6 });
    const carbonMat= new THREE.MeshStandardMaterial({ color: 0x1a1a1e, roughness:0.5, metalness:0.3 });
    const armMat   = new THREE.MeshStandardMaterial({ color: 0x232325, roughness:0.4, metalness:0.5 });
    const motorMat = new THREE.MeshStandardMaterial({ color: 0x888898, roughness:0.3, metalness:0.85 });
    const propMat  = new THREE.MeshStandardMaterial({ color: 0x0d0d10, roughness:0.55, metalness:0.1, transparent:true, opacity:0.88 });
    const glassMat = new THREE.MeshStandardMaterial({ color: 0x223366, roughness:0.05, metalness:0.0, transparent:true, opacity:0.65 });

    // ── Central body ─────────────────────────────────────────────────
    // Top plate — slightly tapered
    const topPlate = new THREE.Mesh(new THREE.BoxGeometry(0.19*bs, 0.020*bs, 0.19*bs), bodyMat);
    topPlate.position.y = 0.020*bs;
    topPlate.castShadow = true;
    g.add(topPlate);

    // Bottom plate
    const botPlate = new THREE.Mesh(new THREE.BoxGeometry(0.17*bs, 0.014*bs, 0.17*bs), carbonMat);
    botPlate.position.y = -0.010*bs;
    botPlate.castShadow = true;
    g.add(botPlate);

    // Mid frame (slightly recessed)
    const midFrame = new THREE.Mesh(new THREE.BoxGeometry(0.15*bs, 0.016*bs, 0.15*bs), armMat);
    midFrame.position.y = 0.005*bs;
    g.add(midFrame);

    // Battery pack
    const batt = new THREE.Mesh(new THREE.BoxGeometry(0.11*bs, 0.030*bs, 0.085*bs), darkMat);
    batt.position.set(0, 0.036*bs, 0);
    g.add(batt);

    // Battery strap
    const strap = new THREE.Mesh(new THREE.BoxGeometry(0.115*bs, 0.005*bs, 0.090*bs), new THREE.MeshStandardMaterial({ color:0x336688, roughness:0.7 }));
    strap.position.set(0, 0.053*bs, 0);
    g.add(strap);

    // Stack standoffs (4 corner pillars)
    [-1,1].forEach(sx => [-1,1].forEach(sz => {
      const pillar = new THREE.Mesh(new THREE.CylinderGeometry(0.007*bs, 0.007*bs, 0.032*bs, 8), motorMat.clone());
      pillar.position.set(sx*0.068*bs, 0.009*bs, sz*0.068*bs);
      g.add(pillar);
    }));

    // Flight controller board
    const fc = new THREE.Mesh(new THREE.BoxGeometry(0.06*bs, 0.004*bs, 0.06*bs), new THREE.MeshStandardMaterial({ color: 0x223322, roughness:0.6 }));
    fc.position.set(0, 0.016*bs, 0);
    g.add(fc);

    // FPV camera housing
    const fpvBody = new THREE.Mesh(new THREE.BoxGeometry(0.025*bs, 0.022*bs, 0.018*bs), carbonMat.clone());
    fpvBody.position.set(0, 0.016*bs, 0.094*bs);
    fpvBody.rotation.x = 0.38;
    g.add(fpvBody);
    // Camera lens
    const lens = new THREE.Mesh(new THREE.CylinderGeometry(0.007*bs, 0.007*bs, 0.010*bs, 12), glassMat);
    lens.rotation.x = Math.PI/2;
    lens.position.set(0, 0.016*bs, 0.102*bs);
    g.add(lens);
    // Lens inner
    const lensIn = new THREE.Mesh(new THREE.CircleGeometry(0.005*bs, 10), new THREE.MeshBasicMaterial({ color: 0x001122 }));
    lensIn.rotation.x = -Math.PI/2;
    lensIn.position.set(0, 0.016*bs, 0.107*bs);
    lensIn.rotation.set(-Math.PI/2, 0, 0);
    g.add(lensIn);

    // Antenna stubs
    [-0.06, 0.06].forEach(ox => {
      const ant = new THREE.Mesh(new THREE.CylinderGeometry(0.003*bs, 0.003*bs, 0.06*bs, 5), new THREE.MeshStandardMaterial({ color:0xffffff, roughness:0.9 }));
      ant.position.set(ox*bs, 0.070*bs, -0.07*bs);
      ant.rotation.z = (ox > 0 ? 0.25 : -0.25);
      g.add(ant);
    });

    // ── 4 Arms + Motors + Props ───────────────────────────────────────
    const motorPositions = [
      [ L*0.707,  0,  L*0.707],
      [-L*0.707,  0,  L*0.707],
      [-L*0.707,  0, -L*0.707],
      [ L*0.707,  0, -L*0.707],
    ];
    const ledColors = [0xff2222, 0x22ff44, 0x4488ff, 0xff8800];

    propMeshes = [];
    armMeshes  = [];

    motorPositions.forEach((mpos, i) => {
      const [mx,,mz] = mpos;

      // Arm — tapered trapezoid profile
      const armLength = Math.hypot(mx, mz);
      const armAngle  = Math.atan2(mx, mz);
      const arm = new THREE.Mesh(new THREE.BoxGeometry(0.022*bs, 0.013*bs, armLength), armMat.clone());
      arm.position.set(mx*0.5, 0, mz*0.5);
      arm.rotation.y = armAngle;
      arm.castShadow = true;
      g.add(arm);
      armMeshes.push(arm);

      // Arm carbon stripe detail
      const stripe = new THREE.Mesh(new THREE.BoxGeometry(0.008*bs, 0.0045*bs, armLength*0.85), carbonMat.clone());
      stripe.position.set(mx*0.5, 0.009*bs, mz*0.5);
      stripe.rotation.y = armAngle;
      g.add(stripe);

      // Motor mount ring
      const mountRing = new THREE.Mesh(new THREE.CylinderGeometry(0.028*bs, 0.028*bs, 0.008*bs, 12), armMat.clone());
      mountRing.position.set(mx, -0.003*bs, mz);
      g.add(mountRing);

      // Motor bell
      const motor = new THREE.Mesh(new THREE.CylinderGeometry(0.022*bs, 0.019*bs, 0.024*bs, 12), motorMat.clone());
      motor.position.set(mx, 0.012*bs, mz);
      motor.castShadow = true;
      g.add(motor);

      // Motor bottom cap
      const cap = new THREE.Mesh(new THREE.CylinderGeometry(0.017*bs, 0.017*bs, 0.006*bs, 12), darkMat.clone());
      cap.position.set(mx, -0.001*bs, mz);
      g.add(cap);

      // ── Propeller group ────────────────────────────────────────────
      const propGroup = new THREE.Group();
      propGroup.position.set(mx, 0.026*bs, mz);

      // 3 blades (more realistic)
      const bladeCount = 3;
      for (let b = 0; b < bladeCount; b++) {
        const blade = new THREE.Mesh(
          new THREE.BoxGeometry(rr*bs * 1.9, 0.004*bs, 0.032*bs),
          propMat.clone()
        );
        blade.rotation.y = b * (Math.PI * 2 / bladeCount);
        blade.rotation.z = (i%2===0 ? 1:-1) * 0.06;
        // Taper blade tip
        const bverts = blade.geometry.attributes.position;
        for (let v = 0; v < bverts.count; v++) {
          const bx2 = bverts.getX(v);
          if (Math.abs(bx2) > rr*bs*0.7) {
            const taper = 1 - (Math.abs(bx2) - rr*bs*0.7) / (rr*bs*0.25);
            bverts.setZ(v, bverts.getZ(v) * Math.max(0.1, taper));
          }
        }
        blade.geometry.computeVertexNormals();
        propGroup.add(blade);
      }

      // Prop hub
      const hub = new THREE.Mesh(new THREE.CylinderGeometry(0.013*bs, 0.013*bs, 0.009*bs, 10), motorMat.clone());
      propGroup.add(hub);

      // Prop spinner cone
      const spinner = new THREE.Mesh(new THREE.ConeGeometry(0.010*bs, 0.012*bs, 8), darkMat.clone());
      spinner.position.y = 0.010*bs;
      propGroup.add(spinner);

      g.add(propGroup);
      propMeshes.push(propGroup);

      // ── Landing gear ──────────────────────────────────────────────
      const legMat = new THREE.MeshStandardMaterial({ color: 0x2a2a2e, roughness:0.7, metalness:0.3 });
      // Main strut
      const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.005*bs, 0.005*bs, 0.065*bs, 6), legMat);
      leg.position.set(mx*0.58, -0.042*bs, mz*0.58);
      leg.rotation.z = mx > 0 ? 0.18 : -0.18;
      g.add(leg);
      // Foot skid
      const foot = new THREE.Mesh(new THREE.CylinderGeometry(0.012*bs, 0.008*bs, 0.006*bs, 8), legMat.clone());
      foot.position.set(mx*0.58, -0.074*bs, mz*0.58);
      g.add(foot);
      // Cross brace
      const brace = new THREE.Mesh(new THREE.CylinderGeometry(0.003*bs, 0.003*bs, 0.03*bs, 4), legMat.clone());
      brace.position.set(mx*0.58, -0.055*bs, mz*0.58);
      brace.rotation.x = mz > 0 ? 0.4 : -0.4;
      g.add(brace);

      // ── LED / running light ───────────────────────────────────────
      const led = new THREE.Mesh(new THREE.SphereGeometry(0.009*bs, 8, 6), new THREE.MeshBasicMaterial({ color: ledColors[i] }));
      led.position.set(mx, 0.028*bs, mz);
      g.add(led);

      // LED glow point light (small, subtle)
      const ledLight = new THREE.PointLight(ledColors[i], 0.12, 0.5);
      ledLight.position.set(mx, 0.030*bs, mz);
      g.add(ledLight);
    });

    // Visual scale-up for clarity (same as original)
    g.scale.setScalar(5.0);
    return g;
  }

  // ── Flight path trail ─────────────────────────────────────────────
  function initTrail() {
    const mat = new THREE.LineBasicMaterial({ color: 0xEE9346, transparent: true, opacity: 0.60, linewidth: 1 });
    const geo = new THREE.BufferGeometry();
    const pts = new Float32Array(500 * 3);
    geo.setAttribute('position', new THREE.BufferAttribute(pts, 3));
    geo.setDrawRange(0, 0);
    _trailLine = new THREE.Line(geo, mat);
    _trailPoints = [];
    return _trailLine;
  }

  function updateTrail() {
    const p = PHYS.pos;
    const last = _trailPoints[_trailPoints.length-1] || {x:p.x+999,y:p.y,z:p.z+999};
    if (V3.len(V3.sub(p, last)) > 0.3) {
      // Store world coords (doubles) in trail array — convert to render space at draw time
      _trailPoints.push({ x:p.x, y:p.y, z:p.z });
      if (_trailPoints.length > 600) _trailPoints.shift();
    }
    // Write trail buffer in render space to avoid float32 precision loss
    const buf = _trailLine.geometry.attributes.position.array;
    const rox = _renderOriginX, roz = _renderOriginZ;
    for (let i = 0; i < _trailPoints.length; i++) {
      buf[i*3  ] = _trailPoints[i].x - rox;
      buf[i*3+1] = _trailPoints[i].y;
      buf[i*3+2] = _trailPoints[i].z - roz;
    }
    _trailLine.geometry.setDrawRange(0, _trailPoints.length);
    _trailLine.geometry.attributes.position.needsUpdate = true;
    // Trail mesh stays at origin (render-space coords baked into vertices)
    _trailLine.position.set(0, 0, 0);
  }

  // ── Waypoint markers ─────────────────────────────────────────────
  function addWaypointMarker(pos) {
    const mat = new THREE.MeshStandardMaterial({ color: 0x10256D, transparent:true, opacity:0.88, roughness:0.5 });
    const mesh = new THREE.Mesh(new THREE.ConeGeometry(0.4, 1.2, 6), mat);
    mesh.position.set(pos.x, pos.y + 0.8, pos.z);
    scene.add(mesh);
    _waypointMarkers.push(mesh);
  }
  function clearWaypointMarkers() {
    _waypointMarkers.forEach(m => scene.remove(m));
    _waypointMarkers = [];
  }

  // ── Sun update ───────────────────────────────────────────────────
  function _updateSunFromTime(t) {
    const angle = (t - 0.25) * Math.PI * 2;
    const sunX  = Math.cos(angle) * 0.65;
    const sunY  = Math.sin(angle);
    const sunZ  = 0.38;
    const sunDir = new THREE.Vector3(sunX, sunY, sunZ).normalize();

    if (shadowLight) {
      shadowLight.position.copy(sunDir.clone().multiplyScalar(180));
      shadowLight.intensity = Math.max(0, sunY * 2.2 + 0.1);
      // Warm/cool color based on height
      const dusk = Math.max(0, 1 - sunY * 5);
      shadowLight.color.lerpColors(new THREE.Color(0xfff5e0), new THREE.Color(0xff8844), dusk*0.5);
    }
    const night = sunY < 0;
    const nightBlend = Math.max(0, -sunY * 1.8);

    if (skyMesh && skyMesh.material.uniforms) {
      skyMesh.material.uniforms.sunDir.value = sunDir;
      skyMesh.material.uniforms.nightBlend.value = Math.min(1, nightBlend);
      if (night) {
        skyMesh.material.uniforms.topColor.value.set(0x010306);
        skyMesh.material.uniforms.midColor.value.set(0x020810);
        skyMesh.material.uniforms.horizColor.value.set(0x050c1a);
      } else {
        const dusk = Math.max(0, 1 - sunY * 4);
        const top  = new THREE.Color().lerpColors(new THREE.Color(0x0d2e6a), new THREE.Color(0xdd4411), dusk*0.55);
        const mid  = new THREE.Color().lerpColors(new THREE.Color(0x1a5a9e), new THREE.Color(0xee7722), dusk*0.6);
        const hor  = new THREE.Color().lerpColors(new THREE.Color(0xb8d8f0), new THREE.Color(0xffcc88), dusk*0.8);
        skyMesh.material.uniforms.topColor.value.copy(top);
        skyMesh.material.uniforms.midColor.value.copy(mid);
        skyMesh.material.uniforms.horizColor.value.copy(hor);
      }
    }
    if (hemiLight) {
      hemiLight.intensity = Math.max(0.06, 0.72 * Math.max(0, sunY));
      hemiLight.color.lerpColors(new THREE.Color(0xc0d8f8), new THREE.Color(0xff9955), Math.max(0, 1 - sunY*5)*0.4);
    }
    if (moonLight) {
      moonLight.intensity = Math.max(0, nightBlend * 0.22);
    }
    if (scene.fog) {
      scene.fog.color.setHSL(0.58, night ? 0.15 : 0.35, night ? 0.03 : 0.80);
    }
  }

  // ── Chunk management ─────────────────────────────────────────────
  function _chunkKey(cx, cz) { return `${cx},${cz}`; }

  // ── Dispose all Three objects in a chunk ──────────────────────────
  function _disposeChunkData(cd) {
    ['mesh','veg','flowers','grass','rocks'].forEach(k => {
      if (!cd[k]) return;
      scene.remove(cd[k]);
      cd[k].traverse(o => {
        if (o.geometry) o.geometry.dispose();
        if (o.material) {
          if (Array.isArray(o.material)) o.material.forEach(m => m.dispose());
          else o.material.dispose();
        }
      });
    });
  }

  // ── Build one chunk and place it in render space ──────────────────
  function _buildChunk(cx, cz, lod) {
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
  }

  function _unloadChunk(key) {
    const cd = _chunks.get(key);
    if (!cd) return;
    _disposeChunkData(cd);
    _chunks.delete(key);
  }

  // ── Time-budgeted chunk drain — max 6ms per frame ────────────────
  const CHUNK_BUDGET_MS = 6;
  function _drainLoadQueue() {
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
  }

  // ── Called every render frame: determine which chunks are needed ──
  function _updateChunks() {
    const p = PHYS.pos;
    const cx = Math.round(p.x / CHUNK_SIZE);
    const cz = Math.round(p.z / CHUNK_SIZE);

    // Always drain queue first (spread build cost over frames)
    _drainLoadQueue();

    // Only recompute needed set when drone crosses a chunk boundary
    if (cx === _lastChunkX && cz === _lastChunkZ) return;
    _lastChunkX = cx; _lastChunkZ = cz;

    const needed = new Map(); // key -> lod (0=full, 1=low)

    // Full-detail inner ring
    for (let dx = -RENDER_DIST; dx <= RENDER_DIST; dx++) {
      for (let dz = -RENDER_DIST; dz <= RENDER_DIST; dz++) {
        needed.set(_chunkKey(cx+dx, cz+dz), 0);
      }
    }
    // Low-detail outer ring
    for (let dx = -LOD_DIST; dx <= LOD_DIST; dx++) {
      for (let dz = -LOD_DIST; dz <= LOD_DIST; dz++) {
        if (Math.abs(dx) <= RENDER_DIST && Math.abs(dz) <= RENDER_DIST) continue; // already full
        needed.set(_chunkKey(cx+dx, cz+dz), 1);
      }
    }

    // Queue new / upgraded chunks, sorted closest-first so nearest loads first
    const toLoad = [];
    for (const [key, lod] of needed) {
      const ex = _chunks.get(key);
      if (!ex || ex.lod > lod) {
        // Parse cx/cz back from key for distance sort
        const [kcx, kcz] = key.split(',').map(Number);
        const dist2 = (kcx-cx)**2 + (kcz-cz)**2;
        toLoad.push({ cx: kcx, cz: kcz, lod, dist2 });
      }
    }
    toLoad.sort((a, b) => a.dist2 - b.dist2);
    // Prepend to queue (priority: new closest chunks jump the line)
    _loadQueue = [...toLoad, ..._loadQueue.filter(e => needed.has(_chunkKey(e.cx, e.cz)))];

    // Unload chunks that are no longer in range — unload synchronously (GPU memory freed immediately)
    for (const [key] of _chunks) {
      if (!needed.has(key)) _unloadChunk(key);
    }
  }

  // ── Volumetric fog planes ─────────────────────────────────────────
  function buildFogLayers() {
    const group = new THREE.Group();
    const fogMat = new THREE.MeshBasicMaterial({
      color: 0xd8eeff, transparent: true, opacity: 0.18, depthWrite: false, side: THREE.DoubleSide
    });
    for (let i = 0; i < 4; i++) {
      const fp = new THREE.Mesh(new THREE.PlaneGeometry(600, 600), fogMat.clone());
      fp.rotation.x = -Math.PI/2;
      fp.position.y = 0.3 + i * 0.4;
      fp.material.opacity = 0.14 - i*0.025;
      group.add(fp);
    }
    return group;
  }

  // ── Init ──────────────────────────────────────────────────────────
  function init(canvasId) {
    _canvas = document.getElementById(canvasId);
    const vp = _canvas.parentElement;
    const W = vp.clientWidth || 800;
    const H = vp.clientHeight || 500;

    renderer = new THREE.WebGLRenderer({ canvas: _canvas, antialias: true, alpha: false, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));
    renderer.setSize(W, H);
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.18;
    renderer.outputEncoding = THREE.sRGBEncoding;
    renderer.physicallyCorrectLights = true;

    scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0xc8e4f8, 0.0018);

    camera = new THREE.PerspectiveCamera(72, W/H, 0.05, 700);
    camera.position.set(0, 5, -12);
    camera.lookAt(0, 2, 0);

    clock = new THREE.Clock();

    // ── Lighting setup ───────────────────────────────────────────────
    hemiLight = new THREE.HemisphereLight(0xb0d0f8, 0x4a7040, 0.58);
    scene.add(hemiLight);

    shadowLight = new THREE.DirectionalLight(0xfff5e0, 1.8);
    shadowLight.position.set(90, 180, 70);
    shadowLight.castShadow = true;
    shadowLight.shadow.mapSize.width  = 4096;
    shadowLight.shadow.mapSize.height = 4096;
    shadowLight.shadow.camera.near    = 1;
    shadowLight.shadow.camera.far     = 600;
    shadowLight.shadow.camera.left    = shadowLight.shadow.camera.bottom = -140;
    shadowLight.shadow.camera.right   = shadowLight.shadow.camera.top    =  140;
    shadowLight.shadow.bias = -0.0002;
    shadowLight.shadow.normalBias = 0.02;
    scene.add(shadowLight);

    // Fill light — soft blue from opposite direction
    const fillLight = new THREE.DirectionalLight(0x6688bb, 0.28);
    fillLight.position.set(-60, 80, -40);
    scene.add(fillLight);

    moonLight = new THREE.DirectionalLight(0x8899cc, 0);
    moonLight.position.set(-80, 120, -60);
    scene.add(moonLight);

    // Ambient for interior
    const ambLight = new THREE.AmbientLight(0x404060, 0.12);
    scene.add(ambLight);

    // Build drone
    droneGroup = buildDrone(PHYS.droneVisual.color);
    scene.add(droneGroup);

    // Flight trail
    scene.add(initTrail());

    // Orbit mouse
    const vpEl = document.getElementById('viewport');
    vpEl.addEventListener('mousedown', e => { if(_camMode==='orbit'){_mouse.down=true;_mouse.lx=e.clientX;_mouse.ly=e.clientY;} });
    vpEl.addEventListener('mousemove', e => {
      if(_mouse.down&&_camMode==='orbit'){
        _orbitAngle += (e.clientX-_mouse.lx)*0.008;
        _orbitH = Math.max(0.5, Math.min(30, _orbitH - (e.clientY-_mouse.ly)*0.04));
        _mouse.lx=e.clientX; _mouse.ly=e.clientY;
      }
    });
    vpEl.addEventListener('mouseup', ()=>{ _mouse.down=false; });
    vpEl.addEventListener('wheel', e => { if(_camMode==='orbit'){ _orbitDist=Math.max(2,Math.min(60,_orbitDist+e.deltaY*0.02)); }});

    window.addEventListener('resize', () => _resize(vp));
  }

  function _resize(vp) {
    if (!renderer || !vp) return;
    const W = vp.clientWidth, H = vp.clientHeight;
    if (!W || !H) return;
    renderer.setSize(W, H);
    camera.aspect = W/H;
    camera.updateProjectionMatrix();
    const mc = document.getElementById('miniCanvas');
    if(mc) { mc.width=mc.parentElement.clientWidth; mc.height=mc.parentElement.clientHeight; }
  }

  // ── Rebuild scene (env presets) ──────────────────────────────────
  function rebuild(envName) {
    _envName = envName;
    PHYS.colliders = [];

    // Reset render origin and clear everything on env rebuild
    _renderOriginX = 0; _renderOriginZ = 0;
    _loadQueue = [];
    for (const [key] of _chunks) _unloadChunk(key);
    _chunks.clear();
    _lastChunkX = null; _lastChunkZ = null;

    // Remove env objects (preserve drone, trail, lights)
    const keepSet = new Set([droneGroup, _trailLine, shadowLight, hemiLight, moonLight]);
    _waypointMarkers.forEach(m => keepSet.add(m));
    const toRemove = [];
    for (const child of [...scene.children]) {
      if (keepSet.has(child)) continue;
      if (child.isLight) continue;
      toRemove.push(child);
    }
    toRemove.forEach(o => {
      scene.remove(o);
      if (o.geometry) o.geometry.dispose();
      if (o.material) {
        if (Array.isArray(o.material)) o.material.forEach(m=>m.dispose());
        else o.material.dispose();
      }
    });

    // Ensure drone and trail are present
    if (droneGroup && !scene.children.includes(droneGroup)) scene.add(droneGroup);
    if (_trailLine  && !scene.children.includes(_trailLine))  scene.add(_trailLine);

    // Sky
    skyMesh = buildSky(_nightMode);
    scene.add(skyMesh);

    // Clouds
    cloudGroup = buildClouds();
    scene.add(cloudGroup);

    // Urban special-case (fixed, not chunked)
    if (envName === 'urban') {
      const flatMesh = buildChunkMesh(0, 0, 'urban');
      scene.add(flatMesh);
      scene.add(buildUrban());
    }

    // Indoor special-case
    if (envName === 'indoor') {
      const wallMat = new THREE.MeshStandardMaterial({ color: 0xd0cfc2, roughness:0.85 });
      const floorMat = new THREE.MeshStandardMaterial({ color: 0xb0aeaa, roughness:0.9 });
      // Floor
      const floor = new THREE.Mesh(new THREE.PlaneGeometry(60, 60), floorMat);
      floor.rotation.x = -Math.PI/2; floor.receiveShadow = true;
      scene.add(floor);
      // Ceiling
      const ceil = new THREE.Mesh(new THREE.PlaneGeometry(60, 60), wallMat.clone());
      ceil.rotation.x = Math.PI/2; ceil.position.y = 20;
      scene.add(ceil);
      // Floor grid lines for visual reference
      const gridHelper = new THREE.GridHelper(60, 12, 0x888880, 0x666660);
      gridHelper.position.y = 0.01;
      scene.add(gridHelper);
      // Walls — fixed normals per side
      const wallDefs = [
        { pos:[  0, 10,  30], size:[60,20,0.5], norm:{x:0,y:0,z:-1} }, // N wall
        { pos:[  0, 10, -30], size:[60,20,0.5], norm:{x:0,y:0,z: 1} }, // S wall
        { pos:[ 30, 10,   0], size:[0.5,20,60], norm:{x:-1,y:0,z:0} }, // E wall
        { pos:[-30, 10,   0], size:[0.5,20,60], norm:{x: 1,y:0,z:0} }, // W wall
      ];
      wallDefs.forEach(wd => {
        const wg = new THREE.BoxGeometry(...wd.size);
        const w = new THREE.Mesh(wg, wallMat.clone());
        w.position.set(...wd.pos);
        w.receiveShadow = true; w.castShadow = true;
        scene.add(w);
        const [wx,wy,wz] = wd.pos; const [sw,sh,sd] = wd.size;
        PHYS.colliders.push({
          min:{x:wx-sw/2, y:0, z:wz-sd/2},
          max:{x:wx+sw/2, y:sh, z:wz+sd/2},
          normal: wd.norm,
        });
      });
      // Warehouse shelving units for visual interest
      const shelfMat = new THREE.MeshStandardMaterial({ color:0x8a7a6a, roughness:0.8, metalness:0.1 });
      const shelfPositions = [[-15,0,-10],[-15,0,0],[-15,0,10],[15,0,-10],[15,0,0],[15,0,10]];
      shelfPositions.forEach(([sx,sy,sz]) => {
        const shelf = new THREE.Mesh(new THREE.BoxGeometry(2,5,1), shelfMat.clone());
        shelf.position.set(sx, 2.5, sz);
        shelf.castShadow = true; shelf.receiveShadow = true;
        scene.add(shelf);
        // Don't add shelf colliders — too small to affect flight meaningfully
      });
      // Overhead lighting rigs
      const rigMat = new THREE.MeshStandardMaterial({ color:0x444444, roughness:0.6, metalness:0.5 });
      [-15,0,15].forEach(lx => {
        const rig = new THREE.Mesh(new THREE.BoxGeometry(1,0.2,50), rigMat.clone());
        rig.position.set(lx, 19.8, 0);
        scene.add(rig);
        // Add point lights along rig
        [-20,0,20].forEach(lz => {
          const pl = new THREE.PointLight(0xfff5e0, 0.6, 40);
          pl.position.set(lx, 18, lz);
          scene.add(pl);
        });
      });
      hemiLight.intensity = 0.85;
      shadowLight.intensity = 0.45;
    }

    // Volumetric fog planes
    if (_fogOn || envName === 'field' || envName === 'windy') {
      const fogLayers = buildFogLayers();
      scene.add(fogLayers);
    }

    // Rain
    if (_rainOn) {
      const r = buildRain();
      _rainParticles = r.pts; _rainGeo = r.geo; _rainPositions = r.pos;
      scene.add(_rainParticles);
    }

    // Fog distances per environment
    switch(envName) {
      case 'mountains': scene.fog = new THREE.FogExp2(0xd0e8f0, 0.0012); break;
      case 'desert':    scene.fog = new THREE.FogExp2(0xffe8a0, 0.0020); break;
      case 'urban':     scene.fog = new THREE.FogExp2(0xc0c8d8, 0.0022); break;
      case 'windy':     scene.fog = new THREE.FogExp2(0xb8d8f0, 0.0025); break;
      case 'indoor':    scene.fog = new THREE.Fog(0xddd8c8, 30, 80); break;
      default:          scene.fog = new THREE.FogExp2(0xc8e4f8, 0.0018);
    }

    _updateSunFromTime(_dayTime);

    // Initial chunk load (around spawn)
    if (envName !== 'urban' && envName !== 'indoor') {
      _lastChunkX = null; _lastChunkZ = null;
      // Build centre 3×3 immediately so there's terrain underfoot on first frame
      const spawnCX = Math.round(PHYS.pos.x / CHUNK_SIZE);
      const spawnCZ = Math.round(PHYS.pos.z / CHUNK_SIZE);
      for (let dx = -1; dx <= 1; dx++) {
        for (let dz = -1; dz <= 1; dz++) {
          _buildChunk(spawnCX+dx, spawnCZ+dz, 0);
        }
      }
      // Queue the rest for async streaming
      _updateChunks();
    }
  }

  // ── Camera update (render-space) ─────────────────────────────────
  function _camMinY(wx, wz, margin) {
    return terrainHeight(wx, wz, _envName) + (margin || 0.8);
  }

  function updateCamera() {
    const p  = PHYS.pos;
    const quat = PHYS.quat;
    const yaw  = PHYS.euler.yaw;
    const rox  = _renderOriginX, roz = _renderOriginZ;

    // Drone render-space position (always near origin)
    const drx = p.x - rox, drz = p.z - roz;

    if (_camMode === 'third') {
      const dist = 4.5, height = 2.2;
      const twx = p.x - Math.sin(yaw)*dist; // world
      const twz = p.z - Math.cos(yaw)*dist;
      const ty  = Math.max(p.y + height, _camMinY(twx, twz, 1.2));
      camera.position.lerp(_camTargetV3.set(twx - rox, ty, twz - roz), 0.12);
      camera.lookAt(drx, Math.max(p.y+0.3, PHYS.groundY+0.5), drz);
    } else if (_camMode === 'fpv') {
      const fwd = Q.rotVec(quat, {x:0, y:0.05, z:0.15});
      const fpvY = Math.max(p.y + fwd.y, PHYS.groundY + 0.15);
      camera.position.set(drx + fwd.x, fpvY, drz + fwd.z);
      const aim = Q.rotVec(quat, {x:0, y:-0.1, z:1.0});
      const lookY = PHYS.crashed ? PHYS.groundY + 0.5 : p.y + aim.y;
      camera.lookAt(drx + aim.x, lookY, drz + aim.z);
    } else if (_camMode === 'orbit') {
      const owx = p.x + Math.sin(_orbitAngle)*_orbitDist;
      const owz = p.z + Math.cos(_orbitAngle)*_orbitDist;
      const oy  = Math.max(p.y + _orbitH, _camMinY(owx, owz, 1.5));
      camera.position.lerp(_camTargetV3.set(owx - rox, oy, owz - roz), 0.08);
      camera.lookAt(drx, Math.max(p.y, PHYS.groundY + 0.3), drz);
    } else if (_camMode === 'free') {
      // Free cam stored in render space (small numbers)
      camera.position.lerp(_camTargetV3.set(_freeCam.x, _freeCam.y, _freeCam.z), 0.05);
      camera.lookAt(drx, p.y, drz);
    } else if (_camMode === 'top') {
      camera.position.lerp(_camTargetV3.set(drx, p.y+22, drz+0.001), 0.06);
      camera.lookAt(drx, p.y, drz);
    }
  }

  // ── Software bloom (additive overdraw) ───────────────────────────
  let _bloomCanvas = null, _bloomCtx = null, _bloomEnabled = false;
  function _initBloom() {
    _bloomCanvas = document.createElement('canvas');
    _bloomCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1;mix-blend-mode:screen;opacity:0.28;';
    const vp = document.getElementById('viewport');
    if (vp) vp.appendChild(_bloomCanvas);
    _bloomCtx = _bloomCanvas.getContext('2d', {willReadFrequently: true});
  }

  function _drawBloom(W, H) {
    if (!_bloomCanvas || !_bloomCtx || W < 4 || H < 4) return;
    _bloomCanvas.width  = Math.round(W * 0.25);
    _bloomCanvas.height = Math.round(H * 0.25);
    const bW = _bloomCanvas.width, bH = _bloomCanvas.height;
    const ctx = _bloomCtx;
    // Grab the three.js canvas and downscale
    ctx.drawImage(_canvas, 0, 0, bW, bH);
    // Multi-pass gaussian-like blur
    ctx.filter = 'blur(6px)';
    ctx.globalCompositeOperation = 'source-over';
    ctx.drawImage(_bloomCanvas, 0, 0);
    ctx.filter = 'none';
    // Scale back up via CSS — the browser handles the upscale blur
    _bloomCanvas.style.width  = W + 'px';
    _bloomCanvas.style.height = H + 'px';
  }

  // ── Render tick ──────────────────────────────────────────────────
  let _frame = 0, _fps = 60, _fpsSmooth = 60, _lastFPSTime = 0;
  let _simTime = 0;
  const _camTargetV3 = new THREE.Vector3();
  const _shadowOffsetV3 = new THREE.Vector3();
  let _viewportEl = null;
  function render() {
    requestAnimationFrame(render);
    const dt = Math.min(0.05, clock.getDelta());
    _frame++;
    _simTime += dt;
    // Render-origin for this frame (used by camera, shadow, drone, trail)
    const rox = _renderOriginX, roz = _renderOriginZ;

    // FPS counter
    const now = performance.now();
    const instantFps = 1/Math.max(dt, 0.001);
    _fps = _fps * 0.92 + instantFps * 0.08;
    if (now - _lastFPSTime > 500) { _fpsSmooth = Math.round(_fps); _lastFPSTime = now; }

    // Sky time uniform
    if (skyMesh && skyMesh.material.uniforms) {
      skyMesh.material.uniforms.time.value = _simTime;
    }

    // ── Floating-origin rebase check ──────────────────────────────────
    // Rebase when the drone strays too far from render origin
    const p = PHYS.pos;
    const distFromOrigin = Math.max(Math.abs(p.x - _renderOriginX), Math.abs(p.z - _renderOriginZ));
    if (distFromOrigin > REBASE_THRESHOLD) {
      _rebaseRenderOrigin();
    }

    // Place drone in render space (world minus render origin)
    droneGroup.position.set(p.x - rox, p.y, p.z - roz);
    droneGroup.quaternion.set(PHYS.quat.x, PHYS.quat.y, PHYS.quat.z, PHYS.quat.w);

    // Propeller spin
    for (let i = 0; i < 4; i++) {
      const rpm = PHYS.motorRPM[i] || 0;
      const dir = PHYS.motorDir[i] || 1;
      propAngle[i] += dir * rpm * (Math.PI*2/60) * dt;
      if (propMeshes[i]) propMeshes[i].rotation.y = propAngle[i];
    }

    // Propeller disc blur effect — scale up disc opacity with RPM
    propMeshes.forEach((pm, i) => {
      const pct = Math.min(1, (PHYS.motorRPM[i]||0) / (PHYS.maxRPM||14000));
      pm.children.forEach(child => {
        if (child.material && child.material.opacity !== undefined && child.material !== undefined) {
          // blades get more transparent at high RPM (motion blur illusion)
          const isHub = child.geometry && child.geometry.type === 'CylinderGeometry';
          if (!isHub) child.material.opacity = 0.88 - pct * 0.62;
        }
      });
    });

    // Cloud drift with wind — wrap within a max radius to prevent glitching
    if (cloudGroup) {
      cloudGroup.position.x += PHYS.windVec.x * dt * 0.12;
      cloudGroup.position.z += PHYS.windVec.z * dt * 0.12;
      // Gentle bob — additive delta so it doesn't snap
      const prevBob = cloudGroup.userData._lastBob || 0;
      const newBob = Math.sin(_simTime * 0.06) * 1.2;
      cloudGroup.position.y += newBob - prevBob;
      cloudGroup.userData._lastBob = newBob;
      // Wrap cloud group back around drone when it drifts too far
      const maxDrift = 180;
      if (Math.abs(cloudGroup.position.x - p.x) > maxDrift) cloudGroup.position.x = p.x + (Math.random()-0.5)*60;
      if (Math.abs(cloudGroup.position.z - p.z) > maxDrift) cloudGroup.position.z = p.z + (Math.random()-0.5)*60;
    }

    // Rain animation (streak-based: stride 6)
    if (_rainOn && _rainPositions) {
      const dropCount = _rainPositions.length / 6;
      const windX = PHYS.windVec.x * dt * 0.4;
      const windZ = PHYS.windVec.z * dt * 0.4;
      const fallSpeed = 20 * dt;
      for (let i = 0; i < dropCount; i++) {
        const b = i * 6;
        _rainPositions[b+1] -= fallSpeed;   // top y
        _rainPositions[b+4] -= fallSpeed;   // bottom y
        _rainPositions[b  ] += windX;       // top x drift
        _rainPositions[b+3] += windX;
        _rainPositions[b+2] += windZ;       // top z drift
        _rainPositions[b+5] += windZ;
        if (_rainPositions[b+1] < -8) {
          // Use render-space drone position (small numbers, no precision loss)
          const drx2 = p.x - _renderOriginX, drz2 = p.z - _renderOriginZ;
          const nx = drx2 + (Math.random()-0.5)*80;
          const nz = drz2 + (Math.random()-0.5)*80;
          const ny = 58 + Math.random()*5;
          const sl = 0.45 + Math.random()*0.35;
          _rainPositions[b  ] = nx;     _rainPositions[b+1] = ny;
          _rainPositions[b+2] = nz;
          _rainPositions[b+3] = nx+0.05;_rainPositions[b+4] = ny - sl;
          _rainPositions[b+5] = nz;
        }
      }
      _rainGeo.attributes.position.needsUpdate = true;
      // Keep rain centred on drone
      if (_rainParticles) _rainParticles.position.set(0, 0, 0);
    }

    // Day cycle
    _dayTime += dt * 0.00055;
    if (_dayTime > 1) _dayTime -= 1;
    if (!_nightMode) _updateSunFromTime(_dayTime);

    // Shadow frustum follows drone in render space
    if (shadowLight) {
      const sdx = p.x - rox, sdz = p.z - roz;
      shadowLight.target.position.set(sdx, 0, sdz);
      shadowLight.target.updateMatrixWorld();
    }

    // Chunk streaming
    _updateChunks();

    updateCamera();
    // [FIX] Sky sphere follows camera so it never exits the sphere (eliminates black-hole gap)
    if (skyMesh) skyMesh.position.copy(camera.position);
    updateTrail();
    renderer.render(scene, camera);

    // Software bloom (every 2nd frame for perf)
    if (_bloomEnabled && _frame % 4 === 0) {
      if (!_viewportEl) _viewportEl = document.getElementById('viewport');
      if (_viewportEl) _drawBloom(_canvas.width, _canvas.height);
    }
  }

  // Init bloom canvas after a short delay (DOM ready)
  setTimeout(_initBloom, 500);

  // Public API — exactly matching original
  return {
    init,
    _resize,
    rebuild,
    addWaypointMarker,
    clearWaypointMarkers,
    setCamera(mode) { _camMode = mode; },
    setNight(on) {
      _nightMode = on;
      _dayTime = on ? 0.0 : 0.5;
      _updateSunFromTime(_dayTime);
      if (skyMesh && skyMesh.material.uniforms) {
        skyMesh.material.uniforms.nightBlend.value = on ? 1.0 : 0.0;
      }
    },
    setRain(on) { _rainOn = on; rebuild(_envName); },
    setFog(on)  { _fogOn  = on; rebuild(_envName); },
    rebuildDrone(color) {
      if (droneGroup) scene.remove(droneGroup);
      droneGroup = buildDrone(color);
      scene.add(droneGroup);
    },
    getFPS() { return _fpsSmooth; },
    getTerrainHeight(x, z) { return terrainHeight(x, z, _envName); },
    getSafeSpawnPoint() { return getSafeSpawnPoint(_envName); },
    getChunkInfo() {
      return { loaded: _chunks.size, queued: _loadQueue.length };
    },
    render,
  };
})();


/* ══════════════════════════════════════════════════════════════════════
   MINIMAP
══════════════════════════════════════════════════════════════════════ */
const MINIMAP = {
  _trail: [],
  draw() {
    const canvas = document.getElementById('miniCanvas');
    if (!canvas) return;
    if (typeof this._lastSync === 'undefined' || performance.now() - this._lastSync > 1000) {
      this._lastSync = performance.now();
      const newW = canvas.clientWidth || 220;
      const newH = canvas.clientHeight || 120;
      if (canvas.width !== newW || canvas.height !== newH) {
        canvas.width = newW;
        canvas.height = newH;
      }
    }
    if (!canvas.width || !canvas.height) return;
    const W = canvas.width, H = canvas.height;
    const ctx = canvas.getContext('2d', {willReadFrequently: true});
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = '#1a2744';
    ctx.fillRect(0, 0, W, H);

    const scale = 1.2;
    const cx = W/2, cy = H/2;
    const px = PHYS.pos.x, pz = PHYS.pos.z;

    // Grid
    ctx.strokeStyle = 'rgba(255,255,255,0.06)';
    ctx.lineWidth = 1;
    for (let i = -5; i <= 5; i++) {
      const gx = cx + i*10*scale; ctx.beginPath(); ctx.moveTo(gx, 0); ctx.lineTo(gx, H); ctx.stroke();
      const gy = cy + i*10*scale; ctx.beginPath(); ctx.moveTo(0, gy); ctx.lineTo(W, gy); ctx.stroke();
    }

    // Trail
    if (this._trail.length > 1) {
      ctx.strokeStyle = 'rgba(238,147,70,0.5)'; ctx.lineWidth = 1.5; ctx.beginPath();
      this._trail.forEach((pt, i) => {
        const tx = cx + (pt.x - px)*scale, ty = cy + (pt.z - pz)*scale;
        if (i===0) ctx.moveTo(tx, ty); else ctx.lineTo(tx, ty);
      });
      ctx.stroke();
    }

    // Waypoints
    if (typeof MISSION !== 'undefined') {
      MISSION.waypoints.forEach((wp, i) => {
        const wx = cx + (wp.x - px)*scale, wy = cy + (wp.z - pz)*scale;
        ctx.fillStyle = '#10256D'; ctx.beginPath(); ctx.arc(wx, wy, 4, 0, Math.PI*2); ctx.fill();
        ctx.fillStyle = 'white'; ctx.font = '8px Inter'; ctx.fillText(i+1, wx-2, wy+3);
      });
    }

    // Home marker — [FIX-6.23] clamp to minimap bounds if drone flew far
    if (PHYS.homePos) {
      const rawHx = cx + (PHYS.homePos.x-px)*scale;
      const rawHy = cy + (PHYS.homePos.z-pz)*scale;
      const margin = 8;
      const hx = Math.max(margin, Math.min(W-margin, rawHx));
      const hy = Math.max(margin, Math.min(H-margin, rawHy));
      ctx.fillStyle = '#4CAF50'; ctx.font = '12px Arial'; ctx.fillText('⌂', hx-6, hy+4);
      // Draw arrow pointing toward true home if it's off-screen
      if(rawHx !== hx || rawHy !== hy){
        ctx.strokeStyle='#4CAF50'; ctx.lineWidth=1;
        ctx.setLineDash([2,2]);
        ctx.beginPath(); ctx.moveTo(cx,cy); ctx.lineTo(hx,hy); ctx.stroke();
        ctx.setLineDash([]);
      }
    }

    // Drone
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(-PHYS.euler.yaw);
    ctx.fillStyle = '#EE9346';
    ctx.beginPath();
    ctx.moveTo(0, -6); ctx.lineTo(-4, 4); ctx.lineTo(4, 4);
    ctx.closePath(); ctx.fill();
    ctx.restore();

    // Badge — show world position + loaded chunk count
    const badge = document.getElementById('minimap-badge');
    if (badge) {
      const wx = PHYS.pos.x.toFixed(0), wz = PHYS.pos.z.toFixed(0);
      badge.textContent = `${wx}, ${wz}`;
    }

    // Trail update
    if (this._trail.length === 0 || Math.hypot(px - (this._trail[this._trail.length-1]?.x||0), pz - (this._trail[this._trail.length-1]?.z||0)) > 1) {
      this._trail.push({x:px, z:pz});
      if (this._trail.length > 200) this._trail.shift();
    }
  },
};

/* ══════════════════════════════════════════════════════════════════════
   ATTITUDE INDICATOR
   [FIX-6.22] Correct sign convention: positive roll = right bank (CW from pilot POV)
   [FIX-6.22] Horizon drawn using actual roll quaternion, not Euler-only
══════════════════════════════════════════════════════════════════════ */
function drawAttitude() {
  const canvas = document.getElementById('attCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d', {willReadFrequently: true});
  const W = canvas.width, H = canvas.height;
  const cx = W/2, cy = H/2, r = W/2 - 2;
  // [FIX-6.22] Use quaternion directly for roll to avoid gimbal lock at ±90° pitch
  // Extract roll from quaternion: avoids Euler singularity
  const q = PHYS.quat;
  const pitch = PHYS.euler.pitch;
  // Roll angle from quat (rotation about Z in body frame)
  const sinr = 2*(q.w*q.z + q.x*q.y);
  const cosr = 1 - 2*(q.y*q.y + q.z*q.z);
  const roll = Math.atan2(sinr, cosr); // positive = right bank ✓

  ctx.clearRect(0, 0, W, H);
  ctx.save();
  ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI*2); ctx.clip();

  ctx.save();
  ctx.translate(cx, cy);
  // [FIX-6.22] Positive roll = right bank = clockwise rotation from pilot POV
  // In canvas: negative rotation = counterclockwise = left; we negate roll for canvas
  ctx.rotate(-roll);
  const pitchPx = pitch * (H * 0.9);
  // Sky
  ctx.fillStyle = '#1a6bb0';
  ctx.fillRect(-W, -H-pitchPx, W*2, H*2);
  // Ground
  ctx.fillStyle = '#7a5c2e';
  ctx.fillRect(-W, -pitchPx, W*2, H*2);
  // Horizon line
  ctx.strokeStyle = 'rgba(255,255,255,0.9)'; ctx.lineWidth = 1.5;
  ctx.beginPath(); ctx.moveTo(-W, -pitchPx); ctx.lineTo(W, -pitchPx); ctx.stroke();
  // Pitch ladder
  ctx.strokeStyle = 'rgba(255,255,255,0.5)'; ctx.font = '8px Inter'; ctx.fillStyle='white'; ctx.lineWidth=1;
  for (let deg = -30; deg <= 30; deg += 10) {
    if (deg === 0) continue;
    const y = -pitchPx - (deg*Math.PI/180) * (H*0.9);
    const ll = Math.abs(deg) === 20 ? r*0.5 : r*0.3;
    ctx.beginPath(); ctx.moveTo(-ll, y); ctx.lineTo(ll, y); ctx.stroke();
    ctx.fillText(deg+'°', ll+3, y+3);
  }
  ctx.restore();

  // Roll arc
  ctx.save(); ctx.translate(cx, cy);
  ctx.strokeStyle = 'rgba(255,255,255,0.5)'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.arc(0, 0, r-4, -Math.PI, 0, false); ctx.stroke();
  // Roll indicator (triangle pointing toward roll angle)
  ctx.rotate(-roll);
  ctx.fillStyle = 'rgba(255,200,50,0.9)';
  ctx.beginPath(); ctx.moveTo(0, -(r-6)); ctx.lineTo(-4, -(r+2)); ctx.lineTo(4, -(r+2)); ctx.closePath(); ctx.fill();
  ctx.restore();

  // Fixed aircraft symbol (centre reticle)
  ctx.strokeStyle = 'rgba(255,210,60,0.95)'; ctx.lineWidth = 2;
  ctx.beginPath(); ctx.moveTo(cx-18, cy); ctx.lineTo(cx-6, cy); ctx.lineTo(cx-4, cy-3); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(cx+18, cy); ctx.lineTo(cx+6, cy); ctx.lineTo(cx+4, cy-3); ctx.stroke();
  ctx.beginPath(); ctx.arc(cx, cy, 2.5, 0, Math.PI*2); ctx.fillStyle='rgba(255,210,60,0.95)'; ctx.fill();

  ctx.restore();
  // Border
  ctx.strokeStyle = 'var(--n3)'; ctx.lineWidth = 1.5;
  ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI*2); ctx.stroke();
}

/* ══════════════════════════════════════════════════════════════════════
   WIND COMPASS
══════════════════════════════════════════════════════════════════════ */
function drawWindCompass() {
  const canvas = document.getElementById('windCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d', {willReadFrequently: true});
  const W = canvas.width, H = canvas.height;
  const cx = W/2, cy = H/2, r = W/2-3;
  ctx.clearRect(0,0,W,H);
  ctx.fillStyle='#1a2744'; ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.fill();
  ctx.strokeStyle='rgba(255,255,255,0.2)'; ctx.lineWidth=1; ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.stroke();
  const dirs=['N','E','S','W']; const dAngles=[0,Math.PI/2,Math.PI,3*Math.PI/2];
  ctx.font='7px Inter'; ctx.fillStyle='rgba(255,255,255,0.6)'; ctx.textAlign='center';
  dirs.forEach((d,i)=>{
    const ax=cx+Math.sin(dAngles[i])*(r-6), ay=cy-Math.cos(dAngles[i])*(r-6);
    ctx.fillText(d,ax,ay+3);
  });
  // Arrow
  const wAngle = Math.atan2(PHYS.windVec.x, PHYS.windVec.z);
  const wMag = V3.len(PHYS.windVec);
  if (wMag > 0.1) {
    ctx.save(); ctx.translate(cx,cy); ctx.rotate(wAngle);
    ctx.strokeStyle='#EE9346'; ctx.lineWidth=1.5;
    ctx.beginPath(); ctx.moveTo(0,r-8); ctx.lineTo(0,-r+10); ctx.stroke();
    ctx.fillStyle='#EE9346'; ctx.beginPath(); ctx.moveTo(0,-r+8); ctx.lineTo(-3,-r+14); ctx.lineTo(3,-r+14); ctx.closePath(); ctx.fill();
    ctx.restore();
  }
}

/* ══════════════════════════════════════════════════════════════════════
   WARNING SYSTEM
══════════════════════════════════════════════════════════════════════ */
const WARN = {
  _active: {},
  trigger(type) {
    const msgs = {
      lowbatt: { txt:'⚡ Battery Critical', level:'err' },
      lowalt:  { txt:'⚠ Low Altitude', level:'warn' },
      crash:   { txt:'💥 Crash Detected', level:'err' },
      wind:    { txt:'💨 High Wind', level:'warn' },
    };
    const m = msgs[type]; if (!m) return;
    this._active[type] = m;
    this._render();
    if (type === 'lowalt') {
      const vw = document.getElementById('vp-warn');
      if (vw) { vw.classList.add('show'); setTimeout(()=>vw.classList.remove('show'), 2500); }
    }
  },
  clear(type) { delete this._active[type]; this._render(); },
  _render() {
    const el = document.getElementById('warn-list');
    if (!el) return;
    const items = Object.values(this._active);
    if (!items.length) {
      el.innerHTML = '<div class="warn-item"><div class="warn-dot ok"></div><span>Systems Nominal</span></div>';
      return;
    }
    el.innerHTML = items.map(m=>`<div class="warn-item"><div class="warn-dot ${m.level}"></div><span>${m.txt}</span></div>`).join('');
  },
};

/* ══════════════════════════════════════════════════════════════════════
   UI UTILITIES
══════════════════════════════════════════════════════════════════════ */
const UI = {
  _logItems: [],
  toast(msg) {
    let t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg; t.style.opacity='1';
    clearTimeout(this._toastTimer);
    this._toastTimer = setTimeout(()=>t.style.opacity='0', 2200);
  },
  log(msg, level='ok') {
    const el = document.getElementById('log-list'); if (!el) return;
    const now = new Date(); const ts = now.getMinutes().toString().padStart(2,'0')+':'+now.getSeconds().toString().padStart(2,'0');
    const item = document.createElement('div');
    item.className=`log-item ${level}`;
    item.innerHTML=`<span class="log-tag">${level.toUpperCase()}</span><span>${ts} ${msg}</span>`;
    el.prepend(item);
    while (el.children.length > 12) el.removeChild(el.lastChild);
  },
};

/* ══════════════════════════════════════════════════════════════════════
   MISSION PLANNER
══════════════════════════════════════════════════════════════════════ */
const MISSION = {
  waypoints: [],
  active: false, paused: false, _idx: 0,

  add(pos) {
    this.waypoints.push({ x:pos.x, y:Math.max(pos.y+1, 3), z:pos.z });
    this._updateUI();
    THREE_ENV.addWaypointMarker({ x:pos.x, y:pos.y, z:pos.z });
    UI.log(`WP${this.waypoints.length} added`, 'ok');
  },
  start() {
    if (!this.waypoints.length) return;
    this.active=true; this.paused=false; this._idx=0;
    FC.setMode('gpshold'); State.armed=true; updateArmUI();
    UI.toast('▶ Mission started'); UI.log('Mission start','ok');
  },
  pause() {
    this.paused=!this.paused;
    UI.toast(this.paused?'⏸ Mission paused':'▶ Mission resumed');
  },
  clear() {
    this.waypoints=[]; this.active=false; this._idx=0;
    this._updateUI(); THREE_ENV.clearWaypointMarkers();
    UI.toast('✕ Mission cleared');
  },
  update() {
    if (!this.active || this.paused || !this.waypoints.length) return;
    if (this._idx >= this.waypoints.length) { this.active=false; FC.setMode('althold'); UI.toast('✅ Mission complete'); return; }
    const wp = this.waypoints[this._idx];
    const dx = wp.x - PHYS.pos.x, dz = wp.z - PHYS.pos.z;
    FC.altTarget = wp.y - PHYS.groundY;
    FC.posTarget = { x:wp.x, z:wp.z };
    if (Math.hypot(dx,dz) < 1.5 && Math.abs(PHYS.pos.y - wp.y) < 1.0) {
      UI.log(`WP${this._idx+1} reached`, 'ok');
      this._idx++;
    }
  },
  _updateUI() {
    const el = document.getElementById('wp-list'); if (!el) return;
    el.innerHTML = this.waypoints.map((wp,i)=>
      `<div class="wp-item"><div class="wp-num">${i+1}</div><span>WP ${i+1}</span><span class="wp-coords">${wp.x.toFixed(1)},${wp.z.toFixed(1)}</span></div>`
    ).join('');
  },
};

/* ══════════════════════════════════════════════════════════════════════
   ENVIRONMENT CONFIG
══════════════════════════════════════════════════════════════════════ */
const ENV = {
  _name: 'field',
  set(name) {
    this._name = name;
    // Configure physics wind/turbulence per environment
    const configs = {
      field:     { wind:0,  dir:0,   turb:0 },
      mountains: { wind:4,  dir:315, turb:30 },
      urban:     { wind:2,  dir:220, turb:15 },
      indoor:    { wind:0,  dir:0,   turb:0 },
      desert:    { wind:6,  dir:60,  turb:20 },
      windy:     { wind:12, dir:180, turb:60 },
    };
    const cfg = configs[name] || configs.field;
    const rad = (cfg.dir * Math.PI/180);
    PHYS.windVec = { x:Math.sin(rad)*cfg.wind, y:0, z:Math.cos(rad)*cfg.wind };
    PHYS.turbulenceIntensity = cfg.turb/100;
    // Update sliders
    const ws = document.getElementById('wind-speed'); if(ws) ws.value = cfg.wind;
    const wv = document.getElementById('wind-val');   if(wv) wv.textContent = cfg.wind+' m/s';
    const tb = document.getElementById('turbulence'); if(tb) tb.value = cfg.turb;
    const tv = document.getElementById('turb-val');   if(tv) tv.textContent = cfg.turb+'%';
    // Ground height for indoor/warehouse
    PHYS.groundY = 0;
    THREE_ENV.rebuild(name);
    // Use getSafeSpawnPoint to find lowest terrain valley — drone no longer spawns inside mountains
    const _spawnPt = THREE_ENV.getSafeSpawnPoint();
    const gY = _spawnPt.y;
    // Set groundY BEFORE reset so PHYS.reset snaps to correct height
    PHYS.groundY = gY;
    const _droneHalf = 0.074 * (PHYS.droneVisual.bodyScale || 1.0) * 5.0;
    // Always respawn at safe location when switching environments or when clearly underground
    const clearlyUnderground = PHYS.pos.y < gY + _droneHalf - 0.5;
    if (PHYS.grounded || PHYS.crashed || clearlyUnderground) {
      PHYS.crashed = false;
      PHYS.grounded = true;
      PHYS.pos.x = _spawnPt.x;
      PHYS.pos.z = _spawnPt.z;
      PHYS.pos.y = gY + _droneHalf;
      PHYS.vel = {x:0, y:0, z:0};
      PHYS.angVel = {x:0, y:0, z:0};
      PHYS.quat = {w:1,x:0,y:0,z:0};
      PHYS.euler = {roll:0,pitch:0,yaw:0};
    }
    document.querySelectorAll('[data-env]').forEach(b => b.classList.toggle('on', b.dataset.env===name));
    UI.log(`Environment: ${name}`, 'ok');
  },
};

/* ══════════════════════════════════════════════════════════════════════
   SIMULATION LOOP
══════════════════════════════════════════════════════════════════════ */
const State = {
  armed: false,
  
  flightMode: 'stabilized',
  motorDamage: [0,0,0,0],
  flightTime: 0,
};

const SIM = {
  _last: 0, _running: false, _paused: false, _speed: 4.0,
  start() {
    this._running = true;
    this._paused = false;
    this._last = performance.now();
    THREE_ENV.render();
    requestAnimationFrame(() => this._loop());
  },
  pause() {
    this._paused = true;
    const btn = document.getElementById('pause-btn');
    if (btn) { btn.textContent = '▶ Resume'; btn.classList.add('paused'); }
    const sysStat = document.getElementById('sys-status');
    if (sysStat) sysStat.textContent = 'PAUSED';
    const sysDot = document.getElementById('sys-dot');
    if (sysDot) sysDot.className = 'sdot w';
    UI.toast('⏸ Simulation paused');
  },
  resume() {
    this._paused = false;
    this._last = performance.now(); // reset to avoid dt spike
    const btn = document.getElementById('pause-btn');
    if (btn) { btn.textContent = '⏸ Pause'; btn.classList.remove('paused'); }
    UI.toast('▶ Simulation resumed');
  },
  setSpeed(s) {
    this._speed = parseFloat(s) || 1.0;
    UI.toast('⏩ Speed: ' + this._speed + '×');
  },
  _loop() {
    if (!this._running) return;
    requestAnimationFrame(() => this._loop());
    if (this._paused) return;
    const now = performance.now();
    // [FIX-Bug-26b] rawDt = real wall time (unscaled) for clock display
    const rawDt = Math.min(0.05, (now - this._last) / 1000);
    this._last = now;
    const dt = rawDt * this._speed;

    // [FIX-Bug-26c] Absolute sim time always advances (not only when armed)
    _simClock.t += dt;

          // Fixed Accumulator for perfect physics stability
      if (typeof this._acc === 'undefined') this._acc = 0;
      const FIXED_DT = 1 / 60; // 60Hz physics base rate
      this._acc += rawDt * this._speed;
      
      // Update inputs once per visual frame
      INPUT.update(rawDt * this._speed);
      const inp = INPUT.get();
      
      // Environment check
      const _envName_sim = typeof ENV !== 'undefined' ? ENV._name : 'field';
      const checkGround = _envName_sim !== 'indoor' && _envName_sim !== 'urban';
      
      while (this._acc >= FIXED_DT) {
        if (checkGround) {
          PHYS.groundY = THREE_ENV.getTerrainHeight(PHYS.pos.x, PHYS.pos.z);
          if (PHYS.groundY < 0) PHYS.groundY = 0;
        }
        FC.update(FIXED_DT, inp);
        PHYS.step(FIXED_DT);
        this._acc -= FIXED_DT;
      }
      MISSION.update();

    if (State.armed) State.flightTime += rawDt;  // [FIX-Bug-26b] use real time for clock

    // Update telemetry systems
    GPS_SIM.update(dt);
    VISION_POS.update(dt);
    OBSTACLE_DIST.update();
    PID_TELEM.capture();

    // [FIX-Bug-26c] Use shared sim clock (same reference as sim-engine.js)
    BLACKBOX.tick(_simClock.t);
    TELEM_GRAPH.push(PHYS);
    
    // Throttle UI and 2D canvas draws to ~20 Hz (every 3rd frame) to reduce CPU load
    if (typeof this._simUIFrame === 'undefined') this._simUIFrame = 0;
    this._simUIFrame++;
    if (this._simUIFrame % 6 === 0) {
      this._updateUI(rawDt); // Throttled DOM text updates
      TELEM_GRAPH.draw();
      DEBUG.draw();
      MINIMAP.draw();
      drawAttitude();
      drawWindCompass();
      updateRecordingUI();
    }
  },

  // ── Cached DOM references — populated on first _updateUI call ──
  _dom: null,
  _initDomCache() {
    const ids = [
      't-alt','t-vel','t-hdng','t-pitch','t-roll','t-yaw',
      't-vx','t-vy','t-vz','t-px','t-py','t-pz',
      'batt-pct','batt-top','t-volt','t-curr','batt-bar',
      't-wind','t-gust','top-clock','t-batt-eta',
      'gps-lat','gps-lon','gps-alt','gps-sat-count','gps-hdop','gps-fix-badge',
      'gps-sat-row','vslam-x','vslam-y','vslam-z','vslam-quality-val',
      'vslam-badge','vslam-quality','fps-val','sys-dot','sys-status',
      'm0-rpm','m1-rpm','m2-rpm','m3-rpm',
      'm0-bar','m1-bar','m2-bar','m3-bar',
      'obs-fwd','obs-right','obs-back','obs-left','obs-up',
      'obs-fwd-v','obs-right-v','obs-back-v','obs-left-v','obs-up-v',
      'pid-roll-kp','pid-roll-ki','pid-roll-kd','pid-roll-err-lbl','pid-roll-err',
      'pid-pitch-kp','pid-pitch-ki','pid-pitch-kd','pid-pitch-err-lbl','pid-pitch-err',
      'pid-yaw-kp','pid-yaw-ki','pid-yaw-kd','pid-yaw-err-lbl','pid-yaw-err',
      'pid-thr-kp','pid-thr-ki','pid-thr-kd','pid-thr-err-lbl','pid-thr-err',
    ];
    this._dom = {};
    for (const id of ids) this._dom[id] = document.getElementById(id);
  },

  _updateUI(dt) {
    // Init DOM cache on first call (DOM must be ready)
    if (!this._dom) this._initDomCache();
    const D = this._dom;
    const set = (id,v) => { const el=D[id]; if(el) el.textContent=v; };
    const p = PHYS, e = p.euler;
    const R2D = 180/Math.PI;
    const alt = Math.max(0, p.pos.y - p.groundY);
    const vel = V3.len(p.vel);

    // Telemetry — all reads from cached elements
    set('t-alt', alt.toFixed(1));
    set('t-vel', vel.toFixed(1));
    set('t-hdng', (((e.yaw*R2D+360)%360)|0).toString().padStart(3,'0'));
    set('t-pitch', (e.pitch*R2D).toFixed(1));
    set('t-roll',  (e.roll *R2D).toFixed(1));
    set('t-yaw',   (e.yaw  *R2D).toFixed(1));
    set('t-vx', p.vel.x.toFixed(1));
    set('t-vy', p.vel.y.toFixed(1));
    set('t-vz', p.vel.z.toFixed(1));
    set('t-px', p.pos.x.toFixed(1));
    set('t-py', p.pos.y.toFixed(1));
    set('t-pz', p.pos.z.toFixed(1));

    // Battery
    const battPct = p.battPct;
    const battStr = battPct.toFixed(0)+'%';
    set('batt-pct', battStr);
    set('batt-top', battStr);
    set('t-volt', p.battVoltage.toFixed(2));
    set('t-curr', p.currentDraw.toFixed(1));
    const bbar = D['batt-bar'];
    if (bbar) {
      bbar.style.transform = 'scaleX(' + (battPct/100) + ')';
      bbar.className = 'bgauge-fill ' + (battPct<20?'red':battPct<50?'orange':'green');
    }
    if (battPct < 15) WARN.trigger('lowbatt'); else WARN.clear('lowbatt');

    // Wind
    const wMag = V3.len(p.windVec);
    const gustMag = V3.len(DRYDEN.get());
    set('t-wind', wMag.toFixed(1));
    set('t-gust', gustMag.toFixed(1));
    if (wMag > 10 || gustMag > 5) WARN.trigger('wind'); else WARN.clear('wind');

    // Low alt warning
    if (State.armed && alt < 0.8 && alt > 0.15) WARN.trigger('lowalt'); else WARN.clear('lowalt');

    // Motors
    for (let i = 0; i < 4; i++) {
      const rpmEl = D[`m${i}-rpm`]; const barEl = D[`m${i}-bar`];
      const rpm = Math.round(p.motorRPM[i]);
      if (rpmEl) _setTxt(rpmEl, rpm);
      if (barEl) {
        barEl.style.transform = 'scaleX(' + (rpm/p.maxRPM) + ')';
        const dmg = State.motorDamage[i]||0;
        barEl.className = 'motor-bar' + (dmg>0.5?' red':dmg>0.2?' orange':'');
      }
    }

    // Clock (Session Time)
    const ft = performance.now() / 1000;
    const clk = D['top-clock'];
    if (clk) clk.textContent = Math.floor(ft/60).toString().padStart(2,'0')+':'+Math.floor(ft%60).toString().padStart(2,'0');

    // Battery ETA
    const etaSec = getBattEstimatedFlightTime();
    set('t-batt-eta', etaSec < 9999 ? (etaSec/60).toFixed(1) : '--');

    // ── GPS_RAW_INT ────────────────────────────────────────────
    const gps = GPS_SIM;
    const fixType = gps.getFixType();
    const satCount = gps.getSatCount();
    const hdop = gps.getHdop();
    set('gps-lat', gps.getLat().toFixed(5));
    set('gps-lon', gps.getLon().toFixed(5));
    set('gps-alt', gps.getAltMSL().toFixed(1));
    set('gps-sat-count', satCount);
    set('gps-hdop', hdop.toFixed(2));
    const fixBadge = D['gps-fix-badge'];
    if (fixBadge) {
      const fixLabels = { 0:'NO FIX', 1:'NO FIX', 2:'2D FIX', 3:'3D FIX', 4:'DGPS', 5:'RTK' };
      const fixClasses= { 0:'gps-fix-none', 1:'gps-fix-none', 2:'gps-fix-2d', 3:'gps-fix-3d', 4:'gps-fix-3d', 5:'gps-fix-3d' };
      fixBadge.textContent = fixLabels[fixType]||'NO FIX';
      fixBadge.className = 'gps-fix-badge '+(fixClasses[fixType]||'gps-fix-none');
    }
    // Satellite dots — lazy-build once, then update classes only
    const satRow = D['gps-sat-row'];
    if (satRow && satRow.children.length !== 16) {
      satRow.innerHTML = Array(16).fill(0).map((_,i)=>`<div class="gps-sat-dot" id="sat-dot-${i}"></div>`).join('');
    }
    if (satRow) {
      const dots = satRow.children;
      for (let i = 0; i < 16; i++) dots[i].className = 'gps-sat-dot'+(i<satCount?' on':i<satCount+2?' dim':'');
    }

    // ── VISION_POSITION ────────────────────────────────────────────
    const vp = VISION_POS.get();
    set('vslam-x', vp.x);
    set('vslam-y', vp.y);
    set('vslam-z', vp.z);
    set('vslam-quality-val', vp.quality+'%');
    const vslamBadge = D['vslam-badge'];
    if (vslamBadge) {
      vslamBadge.textContent = vp.active ? 'VSLAM ACTIVE' : 'GPS ACTIVE';
      vslamBadge.className = 'vslam-badge '+(vp.active ? 'vslam-active' : 'vslam-idle');
    }
    const vslamQ = D['vslam-quality'];
    if (vslamQ) vslamQ.style.transform = 'scaleX(' + (vp.quality/100) + ')';

    // ── OBSTACLE_DISTANCE ─────────────────────────────────────────
    const obs = OBSTACLE_DIST.get();
    const obsMax = OBSTACLE_DIST.SENSOR_RANGE;
    const obsIds = ['fwd','right','back','left','up'];
    for (let i = 0; i < 5; i++) {
      const pct = Math.min(100, (obs[i]/obsMax)*100);
      const barEl = D['obs-'+obsIds[i]];
      const valEl = D['obs-'+obsIds[i]+'-v'];
      if (barEl) barEl.style.transform = 'scaleX(' + (pct/100) + ')';
      if (valEl) _setTxt(valEl, obs[i].toFixed(1)+'m');
    }
    _updateObstacleRadar(obs, obsMax);

    // ── PID TELEMETRY ─────────────────────────────────────────
    const pt = PID_TELEM.axes;
    const pidKeys  = ['roll','pitch','yaw','throttle'];
    const pidIds   = ['roll','pitch','yaw','thr'];
    const errScale = [5,5,3,20];
    for (let pi=0; pi<4; pi++) {
      const key=pidKeys[pi], id=pidIds[pi], ax=pt[key];
      set(`pid-${id}-kp`, ax.kp.toFixed(3));
      set(`pid-${id}-ki`, ax.ki.toFixed(3));
      set(`pid-${id}-kd`, ax.kd.toFixed(3));
      const txt = (ax.error >= 0 ? '+' : '') + ax.error.toFixed(3); if(D[`pid-${id}-err-lbl`].textContent !== txt) D[`pid-${id}-err-lbl`].textContent = txt;
      const errEl = D[`pid-${id}-err`];
      if (errEl) {
        const norm = Math.max(-1, Math.min(1, ax.error / errScale[pi]));
        const w = Math.abs(norm) * 50;
        errEl.style.width = w+'%';
        errEl.style.left = (norm >= 0 ? 50 : 50-w)+'%';
        errEl.style.background = w > 35 ? 'var(--s)' : 'var(--p)';
      }
    }

    // FPS
    const fpsEl = D['fps-val'];
    if (fpsEl) fpsEl.textContent = THREE_ENV.getFPS()+'fps';

    // Chunk count (live)
    const chunkEl = document.getElementById('chunk-count');
    if (chunkEl) {
      const info = THREE_ENV.getChunkInfo ? THREE_ENV.getChunkInfo() : null;
      if (info) chunkEl.textContent = `${info.loaded} chunks · ${info.queued} queued`;
    }

    // System status
    const sysDot = D['sys-dot'], sysStat = D['sys-status'];
    const crashOverlay = document.getElementById('crash-overlay');
    if (p.crashed) {
      if (sysDot) { sysDot.className='sdot e'; }
      if (sysStat) sysStat.textContent='CRASHED';
      if (crashOverlay && !crashOverlay.classList.contains('show')) crashOverlay.classList.add('show');
    } else {
      if (crashOverlay && crashOverlay.classList.contains('show')) crashOverlay.classList.remove('show');
      if (State.armed) {
        if (sysDot) sysDot.className='sdot w';
        if (sysStat) sysStat.textContent='ARMED';
      } else {
        if (sysDot) sysDot.className='sdot';
        if (sysStat) sysStat.textContent='READY';
      }
    }
  },
};

/* ══════════════════════════════════════════════════════════════════════
   UI CALLBACKS (called from HTML onclick / oninput)
══════════════════════════════════════════════════════════════════════ */

/* ── Obstacle Radar SVG updater ──
 * [FIX-6.25] Line length ∝ INVERSE of distance (close=long, far=short)
 * [FIX-6.25] UP sector shown as separate vertical bar gauge (can't show on plan-view radar)
 */
function _updateObstacleRadar(obs, maxRange) {
  const svg = document.getElementById('obs-sectors');
  if (!svg) return;
  const R = 35;
  const dirs = [
    { idx:0, angle:-Math.PI/2 },   // FWD → up
    { idx:1, angle:0 },            // RIGHT → right
    { idx:2, angle:Math.PI/2 },    // BACK → down
    { idx:3, angle:Math.PI },      // LEFT → left
  ];
  svg.innerHTML = '';
  dirs.forEach(({ idx, angle }) => {
    const d = obs[idx];
    // [FIX-6.25] Inverse: close obstacle = long line, far = short line
    const invNorm = Math.min(1, 1 - d / maxRange); // 0=far, 1=very close
    const len = Math.max(2, invNorm * R); // minimum 2px to always be visible
    const x = Math.cos(angle) * len;
    const y = Math.sin(angle) * len;
    // Color: close=red (long line), far=green (short line)
    const hue = invNorm > 0.7 ? '#F44336' : invNorm > 0.4 ? '#EE9346' : '#4CAF50';
    const line = document.createElementNS('http://www.w3.org/2000/svg','line');
    line.setAttribute('x1','0'); line.setAttribute('y1','0');
    line.setAttribute('x2', x.toFixed(2)); line.setAttribute('y2', y.toFixed(2));
    line.setAttribute('stroke', hue); line.setAttribute('stroke-width','3.5');
    line.setAttribute('stroke-linecap','round'); line.setAttribute('opacity','0.85');
    svg.appendChild(line);
    const circle = document.createElementNS('http://www.w3.org/2000/svg','circle');
    circle.setAttribute('cx', x.toFixed(2)); circle.setAttribute('cy', y.toFixed(2));
    circle.setAttribute('r','2.5'); circle.setAttribute('fill', hue);
    svg.appendChild(circle);
  });
  // [FIX-6.25] UP sector: separate vertical bar gauge (right of radar, already in HTML as obs-up bar)
  // The obs-up bar in the right-side table already handles this — no SVG needed for up/down
}

function updateArmUI() {
  const el = document.getElementById('arm-status');
  if (el) {
    el.textContent = State.armed ? 'ARMED' : 'DISARMED';
    el.className = State.armed ? 'armed' : '';
    el.id = 'arm-status'; // keep id for CSS styling
  }
}

function updateDroneProfileUI(name) {
  const p = DRONE_PROFILES[name];
  if (!p) return;
  const s = id => document.getElementById(id);
  const dl = s('drone-profile-label'); if(dl) dl.textContent = p.label;
  const dm = s('drone-mass-val');    if(dm) dm.textContent = p.mass+' kg';
  const db = s('drone-batt-val');    if(db) db.textContent = p.cells+'S '+p.battTotalAh+' Ah';
  const dr = s('drone-maxrpm-val');  if(dr) dr.textContent = p.maxRPM.toLocaleString();
}

function setFlightModeUI(mode) {
  document.querySelectorAll('.fmode-btn').forEach(b => b.classList.toggle('on', b.dataset.mode===mode));
}

function setFlightMode(mode) {
  FC.setMode(mode);
  State.flightMode = mode;
  setFlightModeUI(mode);
  // Auto-center throttle when entering any altitude-holding mode
  if (mode === 'althold' || mode === 'gpshold' || mode === 'rth') {
    animateThrottle(0.5, 300);
  }
  UI.toast('Mode: '+mode.toUpperCase());
  UI.log('Mode → '+mode, 'ok');
}

function setCamera(mode) {
  _camMode_global = mode;
  THREE_ENV.setCamera(mode);
  document.querySelectorAll('.cam-btn').forEach(b => b.classList.toggle('on', b.dataset.cam===mode));
  const badge = document.getElementById('cam-badge');
  const labels = {third:'THIRD PERSON', fpv:'FPV', orbit:'ORBIT', free:'FREE', top:'TOP DOWN'};
  if (badge) badge.textContent = labels[mode]||mode.toUpperCase();
}
let _camMode_global = 'third';

function cycleCamera() {
  const modes = ['third','fpv','orbit','top','free'];
  const idx = modes.indexOf(_camMode_global);
  setCamera(modes[(idx+1)%modes.length]);
}

function setEnvironment(name) {
  ENV.set(name);
  document.querySelectorAll('[data-env]').forEach(b => b.classList.toggle('on', b.dataset.env===name));
}

function applyWorldSeed() {
  const el = document.getElementById('world-seed-input');
  const seed = parseInt(el?.value || '12345', 10) || 12345;
  if (typeof setWorldSeed === 'function') setWorldSeed(seed);
  // Re-run current environment to regenerate terrain with new seed
  ENV.set(typeof ENV !== 'undefined' ? ENV._name : 'field');
  UI.toast('🌍 World seed: ' + seed);
  UI.log('New seed: ' + seed, 'ok');
}

function randomWorldSeed() {
  const seed = Math.floor(Math.random() * 999999);
  const el = document.getElementById('world-seed-input');
  if (el) el.value = seed;
  applyWorldSeed();
}

function setDroneProfile(name) {
  PHYS.applyProfile(name);
  updateDroneProfileUI(name);
  THREE_ENV.rebuildDrone(PHYS.droneVisual.color);
  UI.toast('Profile: '+(DRONE_PROFILES[name]?.label||name));
  UI.log('Profile → '+name, 'ok');
  // Sync customize panel if open
  const panel = document.getElementById('profile-customize-panel');
  if (panel && panel.classList.contains('open')) populateCustomizeFields(name);
}

function setWind(val) {
  const spd = parseFloat(val);
  const dir = parseFloat(document.getElementById('wind-dir')?.value||0);
  const rad = dir * Math.PI/180;
  PHYS.windVec = { x:Math.sin(rad)*spd, y:0, z:Math.cos(rad)*spd };
  const el = document.getElementById('wind-val');
  if (el) el.textContent = spd.toFixed(0)+' m/s';
}

function setWindDir(val) {
  const dir = parseFloat(val);
  const spd = parseFloat(document.getElementById('wind-speed')?.value||0);
  const rad = dir * Math.PI/180;
  PHYS.windVec = { x:Math.sin(rad)*spd, y:0, z:Math.cos(rad)*spd };
  const dirs = ['N','NE','E','SE','S','SW','W','NW'];
  const dIdx = Math.round(dir/45)%8;
  const el = document.getElementById('wdir-val');
  if (el) el.textContent = dirs[dIdx]+' '+Math.round(dir)+'°';
}

function setTurbulence(val) {
  PHYS.turbulenceIntensity = parseFloat(val)/100;
  const el = document.getElementById('turb-val');
  if (el) el.textContent = Math.round(val)+'%';
}

function toggleWeather(type, el) {
  const track = document.getElementById(type+'-track');
  if (!track) return;
  const on = !track.classList.contains('on');
  track.classList.toggle('on', on);
  if (type === 'rain') THREE_ENV.setRain(on);
  if (type === 'fog')  THREE_ENV.setFog(on);
  UI.toast((on?'🌧 ':'☀ ')+(type.charAt(0).toUpperCase()+type.slice(1))+' '+(on?'on':'off'));
}

function toggleDayNight(el) {
  const track = document.getElementById('daynight-track');
  if (!track) return;
  const night = !track.classList.contains('on');
  track.classList.toggle('on', night);
  THREE_ENV.setNight(night);
  UI.toast(night?'🌙 Night mode':'☀ Day mode');
}

function setPID(param, val) {
  val = parseFloat(val);
  FC.gains[param] = val;
  FC.applyGains();
  const labels = {rp:'rp-val',ri:'ri-val',rd:'rd-val',yp:'yp-val',ap:'ap-val'};
  const el = document.getElementById(labels[param]);
  if (el) el.textContent = val.toFixed(4).replace(/0+$/,'').replace(/\.$/,'');
}

function setThrottleSlider(val) {
  INPUT._thrRaw = parseFloat(val)/100;
  const tv = document.getElementById('thr-val');
  if (tv) tv.textContent = Math.round(val)+'%';
  const slEl = document.getElementById('throttle-slider');
  if(slEl) slEl._dragging=true;
  clearTimeout(INPUT._sliderTimer);
  INPUT._sliderTimer = setTimeout(()=>{if(slEl)slEl._dragging=false;},300);
}

function setSensitivity(val) {
  INPUT.sensitivity = parseFloat(val)/100;
  const el = document.getElementById('sens-val');
  if (el) el.textContent = val+'%';
}

function toggleArm() {
  State.armed = !State.armed;
  if (!State.armed) {
    FC.altTarget = null; FC.posTarget = null;
    FC.resetPIDs();
  } else {
    PHYS.saveHome();
    PHYS._gyroBias={x:0,y:0,z:0}; // [FIX-H] zero gyro bias on arm
    FC.resetPIDs();
  }
  updateArmUI();
  UI.toast(State.armed ? '✅ Armed' : '🔴 Disarmed');
  UI.log(State.armed?'Armed':'Disarmed', State.armed?'ok':'warn');
}

function takeoff() {
  if (!State.armed) {
    State.armed = true;
    PHYS.saveHome();
    PHYS._gyroBias={x:0,y:0,z:0}; // [FIX-H] zero gyro bias on takeoff
    FC.resetPIDs();
    FC.setMode('althold');
    FC.altTarget = 3.0;
    State.flightMode = 'althold';
    setFlightModeUI('althold');
    animateThrottle(0.5, 300);
    updateArmUI();
    UI.toast('🚁 Auto-takeoff to 3m');
    UI.log('Auto-takeoff','ok');
  } else {
    FC.altTarget = Math.max(FC.altTarget||3, PHYS.pos.y - PHYS.groundY)+3;
    FC.setMode('althold');
    UI.toast('↑ Climbing');
  }
}

// Smoothly animate _thrRaw to a target value over ~400ms
function animateThrottle(target, duration=400) {
  const start = INPUT._thrRaw;
  const startTime = performance.now();
  const slEl = document.getElementById('throttle-slider');
  const tv   = document.getElementById('thr-val');
  if (slEl) slEl._dragging = true; // prevent INPUT.update from fighting us
  function step(now) {
    const t = Math.min(1, (now - startTime) / duration);
    // Ease-out cubic
    const ease = 1 - Math.pow(1 - t, 3);
    const val = start + (target - start) * ease;
    INPUT._thrRaw = val;
    if (slEl) slEl.value = Math.round(val * 100);
    if (tv)   tv.textContent = Math.round(val * 100) + '%';
    if (t < 1) requestAnimationFrame(step);
    else {
      INPUT._thrRaw = target;
      if (slEl) slEl._dragging = false;
    }
  }
  requestAnimationFrame(step);
}

function doHover() {
  if (!State.armed) return;
  FC.setMode('althold');
  FC.altTarget = PHYS.pos.y - PHYS.groundY;
  FC.posTarget = { x:PHYS.pos.x, z:PHYS.pos.z };
  State.flightMode = 'althold';
  setFlightModeUI('althold');
  // Snap throttle to center so PID deadzone activates immediately
  animateThrottle(0.5, 350);
  UI.toast('⏸ Hovering — throttle locked to altitude hold');
}

function returnHome() {
  if (!State.armed) { takeoff(); }
  FC.setMode('rth');
  State.flightMode = 'rth';
  setFlightModeUI('rth');
  UI.toast('🏠 Return To Home');
  UI.log('RTH initiated','ok');
}

function emergStop() {
  State.armed = false;
  for(let i=0;i<4;i++) PHYS.motorCmd[i]=0;
  FC.altTarget=null; FC.posTarget=null;
  INPUT._thrRaw=0;
  FC.resetPIDs();
  updateArmUI();
  UI.toast('⛔ EMERGENCY STOP');
  UI.log('Emergency stop!','err');
}

function resetDrone() {
  // [FIX] Use getSafeSpawnPoint — avoids resetting drone into a mountainside
  const _spawnPt = THREE_ENV.getSafeSpawnPoint();
  const groundY = _spawnPt.y;
  PHYS.groundY = groundY;
  const _droneHalfR = 0.074 * (PHYS.droneVisual.bodyScale || 1.0) * 5.0;
  PHYS.reset({x: _spawnPt.x, y: groundY + _droneHalfR, z: _spawnPt.z});
  State.armed=false; State.flightMode='stabilized';
  State.motorDamage=[0,0,0,0];
  FC.altTarget=null; FC.posTarget=null; FC.rthPhase=0;
  INPUT._thrRaw=0;
  setFlightModeUI('stabilized');
  updateArmUI();
  const co = document.getElementById('crash-overlay');
  if (co) co.classList.remove('show');
  UI.toast('🔄 Drone reset');
  UI.log('Drone reset','ok');
}
function resetSim() { resetDrone(); }

function addWaypoint() { MISSION.add(PHYS.pos); UI.toast('📍 Waypoint added'); }
function startMission() { MISSION.start(); }
function pauseMission() { MISSION.pause(); }
function clearMission()  { MISSION.clear(); }

/* ── Sim Pause / Speed ── */
function toggleSimPause() {
  if (SIM._paused) SIM.resume(); else SIM.pause();
}
function setSimSpeed(v) {
  SIM.setSpeed(v);
}

/* ── Recording / Export ── */
let _recording = false;
function toggleRecording() {
  _recording = !_recording;
  const btn = document.getElementById('rec-btn');
  if (_recording) {
    BLACKBOX.start();
    if (btn) { btn.textContent = '⏹ Stop'; btn.classList.add('active-btn'); }
    UI.toast('⏺ Recording started');
  } else {
    BLACKBOX.stop();
    if (btn) { btn.textContent = '⏺ Record'; btn.classList.remove('active-btn'); }
    UI.toast('⏹ Recording stopped — ' + BLACKBOX.getLog().length + ' frames');
    updateExportStats();
  }
}

function updateRecordingUI() {
  if (!_recording) return;
  const n = BLACKBOX.getLog().length;
  const btn = document.getElementById('rec-btn');
  if (btn && n % 30 === 0) btn.textContent = '⏹ ' + n + 'f';
}

function updateExportStats() {
  const stats = BLACKBOX.getStats();
  const panel = document.getElementById('export-stats');
  if (!stats || !panel) return;
  panel.style.display = 'grid';
  const s = id => document.getElementById(id);
  s('es-dur') && (s('es-dur').textContent = stats.duration + 's');
  s('es-samp') && (s('es-samp').textContent = stats.samples);
  s('es-maxalt') && (s('es-maxalt').textContent = stats.maxAlt + 'm');
  s('es-maxvel') && (s('es-maxvel').textContent = stats.maxVel + ' m/s');
}

function exportMAVLink() {
  const log = BLACKBOX.getLog();
  if (!log.length) { UI.toast('⚠ No data — start recording first'); return; }
  const ok = MAVLINK.downloadTlog();
  if (ok) UI.toast('📡 MAVLink .tlog exported (' + log.length + ' frames)');
  else UI.toast('⚠ Export failed');
}

function exportJSON() {
  const log = BLACKBOX.getLog();
  if (!log.length) { UI.toast('⚠ No data — start recording first'); return; }
  const ok = MAVLINK.downloadJSON();
  if (ok) UI.toast('{ } JSON telemetry exported');
}

/* ── Telemetry Graph Legend Toggle ── */
function toggleTGraph(ch) {
  TELEM_GRAPH.toggle(ch);
  const el = document.getElementById('tgl-' + ch);
  if (el) el.classList.toggle('on');
}

function showSection(sec) {
  document.querySelectorAll('.npill').forEach(b => b.classList.toggle('on', b.id==='nav-'+sec));
  const dbg = document.getElementById('debug-section');
  if (dbg) dbg.style.display = (sec==='debug') ? 'block' : 'none';
}

/* ══════════════════════════════════════════════════════════════════════
   DRONE PROFILE CUSTOMIZATION
══════════════════════════════════════════════════════════════════════ */

/* ── Rebuild the profile select dropdown ── */
function rebuildProfileSelect(activeKey) {
  const sel = document.getElementById('drone-profile-select');
  if (!sel) return;
  sel.innerHTML = '';
  for (const [key, p] of Object.entries(DRONE_PROFILES)) {
    const opt = document.createElement('option');
    opt.value = key;
    opt.textContent = p.label;
    if (key === activeKey) opt.selected = true;
    sel.appendChild(opt);
  }
}

/* ── Toggle inline customize panel ── */
function toggleProfileCustomize() {
  const panel = document.getElementById('profile-customize-panel');
  const btn   = document.getElementById('customize-toggle-btn');
  if (!panel) return;
  const open = panel.classList.toggle('open');
  if (btn) btn.textContent = open ? '✕ Close' : '✏️ Customize';
  if (open) populateCustomizeFields(PHYS.droneProfile);
}

/* ── Fill customize fields from named profile ── */
function populateCustomizeFields(profileName) {
  const p = DRONE_PROFILES[profileName] || PHYS;
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
  set('cust-mass',    p.mass);
  set('cust-arm',     p.armLen);
  set('cust-maxrpm',  p.maxRPM);
  set('cust-idlerpm', p.idleRPM);
  set('cust-kt',      p.kT);
  set('cust-kq',      p.kQ);
  set('cust-cells',   p.cells);
  set('cust-batt',    p.battTotalAh);
  set('cust-tilt',    p.maxTiltDeg || Math.round((p.maxTiltRad||0.96)*180/Math.PI));
  set('cust-drag',    p.dragArea);
  // color
  const colorEl = document.getElementById('cust-color');
  if (colorEl) {
    const c = p.color !== undefined ? p.color : (PHYS.droneVisual && PHYS.droneVisual.color);
    if (c !== undefined) {
      const hex = '#' + c.toString(16).padStart(6, '0');
      colorEl.value = hex;
    }
  }
}

/* ── Live-apply customize fields to running sim ── */
function applyCustomize() {
  const get = id => parseFloat(document.getElementById(id)?.value) || 0;
  const mass    = get('cust-mass')    || PHYS.mass;
  const armLen  = get('cust-arm')     || PHYS.armLen;
  const maxRPM  = get('cust-maxrpm')  || PHYS.maxRPM;
  const idleRPM = get('cust-idlerpm') || PHYS.idleRPM;
  const kT      = get('cust-kt')      || PHYS.kT;
  const kQ      = get('cust-kq')      || PHYS.kQ;
  const cells   = Math.round(get('cust-cells')) || PHYS.cells;
  const battAh  = get('cust-batt')    || PHYS.battTotalAh;
  const tiltDeg = get('cust-tilt')    || 55;
  const drag    = get('cust-drag')    || PHYS.dragArea;

  Object.assign(PHYS, { mass, armLen, maxRPM, idleRPM, kT, kQ, cells, battTotalAh: battAh,
    maxTiltRad: tiltDeg * Math.PI / 180, dragArea: drag });
  PHYS._recomputeHover();
  if (typeof FC !== 'undefined') FC.autoTuneFromPhysics();

  // Update info panel
  const s = id => document.getElementById(id);
  if (s('drone-mass-val'))   s('drone-mass-val').textContent   = mass + ' kg';
  if (s('drone-batt-val'))   s('drone-batt-val').textContent   = cells + 'S ' + battAh + ' Ah';
  if (s('drone-maxrpm-val')) s('drone-maxrpm-val').textContent = maxRPM.toLocaleString();
  const htv = s('hover-thr-val');
  if (htv) htv.textContent = Math.round(PHYS.hoverThrottle * 100);
}

/* ── Apply color tweak ── */
function applyCustomizeColor(hexStr) {
  const c = parseInt(hexStr.replace('#', ''), 16);
  PHYS.droneVisual = { ...PHYS.droneVisual, color: c };
  if (typeof THREE_ENV !== 'undefined') THREE_ENV.rebuildDrone(c);
}

/* ── Save the customized values as a brand new profile ── */
function saveCustomizeAsProfile() {
  const name = prompt('Enter a name for this custom profile:', 'My Custom Drone');
  if (!name) return;
  const get = id => parseFloat(document.getElementById(id)?.value) || 0;
  const hexStr = document.getElementById('cust-color')?.value || '#1e88e5';
  const color = parseInt(hexStr.replace('#', ''), 16);
  const tiltDeg = get('cust-tilt') || 55;
  const key = 'custom_' + Date.now();
  DRONE_PROFILES[key] = {
    label: name,
    mass:       get('cust-mass'),
    Ixx: get('cust-mass')*0.005, Iyy: get('cust-mass')*0.009, Izz: get('cust-mass')*0.005,
    armLen:     get('cust-arm'),
    kT:         get('cust-kt'),
    kQ:         get('cust-kq'),
    maxRPM:     get('cust-maxrpm'),
    idleRPM:    get('cust-idlerpm'),
    motorTau:   PHYS.motorTau,
    escDelay:   PHYS.escDelay,
    dragArea:   get('cust-drag'),
    dragCd:     PHYS.dragCd,
    angDrag:    PHYS.angDrag,
    cells:      Math.round(get('cust-cells')),
    battTotalAh:get('cust-batt'),
    color,
    bodyScale:  PHYS.droneVisual?.bodyScale || 1,
    rotorRadius:PHYS.droneVisual?.rotorRadius || 0.09,
    maxTiltDeg: tiltDeg,
    maxRate:    { ...PHYS.maxRate },
    propInertia:PHYS.propInertia,
    Cqlift:     PHYS.Cqlift,
  };
  rebuildProfileSelect(key);
  setDroneProfile(key);
  UI.toast('💾 Profile "' + name + '" saved!');
  UI.log('Custom profile saved: ' + name, 'ok');
}

/* ══════════════════════════════════════════════════════════════════════
   CUSTOM PROFILE MODAL
══════════════════════════════════════════════════════════════════════ */
function openCustomProfileModal() {
  const modal = document.getElementById('custom-profile-modal');
  if (modal) modal.classList.add('open');
  buildModalPresetCards();
  // Default load current profile values
  loadPresetIntoModal(PHYS.droneProfile);
}

function closeCustomProfileModal() {
  const modal = document.getElementById('custom-profile-modal');
  if (modal) modal.classList.remove('open');
}

function buildModalPresetCards() {
  const container = document.getElementById('modal-preset-cards');
  if (!container) return;
  container.innerHTML = '';
  const colorMap = {
    racing5:  '#1e88e5', cinequad: '#ffc107',
    micro2:   '#43a047', explorer6:'#8e24aa',
  };
  for (const [key, p] of Object.entries(DRONE_PROFILES)) {
    const card = document.createElement('div');
    card.className = 'profile-card';
    const dotColor = colorMap[key] || ('#' + (p.color||0x607d8b).toString(16).padStart(6,'0'));
    card.innerHTML = `<span class="pc-dot" style="background:${dotColor}"></span>${p.label}`;
    card.onclick = () => {
      document.querySelectorAll('.profile-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      loadPresetIntoModal(key);
    };
    container.appendChild(card);
  }
}

function loadPresetIntoModal(name) {
  const p = DRONE_PROFILES[name];
  if (!p) return;
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
  set('cp-name',      p.label);
  set('cp-mass',      p.mass);
  set('cp-arm',       p.armLen);
  set('cp-bodyscale', p.bodyScale || 1);
  set('cp-rotor',     p.rotorRadius || 0.09);
  set('cp-tilt',      p.maxTiltDeg || 55);
  set('cp-drag',      p.dragArea);
  set('cp-cd',        p.dragCd);
  set('cp-angdrag',   p.angDrag);
  set('cp-maxrpm',    p.maxRPM);
  set('cp-idlerpm',   p.idleRPM);
  set('cp-kt',        p.kT);
  set('cp-kq',        p.kQ);
  set('cp-tau',       p.motorTau);
  set('cp-esc',       p.escDelay);
  set('cp-propI',     p.propInertia || 0.000025);
  set('cp-cq',        p.Cqlift || 0.015);
  set('cp-cells',     p.cells);
  set('cp-batt',      p.battTotalAh);
  set('cp-ratepitch', p.maxRate?.pitch || 10);
  set('cp-rateroll',  p.maxRate?.roll  || 10);
  set('cp-rateyaw',   p.maxRate?.yaw   || 4.5);
  const colorEl = document.getElementById('cp-color');
  if (colorEl && p.color !== undefined) colorEl.value = '#' + p.color.toString(16).padStart(6,'0');
}

function createCustomProfile() {
  const get  = id => parseFloat(document.getElementById(id)?.value) || 0;
  const geti = id => Math.round(get(id));
  const gets = id => document.getElementById(id)?.value?.trim() || '';
  const name = gets('cp-name') || ('Custom Drone ' + Object.keys(DRONE_PROFILES).length);
  const hexStr = document.getElementById('cp-color')?.value || '#1e88e5';
  const color = parseInt(hexStr.replace('#',''), 16);
  const key = 'custom_' + Date.now();
  const mass = get('cp-mass') || 1.24;
  DRONE_PROFILES[key] = {
    label:      name,
    mass,
    Ixx: mass * 0.005, Iyy: mass * 0.009, Izz: mass * 0.005,
    armLen:     get('cp-arm'),
    kT:         get('cp-kt'),
    kQ:         get('cp-kq'),
    maxRPM:     get('cp-maxrpm'),
    idleRPM:    get('cp-idlerpm'),
    motorTau:   get('cp-tau'),
    escDelay:   get('cp-esc'),
    dragArea:   get('cp-drag'),
    dragCd:     get('cp-cd'),
    angDrag:    get('cp-angdrag'),
    cells:      geti('cp-cells'),
    battTotalAh:get('cp-batt'),
    color,
    bodyScale:  get('cp-bodyscale'),
    rotorRadius:get('cp-rotor'),
    maxTiltDeg: get('cp-tilt'),
    maxRate: {
      pitch: get('cp-ratepitch'),
      roll:  get('cp-rateroll'),
      yaw:   get('cp-rateyaw'),
    },
    propInertia: get('cp-propI'),
    Cqlift:      get('cp-cq'),
  };
  rebuildProfileSelect(key);
  setDroneProfile(key);
  closeCustomProfileModal();
  UI.toast('🚁 Custom profile "' + name + '" created!');
  UI.log('New custom profile: ' + name, 'ok');
}

/* ── Close modal on backdrop click ── */
document.addEventListener('click', e => {
  const modal = document.getElementById('custom-profile-modal');
  if (modal && e.target === modal) closeCustomProfileModal();
});

/* ══════════════════════════════════════════════════════════════════════
   STARTUP SEQUENCE
══════════════════════════════════════════════════════════════════════ */
const STARTUP_STEPS = [
  {msg:'Initializing physics engine…',    pct:8},
  {msg:'Loading Three.js renderer…',      pct:20},
  {msg:'Building terrain & environment…', pct:35},
  {msg:'Compiling flight controller…',    pct:50},
  {msg:'Calibrating PID controllers…',    pct:62},
  {msg:'Initializing sensor systems…',    pct:74},
  {msg:'Loading mission planner…',        pct:84},
  {msg:'Warming up motors…',              pct:92},
  {msg:'Systems nominal — launching…',    pct:100},
];

function runStartup() {
  const bar = document.getElementById('sbar');
  const stat= document.getElementById('sstat');
  let i = 0;
  function step() {
    if (i >= STARTUP_STEPS.length) {
      setTimeout(() => {
        document.getElementById('startup').classList.add('hide');
        document.getElementById('app').style.display = '';
        SIM.start();
      }, 400);
      return;
    }
    const s = STARTUP_STEPS[i++];
    if (stat) stat.textContent = s.msg;
    if (bar)  bar.style.width  = s.pct+'%';
    setTimeout(step, 200 + Math.random()*150);
  }
  step();
}

/* ══════════════════════════════════════════════════════════════════════
   DOM INIT
══════════════════════════════════════════════════════════════════════ */
/* ══════════════════════════════════════════════════════════════════════
   VIRTUAL JOYSTICK + STICK VISUALIZER
══════════════════════════════════════════════════════════════════════ */

// Virtual joystick interaction
(function(){
  function initVJ(padId, knobId, stickSide) {
    const pad = document.getElementById(padId);
    const knob = document.getElementById(knobId);
    if (!pad || !knob) return;

    const R = 88 / 2;   // pad radius px
    const MAX = R - 14; // max knob travel
    let active = false, startX = 0, startY = 0;

    function getCenter() {
      const r = pad.getBoundingClientRect();
      return { x: r.left + r.width/2, y: r.top + r.height/2 };
    }

    function setKnob(dx, dy) {
      const dist = Math.hypot(dx, dy);
      if (dist > MAX) { dx = dx/dist*MAX; dy = dy/dist*MAX; }
      knob.style.transform = `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px))`;
      // Normalise to -1..1
      const nx =  dx / MAX;
      const ny = -dy / MAX; // y-up positive
      if (stickSide === 'left') {
        INPUT._vjLeft.x = nx;
        INPUT._vjLeft.y = -ny; // throttle: up = +1 in screen, but we want -ny for rate
      } else {
        INPUT._vjRight.x = nx;
        INPUT._vjRight.y = -ny;
      }
      INPUT._vjActive = true;
    }

    function resetKnob() {
      knob.style.transform = 'translate(-50%, -50%)';
      if (stickSide === 'left') { INPUT._vjLeft.x = 0; INPUT._vjLeft.y = 0; }
      else { INPUT._vjRight.x = 0; INPUT._vjRight.y = 0; }
      // Only deactivate if both sticks at rest
      if (INPUT._vjLeft.x === 0 && INPUT._vjLeft.y === 0 &&
          INPUT._vjRight.x === 0 && INPUT._vjRight.y === 0) {
        INPUT._vjActive = false;
      }
    }

    // Mouse
    pad.addEventListener('mousedown', e => {
      e.preventDefault(); active = true; pad.classList.add('active');
      const c = getCenter();
      setKnob(e.clientX - c.x, e.clientY - c.y);
    });
    window.addEventListener('mousemove', e => {
      if (!active) return;
      const c = getCenter();
      setKnob(e.clientX - c.x, e.clientY - c.y);
    });
    window.addEventListener('mouseup', () => {
      if (!active) return;
      active = false; pad.classList.remove('active'); resetKnob();
    });

    // Touch
    pad.addEventListener('touchstart', e => {
      e.preventDefault(); active = true; pad.classList.add('active');
      const t = e.touches[0]; const c = getCenter();
      setKnob(t.clientX - c.x, t.clientY - c.y);
    }, { passive: false });
    pad.addEventListener('touchmove', e => {
      e.preventDefault(); if (!active) return;
      const t = e.touches[0]; const c = getCenter();
      setKnob(t.clientX - c.x, t.clientY - c.y);
    }, { passive: false });
    pad.addEventListener('touchend', () => {
      active = false; pad.classList.remove('active'); resetKnob();
    });
  }

  // Init both sticks after DOM ready
  document.addEventListener('DOMContentLoaded', () => {
    initVJ('vj-left',  'vj-left-knob',  'left');
    initVJ('vj-right', 'vj-right-knob', 'right');
  });
})();

// Stick visualizer canvases
function _drawStickCanvas(canvasId, x, y, label, accentColor) {
  const c = document.getElementById(canvasId); if (!c) return;
  const ctx = c.getContext('2d', {willReadFrequently: true});
  const W = c.width, H = c.height;
  ctx.clearRect(0, 0, W, H);

  // Background
  ctx.fillStyle = 'rgba(28,33,48,0.97)';
  ctx.beginPath(); ctx.roundRect(0,0,W,H,8); ctx.fill();

  // Grid lines
  ctx.strokeStyle = 'rgba(96,125,139,0.15)'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(W/2,0); ctx.lineTo(W/2,H); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(0,H/2); ctx.lineTo(W,H/2); ctx.stroke();

  // Outer ring
  ctx.strokeStyle = 'rgba(96,125,139,0.2)'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.arc(W/2, H/2, W/2-3, 0, Math.PI*2); ctx.stroke();

  // Trail (last position faded)
  if (c._lastX !== undefined) {
    const tx = W/2 + c._lastX * (W/2-8);
    const ty = H/2 - c._lastY * (H/2-8);
    ctx.fillStyle = accentColor + '30';
    ctx.beginPath(); ctx.arc(tx, ty, 5, 0, Math.PI*2); ctx.fill();
  }
  c._lastX = x; c._lastY = y;

  // Dot
  const px = W/2 + x * (W/2 - 8);
  const py = H/2 - y * (H/2 - 8);
  // Glow
  ctx.shadowColor = accentColor; ctx.shadowBlur = 8;
  ctx.fillStyle = accentColor;
  ctx.beginPath(); ctx.arc(px, py, 6, 0, Math.PI*2); ctx.fill();
  ctx.shadowBlur = 0;
  // White centre
  ctx.fillStyle = 'white';
  ctx.beginPath(); ctx.arc(px, py, 2.5, 0, Math.PI*2); ctx.fill();
}

function _updateStickViz() {
  const inp = INPUT.get();
  // Left stick: X=yaw, Y=throttle (0..1 → center at 0.5)
  const ly = inp.throttle * 2 - 1; // 0..1 → -1..1
  _drawStickCanvas('stick-viz-l', inp.yaw, ly, 'LEFT (THR/YAW)', '#EE9346');
  // Right stick: X=roll, Y=pitch
  _drawStickCanvas('stick-viz-r', inp.roll, inp.pitch, 'RIGHT (PITCH/ROLL)', '#10256D');

  // Meters
  const setM = (id, pct, left) => {
    const el = document.getElementById(id); if (!el) return;
    if (left !== undefined) {
      // bidirectional: centre at 50%, width = |val|*50%, left = 50% or (50%-width)
      const w = Math.abs(pct) * 50;
      el.style.width = w + '%';
      el.style.left  = (pct >= 0 ? 50 : 50 - w) + '%';
    } else {
      el.style.width = pct + '%';
      el.style.left  = '0';
    }
  };
  setM('sm-thr',   inp.throttle * 100);
  setM('sm-yaw',   inp.yaw,  true);
  setM('sm-pitch', inp.pitch, true);
  setM('sm-roll',  inp.roll,  true);
}

// Position readouts (t-px, t-py, t-pz) — update in SIM loop via existing _updateUI

/* ══════════════════════════════════════════════════════════════════════
   DOM INIT
══════════════════════════════════════════════════════════════════════ */
window.addEventListener('DOMContentLoaded', () => {
  // Toast element
  if (!document.getElementById('toast')) {
    const t = document.createElement('div');
    t.id='toast';
    t.style.cssText='position:fixed;bottom:18px;left:50%;transform:translateX(-50%);background:var(--p);color:#fff;padding:7px 20px;border-radius:20px;font-family:var(--fh);font-size:12px;font-weight:600;box-shadow:0 4px 14px rgba(0,0,0,.5);opacity:0;transition:opacity .3s;pointer-events:none;z-index:500;';
    document.body.appendChild(t);
  }

  // Injected CSS for dynamic states
  const style = document.createElement('style');
  style.textContent = `
    .ntoggle-track.on{background:var(--p)!important;}
    .ntoggle-track.on .ntoggle-thumb{transform:translateX(18px)!important;}
    .sdot.ok{background:#4CAF50!important;}
    .sdot.warn{background:var(--s)!important;}
    .sdot.err{background:#f44336!important;}
    .motor-bar.orange{background:var(--s)!important;}
    .motor-bar.red{background:#f44336!important;}
    .vp-warn.show{opacity:1!important;}
    .bgauge-fill.red{background:#f44336!important;}
    .bgauge-fill.orange{background:var(--s)!important;}
    #arm-status{font-size:10px;letter-spacing:.8px;text-transform:uppercase;padding:3px 9px;border-radius:10px;font-weight:700;background:#F44336;color:white;margin-left:4px;}
    #arm-status.armed{background:#4CAF50;}
  `;
  document.head.appendChild(style);

  // Init physics
  PHYS.applyProfile('racing5');
  INPUT.init();
  PHYS.groundY = 0;
  const _initDroneHalf = 0.074 * (PHYS.droneVisual.bodyScale || 1.0) * 5.0;
  PHYS.reset({x:0, y:_initDroneHalf, z:0});
  // [FIX-Bug-26c] Shared sim clock already initialised in sim-engine.js as _simClock = {t:0}
  rebuildProfileSelect('racing5');
  updateDroneProfileUI('racing5');
  FC.autoTuneFromPhysics();

  // Init Three.js
  THREE_ENV.init('threeCanvas');
  window.requestAnimationFrame(() => {
    const vp = document.getElementById('threeCanvas')?.parentElement;
    if (vp) THREE_ENV._resize(vp);
  });

  // Set initial environment (rebuilds scene)
  ENV.set('field');

  // Hover throttle display
  const htv = document.getElementById('hover-thr-val');
  if (htv) htv.textContent = Math.round(PHYS.hoverThrottle*100);

  // Init telemetry graph
  TELEM_GRAPH.init('telemGraph');

  // Global keydown: P = pause/resume, [ ] = sim speed
  // (Space/T/R/H/G/X/F/C/M/1-5 are now handled inside INPUT.init())
  document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
    if (e.repeat) return;
    if (e.code === 'KeyP') toggleSimPause();
    if (e.code === 'BracketRight') {
      const speeds = [0.25, 0.5, 1, 2, 4];
      const idx = speeds.indexOf(SIM._speed);
      const next = speeds[Math.min(idx + 1, speeds.length - 1)];
      SIM.setSpeed(next);
      const sel = document.getElementById('sim-speed');
      if (sel) sel.value = next;
    }
    if (e.code === 'BracketLeft') {
      const speeds = [0.25, 0.5, 1, 2, 4];
      const idx = speeds.indexOf(SIM._speed);
      const prev = speeds[Math.max(idx - 1, 0)];
      SIM.setSpeed(prev);
      const sel = document.getElementById('sim-speed');
      if (sel) sel.value = prev;
    }
  });

  showSection('flight');
  runStartup();
});
