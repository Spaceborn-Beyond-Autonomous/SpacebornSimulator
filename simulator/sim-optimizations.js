/* SpaceBorn Simulator Optimizations - Fixed Version */
const TREE_GEOS = (() => {
  if (typeof THREE === 'undefined') return null;
  return {
    pineTrunk: new THREE.CylinderGeometry(0.12, 0.20, 1, 6),
    decTrunk: new THREE.CylinderGeometry(0.12, 0.22, 1, 7),
    birchTrunk: new THREE.CylinderGeometry(0.08, 0.14, 1, 6),
    canopy: new THREE.SphereGeometry(1, 7, 6),
    cone: new THREE.ConeGeometry(1, 1, 7)
  };
})();

const TREE_MATS = (() => {
  if (typeof THREE === 'undefined') return null;
  const leafCols = [0x3a8a2e, 0x2e7a24, 0x4a9a3c, 0x338030, 0x28701e, 0x2d6e2a, 0x245e22, 0x1e5218, 0x8ab840];
  return {
    pineTrunk: new THREE.MeshStandardMaterial({ color: 0x5c3a1e, roughness: 0.95 }),
    decTrunk: new THREE.MeshStandardMaterial({ color: 0x3d2612, roughness: 0.95 }),
    birchTrunk: new THREE.MeshStandardMaterial({ color: 0xddd8cc, roughness: 0.80 }),
    leafCols,
    leafMats: leafCols.map(c => new THREE.MeshStandardMaterial({ color: c, roughness: 0.85 }))
  };
})();

const CHUNK_COLLIDERS = new Map();

function checkTreeColliders(pos, hitR = 0.35) {
  for (const colliders of CHUNK_COLLIDERS.values()) {
    for (let i = 0, n = colliders.length; i < n; i++) {
      const c = colliders[i];
      const dx = pos.x - c.cx, dy = pos.y - c.cy, dz = pos.z - c.cz;
      const dist2 = dx*dx + dy*dy + dz*dz;
      const minD = c.r + hitR;
      if (dist2 < minD*minD) return c;
    }
  }
  return null;
}

function applyTreeCrashPhysics(hitCollider) {
  if (!PHYS || PHYS.crashed) return;
  const spd = Math.hypot(PHYS.vel.x, PHYS.vel.y, PHYS.vel.z);
  if (spd < 1.5) return;
  PHYS._doCrash(spd);

  const nx = PHYS.pos.x - hitCollider.cx;
  const ny = PHYS.pos.y - hitCollider.cy;
  const nz = PHYS.pos.z - hitCollider.cz;
  const nl = Math.hypot(nx, ny, nz) || 1;
  const nnx = nx/nl, nny = ny/nl, nnz = nz/nl;

  const vDotN = PHYS.vel.x*nnx + PHYS.vel.y*nny + PHYS.vel.z*nnz;
  const e = 0.22;
  if (vDotN < 0) {
    PHYS.vel.x -= (1+e)*vDotN*nnx;
    PHYS.vel.y -= (1+e)*vDotN*nny;
    PHYS.vel.z -= (1+e)*vDotN*nnz;
  }

  PHYS.vel.x += (Math.random()-0.5)*spd*0.5;
  PHYS.vel.z += (Math.random()-0.5)*spd*0.5;
  PHYS.vel.y += Math.random()*spd*0.2;

  const spin = spd * 1.5;
  PHYS.angVel.x += (Math.random()-0.5)*spin;
  PHYS.angVel.y += (Math.random()-0.5)*spin;
  PHYS.angVel.z += (Math.random()-0.5)*spin;
}

function buildInstancedVegetationForChunk(cx, cz, envName) {
  if (!TREE_GEOS || !TREE_MATS || typeof THREE_ENV === 'undefined') return null;
  const env = envName || 'field';
  if (env === 'urban' || env === 'indoor' || env === 'desert') return null;

  const CHUNK_SIZE = 60;
  const rng = (typeof _chunkRng !== 'undefined') ? _chunkRng(cx + 3000, cz + 4000) : Math.random;
  const worldOffX = cx * CHUNK_SIZE;
  const worldOffZ = cz * CHUNK_SIZE;
  const count = env === 'mountains' ? 12 : 20;

  const leafColsMtn = [0x2d6e2a, 0x245e22, 0x1e5218];
  const leafColsFld = [0x3a8a2e, 0x2e7a24, 0x4a9a3c, 0x338030, 0x28701e];
  const leafCols = env === 'mountains' ? leafColsMtn : leafColsFld;

  // Pre-calculate tree data for the chunk to allocate InstancedMesh counts exactly
  const trees = [];
  let numPine = 0, numDec = 0, numBirch = 0;
  const leafCounts = new Array(TREE_MATS.leafMats.length).fill(0);
  const coneCounts = new Array(TREE_MATS.leafMats.length).fill(0);

  for (let i = 0; i < count; i++) {
    const lx = (rng() - 0.5) * CHUNK_SIZE * 0.85;
    const lz = (rng() - 0.5) * CHUNK_SIZE * 0.85;
    // KEEP SPAWN CLEAR
    if (Math.abs(lx) < 4 && Math.abs(lz) < 4 && cx === 0 && cz === 0) { rng(); rng(); continue; }

    const wx = lx + worldOffX, wz = lz + worldOffZ;
    const hy = THREE_ENV.getTerrainHeight(wx, wz);
    if (env === 'mountains' && hy > 30) { rng(); rng(); continue; }

    const treeType = Math.floor(rng() * 3);
    const rawCol = leafCols[Math.floor(rng() * leafCols.length)];
    let leafCI = TREE_MATS.leafCols.indexOf(rawCol);
    if (leafCI < 0) leafCI = 0;

    trees.push({ type: treeType, lx, hy, lz, wx, wz, leafCI });

    if (treeType === 0) {
      numPine++;
      const tiers = 3 + Math.floor(rng() * 2);
      coneCounts[leafCI] += tiers;
    } else if (treeType === 1) {
      numDec++;
      leafCounts[leafCI] += 4; // 1 main canopy + 3 lobes
    } else {
      numBirch++;
      leafCounts[leafCI] += 1;
    }
  }

  if (trees.length === 0) return null;

  const group = new THREE.Group();
  const colliders = [];
  const m4 = new THREE.Matrix4();
  const scratchPos = new THREE.Vector3();
  const scratchScale = new THREE.Vector3();
  const scratchQuat = new THREE.Quaternion();

  // Create InstancedMesh objects for this chunk
  const imPine = numPine > 0 ? new THREE.InstancedMesh(TREE_GEOS.pineTrunk, TREE_MATS.pineTrunk, numPine) : null;
  const imDec = numDec > 0 ? new THREE.InstancedMesh(TREE_GEOS.decTrunk, TREE_MATS.decTrunk, numDec) : null;
  const imBirch = numBirch > 0 ? new THREE.InstancedMesh(TREE_GEOS.birchTrunk, TREE_MATS.birchTrunk, numBirch) : null;
  
  const imLeaves = leafCounts.map((cnt, i) => cnt > 0 ? new THREE.InstancedMesh(TREE_GEOS.canopy, TREE_MATS.leafMats[i], cnt) : null);
  const imCones = coneCounts.map((cnt, i) => cnt > 0 ? new THREE.InstancedMesh(TREE_GEOS.cone, TREE_MATS.leafMats[i], cnt) : null);

  [imPine, imDec, imBirch, ...imLeaves, ...imCones].forEach(im => {
    if (im) {
      im.castShadow = true;
      im.receiveShadow = true;
      im.frustumCulled = false; // Prevents disappearing trees since origins might not match perfectly
      group.add(im);
    }
  });

  // Cursor arrays
  let cPine = 0, cDec = 0, cBirch = 0;
  const cLeaves = new Array(TREE_MATS.leafMats.length).fill(0);
  const cCones = new Array(TREE_MATS.leafMats.length).fill(0);

  // Reproject rng sequentially to match EXACTLY the procedural generation
  const treeRng = (typeof _chunkRng !== 'undefined') ? _chunkRng(cx + 3000, cz + 4000) : Math.random;

  for (let i = 0; i < count; i++) {
    const lx = (treeRng() - 0.5) * CHUNK_SIZE * 0.85;
    const lz = (treeRng() - 0.5) * CHUNK_SIZE * 0.85;
    if (Math.abs(lx) < 4 && Math.abs(lz) < 4 && cx === 0 && cz === 0) { treeRng(); treeRng(); continue; }
    
    const wx = lx + worldOffX, wz = lz + worldOffZ;
    const hy = THREE_ENV.getTerrainHeight(wx, wz);
    if (env === 'mountains' && hy > 30) { treeRng(); treeRng(); continue; }

    const treeType = Math.floor(treeRng() * 3);
    const rawCol = leafCols[Math.floor(treeRng() * leafCols.length)];
    let leafCI = TREE_MATS.leafCols.indexOf(rawCol);
    if (leafCI < 0) leafCI = 0;

    if (treeType === 0) {
      const tH = 3 + treeRng() * 4;
      scratchScale.set(1, tH, 1);
      m4.compose(scratchPos.set(lx, hy + tH/2, lz), scratchQuat.identity(), scratchScale);
      imPine.setMatrixAt(cPine++, m4);
      
      const tiers = 3 + Math.floor(treeRng() * 2);
      for (let t = 0; t < tiers; t++) {
        const ty = hy + tH*0.4 + t*(tH*0.22);
        const r = 1.6 - t*0.3 + treeRng()*0.3;
        scratchScale.set(r, tH*0.35, r);
        m4.compose(scratchPos.set(lx, ty, lz), scratchQuat, scratchScale);
        imCones[leafCI].setMatrixAt(cCones[leafCI]++, m4);
      }
      colliders.push({ cx: wx, cy: hy + tH * 0.6, cz: wz, r: 2.2 });

    } else if (treeType === 1) {
      const tH = 2.5 + treeRng() * 3;
      scratchScale.set(1, tH, 1);
      m4.compose(scratchPos.set(lx, hy + tH/2, lz), scratchQuat.identity(), scratchScale);
      imDec.setMatrixAt(cDec++, m4);

      const cr = 1.8 + treeRng() * 1.4;
      scratchScale.set(cr, cr * (0.72 + treeRng() * 0.2), cr);
      m4.compose(scratchPos.set(lx, hy + tH + cr * 0.6, lz), scratchQuat, scratchScale);
      imLeaves[leafCI].setMatrixAt(cLeaves[leafCI]++, m4);

      for (let l = 0; l < 3; l++) {
        const la = (l/3)*Math.PI*2 + treeRng()*0.8;
        const lr = cr*0.55;
        scratchScale.set(lr, lr, lr);
        m4.compose(scratchPos.set(lx + Math.cos(la)*cr*0.55, hy+tH+cr*0.3+treeRng()*0.5, lz + Math.sin(la)*cr*0.55), scratchQuat, scratchScale);
        imLeaves[leafCI].setMatrixAt(cLeaves[leafCI]++, m4);
      }
      colliders.push({ cx: wx, cy: hy + tH + cr * 0.5, cz: wz, r: cr * 1.2 });

    } else {
      const tH = 4 + treeRng() * 5;
      scratchScale.set(1, tH, 1);
      m4.compose(scratchPos.set(lx, hy + tH/2, lz), scratchQuat.identity(), scratchScale);
      imBirch.setMatrixAt(cBirch++, m4);

      const cr = 1.2 + treeRng() * 0.8;
      scratchScale.set(cr, cr * 1.1, cr);
      m4.compose(scratchPos.set(lx, hy + tH + cr * 0.5, lz), scratchQuat, scratchScale);
      imLeaves[leafCI].setMatrixAt(cLeaves[leafCI]++, m4);

      colliders.push({ cx: wx, cy: hy + tH + cr * 0.5, cz: wz, r: cr * 1.1 });
    }
  }

  const key = cx + ',' + cz;
  CHUNK_COLLIDERS.set(key, colliders);

  return group;
}

const SIM_PATCH = {
  _installed: false,
  install() {
    if (this._installed) return;
    if (typeof THREE === 'undefined' || typeof THREE_ENV === 'undefined' || typeof PHYS === 'undefined' || typeof State === 'undefined') {
      setTimeout(() => this.install(), 100);
      return;
    }
    this._installed = true;
    this._patchSimLoop();
    console.log('[SIM_PATCH] Installed spaceborn vegetation optimisations successfully.');
  },
  _patchSimLoop() {
    if (typeof SIM === 'undefined') return;
    const origLoop = SIM._loop.bind(SIM);
    SIM._loop = function () {
      origLoop();
      if (typeof PHYS !== 'undefined' && !PHYS.crashed && CHUNK_COLLIDERS) {
        const hit = checkTreeColliders(PHYS.pos, 0.35);
        if (hit) {
          const spd = Math.hypot(PHYS.vel.x, PHYS.vel.y, PHYS.vel.z);
          if (spd > 2.5) {
            applyTreeCrashPhysics(hit);
            const co = document.getElementById('crash-overlay');
            if (co && !co.classList.contains('show')) co.classList.add('show');
            if (typeof UI !== 'undefined' && UI.toast) {
              UI.toast(`💥 Tree collision! Speed: ${spd.toFixed(1)} m/s`);
            }
          }
        }
      }
    };
  }
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => SIM_PATCH.install());
} else {
  setTimeout(() => SIM_PATCH.install(), 0);
}

if (typeof globalThis !== 'undefined') {
  Object.assign(globalThis, {
    SIM_PATCH,
    checkTreeColliders,
    applyTreeCrashPhysics,
    buildInstancedVegetationForChunk,
    CHUNK_COLLIDERS
  });
}
