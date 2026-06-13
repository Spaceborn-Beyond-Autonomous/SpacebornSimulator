/* SpaceBorn Simulator Optimizations - FIXED: Universal Collision System */
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
    pineTrunk: new THREE.MeshLambertMaterial({ color: 0x5c3a1e }),
    decTrunk: new THREE.MeshLambertMaterial({ color: 0x3d2612 }),
    birchTrunk: new THREE.MeshLambertMaterial({ color: 0xddd8cc }),
    leafCols,
    leafMats: leafCols.map(c => new THREE.MeshLambertMaterial({ color: c }))
  };
})();

// UNIFIED collider system for ALL obstacles (trees, rocks, buildings, etc)
const CHUNK_COLLIDERS = new Map();
let FLAT_COLLIDERS = [];

function updateFlatColliders() {
  FLAT_COLLIDERS = [];
  for (const arr of CHUNK_COLLIDERS.values()) {
    for (let i = 0; i < arr.length; i++) {
      FLAT_COLLIDERS.push(arr[i]);
    }
  }
}

// Check ANY collider (trees, rocks, buildings, terrain obstacles)
function checkTreeColliders(pos, hitR = 0.1) {
  if (typeof CHUNK_COLLIDERS === 'undefined') return null;
  const CHUNK_SIZE = 60;
  const cx = Math.round(pos.x / CHUNK_SIZE);
  const cz = Math.round(pos.z / CHUNK_SIZE);

  for (let dx = -1; dx <= 1; dx++) {
    for (let dz = -1; dz <= 1; dz++) {
      const key = `${cx + dx},${cz + dz}`;
      const colliders = CHUNK_COLLIDERS.get(key);
      if (!colliders) continue;

      for (let i = 0, n = colliders.length; i < n; i++) {
        const c = colliders[i];
        const distSq = (pos.x - c.cx)*(pos.x - c.cx) + (pos.z - c.cz)*(pos.z - c.cz);
        const minD = c.r + hitR;
        if (distSq < minD*minD) {
          if (pos.y >= c.minY && pos.y <= c.maxY) return c;
        }
      }
    }
  }
  return null;
}

function applyTreeCrashPhysics(hitCollider) {
  if (!PHYS || PHYS.crashed) return;
  const spd = Math.hypot(PHYS.vel.x, PHYS.vel.y, PHYS.vel.z);
  
  // ALWAYS trigger crash when hitting ANY rigid obstacle
  PHYS._doCrash(spd);
  const co = document.getElementById('crash-overlay');
  if (co && !co.classList.contains('show')) co.classList.add('show');

  // Treat collision as CYLINDER, ignore Y for distance and normal
  const nx = PHYS.pos.x - hitCollider.cx;
  const nz = PHYS.pos.z - hitCollider.cz;
  const nl = Math.hypot(nx, nz) || 1;
  const nnx = nx/nl, nny = 0, nnz = nz/nl;

  // PUSH OUT of the obstacle to prevent sticking/lag
  const pushDist = (hitCollider.r + 0.25) - nl;
  if (pushDist > 0) {
    PHYS.pos.x += nnx * pushDist;
    PHYS.pos.y += nny * pushDist;
    PHYS.pos.z += nnz * pushDist;
  }

  // If crashed, kill horizontal velocity completely
  PHYS.vel.x = 0;
  PHYS.vel.z = 0;
  PHYS.angVel.x = (Math.random()-0.5)*5;
  PHYS.angVel.z = (Math.random()-0.5)*5;
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
      leafCounts[leafCI] += 4;
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
        colliders.push({ cx: t.wx, cy: ty, cz: t.wz, minY: ty - t.tH * 0.175, maxY: ty + t.tH * 0.175, r: r * 0.4 });
      }
      colliders.push({ cx: t.wx, cy: t.hy + t.tH * 0.4, cz: t.wz, minY: t.hy, maxY: t.hy + t.tH, r: 0.15 });
    } else if (t.type === 1) {
      scratchScale.set(1, t.tH, 1);
      m4.compose(scratchPos.set(t.lx, t.hy + t.tH / 2, t.lz), scratchQuat.identity(), scratchScale);
      imDec.setMatrixAt(cDec++, m4);

      const mty = t.hy + t.tH + t.cr * 0.6;
      scratchScale.set(t.cr, t.cr * 0.8, t.cr);
      m4.compose(scratchPos.set(t.lx, mty, t.lz), scratchQuat, scratchScale);
      imLeaves[t.leafCI].setMatrixAt(cLeaves[t.leafCI]++, m4);
      colliders.push({ cx: t.wx, cy: mty, cz: t.wz, minY: mty - t.cr * 0.4, maxY: mty + t.cr * 0.4, r: t.cr * 0.45 });

      for (let l = 0; l < 3; l++) {
        const la = t.lobes[l].la;
        const lr = t.cr * 0.55;
        const cy = t.hy + t.tH + t.cr * 0.3 + t.lobes[l].yo;
        const lcx = Math.cos(la) * t.cr * 0.55;
        const lcz = Math.sin(la) * t.cr * 0.55;
        scratchScale.set(lr, lr, lr);
        m4.compose(scratchPos.set(t.lx + lcx, cy, t.lz + lcz), scratchQuat, scratchScale);
        imLeaves[t.leafCI].setMatrixAt(cLeaves[t.leafCI]++, m4);
        colliders.push({ cx: t.wx + lcx, cy: cy, cz: t.wz + lcz, minY: cy - lr / 2, maxY: cy + lr / 2, r: lr * 0.45 });
      }
      colliders.push({ cx: t.wx, cy: t.hy + t.tH / 2, cz: t.wz, minY: t.hy, maxY: t.hy + t.tH, r: 0.15 });
    } else {
      scratchScale.set(1, t.tH, 1);
      m4.compose(scratchPos.set(t.lx, t.hy + t.tH / 2, t.lz), scratchQuat.identity(), scratchScale);
      imBirch.setMatrixAt(cBirch++, m4);

      const bty = t.hy + t.tH + t.cr * 0.8;
      scratchScale.set(t.cr, t.cr * 1.6, t.cr);
      m4.compose(scratchPos.set(t.lx, bty, t.lz), scratchQuat, scratchScale);
      imLeaves[t.leafCI].setMatrixAt(cLeaves[t.leafCI]++, m4);
      colliders.push({ cx: t.wx, cy: bty, cz: t.wz, minY: bty - t.cr * 0.8, maxY: bty + t.cr * 0.8, r: t.cr * 0.45 });
      colliders.push({ cx: t.wx, cy: t.hy + t.tH / 2, cz: t.wz, minY: t.hy, maxY: t.hy + t.tH, r: 0.1 });
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
  updateFlatColliders();
  return group;
}

// ADD rock/boulder colliders to chunk (for mountains/desert/fields)
function buildRockCollidersForChunk(cx, cz, envName) {
  if (typeof CHUNK_COLLIDERS === 'undefined') return [];
  
  const env = envName || 'field';
  const CHUNK_SIZE = 60;
  const rng = (typeof _chunkRng !== 'undefined') ? _chunkRng(cx + 5000, cz + 6000) : Math.random;
  
  const worldOffX = cx * CHUNK_SIZE;
  const worldOffZ = cz * CHUNK_SIZE;
  
  // More rocks in mountains, fewer in fields/desert
  const count = env === 'mountains' ? 25 : (env === 'desert' ? 15 : 8);
  
  const colliders = [];
  
  for (let i = 0; i < count; i++) {
    const lx = (rng() - 0.5) * CHUNK_SIZE * 0.85;
    const lz = (rng() - 0.5) * CHUNK_SIZE * 0.85;
    
    // Skip center spawn area
    if (Math.abs(lx) < 5 && Math.abs(lz) < 5 && cx === 0 && cz === 0) continue;
    
    const wx = lx + worldOffX;
    const wz = lz + worldOffZ;
    const hy = THREE_ENV.getTerrainHeight(wx, wz);
    
    // Skip very high peaks in mountains
    if (env === 'mountains' && hy > 35) continue;
    
    const scale = 0.5 + rng() * 1.2;
    const radius = scale * 0.5;
    
    colliders.push({
      cx: wx,
      cy: hy + scale * 0.4,
      cz: wz,
      minY: hy,
      maxY: hy + scale * 1.2,
      r: radius,
      type: 'rock'
    });
  }
  
  return colliders;
}

// ADD building/structure colliders for urban environment
function buildBuildingCollidersForChunk(cx, cz) {
  if (typeof CHUNK_COLLIDERS === 'undefined') return [];
  
  const CHUNK_SIZE = 60;
  const rng = (typeof _chunkRng !== 'undefined') ? _chunkRng(cx + 7000, cz + 8000) : Math.random;
  
  const worldOffX = cx * CHUNK_SIZE;
  const worldOffZ = cz * CHUNK_SIZE;
  
  const colliders = [];
  const buildingCount = 6;
  
  for (let i = 0; i < buildingCount; i++) {
    const lx = (rng() - 0.5) * CHUNK_SIZE * 0.75;
    const lz = (rng() - 0.5) * CHUNK_SIZE * 0.75;
    
    // Skip center spawn
    if (Math.abs(lx) < 8 && Math.abs(lz) < 8 && cx === 0 && cz === 0) continue;
    
    const wx = lx + worldOffX;
    const wz = lz + worldOffZ;
    const bHeight = 8 + rng() * 15;
    const bWidth = 4 + rng() * 6;
    
    colliders.push({
      cx: wx,
      cy: bHeight * 0.5,
      cz: wz,
      minY: 0,
      maxY: bHeight,
      r: bWidth * 0.4,
      type: 'building'
    });
  }
  
  return colliders;
}

// Merge all colliders into the chunk system
function registerChunkColliders(cx, cz, rockColliders, buildingColliders = []) {
  const key = `${cx},${cz}`;
  let allColliders = CHUNK_COLLIDERS.get(key) || [];
  
  if (rockColliders && rockColliders.length > 0) {
    allColliders = allColliders.concat(rockColliders);
  }
  if (buildingColliders && buildingColliders.length > 0) {
    allColliders = allColliders.concat(buildingColliders);
  }
  
  if (allColliders.length > 0) {
    CHUNK_COLLIDERS.set(key, allColliders);
    updateFlatColliders();
  }
}

const SIM_PATCH = {
  _installed: true,
  install() {
    // Tree & rock collisions handled in core sim-engine.js _substep
  }
};

if (typeof globalThis !== 'undefined') {
  Object.assign(globalThis, {
    SIM_PATCH,
    checkTreeColliders,
    applyTreeCrashPhysics,
    buildInstancedVegetationForChunk,
    buildRockCollidersForChunk,
    buildBuildingCollidersForChunk,
    registerChunkColliders,
    CHUNK_COLLIDERS,
    updateFlatColliders
  });
}
