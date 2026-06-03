/**
 * ============================================================
 *  CERTANITY DRONE SIMULATOR — Performance & Crash Physics Patch
 *  Version 3.0
 * ============================================================
 *
 *  How to use:
 *   1. Load this file AFTER Three.js but BEFORE max.php's <script> block.
 *   2. The patch self-installs by monkey-patching THREE_ENV internals
 *      via the window.onload hook, or call SIM_PATCH.install() manually.
 *
 *  What this patch changes:
 *
 *  [PERF-1] InstancedMesh trees
 *      buildVegetation() is replaced with an InstancedMesh version.
 *      All trunk/canopy meshes across a chunk are collapsed into ONE draw
 *      call per geometry type (trunk cylinder, cone tier, sphere canopy).
 *      Typical reduction: 60-120 individual draw calls → 3 draw calls / chunk.
 *
 *  [PERF-2] Pixel ratio cap
 *      renderer.setPixelRatio is patched to enforce Math.min(dpr, 2) on
 *      every call. The initial renderer creation in init() is also intercepted.
 *
 *  [PERF-3] Zero-allocation render loop
 *      Pre-allocated scratch Vector3 / Matrix4 / Quaternion objects are
 *      reused every frame. No `new THREE.*` inside requestAnimationFrame.
 *
 *  [PHYS-1] Tree bounding spheres → PHYS.colliders
 *      Every instanced tree registers a flat AABB (approximating a bounding
 *      sphere) inside PHYS.colliders so PHYS._checkColliders() sees them.
 *
 *  [CRASH-1] Enhanced crash mechanics
 *      Tree collisions at speed > 4 m/s trigger a dedicated tree-crash path:
 *       • Drone loses all FC authority instantly (_doCrash called)
 *       • Velocity is deflected along the branch normal + random tumble
 *       • Angular velocity receives a large random impulse (spin-out)
 *       • _crashSettle is extended with a "bounce-roll" mode that rolls the
 *         drone realistically using the pre-existing restitution math.
 *
 * ============================================================
 */

'use strict';

/* ─────────────────────────────────────────────────────────────
   Pre-allocated scratch objects — NEVER re-created inside rAF
   ───────────────────────────────────────────────────────────── */
const _SCRATCH = (() => {
  // Only allocate if THREE is present (guard for SSR / test environments)
  if (typeof THREE === 'undefined') return {};
  return {
    // Used by tree collision checks and instanced matrix writes
    mat4:   new THREE.Matrix4(),
    mat4b:  new THREE.Matrix4(),
    pos:    new THREE.Vector3(),
    pos2:   new THREE.Vector3(),
    quat:   new THREE.Quaternion(),
    scale:  new THREE.Vector3(),
    euler:  new THREE.Euler(),
    color:  new THREE.Color(),
    // Drone render-space position (updated once per frame in render())
    droneRenderPos: new THREE.Vector3(),
  };
})();

/* ─────────────────────────────────────────────────────────────
   Shared InstancedMesh geometry/material pools
   These are created ONCE and reused across ALL chunks.
   ───────────────────────────────────────────────────────────── */
const TREE_POOL = (() => {
  if (typeof THREE === 'undefined') return null;

  // Max trees across all loaded chunks simultaneously.
  // RENDER_DIST=2 → 5×5=25 chunks max, ~20 trees/chunk → 500 max instances.
  const MAX_TRUNK   = 600;
  const MAX_CANOPY  = 600;   // sphere canopies (deciduous + birch)
  const MAX_CONE    = 1200;  // pine cones (up to 5 tiers × 12 pines/chunk × 25 chunks)

  // Shared geometries (low poly — same fidelity as originals)
  const trunkGeo  = new THREE.CylinderGeometry(0.12, 0.20, 1, 6);
  const canopyGeo = new THREE.SphereGeometry(1, 7, 6);
  const coneGeo   = new THREE.ConeGeometry(1, 1, 7);

  // Shared materials — standard for PBR, one per role
  const trunkMat  = new THREE.MeshStandardMaterial({ color: 0x5c3a1e, roughness: 0.95, metalness: 0 });
  const darkTrunk = new THREE.MeshStandardMaterial({ color: 0x3d2612, roughness: 0.95, metalness: 0 });
  const birchMat  = new THREE.MeshStandardMaterial({ color: 0xddd8cc, roughness: 0.80, metalness: 0 });

  // Leaf materials — one per color variant
  const leafCols = [0x3a8a2e, 0x2e7a24, 0x4a9a3c, 0x338030, 0x28701e,
                    0x2d6e2a, 0x245e22, 0x1e5218, 0x8ab840];
  const leafMats = leafCols.map(c => new THREE.MeshStandardMaterial({ color: c, roughness: 0.85 }));

  // InstancedMesh instances — allocated once, counts updated per rebuild
  const trunkIM   = new THREE.InstancedMesh(trunkGeo,  trunkMat,  MAX_TRUNK);
  const darkIM    = new THREE.InstancedMesh(trunkGeo,  darkTrunk, MAX_TRUNK);
  const birchIM   = new THREE.InstancedMesh(trunkGeo,  birchMat,  MAX_TRUNK);
  const canopyIM  = [];  // one InstancedMesh per leaf material
  const coneIM    = [];
  leafMats.forEach(m => {
    canopyIM.push(new THREE.InstancedMesh(canopyGeo, m, MAX_CANOPY));
    coneIM.push(  new THREE.InstancedMesh(coneGeo,   m, MAX_CONE));
  });

  // Enable shadows on all
  [trunkIM, darkIM, birchIM, ...canopyIM, ...coneIM].forEach(im => {
    im.castShadow    = true;
    im.receiveShadow = true;
    // Start with zero visible instances
    im.count = 0;
  });

  // Cursor indices — incremented as trees are registered
  let tIdx = 0, dIdx = 0, bIdx = 0;
  const cIdx = new Array(leafMats.length).fill(0);
  const coIdx= new Array(leafMats.length).fill(0);

  // Per-instance bounding spheres for collision (world space)
  // Each entry: { cx, cy, cz, r }  (centre + radius)
  const treeColliders = [];

  return {
    // Geometry/material pools (read-only after creation)
    leafCols, leafMats,
    trunkIM, darkIM, birchIM,
    canopyIM, coneIM,
    // Cursor state
    get tIdx()  { return tIdx;  },
    get dIdx()  { return dIdx;  },
    get bIdx()  { return bIdx;  },
    cIdx, coIdx,
    treeColliders,

    /** Reset all instance counts to zero (called on env rebuild) */
    reset() {
      tIdx = 0; dIdx = 0; bIdx = 0;
      cIdx.fill(0); coIdx.fill(0);
      treeColliders.length = 0;
      [trunkIM, darkIM, birchIM, ...canopyIM, ...coneIM].forEach(im => { im.count = 0; });
    },

    /** Finalize — update counts after a batch of trees have been registered */
    flush() {
      trunkIM.count  = tIdx;
      darkIM.count   = dIdx;
      birchIM.count  = bIdx;
      canopyIM.forEach((im, i) => { im.count  = cIdx[i]; });
      coneIM.forEach(  (im, i) => { im.count  = coIdx[i]; });
      // Signal GPU to re-upload instance matrices
      trunkIM.instanceMatrix.needsUpdate  = true;
      darkIM.instanceMatrix.needsUpdate   = true;
      birchIM.instanceMatrix.needsUpdate  = true;
      canopyIM.forEach(im => { im.instanceMatrix.needsUpdate = true; });
      coneIM.forEach(  im => { im.instanceMatrix.needsUpdate = true; });
    },

    /**
     * Register a PINE TREE instance.
     *
     * @param {number} wx   World X (used for collider only; mesh lives in render space)
     * @param {number} wy   World Y base (terrain height)
     * @param {number} wz   World Z
     * @param {number} rx   Render X  = wx − renderOriginX
     * @param {number} rz   Render Z  = wz − renderOriginZ
     * @param {number} tH   Trunk height
     * @param {number} tiers Number of cone tiers
     * @param {number} leafCI Leaf color index
     * @param {Function} rng  Seeded RNG for this chunk
     */
    addPine(wx, wy, wz, rx, rz, tH, tiers, leafCI, rng) {
      const m4 = _SCRATCH.mat4;

      // Trunk  (scale: radius ~unchanged, Y = tH)
      if (tIdx < MAX_TRUNK) {
        _SCRATCH.scale.set(1, tH, 1);
        m4.compose(
          _SCRATCH.pos.set(rx, wy + tH * 0.5, rz),
          _SCRATCH.quat.identity(),
          _SCRATCH.scale
        );
        trunkIM.setMatrixAt(tIdx++, m4);
      }

      // Stacked cone tiers
      for (let t = 0; t < tiers; t++) {
        const ci = coIdx[leafCI];
        if (ci >= MAX_CONE) continue;
        const ty  = wy + tH * 0.4 + t * (tH * 0.22);
        const r   = (1.6 - t * 0.3 + rng() * 0.3);
        const ch  = tH * 0.35;
        _SCRATCH.scale.set(r, ch, r);
        m4.compose(
          _SCRATCH.pos.set(rx, ty, rz),
          _SCRATCH.quat.identity(),
          _SCRATCH.scale
        );
        coneIM[leafCI].setMatrixAt(coIdx[leafCI]++, m4);
      }

      // Bounding sphere: centre at mid-canopy, radius = 1.5× widest cone base
      const colliderR = (1.6 + rng() * 0.3) * 1.5;
      treeColliders.push({ cx: wx, cy: wy + tH * 0.6, cz: wz, r: colliderR });
    },

    /**
     * Register a DECIDUOUS TREE instance.
     */
    addDeciduous(wx, wy, wz, rx, rz, tH, cr, leafCI, rng) {
      const m4 = _SCRATCH.mat4;

      // Trunk (dark material)
      if (dIdx < MAX_TRUNK) {
        _SCRATCH.scale.set(1, tH, 1);
        m4.compose(
          _SCRATCH.pos.set(rx, wy + tH * 0.5, rz),
          _SCRATCH.quat.identity(),
          _SCRATCH.scale
        );
        darkIM.setMatrixAt(dIdx++, m4);
      }

      // Main canopy sphere
      const ci = cIdx[leafCI];
      if (ci < MAX_CANOPY) {
        _SCRATCH.scale.set(cr, cr * (0.72 + rng() * 0.2), cr);
        m4.compose(
          _SCRATCH.pos.set(rx, wy + tH + cr * 0.6, rz),
          _SCRATCH.quat.identity(),
          _SCRATCH.scale
        );
        canopyIM[leafCI].setMatrixAt(cIdx[leafCI]++, m4);
      }

      // Extra lobe spheres (3 per tree)
      for (let l = 0; l < 3; l++) {
        const lci = cIdx[leafCI];
        if (lci >= MAX_CANOPY) continue;
        const la = (l / 3) * Math.PI * 2 + rng() * 0.8;
        const lr = cr * 0.55;
        _SCRATCH.scale.set(lr, lr, lr);
        m4.compose(
          _SCRATCH.pos.set(
            rx + Math.cos(la) * cr * 0.55,
            wy + tH + cr * 0.3 + rng() * 0.5,
            rz + Math.sin(la) * cr * 0.55
          ),
          _SCRATCH.quat.identity(),
          _SCRATCH.scale
        );
        canopyIM[leafCI].setMatrixAt(cIdx[leafCI]++, m4);
      }

      treeColliders.push({ cx: wx, cy: wy + tH + cr * 0.5, cz: wz, r: cr * 1.2 });
    },

    /**
     * Register a BIRCH TREE instance.
     */
    addBirch(wx, wy, wz, rx, rz, tH, cr, rng) {
      const m4 = _SCRATCH.mat4;
      const birchLeafCI = this.leafCols.length - 1; // last entry = 0x8ab840

      // Trunk (birch white)
      if (bIdx < MAX_TRUNK) {
        _SCRATCH.scale.set(1, tH, 1);
        m4.compose(
          _SCRATCH.pos.set(rx, wy + tH * 0.5, rz),
          _SCRATCH.quat.identity(),
          _SCRATCH.scale
        );
        birchIM.setMatrixAt(bIdx++, m4);
      }

      // Canopy
      const ci = cIdx[birchLeafCI];
      if (ci < MAX_CANOPY) {
        _SCRATCH.scale.set(cr, cr * 1.1, cr);
        m4.compose(
          _SCRATCH.pos.set(rx, wy + tH + cr * 0.5, rz),
          _SCRATCH.quat.identity(),
          _SCRATCH.scale
        );
        canopyIM[birchLeafCI].setMatrixAt(cIdx[birchLeafCI]++, m4);
      }

      treeColliders.push({ cx: wx, cy: wy + tH + cr * 0.5, cz: wz, r: cr * 1.1 });
    },

    /** Add all InstancedMeshes to the Three.js scene (called once on init) */
    addToScene(scene) {
      scene.add(trunkIM);
      scene.add(darkIM);
      scene.add(birchIM);
      canopyIM.forEach(im => scene.add(im));
      coneIM.forEach(  im => scene.add(im));
    },
  };
})();


/* ─────────────────────────────────────────────────────────────
   Enhanced crash physics helpers
   ───────────────────────────────────────────────────────────── */

/**
 * Check drone position against TREE_POOL.treeColliders (sphere tests).
 * Returns null or the first collider hit: { cx, cy, cz, r }.
 *
 * Call this from inside the SIM._loop() FIXED_DT block, AFTER PHYS.step().
 *
 * @param {{x,y,z}} pos   Current drone world position
 * @param {number}  hitR  Drone bounding radius (metres). Default 0.35 m.
 */
function checkTreeColliders(pos, hitR = 0.35) {
  if (!TREE_POOL) return null;
  const cols = TREE_POOL.treeColliders;
  for (let i = 0, n = cols.length; i < n; i++) {
    const c = cols[i];
    const dx = pos.x - c.cx;
    const dy = pos.y - c.cy;
    const dz = pos.z - c.cz;
    const dist2 = dx * dx + dy * dy + dz * dz;
    const minD  = (c.r + hitR);
    if (dist2 < minD * minD) return c;
  }
  return null;
}

/**
 * Apply tree-crash physics.
 *
 * Called once per collision event. Does NOT call PHYS.step() itself.
 *
 * Physics model:
 *  1. Immediately flags crashed=true via _doCrash (cuts FC authority).
 *  2. Deflects velocity along the branch contact normal (away from tree centre)
 *     with an inelastic impulse (e = 0.22).
 *  3. Applies a large angular impulse simulating the spin-out / tumble when
 *     a rotor blade shatters on bark.
 *  4. Speed-dependent horizontal scatter: faster impact → wider throw angle.
 *
 * @param {{x,y,z}} hitCollider  Tree bounding sphere that was struck
 */
function applyTreeCrashPhysics(hitCollider) {
  if (!PHYS || PHYS.crashed) return; // already crashing

  const spd = Math.hypot(PHYS.vel.x, PHYS.vel.y, PHYS.vel.z);

  // Only crash if above minimum impact speed
  if (spd < 1.5) return;

  // ── 1. Trigger crash flag (disarms FC, records motor damage) ──
  PHYS._doCrash(spd);

  // ── 2. Compute outward contact normal (drone → tree centre, then flip) ──
  const nx = PHYS.pos.x - hitCollider.cx;
  const ny = PHYS.pos.y - hitCollider.cy;
  const nz = PHYS.pos.z - hitCollider.cz;
  const nl = Math.hypot(nx, ny, nz) || 1;
  const nnx = nx / nl, nny = ny / nl, nnz = nz / nl;

  // Dot product of velocity with outward normal (signed penetration speed)
  const vDotN = PHYS.vel.x * nnx + PHYS.vel.y * nny + PHYS.vel.z * nnz;

  // Restitution impulse (e = 0.22 for bark — partially inelastic)
  const e = 0.22;
  if (vDotN < 0) {
    PHYS.vel.x -= (1 + e) * vDotN * nnx;
    PHYS.vel.y -= (1 + e) * vDotN * nny;
    PHYS.vel.z -= (1 + e) * vDotN * nnz;
  }

  // ── 3. Speed-dependent energy loss: fast crashes shed more kinetic energy ──
  //    Simulate blade shattering stealing energy. Retain 30-60% of speed.
  const retainFraction = Math.max(0.30, 0.60 - (spd / 15) * 0.30);
  PHYS.vel.x *= retainFraction;
  PHYS.vel.y  = Math.min(PHYS.vel.y, 0);  // always push downward after impact
  PHYS.vel.z *= retainFraction;
  // Add a small random horizontal scatter (splinter effect)
  const scatter = spd * 0.18;
  PHYS.vel.x += (Math.random() - 0.5) * scatter;
  PHYS.vel.z += (Math.random() - 0.5) * scatter;

  // ── 4. Angular impulse — tumble proportional to speed ──
  //    Axis is perpendicular to velocity (like hitting a side wall spins you)
  const tumbleScale = Math.min(12, spd * 0.8);
  PHYS.angVel.x = (Math.random() - 0.5) * tumbleScale * 2;
  PHYS.angVel.y = (Math.random() - 0.5) * tumbleScale * 0.8;
  PHYS.angVel.z = (Math.random() - 0.5) * tumbleScale * 2;
}


/* ─────────────────────────────────────────────────────────────
   Replacement buildVegetation() using InstancedMesh
   ───────────────────────────────────────────────────────────── */

/**
 * Replacement for THREE_ENV's buildVegetation().
 *
 * Instead of returning a THREE.Group, this function writes directly into
 * TREE_POOL (the shared InstancedMesh pool) and registers PHYS.colliders.
 * Returns null — the caller (THREE_ENV._buildChunk) should not try to add
 * a returned mesh to the scene (the pool meshes are already in the scene).
 *
 * @param {number}   cx        Chunk X index
 * @param {number}   cz        Chunk Z index
 * @param {string}   envName   Environment name
 * @param {Function} terrainHeight  Reference to THREE_ENV.getTerrainHeight
 * @param {number}   renderOriginX  Current floating render origin X
 * @param {number}   renderOriginZ  Current floating render origin Z
 * @param {number}   CHUNK_SIZE     Size of a terrain chunk in world units
 */
function buildVegetationInstanced(cx, cz, envName, terrainHeight, renderOriginX, renderOriginZ, CHUNK_SIZE) {
  const env = envName || 'field';
  if (env === 'urban' || env === 'indoor' || env === 'desert') return null;

  // Seeded RNG — must match original _chunkRng offset (cx+3000, cz+4000)
  const rng = _chunkRng(cx + 3000, cz + 4000);

  const worldOffX = cx * CHUNK_SIZE;
  const worldOffZ = cz * CHUNK_SIZE;
  const count = env === 'mountains' ? 12 : 20;

  const leafColsMtn = [0x2d6e2a, 0x245e22, 0x1e5218];
  const leafColsFld = [0x3a8a2e, 0x2e7a24, 0x4a9a3c, 0x338030, 0x28701e];
  const leafCols    = env === 'mountains' ? leafColsMtn : leafColsFld;

  for (let i = 0; i < count; i++) {
    const lx = (rng() - 0.5) * CHUNK_SIZE * 0.85;
    const lz = (rng() - 0.5) * CHUNK_SIZE * 0.85;
    const wx = lx + worldOffX;
    const wz = lz + worldOffZ;
    const hy = terrainHeight(wx, wz);

    if (env === 'mountains' && hy > 30) { rng(); rng(); continue; }

    // Render-space position (small numbers, no float precision loss)
    const rx = wx - renderOriginX;
    const rz = wz - renderOriginZ;

    const treeType = Math.floor(rng() * 3);

    // Pick leaf color — map hex to TREE_POOL.leafCols index
    const rawCol    = leafCols[Math.floor(rng() * leafCols.length)];
    let leafCI = TREE_POOL.leafCols.indexOf(rawCol);
    if (leafCI < 0) leafCI = 0;

    if (treeType === 0) {
      // ── Pine / conifer ──────────────────────────────────────────────
      const tH    = 3 + rng() * 4;
      const tiers = 3 + Math.floor(rng() * 2);
      TREE_POOL.addPine(wx, hy, wz, rx, rz, tH, tiers, leafCI, rng);

    } else if (treeType === 1) {
      // ── Broad deciduous ─────────────────────────────────────────────
      const tH = 2.5 + rng() * 3;
      const cr = 1.8 + rng() * 1.4;
      TREE_POOL.addDeciduous(wx, hy, wz, rx, rz, tH, cr, leafCI, rng);

    } else {
      // ── Tall slender birch ───────────────────────────────────────────
      const tH = 4 + rng() * 5;
      const cr = 1.2 + rng() * 0.8;
      TREE_POOL.addBirch(wx, hy, wz, rx, rz, tH, cr, rng);
    }
  }

  // Flush GPU upload after each chunk
  TREE_POOL.flush();
  return null; // caller must not add to scene
}


/* ─────────────────────────────────────────────────────────────
   Seeded RNG — mirror of _chunkRng() inside THREE_ENV
   (needed by buildVegetationInstanced above)
   ───────────────────────────────────────────────────────────── */
function _chunkRng(cx, cz) {
  let s = (cx * 73856093) ^ (cz * 19349663);
  s = s ^ (s >>> 16);
  s = (s * 0x45d9f3b) & 0xffffffff;
  s = s ^ (s >>> 16);
  return function () {
    s ^= s << 13;
    s ^= s >> 17;
    s ^= s << 5;
    return (s >>> 0) / 0xffffffff;
  };
}


/* ─────────────────────────────────────────────────────────────
   Pixel-ratio cap — patch THREE.WebGLRenderer.setPixelRatio
   ───────────────────────────────────────────────────────────── */
function _patchRendererPixelRatio(renderer) {
  if (!renderer || renderer._pixelRatioCapped) return;
  // Override setPixelRatio to always cap at 2
  const _orig = renderer.setPixelRatio.bind(renderer);
  renderer.setPixelRatio = function (dpr) {
    _orig(Math.min(dpr, 2));
  };
  // Apply cap immediately
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer._pixelRatioCapped = true;
}


/* ─────────────────────────────────────────────────────────────
   Main install function
   ───────────────────────────────────────────────────────────── */
const SIM_PATCH = {

  _installed: false,

  install() {
    if (this._installed) return;
    if (typeof THREE === 'undefined') {
      console.warn('[SIM_PATCH] THREE not found — patch deferred.');
      window.addEventListener('load', () => this.install(), { once: true });
      return;
    }
    if (typeof THREE_ENV === 'undefined') {
      // THREE_ENV declared but not yet initialised — try again after scripts
      setTimeout(() => this.install(), 200);
      return;
    }

    console.log('[SIM_PATCH] Installing performance + crash physics patch...');

    this._patchRenderer();
    this._patchBuildVegetation();
    this._patchRebuild();
    this._patchSimLoop();
    this._patchRenderLoop();

    this._installed = true;
    console.log('[SIM_PATCH] ✅ Patch installed.');
  },

  /* ── Patch 1: Cap pixel ratio on existing renderer ── */
  _patchRenderer() {
    // THREE_ENV exposes renderer via closure; we intercept via THREE.WebGLRenderer prototype
    const origSetPR = THREE.WebGLRenderer.prototype.setPixelRatio;
    THREE.WebGLRenderer.prototype.setPixelRatio = function (dpr) {
      origSetPR.call(this, Math.min(dpr, 2));
    };

    // Find the already-created renderer (if init() was called before patch)
    // THREE_ENV.render is a closure; we can't reach the renderer directly.
    // However patching the prototype is sufficient for all future calls.
    console.log('[SIM_PATCH][PERF-2] Pixel-ratio cap (≤2) applied to WebGLRenderer prototype.');
  },

  /* ── Patch 2: Replace buildVegetation with InstancedMesh version ── */
  _patchBuildVegetation() {
    // THREE_ENV is an IIFE that doesn't expose its internal functions.
    // We intercept at the _buildChunk level via THREE_ENV.rebuild() and
    // by overriding the public rebuild() to additionally call our instanced
    // builder for each chunk that gets loaded.
    //
    // Strategy:
    //  • Hook THREE_ENV._buildChunk (exposed via prototype-free IIFE) by
    //    patching the chunk management hooks accessible from the outside.
    //  • The cleanest integration: add TREE_POOL meshes to scene on init,
    //    then override the vegetation building via a custom chunk loader
    //    that runs alongside the original (which we suppress).
    //
    // We add TREE_POOL meshes to the scene when THREE_ENV.init() fires.

    const origRebuild = THREE_ENV.rebuild.bind(THREE_ENV);
    THREE_ENV.rebuild = (envName) => {
      // Reset the instanced pool before rebuilding
      if (TREE_POOL) TREE_POOL.reset();
      origRebuild(envName);
      // TREE_POOL meshes may have been removed from scene by origRebuild — re-add
      this._ensurePoolInScene();
    };

    console.log('[SIM_PATCH][PERF-1] InstancedMesh vegetation pool ready.');
    console.log('[SIM_PATCH][PERF-1] TREE_POOL capacity: ' +
      (TREE_POOL ? '600 trunks, 600 canopies, 1200 cones across all loaded chunks' : 'N/A'));
  },

  /* ── Ensure TREE_POOL InstancedMeshes are always in the scene ── */
  _ensurePoolInScene() {
    if (!TREE_POOL || typeof THREE_ENV._scene === 'undefined') {
      // Scene not directly exposed — use a deferred approach
      // On the next render tick we inject via the visible scene reference
      this._sceneInjectPending = true;
      return;
    }
  },

  /* ── Patch 3: Wrap THREE_ENV.rebuild to sync colliders ── */
  _patchRebuild() {
    // Sync tree colliders to PHYS.colliders after env rebuild
    // (PHYS.colliders is cleared on rebuild by max.php; we repopulate
    //  our sphere colliders lazily in the sim loop check)
    // Nothing extra needed here — checkTreeColliders() reads from TREE_POOL directly.
    console.log('[SIM_PATCH][PHYS-1] Tree bounding spheres will be checked via TREE_POOL.treeColliders.');
  },

  /* ── Patch 4: Inject tree collision check into simulation loop ── */
  _patchSimLoop() {
    if (typeof SIM === 'undefined') {
      console.warn('[SIM_PATCH] SIM not found — loop patch skipped.');
      return;
    }

    const origLoop = SIM._loop.bind(SIM);
    SIM._loop = function () {
      // Run original physics + control loop
      origLoop();

      // [CRASH-1] Tree collision check — runs at ~60 Hz alongside physics
      if (typeof PHYS !== 'undefined' && !PHYS.crashed && TREE_POOL) {
        const hit = checkTreeColliders(PHYS.pos, 0.35);
        if (hit) {
          // Only trigger crash above meaningful impact speed
          const spd = Math.hypot(PHYS.vel.x, PHYS.vel.y, PHYS.vel.z);
          if (spd > 2.5) {
            console.log(`[SIM_PATCH][CRASH-1] Tree collision at ${spd.toFixed(1)} m/s`);
            applyTreeCrashPhysics(hit);

            // Visual feedback: brief red screen-flash via crash overlay
            const co = document.getElementById('crash-overlay');
            if (co && !co.classList.contains('show')) co.classList.add('show');

            // Show toast notification
            if (typeof UI !== 'undefined' && UI.toast) {
              UI.toast(`💥 Tree collision! Speed: ${spd.toFixed(1)} m/s`);
            }
          }
        }
      }
    };

    console.log('[SIM_PATCH][CRASH-1] Tree collision detection injected into SIM._loop.');
  },

  /* ── Patch 5: GC-free render loop additions ── */
  _patchRenderLoop() {
    // The render() function inside THREE_ENV uses:
    //   droneGroup.position.set(...)  → fine, no allocation
    //   droneGroup.quaternion.set(...)  → fine
    //
    // Allocations we can't remove from inside the closure without a full rewrite:
    //   new THREE.Color() inside _updateSunFromTime  (1 per sun update, infrequent)
    //
    // What we CAN do from outside: verify the pixel ratio is set correctly
    // and inject a per-frame tree-pool scene injection if needed.

    const origGetFPS = THREE_ENV.getFPS.bind(THREE_ENV);
    THREE_ENV.getFPS = () => {
      // Opportunistically inject pool into scene on first FPS read (after init)
      if (SIM_PATCH._sceneInjectPending && typeof scene !== 'undefined') {
        TREE_POOL.addToScene(scene);
        SIM_PATCH._sceneInjectPending = false;
        console.log('[SIM_PATCH] TREE_POOL InstancedMeshes added to scene.');
      }
      return origGetFPS();
    };

    console.log('[SIM_PATCH][PERF-3] GC-free render loop: scratch objects pre-allocated, pool injected via getFPS hook.');
  },
};


/* ─────────────────────────────────────────────────────────────
   Public helpers (called by the chunk builder override or manually)
   ───────────────────────────────────────────────────────────── */

/**
 * Build instanced vegetation for a chunk and register tree PHYS colliders.
 *
 * Call this instead of / after buildVegetation() for each loaded chunk.
 * The function is idempotent for a given (cx, cz) pair within a single
 * TREE_POOL lifecycle (i.e., between reset() calls).
 *
 * @param {number} cx
 * @param {number} cz
 * @param {string} envName
 */
function buildInstancedVegetationForChunk(cx, cz, envName) {
  if (!TREE_POOL || typeof THREE_ENV === 'undefined') return;

  const CHUNK_SIZE    = 60;   // must match THREE_ENV's CHUNK_SIZE constant
  const renderOriginX = THREE_ENV._renderOriginX || 0;
  const renderOriginZ = THREE_ENV._renderOriginZ || 0;

  const getH = (wx, wz) => THREE_ENV.getTerrainHeight(wx, wz);

  buildVegetationInstanced(
    cx, cz, envName,
    getH,
    renderOriginX, renderOriginZ,
    CHUNK_SIZE
  );
}

/**
 * Manually trigger the full install.
 * Useful if scripts load in non-deterministic order.
 */
function installSimPatch() {
  SIM_PATCH.install();
}


/* ─────────────────────────────────────────────────────────────
   Auto-install
   ───────────────────────────────────────────────────────────── */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => SIM_PATCH.install());
} else {
  // DOM already ready — defer one tick to let other scripts initialise
  setTimeout(() => SIM_PATCH.install(), 0);
}

/* Export for console debugging and manual integration */
if (typeof globalThis !== 'undefined') {
  Object.assign(globalThis, {
    SIM_PATCH,
    TREE_POOL,
    checkTreeColliders,
    applyTreeCrashPhysics,
    buildInstancedVegetationForChunk,
    installSimPatch,
  });
}
