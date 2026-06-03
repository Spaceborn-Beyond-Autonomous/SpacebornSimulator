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

  const trees = [];
  let numPine = 0, numDec = 0, numBirch = 0;
  const leafCounts = new Array(TREE_MATS.leafMats.length).fill(0);
  const coneCounts = new Array(TREE_MATS.leafMats.length).fill(0);

  for (let i = 0; i < count; i++) {
    const lx = (rng() - 0.5) * CHUNK_SIZE * 0.85;
    const lz = (rng() - 0.5) * CHUNK_SIZE * 0.85;
    if (Math.abs(lx) < 4 && Math.abs(lz) < 4 && cx === 0 && cz === 0) { rng(); rng(); continue; }

    const wx = lx + worldOffX, wz = lz + worldOffZ;
    const hy = THREE_ENV.getTerrainHeight(wx, wz);
    if (env === 'mountains' && hy > 30) { rng(); rng(); continue; }

    const treeType = Math.floor(rng() * 3);
    const rawCol = leafCols[Math.floor(rng() * leafCols.length)];
    let leafCI = TREE_MATS.leafCols.indexOf(rawCol);
    if (leafCI < 0) leafCI = 0;

    let treeData = { type: treeType, lx, hy, lz, wx, wz, leafCI };

    if (treeType === 0) {
      numPine++;
      treeData.tH = 3 + rng() * 4;
      treeData.tiers = 3 + Math.floor(rng() * 2);
      treeData.rs = [];
      for (let t = 0; t < treeData.tiers; t++) {
        treeData.rs.push(1.6 - t * 0.3 + rng() * 0.3);
      }
      coneCounts[leafCI] += treeData.tiers;
    } else if (treeType === 1) {
      numDec++;
      treeData.tH = 2.5 + rng() * 3;
      treeData.cr = 1.8 + rng() * 1.4;
      treeData.lobes = [];
      for (let l = 0; l < 3; l++) {
        treeData.lobes.push({ la: (l / 3) * Math.PI * 2 + rng() * 0.8, yo: rng() * 0.5 });
      }
      leafCounts[leafCI] += 4; // 1 main canopy + 3 lobes
    } else {
      numBirch++;
      treeData.tH = 3.5 + rng() * 3;
      treeData.cr = 1.0 + rng() * 0.6;
      leafCounts[leafCI] += 1;
    }
    trees.push(treeData);
  }

  if (trees.length === 0) return null;

  const group = new THREE.Group();
  const colliders = [];
  const m4 = new THREE.Matrix4();
  const scratchPos = new THREE.Vector3();
  const scratchScale = new THREE.Vector3();
  const scratchQuat = new THREE.Quaternion();

  const imPine = numPine > 0 ? new THREE.InstancedMesh(TREE_GEOS.pineTrunk, TREE_MATS.pineTrunk, numPine) : null;
  const imDec = numDec > 0 ? new THREE.InstancedMesh(TREE_GEOS.decTrunk, TREE_MATS.decTrunk, numDec) : null;
  const imBirch = numBirch > 0 ? new THREE.InstancedMesh(TREE_GEOS.birchTrunk, TREE_MATS.birchTrunk, numBirch) : null;
  const imLeaves = leafCounts.map((cnt, i) => cnt > 0 ? new THREE.InstancedMesh(TREE_GEOS.canopy, TREE_MATS.leafMats[i], cnt) : null);
  const imCones = coneCounts.map((cnt, i) => cnt > 0 ? new THREE.InstancedMesh(TREE_GEOS.cone, TREE_MATS.leafMats[i], cnt) : null);

  let cPine = 0, cDec = 0, cBirch = 0;
  const cLeaves = new Array(TREE_MATS.leafMats.length).fill(0);
  const cCones = new Array(TREE_MATS.leafMats.length).fill(0);

  for (const t of trees) {
    if (t.type === 0) {
      scratchScale.set(1, t.tH, 1);
      m4.compose(scratchPos.set(t.lx, t.hy + t.tH / 2, t.lz), scratchQuat.identity(), scratchScale);
      imPine.setMatrixAt(cPine++, m4);
      
      for (let i = 0; i < t.tiers; i++) {
        const ty = t.hy + t.tH * 0.4 + i * (t.tH * 0.22);
        const r = t.rs[i];
        scratchScale.set(r, t.tH * 0.35, r);
        m4.compose(scratchPos.set(t.lx, ty, t.lz), scratchQuat, scratchScale);
        imCones[t.leafCI].setMatrixAt(cCones[t.leafCI]++, m4);
      }
      colliders.push({ cx: t.wx, cy: t.hy + t.tH * 0.6, cz: t.wz, r: 2.2 });
    } else if (t.type === 1) {
      scratchScale.set(1, t.tH, 1);
      m4.compose(scratchPos.set(t.lx, t.hy + t.tH / 2, t.lz), scratchQuat.identity(), scratchScale);
      imDec.setMatrixAt(cDec++, m4);

      scratchScale.set(t.cr, t.cr * 0.8, t.cr);
      m4.compose(scratchPos.set(t.lx, t.hy + t.tH + t.cr * 0.6, t.lz), scratchQuat, scratchScale);
      imLeaves[t.leafCI].setMatrixAt(cLeaves[t.leafCI]++, m4);

      for (let l = 0; l < 3; l++) {
        const la = t.lobes[l].la;
        const lr = t.cr * 0.55;
        scratchScale.set(lr, lr, lr);
        m4.compose(scratchPos.set(t.lx + Math.cos(la) * t.cr * 0.55, t.hy + t.tH + t.cr * 0.3 + t.lobes[l].yo, t.lz + Math.sin(la) * t.cr * 0.55), scratchQuat, scratchScale);
        imLeaves[t.leafCI].setMatrixAt(cLeaves[t.leafCI]++, m4);
      }
      colliders.push({ cx: t.wx, cy: t.hy + t.tH * 0.5, cz: t.wz, r: t.cr * 1.5 });
    } else {
      scratchScale.set(1, t.tH, 1);
      m4.compose(scratchPos.set(t.lx, t.hy + t.tH / 2, t.lz), scratchQuat.identity(), scratchScale);
      imBirch.setMatrixAt(cBirch++, m4);

      scratchScale.set(t.cr, t.cr * 1.6, t.cr);
      m4.compose(scratchPos.set(t.lx, t.hy + t.tH + t.cr * 0.8, t.lz), scratchQuat, scratchScale);
      imLeaves[t.leafCI].setMatrixAt(cLeaves[t.leafCI]++, m4);
      colliders.push({ cx: t.wx, cy: t.hy + t.tH * 0.6, cz: t.wz, r: t.cr * 1.2 });
    }
  }

  [imPine, imDec, imBirch, ...imLeaves, ...imCones].forEach(im => {
    if (im) {
      im.instanceMatrix.needsUpdate = true;
      im.castShadow = true;
      im.receiveShadow = true;
      im.frustumCulled = false;
      group.add(im);
    }
  });

  const key = `${cx},${cz}`;
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
