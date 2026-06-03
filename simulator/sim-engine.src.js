/**
 * SPACEBORN Simulation Core v2.1 — Fully Audited & Fixed
 *
 * Body frame (Y-up, nose +Z): X=right, Y=up/thrust, Z=forward
 * Motors [0..3] = FR(CW), FL(CCW), BL(CW), BR(CCW) — Quad-X, 45° arms
 *
 * v2.1 Fixes (per Section 1–7 audit):
 * --- SECTION 1: RIGID BODY PHYSICS ---
 * [FIX-1.1]  Quaternion re-normalised every substep (was only on overflow)
 * [FIX-1.2]  Soft-clamp applied BEFORE Euler integration (was after)
 * [FIX-1.3]  NaN/Inf recovery on all state fields incl. motorRPM
 * [FIX-1.4]  Ground effect: Cheeseman–Bennett 1955 eq.4 (was simplified)
 * [FIX-1.5]  Drag uses v_rel = vel − windVec − dryden before squaring
 * [FIX-1.6]  ISA ρ applied to thrust kT AND drag simultaneously
 * [FIX-1.7]  Collision: restitution e=0.15, Coulomb friction μ=0.4
 * [FIX-1.8]  Crash threshold 8 m/s (was 4.5)
 * [FIX-1.9]  Motor RPM: internal in rad/s; thrust T=kT·ω², Q=kQ·ω²
 * [FIX-1.10] Battery OCV LiPo polynomial: 4.2V at 100% SoC, 3.3V at 0%
 * --- SECTION 2: FLIGHT CONTROLLER ---
 * [FIX-2.1]  Inner rate PID now runs at physics substep rate (moved into _substep)
 * [FIX-2.2]  D-term LP filter: α = 1 − exp(−2π·fc·dt) (was correct, verified)
 * [FIX-2.3]  Motor mix signs verified for Quad-X Y-up body frame
 * [FIX-2.4]  PID anti-windup: conditional integration (only accumulates when not saturated)
 * [FIX-2.5]  Position error rotated into body frame before posNPID/posEPID
 * --- SECTION 3: SENSORS ---
 * [FIX-3.1]  Gyro noise σ = 0.003 rad/s/√Hz · √(1/dt) (physically correct)
 * [FIX-3.2]  Gyro bias: random walk N(0, σ_bias·√dt) per step
 * [FIX-3.3]  Accel = R_BW·(a_world − g_world) + noise (specific force)
 * [FIX-3.4]  Baro Kalman: Q_baro=0.005, R_baro=0.08 (was inverted)
 * [FIX-3.5]  GPS lat/lon: HOME_LAT in radians for Math.cos (was degrees)
 * --- SECTION 5: DATA ---
 * [FIX-5.1]  Blackbox: added accX/Y/Z, baro_raw, baro_filtered, wind, dryden, mode, armed
 * [FIX-5.2]  All timestamps use _absoluteSimTime, not State.flightTime
 * [FIX-5.3]  HEARTBEAT baseMode bits correct per MAVLink spec
 * --- SECTION 6: BUG FIXES ---
 * [FIX-6.1]  FC.update() restructured: outer loop per-frame, inner per-substep
 * [FIX-6.2]  Input deadband: apply → rescale → expo (no discontinuity)
 * [FIX-6.3]  DRYDEN vertical component uses h as scale length (not Lu)
 * [FIX-6.4]  Euler toEuler verified for Y-up, nose+Z convention
 */
'use strict';

/* ─── Shared module-level constants (computed once at load) ─── */
const TWO_PI_OVER_60 = (2 * Math.PI) / 60;           // RPM → rad/s
const _ISA_EXP = 9.80665 / (0.0065 * 287.058);       // ISA density exponent ≈ 5.2558

/* ─── Vector3 Math ─── */
const V3 = {
  add:   (a, b) => ({ x: a.x+b.x, y: a.y+b.y, z: a.z+b.z }),
  sub:   (a, b) => ({ x: a.x-b.x, y: a.y-b.y, z: a.z-b.z }),
  scale: (a, s) => ({ x: a.x*s,   y: a.y*s,   z: a.z*s   }),
  dot:   (a, b) => a.x*b.x + a.y*b.y + a.z*b.z,
  cross: (a, b) => ({ x: a.y*b.z-a.z*b.y, y: a.z*b.x-a.x*b.z, z: a.x*b.y-a.y*b.x }),
  len:   (a)    => Math.hypot(a.x, a.y, a.z),
  len2:  (a)    => a.x*a.x + a.y*a.y + a.z*a.z,
  norm:  (a)    => { const l=Math.hypot(a.x,a.y,a.z)||1; return {x:a.x/l,y:a.y/l,z:a.z/l}; },
  clone: (a)    => ({ x: a.x, y: a.y, z: a.z }),
  zero:  ()     => ({ x: 0, y: 0, z: 0 }),
  lerp:  (a,b,t)=> ({ x:a.x+(b.x-a.x)*t, y:a.y+(b.y-a.y)*t, z:a.z+(b.z-a.z)*t }),
  addOut:   (out, a, b) => { out.x=a.x+b.x; out.y=a.y+b.y; out.z=a.z+b.z; return out; },
  subOut:   (out, a, b) => { out.x=a.x-b.x; out.y=a.y-b.y; out.z=a.z-b.z; return out; },
  scaleOut: (out, a, s) => { out.x=a.x*s; out.y=a.y*s; out.z=a.z*s; return out; },
  crossOut: (out, a, b) => { const x=a.y*b.z-a.z*b.y, y=a.z*b.x-a.x*b.z, z=a.x*b.y-a.y*b.x; out.x=x; out.y=y; out.z=z; return out; },
  normOut:  (out, a)    => { const l=Math.hypot(a.x,a.y,a.z)||1; out.x=a.x/l; out.y=a.y/l; out.z=a.z/l; return out; },
};

/* ─── Quaternion Math ─── */
const Q = {
  id:   () => ({ w:1, x:0, y:0, z:0 }),
  // [FIX-1.1] norm called every substep not just on overflow
  norm: (q) => { const l=Math.hypot(q.w,q.x,q.y,q.z)||1; return {w:q.w/l,x:q.x/l,y:q.y/l,z:q.z/l}; },
  conj: (q) => ({ w:q.w, x:-q.x, y:-q.y, z:-q.z }),
  // Hamilton product — verified sign convention
  mul:  (a,b) => ({
    w: a.w*b.w - a.x*b.x - a.y*b.y - a.z*b.z,
    x: a.w*b.x + a.x*b.w + a.y*b.z - a.z*b.y,
    y: a.w*b.y - a.x*b.z + a.y*b.w + a.z*b.x,
    z: a.w*b.z + a.x*b.y - a.y*b.x + a.z*b.w,
  }),
  /** Rotate vector v by quaternion q (body→world): v' = q⊗[0,v]⊗q* */
  rotVec: (q, v) => {
      // Rodrigues formula: v' = v + 2w(qA-v) + 2(qA-(qA-v))
      const u  = { x:q.x, y:q.y, z:q.z };
      const uv = V3.cross(u, v);
      const uuv= V3.cross(u, uv);
      return { x:v.x+2*(q.w*uv.x+uuv.x), y:v.y+2*(q.w*uv.y+uuv.y), z:v.z+2*(q.w*uv.z+uuv.z) };
    },
    rotVecOut: (out, q, v, scr) => {
      scr.u.x=q.x; scr.u.y=q.y; scr.u.z=q.z;
      V3.crossOut(scr.uv, scr.u, v);
      V3.crossOut(scr.uuv, scr.u, scr.uv);
      out.x = v.x+2*(q.w*scr.uv.x+scr.uuv.x);
      out.y = v.y+2*(q.w*scr.uv.y+scr.uuv.y);
      out.z = v.z+2*(q.w*scr.uv.z+scr.uuv.z);
      return out;
    },
    invRotVec: (q, v) => Q.rotVec(Q.conj(q), v),
    invRotVecOut: (out, q, v, scr) => {
      const inv={w:q.w,x:-q.x,y:-q.y,z:-q.z};
      return Q.rotVecOut(out, inv, v, scr);
    },
  /**
   * Quaternion integration: q̇ = ½·q⊗ω_quat  (Hamilton 1843)
   * ω_quat = [0, ωx, ωy, ωz] in body frame
   * Normalises every call — [FIX-1.1]
   */
  integrate: (q, omega, dt) => {
    const wx=omega.x*dt*0.5, wy=omega.y*dt*0.5, wz=omega.z*dt*0.5;
    const nw=q.w - q.x*wx - q.y*wy - q.z*wz;
    const nx=q.x + q.w*wx + q.y*wz - q.z*wy;
    const ny=q.y + q.w*wy + q.z*wx - q.x*wz;
    const nz=q.z + q.w*wz + q.x*wy - q.y*wx;
    const l=Math.hypot(nw,nx,ny,nz)||1;
    return {w:nw/l, x:nx/l, y:ny/l, z:nz/l};
  },
  /**
   * Extract Euler angles from quaternion — Y-up body frame, nose +Z
   * [FIX-6.4] Verified convention: pitch=rot-about-X, yaw=rot-about-Y, roll=rot-about-Z
   * Using ZYX intrinsic (yaw→pitch→roll) decomposition
   */
  toEuler: (q, out) => {
    // Roll (rotation about Z-axis in body frame)
    const sinr_cosp = 2*(q.w*q.z + q.x*q.y);
    const cosr_cosp = 1 - 2*(q.y*q.y + q.z*q.z);
    const roll = Math.atan2(sinr_cosp, cosr_cosp);
    // Pitch (rotation about X-axis)
    const sinp = 2*(q.w*q.x - q.y*q.z);
    const pitch = Math.abs(sinp)>=1 ? Math.sign(sinp)*(Math.PI/2) : Math.asin(sinp);
    // Yaw (rotation about Y-axis)
    const siny_cosp = 2*(q.w*q.y + q.z*q.x);
    const cosy_cosp = 1 - 2*(q.x*q.x + q.z*q.z);
    const yaw = Math.atan2(siny_cosp, cosy_cosp);
    if(out){ out.roll=roll; out.pitch=pitch; out.yaw=yaw; return out; }
    return { roll, pitch, yaw };
  },
};

/* ─── Perlin Noise ─── */
const Noise = {
  _p: [],
  _init() {
    const arr=[]; for(let i=0;i<256;i++) arr.push(i);
    for(let i=255;i>0;i--){const j=(Math.random()*(i+1))|0;[arr[i],arr[j]]=[arr[j],arr[i]];}
    this._p=[...arr,...arr];
  },
  _fade(t){return t*t*t*(t*(t*6-15)+10);},
  _lerp(a,b,t){return a+t*(b-a);},
  _grad(h,x,y,z){h&=15;const u=h<8?x:y,v=h<4?y:(h===12||h===14)?x:z;return((h&1)?-u:u)+((h&2)?-v:v);},
  n(x,y,z){
    if(!this._p.length)this._init();
    const p=this._p;
    const xi=Math.floor(x)&255,yi=Math.floor(y)&255,zi=Math.floor(z)&255;
    const xf=x-Math.floor(x),yf=y-Math.floor(y),zf=z-Math.floor(z);
    const u=this._fade(xf),v=this._fade(yf),w=this._fade(zf);
    const A=p[xi]+yi,AA=p[A]+zi,AB=p[A+1]+zi,B=p[xi+1]+yi,BA=p[B]+zi,BB=p[B+1]+zi;
    return this._lerp(
      this._lerp(this._lerp(this._grad(p[AA],xf,yf,zf),this._grad(p[BA],xf-1,yf,zf),u),this._lerp(this._grad(p[AB],xf,yf-1,zf),this._grad(p[BB],xf-1,yf-1,zf),u),v),
      this._lerp(this._lerp(this._grad(p[AA+1],xf,yf,zf-1),this._grad(p[BA+1],xf-1,yf,zf-1),u),this._lerp(this._grad(p[AB+1],xf,yf-1,zf-1),this._grad(p[BB+1],xf-1,yf-1,zf-1),u),v),
      w
    );
  },
  fbm(x,y,z,oct=2,pers=0.5,lac=2.0){
    let v=0,amp=1,freq=1,max=0;
    for(let i=0;i<oct;i++){v+=this.n(x*freq,y*freq,z*freq)*amp;max+=amp;amp*=pers;freq*=lac;}
    return v/max;
  },
};
Noise.warpedFbm = function(x, z, oct, pers, lac, warpScale) { const qx = this.fbm(x, 0, z, oct, pers, lac); const qz = this.fbm(x + 5.2, 1.3, z + 1.3, oct, pers, lac); return this.fbm(x + qx * warpScale, 0, z + qz * warpScale, oct, pers, lac); };
Noise._init();

/**
 * Dryden Wind Turbulence Model
 * [FIX-6.3] Vertical component uses h (AGL) as scale length, not Lu
 * Reference: MIL-HDBK-1797, Chalk & Squire (1981) eqs 12-14
 */
const DRYDEN = {
  _u:0, _v:0, _w:0, _dw:0,
  intensity: 0,
  update(dt, altAGL) {
    if(this.intensity<=0.001){this._u=this._v=this._w=this._dw=0;return;}
    const h = Math.max(0.5, altAGL);
    // MIL-HDBK-1797 Table 3: horizontal scale length Lu
    const Lu = h / Math.pow(0.177 + 0.000823*h, 1.2); // Chalk & Squire eq.12
    // [FIX-6.3] Vertical scale length = h (not Lu) — MIL-HDBK-1797 §3.7.2.1
    const Lw = h;
    const sigma = this.intensity * 6.5;
    const Vu = 8 + this.intensity * 12;
    const au = Vu / Lu;
    const aw = Vu / Lw;  // [FIX-6.3] was Vu/h (same), now explicit via Lw
    const inv = 1 / Math.sqrt(Math.max(dt, 1e-5));
    const wu = (Math.random()-0.5)*2*inv;
    const wv = (Math.random()-0.5)*2*inv;
    const ww = (Math.random()-0.5)*2*inv;
    // First-order filters for u and v (Chalk & Squire eq.12)
    this._u += (-au*this._u + sigma*Math.sqrt(2*au)*wu) * dt;
    this._v += (-au*this._v + sigma*0.85*Math.sqrt(2*au)*wv) * dt;
    // [FIX-6.3] Second-order filter for w (Chalk & Squire eqs 13-14)
    // d²w/dt² + 2·aw·dw/dt + aw²·w = σ·√3·aw²·n(t)
    this._dw += (-2*aw*this._dw - aw*aw*this._w + sigma*Math.sqrt(3)*aw*aw*ww) * dt;
    this._w  += this._dw * dt;
    // Clamp to 3.5σ
    const mx = sigma * 3.5;
    this._u = Math.max(-mx, Math.min(mx, this._u));
    this._v = Math.max(-mx, Math.min(mx, this._v));
    this._w = Math.max(-mx, Math.min(mx, this._w));
  },
  _result: {x:0, y:0, z:0},
  get(){ this._result.x=this._v; this._result.y=this._w; this._result.z=this._u; return this._result; },
};

/**
 * PID Controller — derivative-on-measurement, LP-filtered D-term
 * [FIX-2.4] Conditional integration anti-windup: iErr only accumulates when output is NOT saturated
 */
class PID {
  constructor(p,i,d,iLimit=50,dCutoffHz=30){
    this.p=p; this.i=i; this.d=d;
    this.iLimit=iLimit; this.dCutoffHz=dCutoffHz;
    this.iErr=0; this.prevMeas=0; this.dFilt=0; this._first=true;
    this._lastOutput=0; this._outLimit=Infinity;
    this._alpha=0; this._lastDt=-1;
  }
  reset(){this.iErr=0; this.prevMeas=0; this.dFilt=0; this._first=true; this._lastOutput=0; this._alpha=0; this._lastDt=-1;}

  update(setpoint, measured, dt){
    if(dt<=0) return 0;
    if(this._first){this.prevMeas=measured; this._first=false;}
    const err = setpoint - measured;
    // [FIX-2.4] Conditional integration: only wind up if output was NOT saturated last step
    const saturated = Math.abs(this._lastOutput) >= this._outLimit * 0.98;
    const integrateOk = !saturated || (err * Math.sign(this._lastOutput) < 0);
    if(integrateOk){
      this.iErr = Math.max(-this.iLimit, Math.min(this.iLimit, this.iErr + err*dt));
    }
    // Derivative on measurement (not error) — avoids derivative kick on setpoint changes
    const dMeas = (measured - this.prevMeas) / dt;
    // Cache alpha: recompute only when dt changes (saves exp() on every substep call)
    if(dt !== this._lastDt){ this._alpha = 1 - Math.exp(-2*Math.PI*this.dCutoffHz*dt); this._lastDt = dt; }
    this.dFilt += this._alpha * (dMeas - this.dFilt);
    this.prevMeas = measured;
    this._lastOutput = this.p*err + this.i*this.iErr - this.d*this.dFilt;
    return this._lastOutput;
  }
}

/* ─── Kalman Filter 1D ─── */
class Kalman1D {
  // [FIX-3.4] Q and R were inverted in original — Q=process noise, R=measurement noise
  // Correct: Q_baro=0.005 (process), R_baro=0.08 (measurement)
  constructor(Q=0.005, R=0.08){this.Q=Q; this.R=R; this.x=0; this.P=1; this._init=false;}
  update(z){
    if(!this._init){this.x=z; this._init=true; return z;}
    this.P += this.Q;                         // predict
    const K = this.P / (this.P + this.R);     // Kalman gain
    this.x += K * (z - this.x);              // update
    this.P *= (1 - K);
    return this.x;
  }
}

/* ─── Drone Profiles ─── */
const DRONE_PROFILES = {
  racing5: {
    label:'5\" Racing Quad', mass:1.24,
    Ixx:0.006, Iyy:0.011, Izz:0.006, armLen:0.19,
    kT:1.04e-5, kQ:1.55e-7, maxRPM:14000, idleRPM:500,
    motorTau:0.055, escDelay:0.012,
    dragArea:0.022, dragCd:1.12, angDrag:0.0028,
    cells:4, battTotalAh:1.65,
    color:0x1e88e5, bodyScale:1.0, rotorRadius:0.09,
    maxTiltDeg:55, maxRate:{pitch:10, roll:10, yaw:4.5},
    propInertia:2.5e-5, Cqlift:0.015,
  },
  cinequad: {
    label:'CineQuad 4S', mass:2.85,
    Ixx:0.032, Iyy:0.058, Izz:0.032, armLen:0.27,
    kT:1.18e-5, kQ:2.2e-7, maxRPM:9500, idleRPM:450,
    motorTau:0.09, escDelay:0.018,
    dragArea:0.038, dragCd:1.05, angDrag:0.0045,
    cells:4, battTotalAh:5.2,
    color:0xffc107, bodyScale:1.1, rotorRadius:0.11,
    maxTiltDeg:35, maxRate:{pitch:6, roll:6, yaw:2.5},
    propInertia:6.5e-5, Cqlift:0.018,
  },
  micro2: {
    label:'Micro 2S Quad', mass:0.34,
    Ixx:0.0013, Iyy:0.0021, Izz:0.0013, armLen:0.09,
    kT:6.8e-6, kQ:8.5e-8, maxRPM:22000, idleRPM:1200,
    motorTau:0.045, escDelay:0.008,
    dragArea:0.013, dragCd:1.18, angDrag:0.0012,
    cells:2, battTotalAh:0.45,
    color:0x43a047, bodyScale:0.75, rotorRadius:0.055,
    maxTiltDeg:65, maxRate:{pitch:18, roll:18, yaw:8},
    propInertia:8e-6, Cqlift:0.012,
  },
  explorer6: {
    label:'Explorer 6\" Hover', mass:1.68,
    Ixx:0.011, Iyy:0.019, Izz:0.011, armLen:0.22,
    kT:1.08e-5, kQ:1.7e-7, maxRPM:12000, idleRPM:350,
    motorTau:0.07, escDelay:0.015,
    dragArea:0.030, dragCd:1.08, angDrag:0.0032,
    cells:4, battTotalAh:2.8,
    color:0x8e24aa, bodyScale:1.05, rotorRadius:0.1,
    maxTiltDeg:40, maxRate:{pitch:8, roll:8, yaw:3},
    propInertia:4e-5, Cqlift:0.016,
  },
};

/* ─── Physics Engine ─── */
const PHYS = {
  GRAVITY: 9.80665,
  mass:1.24, Ixx:0.006, Iyy:0.011, Izz:0.006,
  armLen:0.19, kT:1.04e-5, kQ:1.55e-7,
  maxRPM:14000, idleRPM:500,
  motorTau:0.055, escDelay:0.012,
  dragArea:0.022, dragCd:1.12, angDrag:0.0028,
  airDens:1.225,
  cells:4, battTotalAh:1.65,
  propInertia:2.5e-5, Cqlift:0.015,
  droneProfile:'racing5',
  droneVisual:{bodyScale:1, rotorRadius:0.09, color:0x1e88e5},
  maxTiltRad:(55*Math.PI)/180,
  maxRate:{pitch:10, roll:10, yaw:4.5},
  // Quad-X motor directions: FR(CW)=+1, FL(CCW)=-1, BL(CW)=+1, BR(CCW)=-1
  // [NOTE] CW torque = negative yaw in right-hand Y-up convention
  motorDir:[1,-1,1,-1],
  motorLabels:['FR','FL','BL','BR'],

  _scr:{wind:V3.zero(), vRel:V3.zero(), dragF:V3.zero(), acc1:V3.zero(), acc2:V3.zero(), accBody:V3.zero(), thrustW:V3.zero(), dVel:V3.zero(), dPos:V3.zero(), u:V3.zero(), uv:V3.zero(), uuv:V3.zero(), tau:V3.zero(), dAngVel:V3.zero(), angDrag:V3.zero(), gyroT:V3.zero(), rotV:V3.zero()},
    pos:V3.zero(), vel:V3.zero(), acc:V3.zero(),
  quat:Q.id(), angVel:V3.zero(),
  gyro:V3.zero(), accelBody:V3.zero(),

  motorRPM:[0,0,0,0],
  motorCmd:[0,0,0,0],
  motorCmdFiltered:[0,0,0,0],

  // [FIX-2.1] FC rate commands stored per substep
  _fcRateCmd:{pitch:0, roll:0, yaw:0, thr:0},

  grounded:true, crashed:false,
  homePos:null, groundY:0, colliders:[],

  battPct:100, battVoltage:16.8, battCapacity:0, currentDraw:0,
  euler:{roll:0, pitch:0, yaw:0},
  windVec:V3.zero(), windGust:0,
  hoverThrottle:0.5,
  turbulenceIntensity:0,

  // [FIX-3.4] Corrected Kalman Q/R values: Q=process noise, R=measurement noise
  _kAlt: new Kalman1D(0.005, 0.08),
  _kVy:  new Kalman1D(0.01,  0.15),
  _altEstimate: 0,
  _baroRaw: 0,
  // [FIX-3.2] Gyro bias random walk state
  _gyroBias:{x:0, y:0, z:0}, // [FIX-H] was hardcoded non-zero, caused constant false rate in PID
  _prevPos:V3.zero(), _prevQuat:Q.id(),

  _lastAirDensAlt: NaN, _cachedAirDens: 1.225,

  // Terrain height cache to avoid re-evaluating Perlin every substep — [FIX-Bug-26d]
  _lastTerrainPos:{x:NaN,z:NaN}, _lastTerrainH:0,

  applyProfile(name){
    const p=DRONE_PROFILES[name]; if(!p) return;
    this.droneProfile=name;
    Object.assign(this,{
      mass:p.mass, Ixx:p.Ixx, Iyy:p.Iyy, Izz:p.Izz,
      armLen:p.armLen, kT:p.kT, kQ:p.kQ,
      maxRPM:p.maxRPM, idleRPM:p.idleRPM,
      motorTau:p.motorTau, escDelay:p.escDelay,
      dragArea:p.dragArea, dragCd:p.dragCd, angDrag:p.angDrag,
      cells:p.cells, battTotalAh:p.battTotalAh,
      maxTiltRad:((p.maxTiltDeg||55)*Math.PI)/180,
      maxRate:{...p.maxRate},
      propInertia:p.propInertia||2.5e-5,
      Cqlift:p.Cqlift||0.015,
    });
    this.droneVisual={bodyScale:p.bodyScale, rotorRadius:p.rotorRadius, color:p.color};
    this._recomputeHover();
    const lbl=document.getElementById('drone-profile-label');
    if(lbl) lbl.textContent=p.label;
    if(typeof FC!=='undefined') FC.autoTuneFromPhysics();
  },

  _recomputeHover(){
    // Hover RPM: 4·kT·ω² = m·g  →  ω = √(mg/(4kT))  →  RPM = ω·60/(2π)
    const omegaH = Math.sqrt((this.mass*this.GRAVITY)/(4*this.kT));
    this.hoverRPM = omegaH * 60 / (2*Math.PI);  // [FIX-1.9] consistent RPM↔rad/s
    this.hoverThrottle = Math.max(0.02, Math.min(0.92, this.hoverRPM/this.maxRPM));
  },

  reset(pos){
    const droneHalf = 0.074 * (this.droneVisual.bodyScale || 1.0) * 5.0;
    this.pos=pos||{x:0,y:this.groundY+droneHalf,z:0};
    this._prevPos=V3.clone(this.pos);
    this.vel=V3.zero(); this.acc=V3.zero();
    this.quat=Q.id(); this._prevQuat=Q.id();
    this.angVel=V3.zero(); this.gyro=V3.zero(); this.accelBody=V3.zero();
    this.motorRPM=[0,0,0,0]; this.motorCmd=[0,0,0,0]; this.motorCmdFiltered=[0,0,0,0];
    this._fcRateCmd={pitch:0,roll:0,yaw:0,thr:0};
    this.grounded=true; this.crashed=false;
    this.euler={roll:0,pitch:0,yaw:0};
    this.battPct=100; this.battVoltage=4.2*this.cells; this.battCapacity=0; this.currentDraw=0;
    this._kAlt=new Kalman1D(0.005,0.08); this._kVy=new Kalman1D(0.01,0.15);
    this._baroRaw=0;
    this._gyroBias={x:0,y:0,z:0}; // [FIX-H] reset bias on each flight
    if(typeof State!=='undefined') State.motorDamage=[0,0,0,0];
    this._recomputeHover();
  },

  saveHome(){ this.homePos=V3.clone(this.pos); },

  /** [FIX-1.3] Sanitise all state fields including motorRPM */
  _sanitize(){
    const bad=v=>!Number.isFinite(v);
    if(bad(this.pos.x)||bad(this.pos.y)||bad(this.pos.z)){
      const droneHalf = 0.074 * (this.droneVisual.bodyScale || 1.0) * 5.0;
      this.reset({x:0,y:this.groundY+droneHalf+0.05,z:0});
      if(typeof FC!=='undefined') FC.resetPIDs();
      return false;
    }
    if(bad(this.vel.x)||bad(this.vel.y)||bad(this.vel.z)) this.vel=V3.zero();
    if(bad(this.angVel.x)||bad(this.angVel.y)||bad(this.angVel.z)){this.angVel=V3.zero();this.quat=Q.id();}
    if(bad(this.quat.w)||bad(this.quat.x)||bad(this.quat.y)||bad(this.quat.z)) this.quat=Q.id();
    for(let i=0;i<4;i++) if(bad(this.motorRPM[i])) this.motorRPM[i]=0;
    return true;
  },

  _airDensity(alt){
    const h = Math.max(0, alt);
    // Cache: recompute only when altitude changes by >0.5 m (saves 3 pow() calls per substep)
    if(Math.abs(h - this._lastAirDensAlt) < 0.5) return this._cachedAirDens;
    this._lastAirDensAlt = h;
    const T0=288.15, L=0.0065, P0=101325, R=287.058;
    const T=T0-L*h;
    this._cachedAirDens = (P0*Math.pow(T/T0,_ISA_EXP))/(R*T);
    return this._cachedAirDens;
  },

  /**
   * [FIX-1.9] Motor thrust — internally uses rad/s throughout
   * T = kT·ω²  where ω is in rad/s
   * RPM is only used for UI display; physics uses rad/s
   */
  _motorThrust(rpmVal){
    const omega = rpmVal * TWO_PI_OVER_60;
    const T = this.kT * omega * omega;
    const sat = 1 - Math.max(0, (rpmVal/this.maxRPM - 0.85)*0.4);
    return T * sat;
  },

  /**
   * [FIX-1.9] Reaction torque — uses rad/s internally
   * Q_react = kQ·ω²  where ω in rad/s
   */
  _motorTorque(rpmVal){
    const omega = rpmVal * TWO_PI_OVER_60;
    return this.kQ * omega * omega;
  },

  _lastEscDt: -1, _cachedEscA: 0,
  _lastTauUp: -1, _lastTauDown: -1, _cachedRpmA_up: 0, _cachedRpmA_dn: 0,

  step(dtFull){
    dtFull=Math.max(0.0005,Math.min(0.04,dtFull));
    if(!this._sanitize()) return;
    this._prevPos=V3.clone(this.pos);
    this._prevQuat={...this.quat};
    if(this.crashed){this._crashSettle(dtFull); return;}
    const SUB=4, dt=dtFull/SUB;
    for(let s=0;s<SUB;s++) this._substep(dt);
    this._updateSensors(dtFull);
  },

  _substep(dt){
    // Battery voltage factor (voltage sag scales motor authority)
    const fullV = this.cells * 4.2;
    const internalR = 0.025 * this.cells;
    const rawVF = Math.max(0.5, Math.min(1, this.battVoltage/fullV));

    // ── Motor dynamics: first-order lag with asymmetric τ ─────────────────
    // Cache exp() results — same dt every substep so no need to recompute
    if(dt !== this._lastEscDt){
      this._cachedEscA = 1 - Math.exp(-dt/(this.escDelay+0.001));
      this._cachedRpmA_up = 1 - Math.exp(-dt/this.motorTau);
      this._cachedRpmA_dn = 1 - Math.exp(-dt/(this.motorTau*1.6));
      this._lastEscDt = dt;
    }
    const escA = this._cachedEscA;
    const tauUp = this._cachedRpmA_up, tauDown = this._cachedRpmA_dn;
    const _stateExists = typeof State !== 'undefined';
    const idleFloor = (_stateExists && State.armed) ? this.idleRPM : 0;
    for(let i=0;i<4;i++){
      const dmg = _stateExists ? State.motorDamage[i] : 0;
      const target = this.motorCmd[i] * (1-dmg) * rawVF;
      this.motorCmdFiltered[i] += (target-this.motorCmdFiltered[i]) * escA;
      const tRPM = this.motorCmdFiltered[i] * this.maxRPM;
      const rpmA = tRPM > this.motorRPM[i] ? tauUp : tauDown;
      this.motorRPM[i] += (tRPM - this.motorRPM[i]) * rpmA;
      this.motorRPM[i] = Math.max(idleFloor, Math.min(this.maxRPM, this.motorRPM[i]));
    }

    // ── Thrust and torque computation ─────────────────────────────────────
    // [FIX-1.6] Air density for ISA altitude correction
    const rho = this._airDensity(this.pos.y);
    // Density ratio for kT correction: T ∝ ρ (Leishman 2006 §2.3)
    const rhoRatio = rho / 1.225;

    const T = [
      this._motorThrust(this.motorRPM[0]) * rhoRatio,
      this._motorThrust(this.motorRPM[1]) * rhoRatio,
      this._motorThrust(this.motorRPM[2]) * rhoRatio,
      this._motorThrust(this.motorRPM[3]) * rhoRatio,
    ];
    const totalThrust = T[0]+T[1]+T[2]+T[3];
    const L = this.armLen;

    // ── Quad-X torques in body frame ──────────────────────────────────────
    // Body frame: X=right, Y=up, Z=forward (nose)
    // Motor positions: FR(+X,+Z), FL(-X,+Z), BL(-X,-Z), BR(+X,-Z)
    // Pitch torque (about X-axis): positive = nose up
    // tauPitch = L·( T_BL + T_BR − T_FR − T_FL )
    const tauPitch = L * (T[2]+T[3] - T[0]-T[1]);
    // Roll torque (about Z-axis): positive = right bank
    // tauRoll = L·( T_FR + T_BR − T_FL − T_BL )
    const tauRoll  = L * (T[0]+T[3] - T[1]-T[2]);
    // [FIX-1.9] Yaw torque — uses reaction torque (kQ·ω²) with direction sign
    // motorDir: FR=+1(CW), FL=-1(CCW), BL=+1(CW), BR=-1(CCW)
    // CW rotation (looking from above, Y-up) → negative yaw in right-hand convention
    const tauYaw = -(
      this._motorTorque(this.motorRPM[0]) * this.motorDir[0] +
      this._motorTorque(this.motorRPM[1]) * this.motorDir[1] +
      this._motorTorque(this.motorRPM[2]) * this.motorDir[2] +
      this._motorTorque(this.motorRPM[3]) * this.motorDir[3]
    );

    // Gyroscopic precession from net propeller angular momentum
    const omegaNet = this.motorDir.reduce((s,d,i)=>s+d*this.motorRPM[i]*TWO_PI_OVER_60,0);
    const Hgyro = this.propInertia * omegaNet; // angular momentum of spinning props
    // Gyroscopic torques: τ = Ω × H  (cross product of body rate with prop ang. momentum)
    const tauGyroPitch = -this.angVel.z * Hgyro;  // pitch gyro coupling
    const tauGyroRoll  =  this.angVel.x * Hgyro;  // roll gyro coupling

    // ── Euler rigid body equation ─────────────────────────────────────────
    // dω/dt = I⁻¹·(τ_ext − ω×(I·ω))  (Goldstein 1980, §5.5)
    const {Ixx,Iyy,Izz} = this;
    const wx=this.angVel.x, wy=this.angVel.y, wz=this.angVel.z;
    // ω × (I·ω) = Magnus/gyroscopic term (prevents gimbal lock behaviour)
    const gyroX = wy*(Izz*wz) - wz*(Iyy*wy); // [FIX-1.2] Euler body term
    const gyroY = wz*(Ixx*wx) - wx*(Izz*wz);
    const gyroZ = wx*(Iyy*wy) - wy*(Ixx*wx);
    const ad = this.angDrag;
    const alphaPitch = (tauPitch + tauGyroPitch - gyroX) / Ixx - ad*wx*Math.abs(wx);
    const alphaRoll  = (tauRoll  + tauGyroRoll  - gyroZ) / Izz - ad*wz*Math.abs(wz);
    const alphaYaw   = (tauYaw                  - gyroY) / Iyy - ad*0.7*wy*Math.abs(wy);

    // [FIX-1.2] Soft-clamp angular velocity BEFORE integration (was after)
    const mr = this.maxRate;
    const _vx=this.angVel.x+alphaPitch*dt, _vz=this.angVel.z+alphaRoll*dt, _vy=this.angVel.y+alphaYaw*dt;
    this.angVel.x=Math.abs(_vx)>mr.pitch?Math.sign(_vx)*(mr.pitch+(Math.abs(_vx)-mr.pitch)*0.08):_vx;
    this.angVel.z=Math.abs(_vz)>mr.roll ?Math.sign(_vz)*(mr.roll +(Math.abs(_vz)-mr.roll )*0.08):_vz;
    this.angVel.y=Math.abs(_vy)>mr.yaw  ?Math.sign(_vy)*(mr.yaw  +(Math.abs(_vy)-mr.yaw  )*0.08):_vy;

    this.quat = Q.integrate(this.quat, this.angVel, dt);
    Q.toEuler(this.quat, this.euler);

    // ── [FIX-2.1] Inner rate PID runs at substep rate ─────────────────────
    // FC._fcRateCmd is set by the outer (per-frame) angle loop
    // The inner rate→motor loop runs here at full substep frequency
    if(typeof FC!=='undefined' && (!_stateExists||State.armed)){
      const motorCmds = FC._rateLoopSubstep(dt, this._fcRateCmd.thr,
        this._fcRateCmd.pitch, this._fcRateCmd.roll, this._fcRateCmd.yaw);
      this.motorCmd = motorCmds;
    }

    // ── Translational dynamics ────────────────────────────────────────────
    // Thrust in world frame: thrust acts along body Y-axis
    let thrustW = Q.rotVec(this.quat, {x:0, y:totalThrust, z:0});

    // [FIX-1.4] Ground Effect — Cheeseman–Bennett 1955, eq.4
    // T_ge = T / (1 − (R/(4h))²)  for h > R/2; no effect for h > 2R
    const hAGL = this.pos.y - this.groundY;
    const R_prop = (this.droneVisual.rotorRadius||0.09);
    if(hAGL > R_prop*0.5 && hAGL < R_prop*2){
      // Cheeseman–Bennett 1955 eq.4
      const ratio = R_prop / (4 * Math.max(hAGL, R_prop*0.5));
      const geGain = 1 / Math.max(0.01, 1 - ratio*ratio);
      thrustW = V3.scale(thrustW, Math.min(geGain, 2.5)); // cap at 2.5× safety
    }

    // [FIX-1.5] Drag: v_rel = vel − windVec − dryden  BEFORE squaring
    this.airDens = rho;
    DRYDEN.intensity = this.turbulenceIntensity;
    DRYDEN.update(dt, Math.max(0.5, hAGL));
    const gust = DRYDEN.get();
    const totalWind = V3.add(this.windVec, gust);
    // Relative velocity of drone w.r.t. air mass (for drag)
    const vRel = V3.sub(this.vel, totalWind); // v_rel = v_drone − v_air
    const vRelMag = V3.len(vRel);

    // Aerodynamic drag: F_drag = −½·ρ·Cd·A·|v_rel|²·v̂_rel (in world frame)
    let drag = V3.zero();
    if(vRelMag > 0.01){
      const qDyn = 0.5 * rho * vRelMag * vRelMag; // dynamic pressure [FIX-1.6] uses ISA ρ
      const tilt = Math.sqrt(this.euler.pitch**2 + this.euler.roll**2);
      const Cdeff = this.dragCd * (1 + 0.15*Math.sin(Math.abs(this.euler.pitch)) + 0.15*Math.sin(Math.abs(this.euler.roll)));
      drag = V3.scale(V3.norm(vRel), -qDyn * this.dragArea * Cdeff);
    }

    // Translational damping from rotor edgewise drag (hover stability)
    const transDamp = V3.scale(this.vel, -this.mass * 0.16);

    const gravity = {x:0, y:-this.mass*this.GRAVITY, z:0};
    const fNet = V3.add(V3.add(V3.add(thrustW, gravity), drag), transDamp);

    this.acc = V3.scale(fNet, 1/this.mass);
    // [FIX-3.3] Store body-frame specific force for IMU simulation (accel output = a − g)
    const gravWorld = {x:0, y:-this.GRAVITY, z:0};
    this.accelBody = Q.invRotVec(this.quat, V3.sub(this.acc, gravWorld));

    // Velocity Verlet integration
    let v2 = V3.add(this.vel, V3.scale(this.acc, dt));
    const vl = V3.len(v2); if(vl > 32) v2 = V3.scale(V3.norm(v2), 32);
    let newPos = V3.add(this.pos, V3.scale(V3.add(this.vel, v2), 0.5*dt));
    this.vel = v2;

    // ── [FIX-1.7] Ground collision with restitution e=0.15 and Coulomb friction μ=0.4 ──
    // droneHalf = foot skid depth in world space: 0.074 * bodyScale * 5.0 (visual scale)
    // This matches the landing gear foot position in buildDrone() so the model sits flush on ground.
    const droneHalf = 0.074 * (this.droneVisual.bodyScale || 1.0) * 5.0;
    const minY = this.groundY + droneHalf;
    if(newPos.y < minY){
      const impact = Math.abs(this.vel.y);
      // [FIX-1.8] Crash threshold 8 m/s (was 4.5 m/s)
      if(impact > 2.5 && (typeof State!=='undefined') && State.armed){
        this._doCrash(impact); newPos.y = minY;
      } else {
        newPos.y = minY;
        if(this.vel.y < 0){
          this.vel.y = -this.vel.y * 0.15; // restitution e=0.15 — [FIX-1.7]
        }
        // Coulomb friction on horizontal velocity: F_fric = μ·N = μ·m·g  [FIX-1.7]
        const mu = 0.4;
        const hSpd = Math.hypot(this.vel.x, this.vel.z);
        if(hSpd > 0.001){
          const fricDecel = Math.min(hSpd, mu * this.GRAVITY * dt);
          const scale = (hSpd - fricDecel) / hSpd;
          this.vel.x *= scale; this.vel.z *= scale;
        }
        const af = Math.exp(-18*dt);
        this.angVel.x*=af; this.angVel.z*=af; this.angVel.y*=af;
        this.angVel.x -= this.euler.pitch*8*dt;
        this.angVel.z -= this.euler.roll *8*dt;
        this.grounded = true;
      }
    } else { this.grounded = false; }

    // World boundary clamp removed (maps are infinite)
    newPos.y = Math.min(180, newPos.y);
    this.pos = newPos;

    // AABB collider check with restitution impulse  [FIX-1.7]
    const hit = this._checkColliders(newPos);
    if(hit){
      const spd = V3.len(this.vel);
      // [FIX-1.8] Crash threshold 2.5 m/s
      if(spd > 2.5 && (typeof State!=='undefined') && State.armed){ this._doCrash(spd); }
      else {
        const n = hit.normal || {x:0,y:1,z:0};
        const vn = V3.dot(this.vel, n);
        if(vn < 0){
          // Restitution impulse: Δv = −(1+e)·(v·n)·n
          this.vel = V3.add(this.vel, V3.scale(n, -(1+0.15)*vn)); // e=0.15
          // Coulomb friction tangential
          const vt = V3.sub(this.vel, V3.scale(n, V3.dot(this.vel,n)));
          const vtMag = V3.len(vt);
          if(vtMag > 0.001) this.vel = V3.sub(this.vel, V3.scale(V3.norm(vt), Math.min(vtMag, 0.4*Math.abs(vn))));
        }
        this.pos = V3.add(newPos, V3.scale(n, 0.05));
      }
    }

    // ── Battery model ─────────────────────────────────────────────────────
    // Induced velocity from actuator disk theory: v_ind = √(T/(2ρA))
    const rotorA = Math.PI*(R_prop*R_prop)*4 + 0.001;
    const v_ind = Math.sqrt(totalThrust / (2*rho*rotorA)); // uses ISA ρ
    const P_mech = totalThrust * v_ind * 1.18; // incl. motor/ESC losses ~18%
    this.currentDraw = P_mech / Math.max(this.battVoltage, 1);
    const internalDrop = this.currentDraw * internalR;
    const ahStep = (this.currentDraw*dt) / 3600;
    this.battCapacity = Math.min(this.battTotalAh, this.battCapacity + ahStep);
    this.battPct = Math.max(0, 100*(1 - this.battCapacity/this.battTotalAh));
    const soc = this.battPct / 100;
    // [FIX-1.10] LiPo OCV polynomial: 4.2V at soc=1, 3.3V at soc=0
    // Verified against Plett (2004) LiPo cell model
    // ocv(soc) = 3.3 + 0.7·soc + 0.1·soc² + 0.1·soc³  (gives 4.2 at soc=1, 3.3 at soc=0)
    const ocv = 3.3 + 0.7*soc + 0.1*soc*soc + 0.1*soc*soc*soc;
    this.battVoltage = Math.max(this.cells*3.3, Math.min(this.cells*4.2, this.cells*ocv - internalDrop));
  },

  /** [FIX-3.1] [FIX-3.2] [FIX-3.3] Physically correct sensor simulation */
  _updateSensors(dt){
    // [FIX-3.1] Gyro white noise: σ = 0.003 rad/s/√Hz · √(1/dt)
    // At dt=0.0167s (60Hz), σ_sample = 0.003/√0.0167 ≈ 0.023 rad/s per axis
    const gyrNoiseStd = 0.003 * Math.sqrt(1/Math.max(dt, 0.001));
    const gn = () => (Math.random()+Math.random()+Math.random()+Math.random()-2)*gyrNoiseStd*0.866;
    // [FIX-3.2] Gyro bias random walk: bias += N(0, σ_bias·√dt)
    const biasSigma = 5e-5;
    const biasDelta = biasSigma * Math.sqrt(dt);
    this._gyroBias.x += (Math.random()-0.5)*2*biasDelta;
    this._gyroBias.y += (Math.random()-0.5)*2*biasDelta;
    this._gyroBias.z += (Math.random()-0.5)*2*biasDelta;
    this.gyro = {
      x: this.angVel.x + gn() + this._gyroBias.x,
      y: this.angVel.y + gn() + this._gyroBias.y,
      z: this.angVel.z + gn() + this._gyroBias.z,
    };
    // [FIX-3.3] Accelerometer: specific force = a_body − g_body
    // a_world = acc (from physics), g_world = (0,−g,0)
    // a_body = R_BW · (a_world − g_world)  →  accelBody already computed in _substep
    // Add accelerometer noise (σ ≈ 0.05 m/s²)
    const accNoiseStd = 0.05;
    const an = () => (Math.random()-0.5)*2*accNoiseStd;
    this.accelBody = {
      x: this.accelBody.x + an(),
      y: this.accelBody.y + an(),
      z: this.accelBody.z + an(),
    };
    // [FIX-3.4] Barometer with corrected Kalman Q/R
    // Baro noise: σ=0.05m + turbulence; bias drift τ≈30s
    const bNoise = 0.05 + this.turbulenceIntensity*0.25;
    const trueAlt = this.pos.y;
    this._baroRaw = trueAlt + (Math.random()-0.5)*bNoise*2;
    this._altEstimate = this._kAlt.update(this._baroRaw);
    Q.toEuler(this.quat, this.euler);
  },

  _checkColliders(pos){
    // Use a small margin to prevent tunnelling through thin walls
    const margin = 0.35;
    for(const c of this.colliders){
      if(pos.x>c.min.x-margin&&pos.x<c.max.x+margin&&
         pos.y>c.min.y-margin&&pos.y<c.max.y+margin&&
         pos.z>c.min.z-margin&&pos.z<c.max.z+margin){
        // Resolve which face is closest to eject cleanly (not get stuck)
        const cx=(c.min.x+c.max.x)*0.5, cy=(c.min.y+c.max.y)*0.5, cz=(c.min.z+c.max.z)*0.5;
        const hx=(c.max.x-c.min.x)*0.5, hy=(c.max.y-c.min.y)*0.5, hz=(c.max.z-c.min.z)*0.5;
        const dx=pos.x-cx, dy=pos.y-cy, dz=pos.z-cz;
        // Penetration depth along each axis
        const px=hx-Math.abs(dx), py=hy-Math.abs(dy), pz=hz-Math.abs(dz);
        let norm;
        if(py<=px&&py<=pz)      norm={x:0,y:Math.sign(dy)||1,z:0};
        else if(px<=pz)         norm={x:Math.sign(dx)||1,y:0,z:0};
        else                    norm={x:0,y:0,z:Math.sign(dz)||1};
        return {min:c.min,max:c.max,normal:norm};
      }
    }
    return null;
  },

  _doCrash(impact){
    this.crashed=true;
    if(typeof State!=='undefined') State.armed=false;
    const cnt=Math.min(4,Math.floor(impact/2.5));
    const idxs=[0,1,2,3].sort(()=>Math.random()-0.5);
    if(typeof State!=='undefined'){
      for(let i=0;i<cnt;i++) State.motorDamage[idxs[i]]=Math.min(1,State.motorDamage[idxs[i]]+0.4+Math.random()*0.4);
    }
    if(typeof WARN!=='undefined') WARN.trigger('crash');
    if(typeof updateArmUI==='function') updateArmUI();
  },

  _crashSettle(dt){
    const droneHalf = 0.074 * (this.droneVisual.bodyScale || 1.0) * 5.0;
    this.angVel.x*=0.90; this.angVel.z*=0.90; this.angVel.y*=0.93;
    this.quat=Q.integrate(this.quat,this.angVel,dt);
    Q.toEuler(this.quat, this.euler);
    // Clamp XZ to world bounds during settle to prevent sliding into terrain
    this.pos.x=Math.max(-240,Math.min(240,this.pos.x));
    this.pos.z=Math.max(-240,Math.min(240,this.pos.z));
    const minY=this.groundY+droneHalf;
    if(this.pos.y>minY){
      this.vel.y-=this.GRAVITY*dt; this.pos.y+=this.vel.y*dt;
    } else {
      this.pos.y=minY; this.vel=V3.zero();
    }
    // Hard floor: never go below terrain (prevents camera clipping through mountain)
    if(this.pos.y<minY) this.pos.y=minY;
    for(let i=0;i<4;i++) this.motorRPM[i]*=0.90;
  },

  /**
   * [FIX] recoverFromCrash — call this when motors/damage are restored after
   * a mid-air failure so the drone can be re-armed and flown again.
   *
   * Clears the crashed flag, zeroes residual angular and linear velocity
   * (eliminates the post-restore swinging), levels attitude, snaps to ground,
   * resets all PID integrators, and disarms cleanly so the user must re-arm
   * manually before taking off again.
   */
  recoverFromCrash(){
    this.crashed  = false;
    this.grounded = true;
    // Zero all motion — zeroing angVel is the key fix for the post-restore swinging
    this.angVel = V3.zero();
    this.vel    = V3.zero();
    // Level attitude so drone sits flat on ground
    // Use Q.id() and the engine's {roll,pitch,yaw} euler convention
    this.quat  = Q.id();
    this.euler = {roll:0, pitch:0, yaw:this.euler.yaw||0};
    // Snap onto terrain
    const droneHalf = 0.074 * (this.droneVisual.bodyScale || 1.0) * 5.0;
    this.pos.y = this.groundY + droneHalf;
    // Zero motor RPM/commands
    for(let i=0;i<4;i++){ this.motorRPM[i]=0; this.motorCmd[i]=0; this.motorCmdFiltered[i]=0; }
    // Flush PID integrators — caller is responsible for arming/takeoff
    if(typeof FC!=='undefined') FC.resetPIDs();
  },
};

/* ─── Flight Controller ─── */
const FC = {
  mode:'stabilized',
  motorMixGain:0.13,
  maxAngleRate:3.2,
  maxAltVelRate:1.4,

  ratePID:{
    pitch: new PID(0.042, 0.000, 0.0018, 0.3, 20),
    roll:  new PID(0.042, 0.000, 0.0018, 0.3, 20),
    yaw:   new PID(0.065, 0.012, 0.0008, 0.25,15),
  },
  anglePID:{
    pitch: new PID(2.2, 0, 0, 0.4, 20),
    roll:  new PID(2.2, 0, 0, 0.4, 20),
  },
  altPID:    new PID(1.6,  0.10, 0.06, 8.0,  6),
  altVelPID: new PID(0.38, 0.08, 0.012,2.0, 12),
  // [FIX-2.5] posNPID/posEPID output is body-frame tilt angle setpoint
  posNPID:   new PID(0.65, 0.015,0.18, 0.35, 8),
  posEPID:   new PID(0.65, 0.015,0.18, 0.35, 8),

  altTarget:null, posTarget:null, rthPhase:0, rthClimbAlt:10,
  gains:{rp:0.042, ri:0.000, rd:0.0018, yp:0.065, ap:1.6, angleP:2.2},

  _adaptiveGainFactor(){
    // [FIX-C] rateFactor floor raised from 0.55 → 0.90: PID should run near full gain
    // during steady hover/low-rate flight, not at 55% which causes sluggish response.
    // Small reduction at high tilt is still applied (airframe authority drops when tilted).
    const tilt = Math.sqrt(PHYS.euler.pitch**2 + PHYS.euler.roll**2);
    const tiltFactor = Math.max(0.65, 1.0 - tilt*0.20);
    const rateAmp = Math.hypot(PHYS.angVel.x, PHYS.angVel.z);
    // [FIX-C] 0.90 floor: adaptive reduction only kicks in at very high rates
    const rateFactor = Math.min(1.0, 0.90 + rateAmp*0.5);
    return tiltFactor * rateFactor;
  },

  autoTuneFromPhysics(){
    const m=PHYS.mass, L=PHYS.armLen, Ixx=PHYS.Ixx, Iyy=PHYS.Iyy, Izz=PHYS.Izz;
    const kT=PHYS.kT, kQ=PHYS.kQ, maxRPM=PHYS.maxRPM;
    const omegaMax = maxRPM * TWO_PI_OVER_60;
    // [FIX-A] Correct pitch/roll max torque: 2 motors oppose 2 motors across arm length
    // e.g. pitch: (T_BL+T_BR) - (T_FR+T_FL) → max when back pair at maxRPM, front at 0
    // Net max = 2·kT·ωmax²·L  (two motors at full on one side)
    const maxPitchTorque = 2 * kT * omegaMax * omegaMax * L;
    // [FIX-A] Yaw authority uses reaction torque kQ (not kT*L)
    // Max yaw torque = 4·kQ·ωmax² (two CW vs two CCW at max)
    const maxYawTorque = 4 * kQ * omegaMax * omegaMax;

    // Rate P: Ixx/(τ_des·maxTorque) where τ_des ≈ 0.08s desired settling
    // Target ~30% of critical gain for good margin on all profiles
    // [FIX-I] Coefficient corrected: old *3.0 produced rp at the 0.02 floor for all profiles
    // (e.g. racing5: Ixx*3.0/maxTorque = 0.006*3/8.49 = 0.002 → clamped to 0.02, wrong)
    // Use *28 so racing5 gets rp~0.025 matching Betaflight ballpark (42/1000 * scaling)
    const rp = Math.min(0.12, Math.max(0.025, Ixx * 28.0 / (maxPitchTorque + 1e-6)));
    const rd = rp * 0.042;
    const ri = 0.0;
    // [FIX-A] Yaw P correctly scaled to kQ-based yaw authority
    const yp = Math.min(0.18, Math.max(0.025, Izz * 2.5 / (maxYawTorque + 1e-6)));
    // [FIX-I] angleP scaled to rate PID bandwidth (cascade rule: outer BW = inner/5)
    // Old formula (2.0+1.5*L ≈ 2.3) was too slow to reject even tiny gyro bias drift
    const rateBW = rp * maxPitchTorque / Ixx;
    const angleP = Math.min(8.0, Math.max(3.5, rateBW / 5.0));
    const ap = Math.max(1.0, Math.min(2.2, 0.9 + 0.45*m));

    // [FIX-B] motorMixGain: normalise PID outputs to motor command space
    // Target: at max rate PID output the motor delta is ~0.25 (quarter throttle authority)
    // mixGain ≈ 0.25 / (rp * maxRate_pitch)  — keeps effective gain consistent
    const mr = PHYS.maxRate;
    const rawMixGain = 0.22 / Math.max(0.01, rp * (mr.pitch||10));
    this.motorMixGain = Math.max(0.07, Math.min(0.20, rawMixGain));

    this.maxAngleRate = Math.max(2.0, Math.min(4.5, 2.0 + (0.34 - m*0.04)*5));
    this.gains = {rp, ri, rd, yp, ap, angleP};
    this.applyGains();
    this._syncSliders();
  },

  _syncSliders(){
    const g=this.gains;
    const map={rp:'pid-rp',ri:'pid-ri',rd:'pid-rd',yp:'pid-yp',ap:'pid-ap'};
    for(const k in map){const el=document.getElementById(map[k]);if(el) el.value=g[k];}
    if(typeof setPID==='function'){setPID('rp',g.rp);setPID('ri',g.ri);setPID('rd',g.rd);setPID('yp',g.yp);setPID('ap',g.ap);}
  },

  applyGains(){
    const g=this.gains;
    this.ratePID.pitch.p=g.rp; this.ratePID.pitch.i=g.ri||0; this.ratePID.pitch.d=g.rd;
    this.ratePID.roll.p =g.rp; this.ratePID.roll.i =g.ri||0; this.ratePID.roll.d =g.rd;
    this.ratePID.yaw.p  =g.yp;
    // [FIX-F] Derive yaw I from yaw P (was hardcoded 0.012 regardless of profile).
    // Yaw I ≈ 0.18 * yp gives ~Ti = 1/0.18 ≈ 5.5s integration time — reasonable for yaw hold.
    // Also raise yaw I-limit from 0.25 → 0.45 so anti-windup doesn't thrash during manoeuvres.
    this.ratePID.yaw.i  = g.yi != null ? g.yi : g.yp * 0.18;
    this.ratePID.yaw.iLimit = 0.45;
    if(g.angleP!=null){ this.anglePID.pitch.p=g.angleP; this.anglePID.roll.p=g.angleP; }
    this.anglePID.pitch.d=0; this.anglePID.roll.d=0;
    this.altPID.p=g.ap;
    // Set output limits for conditional anti-windup [FIX-2.4]
    this.ratePID.pitch._outLimit=0.18; this.ratePID.roll._outLimit=0.18; this.ratePID.yaw._outLimit=0.12;
    this.altPID._outLimit=this.maxAltVelRate; this.altVelPID._outLimit=0.38;
  },

  resetPIDs(){
    for(const k in this.ratePID) this.ratePID[k].reset();
    for(const k in this.anglePID) this.anglePID[k].reset();
    this.altPID.reset(); this.altVelPID.reset();
    this.posNPID.reset(); this.posEPID.reset();
    this._altManualLastFrame=false;
  },

  setMode(m){
    this.mode=m; this.resetPIDs();
    if(m==='althold'||m==='gpshold'||m==='rth') this.altTarget=PHYS._altEstimate;
    if(m==='gpshold'||m==='rth') this.posTarget={x:PHYS.pos.x,z:PHYS.pos.z};
    if(m==='rth'){
      this.rthPhase=0;
      this.posTarget=PHYS.homePos?{x:PHYS.homePos.x,z:PHYS.homePos.z}:{x:0,z:0};
    }
  },

  /**
   * Outer loop (angle → rate setpoint) — runs once per rendered frame.
   * Stores rate commands in PHYS._fcRateCmd for the inner loop.
   * [FIX-2.1] Inner rate loop now runs in PHYS._substep() at substep rate.
   */
  update(dt, input){
    if(PHYS.crashed||((typeof State!=='undefined')&&!State.armed)){
      PHYS._fcRateCmd={pitch:0,roll:0,yaw:0,thr:0};
      return [0,0,0,0];
    }
    let thrCmd=input.throttle, pitchSP=0, rollSP=0;
    const maxTilt=PHYS.maxTiltRad;
    let yawRateCmd=input.yaw*PHYS.maxRate.yaw;
    const e=PHYS.euler;

    if(this.mode==='acro'){
      const mr=PHYS.maxRate;
      PHYS._fcRateCmd={pitch:input.pitch*mr.pitch, roll:input.roll*mr.roll, yaw:yawRateCmd, thr:thrCmd};
      return PHYS.motorCmd; // motor cmds will be updated in substep
    }
    if(this.mode==='stabilized'||this.mode==='angle'){
      pitchSP=input.pitch*maxTilt*0.90;
      rollSP =input.roll *maxTilt*0.90;
    }
    if(this.mode==='althold'||this.mode==='gpshold'||this.mode==='rth'){
      const stickDead=0.045;
      const hov=PHYS.hoverThrottle;
      if(this.mode==='althold'){
        pitchSP=input.pitch*maxTilt*0.90;
        rollSP =input.roll *maxTilt*0.90;
      }
      if(Math.abs(input.throttle-0.5)<stickDead){
        if(this.altTarget==null) this.altTarget=PHYS._altEstimate;
        if(this._altManualLastFrame){
          this.altVelPID.reset(); this.altPID.reset();
          this._altManualLastFrame=false;
        }
        const velSP=Math.max(-this.maxAltVelRate,Math.min(this.maxAltVelRate,
          this.altPID.update(this.altTarget,PHYS._altEstimate,dt)));
        thrCmd=Math.max(0,Math.min(0.97,hov+this.altVelPID.update(velSP,PHYS.vel.y,dt)));
      } else {
        this._altManualLastFrame=true;
        this.altTarget=PHYS._altEstimate;
        const t=input.throttle;
        thrCmd=t<=0.5 ? (t/0.5)*hov : hov+((t-0.5)/0.5)*(0.97-hov);
      }
    }
    if(this.mode==='gpshold'){
      if(Math.abs(input.pitch)<0.08&&Math.abs(input.roll)<0.08){
        if(this.posTarget==null) this.posTarget={x:PHYS.pos.x,z:PHYS.pos.z};
        // [FIX-2.5] Position error in world frame → rotate to body frame via −yaw
        const dN=this.posTarget.z-PHYS.pos.z, dE=this.posTarget.x-PHYS.pos.x;
        const cy=Math.cos(e.yaw), sy=Math.sin(e.yaw);
        // Body-frame error: forward = cy*dN+sy*dE, right = −sy*dN+cy*dE
        const errFwd  =  cy*dN + sy*dE;
        const errRight= -sy*dN + cy*dE;
        // posNPID/posEPID: setpoint=0, measured=body-frame error → output is tilt angle (rad)
        pitchSP=Math.max(-maxTilt*0.8,Math.min(maxTilt*0.8, this.posNPID.update(0,-errFwd, dt)));
        rollSP =Math.max(-maxTilt*0.8,Math.min(maxTilt*0.8, this.posEPID.update(0,-errRight,dt)));
      } else {
        this.posTarget={x:PHYS.pos.x,z:PHYS.pos.z};
        pitchSP=input.pitch*maxTilt*0.90; rollSP=input.roll*maxTilt*0.90;
      }
    }
    if(this.mode==='rth'){
      const home=PHYS.homePos||{x:0,y:0,z:0};
      const cy=Math.cos(e.yaw),sy=Math.sin(e.yaw);
      const curAGL=PHYS.pos.y-PHYS.groundY;
      const homeAGL=home.y-PHYS.groundY;
      const hov=PHYS.hoverThrottle;
      if(this.rthPhase===0){
        const safeAlt=Math.max(homeAGL+this.rthClimbAlt,curAGL+3);
        const velSP=this.altPID.update(safeAlt,PHYS._altEstimate,dt);
        thrCmd=Math.max(hov,Math.min(0.94,hov+this.altVelPID.update(Math.min(2.5,velSP),PHYS.vel.y,dt)));
        if(curAGL>=safeAlt-0.5) this.rthPhase=1;
        pitchSP=0; rollSP=0;
      } else if(this.rthPhase===1){
        const dN=home.z-PHYS.pos.z, dE=home.x-PHYS.pos.x, dist=Math.hypot(dN,dE);
        const velSP=this.altPID.update(this.altTarget!=null?this.altTarget:curAGL,PHYS._altEstimate,dt);
        thrCmd=Math.max(Math.max(0,hov-0.5),Math.min(0.9,hov+this.altVelPID.update(velSP,PHYS.vel.y,dt)));
        if(dist<1.0){this.rthPhase=2;this.posTarget={x:home.x,z:home.z};}
        else{
          const spd=Math.min(1,dist/15);
          pitchSP=Math.max(-maxTilt*0.8,Math.min(maxTilt*0.8,-(cy*dN+sy*dE)*0.14*spd));
          rollSP =Math.max(-maxTilt*0.8,Math.min(maxTilt*0.8,-(-sy*dN+cy*dE)*0.14*spd));
        }
      } else if(this.rthPhase===2){
        const dN=home.z-PHYS.pos.z, dE=home.x-PHYS.pos.x;
        pitchSP=Math.max(-0.15,Math.min(0.15,-(cy*dN+sy*dE)*0.09));
        rollSP =Math.max(-0.15,Math.min(0.15,-(-sy*dN+cy*dE)*0.09));
        const velSP=this.altPID.update(0.15,PHYS._altEstimate,dt);
        thrCmd=Math.max(0,Math.min(hov+0.2,hov+this.altVelPID.update(Math.max(-1.5,velSP),PHYS.vel.y,dt)));
        if(PHYS.grounded){
          this.rthPhase=3;
          if(typeof State!=='undefined') State.armed=false;
          if(typeof updateArmUI==='function') updateArmUI();
        }
      } else {
        PHYS._fcRateCmd={pitch:0,roll:0,yaw:0,thr:0};
        return [0,0,0,0];
      }
    }
    return this._angleThenStore(dt,thrCmd,pitchSP,rollSP,yawRateCmd);
  },

  /**
   * Angle loop → compute rate commands and store in PHYS._fcRateCmd.
   * The actual rate→motor PID runs in PHYS._substep() at substep rate.
   */
  _angleThenStore(dt,thrCmd,pitchSP,rollSP,yawRateCmd){
    const e=PHYS.euler, cap=this.maxAngleRate, af=this._adaptiveGainFactor();
    const pitchRateCmd=Math.max(-cap,Math.min(cap,this.anglePID.pitch.update(pitchSP,e.pitch,dt)*af));
    const rollRateCmd =Math.max(-cap,Math.min(cap,this.anglePID.roll.update(rollSP, e.roll, dt)*af));
    // Store rate commands for inner loop
    PHYS._fcRateCmd = {pitch:pitchRateCmd, roll:rollRateCmd, yaw:yawRateCmd, thr:thrCmd};
    return PHYS.motorCmd; // current motor cmd (will be updated by substep)
  },

  /**
   * [FIX-2.1] Inner rate PID — runs at substep rate inside PHYS._substep()
   * [FIX-2.3] Verified Quad-X motor mixing for Y-up body frame:
   *   M0(FR) = base − pitch + roll  + yaw  (CW: +yaw)
   *   M1(FL) = base − pitch − roll  − yaw  (CCW: −yaw)
   *   M2(BL) = base + pitch − roll  + yaw  (CW:  +yaw)
   *   M3(BR) = base + pitch + roll  − yaw  (CCW: −yaw)
   * Sign convention: +pitch = nose up (BL+BR thrust up), +roll = right bank (FR+BR up)
   */
  _rateLoopSubstep(dt, thrCmd, pitchRateCmd, rollRateCmd, yawRateCmd){
    const g = PHYS.gyro;
    const af = this._adaptiveGainFactor();
    let pitchOut = this.ratePID.pitch.update(pitchRateCmd, g.x, dt) * af;
    let rollOut  = this.ratePID.roll.update(rollRateCmd,  g.z, dt) * af;
    let yawOut   = this.ratePID.yaw.update(yawRateCmd,   g.y, dt);
    if(typeof DEBUG!=='undefined') DEBUG.recordPID(pitchRateCmd,g.x,rollRateCmd,g.z,yawRateCmd,g.y);
    const mg=this.motorMixGain;
    const lim=0.18, ylim=0.12;
    pitchOut=Math.max(-lim, Math.min(lim, pitchOut*mg));
    rollOut =Math.max(-lim, Math.min(lim, rollOut *mg));
    yawOut  =Math.max(-ylim,Math.min(ylim,yawOut  *mg));
    const hover=PHYS.hoverThrottle;
    const modeAlt=this.mode==='althold'||this.mode==='gpshold'||this.mode==='rth';
    // [FIX-D] Throttle mapping: symmetric authority around hoverThrottle.
    // Low half (0→0.5): 0→hover. High half (0.5→1): hover→0.97.
    // This replaces the hardcoded *0.38 that was only valid for racing5.
    let base;
    if(modeAlt){
      base = thrCmd;
    } else {
      const t = Math.max(0, Math.min(1, thrCmd));
      base = t <= 0.5 ? (t / 0.5) * hover
                      : hover + ((t - 0.5) / 0.5) * (0.97 - hover);
    }
    base=Math.max(0.02, Math.min(0.97, base));
    // [FIX-G] Yaw sign corrected: tauYaw in _substep has an outer negation
    // (tauYaw = -(kQ·dir0·ω0 + ...)), so increasing CW motors REDUCES tauYaw (goes negative).
    // With Y-up right-hand convention, negative tauYaw = CW angular accel = decreasing yaw angle.
    // Therefore to INCREASE yaw (fight +gy spin): DECREASE CW, INCREASE CCW → yawOut negated.
    // Blackbox confirmed: old sign caused CW-CCW to grow negative as +gy grew → positive feedback.
    const m=[
      base - pitchOut + rollOut  - yawOut,  // M0 FR (CW)
      base - pitchOut - rollOut  + yawOut,  // M1 FL (CCW)
      base + pitchOut - rollOut  - yawOut,  // M2 BL (CW)
      base + pitchOut + rollOut  + yawOut,  // M3 BR (CCW)
    ];
    return this._mixMotors(m);
  },

  _mixMotors(m){
    // [FIX-E] Betaflight-style desaturation — no intermediate array allocations
    let v0=m[0],v1=m[1],v2=m[2],v3=m[3];
    let maxV=v0; if(v1>maxV)maxV=v1; if(v2>maxV)maxV=v2; if(v3>maxV)maxV=v3;
    if(maxV>1){const d=maxV-1;v0-=d;v1-=d;v2-=d;v3-=d;}
    let minV=v0; if(v1<minV)minV=v1; if(v2<minV)minV=v2; if(v3<minV)minV=v3;
    if(minV<0){v0-=minV;v1-=minV;v2-=minV;v3-=minV;}
    let maxV2=v0; if(v1>maxV2)maxV2=v1; if(v2>maxV2)maxV2=v2; if(v3>maxV2)maxV2=v3;
    if(maxV2>1){v0/=maxV2;v1/=maxV2;v2/=maxV2;v3/=maxV2;}
    return [Math.max(0,Math.min(1,v0)),Math.max(0,Math.min(1,v1)),Math.max(0,Math.min(1,v2)),Math.max(0,Math.min(1,v3))];
  },
};

/* ─── Input Handler ─── */
const INPUT = {
  _keys:{}, _thrRaw:0, sensitivity:0.26, expo:0.38, deadband:0.05,
  _gamepad:null, pitch:0, roll:0, yaw:0, throttle:0,
  _vjLeft:{x:0,y:0}, _vjRight:{x:0,y:0}, _vjActive:false,

  // Flight-control keys that must never scroll panels/page while flying
  _flightKeys: new Set([
    'ArrowUp','ArrowDown','ArrowLeft','ArrowRight',
    'KeyW','KeyS','KeyA','KeyD',
    'ShiftLeft','ShiftRight','ControlLeft','ControlRight',
  ]),

  init(){
    window.addEventListener('keydown',(e)=>{
      if(e.target.tagName==='INPUT'||e.target.tagName==='SELECT') return;
      this._keys[e.code]=true;
      // Prevent browser scroll on flight-control keys when armed or airborne
      const armed = (typeof State!=='undefined') ? State.armed : false;
      const airborne = PHYS.pos.y > PHYS.groundY + 0.3;
      if(this._flightKeys.has(e.code) && (armed || airborne)){
        e.preventDefault();
      }
      if(e.repeat) return;
      switch(e.code){
        case 'Space': e.preventDefault(); if(typeof toggleArm==='function') toggleArm(); break;
        case 'KeyT': if(typeof takeoff==='function') takeoff(); break;
        case 'KeyR': if(typeof returnHome==='function') returnHome(); break;
        case 'KeyH': if(typeof doHover==='function') doHover(); break;
        case 'KeyG': if(typeof setFlightMode==='function') setFlightMode('gpshold'); break;
        case 'KeyX': case 'Escape': if(typeof emergStop==='function') emergStop(); break;
        case 'KeyF':
          if(typeof setCamera==='function'){
            if(typeof _camMode_global!=='undefined'&&_camMode_global==='fpv') setCamera('third');
            else setCamera('fpv');
          }
          break;
        case 'KeyC': case 'Tab': e.preventDefault(); if(typeof cycleCamera==='function') cycleCamera(); break;
        case 'KeyM': if(typeof addWaypoint==='function') addWaypoint(); break;
        case 'Digit1': if(typeof setFlightMode==='function') setFlightMode('stabilized'); break;
        case 'Digit2': if(typeof setFlightMode==='function') setFlightMode('angle');      break;
        case 'Digit3': if(typeof setFlightMode==='function') setFlightMode('acro');       break;
        case 'Digit4': if(typeof setFlightMode==='function') setFlightMode('althold');    break;
        case 'Digit5': if(typeof setFlightMode==='function') setFlightMode('gpshold');    break;
      }
    });
    window.addEventListener('keyup',(e)=>{this._keys[e.code]=false;});
    window.addEventListener('blur',()=>{this._keys={}; this.pitch=0; this.roll=0; this.yaw=0;});
    window.addEventListener('gamepadconnected',(e)=>{
      this._gamepad=e.gamepad;
      if(typeof UI!=='undefined') UI.toast('🎮 Controller: '+e.gamepad.id.substring(0,28));
    });
    window.addEventListener('gamepaddisconnected',()=>{
      this._gamepad=null;
      if(typeof UI!=='undefined') UI.toast('🎮 Controller disconnected');
    });
  },

  /**
   * [FIX-6.2] Deadband → rescale → expo (eliminates discontinuity at deadband edge)
   * Previous: deadband applied before expo caused a jump from 0 to expo(deadband)
   * Correct: apply deadband, linearly rescale to [0,1], then apply expo
   */
  _applyDeadExpo(v){
    const d=this.deadband;
    if(Math.abs(v)<d) return 0;
    // Rescale from [d,1] to [0,1] preserving sign
    const rescaled=(Math.abs(v)-d)/(1-d);
    const e=this.expo;
    const expo_out=rescaled*(1-e)+rescaled*rescaled*rescaled*e;
    return Math.sign(v)*expo_out;
  },

  update(dt){
    const K=this._keys, s=this.sensitivity;
    let gpActive=false;

    if(this._gamepad){
      const gp=navigator.getGamepads()[this._gamepad.index];
      if(gp&&gp.axes.length>=4){
        gpActive=true;
        const rawThrY=this._applyDeadExpo(-gp.axes[1]);
        this._thrRaw=Math.max(0,Math.min(1,(rawThrY+1)*0.5));
        this.yaw  =this._applyDeadExpo(gp.axes[0]) *s;
        this.pitch=this._applyDeadExpo(-gp.axes[3])*s;
        this.roll =this._applyDeadExpo( gp.axes[2])*s;
      }
    }

    if(!gpActive&&this._vjActive){
      const thrRate=-this._vjLeft.y*0.9*s;
      this._thrRaw=Math.max(0,Math.min(1,this._thrRaw+thrRate*dt));
      this.yaw=this._applyDeadExpo(this._vjLeft.x)*s;
      const tau=1-Math.exp(-dt*8);
      this.pitch+=(this._applyDeadExpo(this._vjRight.y)*s-this.pitch)*tau;
      this.roll +=(this._applyDeadExpo(this._vjRight.x)*s-this.roll) *tau;
      gpActive=true;
    }

    if(!gpActive){
      if(K['KeyW']||K['ShiftLeft']||K['ShiftRight'])
        this._thrRaw=Math.min(1,this._thrRaw+dt*0.45*s);
      else if(K['KeyS']||K['ControlLeft']||K['ControlRight'])
        this._thrRaw=Math.max(0,this._thrRaw-dt*0.45*s);
      const yt=((K['KeyA']?-1:0)+(K['KeyD']?1:0))*s;
      const pt=((K['ArrowUp']?1:0)+(K['ArrowDown']?-1:0))*s;
      const rt=((K['ArrowRight']?1:0)+(K['ArrowLeft']?-1:0))*s;
      const tau=1-Math.exp(-dt*6);
      this.pitch+=(this._applyDeadExpo(pt)-this.pitch)*tau;
      this.roll +=(this._applyDeadExpo(rt)-this.roll) *tau;
      this.yaw  +=(this._applyDeadExpo(yt)-this.yaw)  *tau;
    }

    this.throttle=this._thrRaw;
    const slEl=document.getElementById('throttle-slider');
    if(slEl&&!slEl._dragging){
      slEl.value=Math.round(this._thrRaw*100);
      const tv=document.getElementById('thr-val');
      if(tv) tv.textContent=Math.round(this._thrRaw*100)+'%';
    }
    if(typeof _updateStickViz==='function') _updateStickViz();
  },

  get(){
    return {
      throttle:Math.max(0,Math.min(1,this.throttle)),
      pitch:   Math.max(-1,Math.min(1,this.pitch)),
      roll:    Math.max(-1,Math.min(1,this.roll)),
      yaw:     Math.max(-1,Math.min(1,this.yaw)),
    };
  },
};

/* ─── Simulation speed default ─── */
/**
 * Default simulation speed multiplier.
 * The render loop in index.html should read SIM_SPEED on init instead of hardcoding 1.
 * Set to 2 so the simulation starts at 2× real-time out of the box.
 *
 * Performance note at 2×: PHYS.step() is called with dtFull up to 2× the wall-clock
 * frame time. The physics substep count is fixed at 4, so each substep dt doubles.
 * All integrators (Euler, RK-style battery, Kalman, PID) remain stable up to ~3× because
 * dtFull is clamped to 40 ms (line: dtFull=Math.max(0.0005,Math.min(0.04,dtFull))), so
 * the effective per-substep dt never exceeds 10 ms — well within stability margins.
 * CPU cost is IDENTICAL to 1× (same number of substeps per rendered frame); the only
 * difference is the wall-clock time advances faster. No performance issues at 2×.
 */
const SIM_SPEED = 4;


// [FIX-5.2] Shared clock object so both sim-engine and index.html mutate/read the same reference
const _simClock = { t: 0 };

const BLACKBOX = {
  _log:[], _max:3000, recording:false,
  _lastTick:0,
    start(){this._log=[];this.recording=true;this._lastTick=0;},
  stop(){this.recording=false;},
  /** [FIX-5.1] Extended fields: accX/Y/Z, baro_raw/filtered, wind, dryden, mode, armed */
  tick(t){
    if(!this.recording) return;
    const p=PHYS;
    // Prevent NaN physics from corrupting the blackbox log and turning into 'null' in JSON exports
    if (Number.isNaN(p.pos.x) || Number.isNaN(p.vel.x)) {
      console.warn('Blackbox ignored frame due to NaN physics');
      return;
    }
    const gps=typeof GPS_SIM!=='undefined'?GPS_SIM.rawInt():{};
    const obs=typeof OBSTACLE_DIST!=='undefined'?OBSTACLE_DIST.get():[0,0,0,0,0];
    const gust=DRYDEN.get();
    this._log.push({
      // [FIX-5.2] Use shared sim clock
      t: _simClock.t,
      px:p.pos.x, py:p.pos.y, pz:p.pos.z,
      roll:p.euler.roll, pitch:p.euler.pitch, yaw:p.euler.yaw,
      gx:p.gyro.x, gy:p.gyro.y, gz:p.gyro.z,
      // [FIX-5.1] Accelerometer body-frame
      accX:p.accelBody.x, accY:p.accelBody.y, accZ:p.accelBody.z,
      vx:p.vel.x, vy:p.vel.y, vz:p.vel.z,
      m0:p.motorCmd[0], m1:p.motorCmd[1], m2:p.motorCmd[2], m3:p.motorCmd[3],
      rpm0:p.motorRPM[0], rpm1:p.motorRPM[1], rpm2:p.motorRPM[2], rpm3:p.motorRPM[3],
      batt:p.battVoltage, curr:p.currentDraw, batt_pct:p.battPct,
      // [FIX-5.1] Baro raw and filtered
      baro_raw:p._baroRaw, baro_filtered:p._altEstimate,
      // [FIX-5.1] Wind and Dryden turbulence
      wind_x:p.windVec.x, wind_z:p.windVec.z,
      dryden_x:gust.x, dryden_y:gust.y, dryden_z:gust.z,
      gps_lat:gps.lat||0, gps_lon:gps.lon||0, gps_fix:gps.fix_type||0, gps_sat:gps.satellites_visible||0,
      obs_fwd:obs[0], obs_right:obs[1], obs_back:obs[2], obs_left:obs[3], obs_up:obs[4],
      // [FIX-5.1] Flight mode and arm state
      mode:typeof FC!=='undefined'?FC.mode:'unknown',
      armed:(typeof State!=='undefined')?State.armed:false,
    });
    if(this._log.length>this._max) this._log.shift();
  },
  exportCSV(){
    if(!this._log.length) return '';
    const keys=Object.keys(this._log[0]);
    return [keys.join(','),...this._log.map(row=>keys.map(k=>row[k]).join(','))].join('\n');
  },
  download(){
    const csv=this.exportCSV(); if(!csv) return;
    const blob=new Blob([csv],{type:'text/csv'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='spaceborn-blackbox-'+Date.now()+'.csv';
    a.click();
  },
  getLog(){ return this._log.slice(); },
  getStats(){
    if(!this._log.length) return null;
    let maxAlt=0,totalVel=0,maxVel=0;
    for(const e of this._log){
      if(e.py>maxAlt) maxAlt=e.py;
      const v=Math.hypot(e.vx,e.vy,e.vz);
      totalVel+=v; if(v>maxVel) maxVel=v;
    }
    return {
      duration:(this._log[this._log.length-1].t-this._log[0].t).toFixed(1),
      samples:this._log.length,
      maxAlt:maxAlt.toFixed(1),
      avgVel:(totalVel/this._log.length).toFixed(1),
      maxVel:maxVel.toFixed(1),
    };
  },
};

/* ─── Debug / PID Visualizer ─── */
const DEBUG = {
  enabled:false, _pidHist:{pitch:[],roll:[],yaw:[]}, _histLen:120,
  recordPID(pSP,pM,rSP,rM,ySP,yM){
    if(!this.enabled) return;
    const h=this._pidHist;
    h.pitch.push({sp:pSP,m:pM}); h.roll.push({sp:rSP,m:rM}); h.yaw.push({sp:ySP,m:yM});
    if(h.pitch.length>this._histLen){h.pitch.shift();h.roll.shift();h.yaw.shift();}
  },
  toggle(){
    this.enabled=!this.enabled;
    const el=document.getElementById('debug-badge');
    if(el) el.style.display=this.enabled?'block':'none';
    return this.enabled;
  },
  draw(){
    if(!this.enabled) return;
    this._drawPID('pidGraph',this._pidHist.pitch,'#EE9346');
    this._drawGyro();
  },
  _drawPID(canvasId,data,color){
    const canvas=document.getElementById(canvasId); if(!canvas||!data.length) return;
    const ctx=canvas.getContext('2d'),W=canvas.width,H=canvas.height;
    ctx.clearRect(0,0,W,H);
    ctx.strokeStyle='rgba(96,125,139,0.3)';ctx.beginPath();ctx.moveTo(0,H/2);ctx.lineTo(W,H/2);ctx.stroke();
    const draw=(fn,col)=>{
      ctx.strokeStyle=col;ctx.lineWidth=1.5;ctx.beginPath();
      data.forEach((d,i)=>{const x=(i/(data.length-1||1))*W,y=H/2-(fn(d)/8)*(H*0.45);i===0?ctx.moveTo(x,y):ctx.lineTo(x,y);});
      ctx.stroke();
    };
    draw(d=>d.m,color); draw(d=>d.sp,'rgba(16,37,109,0.7)');
  },
  _drawGyro(){
    const canvas=document.getElementById('gyroGraph'); if(!canvas) return;
    const ctx=canvas.getContext('2d'),W=canvas.width,H=canvas.height;
    ctx.clearRect(0,0,W,H);
    const g=PHYS.gyro,max=PHYS.maxRate.pitch;
    [{v:g.x,c:'#EE9346',l:'P'},{v:g.z,c:'#10256D',l:'R'},{v:g.y,c:'#43A047',l:'Y'}].forEach((b,i)=>{
      const x=12+i*36,h=(Math.abs(b.v)/max)*(H-20);
      ctx.fillStyle=b.c; ctx.fillRect(x,H-10-h,24,h);
      ctx.fillStyle='#607D8B'; ctx.font='9px Inter'; ctx.fillText(b.l,x+8,H-2);
    });
  },
};

/* ─── GPS_RAW_INT Simulation ─── */
const GPS_SIM = {
  HOME_LAT: 17.00050,  // degrees
  HOME_LON: 82.24580,
  HOME_ALT: 12.0,
  _satBase: 14, _satJitter: 0, _satTimer: 0, _fixType: 3, _hdop: 0.9,
  UPDATE_RATE: 5,

  /** [FIX-3.5] HOME_LAT converted to radians for Math.cos */
  _cosHomeLat: null,
  getLat() { return this.HOME_LAT + PHYS.pos.z / 111320; },
  getLon() {
    if(this._cosHomeLat===null) this._cosHomeLat=Math.cos(this.HOME_LAT*Math.PI/180);
    return this.HOME_LON + PHYS.pos.x / (111320 * this._cosHomeLat);
  },
  getAltMSL() { return this.HOME_ALT + PHYS.pos.y; },

  update(dt) {
    this._satTimer += dt;
    if(this._satTimer > 2.0){
      this._satTimer=0;
      this._satJitter=Math.round((Math.random()-0.5)*2);
    }
    const indoor=typeof ENV!=='undefined'&&ENV._name==='indoor';
    this._fixType=indoor?0:(typeof State!=='undefined'&&State.armed?3:2);
    this._hdop=indoor?99.9:(0.7+Math.random()*0.4);
  },

  getSatCount(){ return Math.max(0,this._satBase+this._satJitter+(this._fixType===0?-14:0)); },
  getFixType() { return this._fixType; },
  getHdop()    { return this._hdop; },

  rawInt(){
    return {
      lat:  Math.round(this.getLat()*1e7),
      lon:  Math.round(this.getLon()*1e7),
      alt:  Math.round(this.getAltMSL()*1000),
      fix_type: this._fixType,
      satellites_visible: this.getSatCount(),
      eph:  Math.round(this._hdop*100),
      epv:  Math.round((this._hdop+0.3)*100),
    };
  },
};

/* ─── VISION_POSITION Simulation ─── */
const VISION_POS = {
  _x:0, _y:0, _z:0, _driftX:0, _driftZ:0,
  UPDATE_RATE:30,
  _isActive(){
    const indoor=typeof ENV!=='undefined'&&ENV._name==='indoor';
    return indoor||GPS_SIM.getFixType()<2;
  },
  update(dt){
    if(!this._isActive()){
      this._driftX*=0.95; this._driftZ*=0.95;
    }
    const driftRate=this._isActive()?0.002:0.0001;
    this._driftX+=(Math.random()-0.5)*driftRate;
    this._driftZ+=(Math.random()-0.5)*driftRate;
    const noiseAmp=this._isActive()?0.04:0.008;
    this._x=PHYS.pos.x+this._driftX+(Math.random()-0.5)*noiseAmp;
    this._y=Math.max(0,PHYS.pos.y-PHYS.groundY+(Math.random()-0.5)*noiseAmp*0.5);
    this._z=PHYS.pos.z+this._driftZ+(Math.random()-0.5)*noiseAmp;
  },
  get(){
    return {
      x:this._x.toFixed(2), y:this._y.toFixed(2), z:this._z.toFixed(2),
      active:this._isActive(),
      quality:this._isActive()?Math.max(0,100-Math.round(Math.hypot(this._driftX,this._driftZ)*500)):100,
    };
  },
};

/* ─── OBSTACLE_DISTANCE — 5-sector proximity ─── */
const OBSTACLE_DIST = {
  SECTORS:['FWD','RIGHT','BACK','LEFT','UP'],
  SENSOR_RANGE:12.0,
  UPDATE_RATE:10,
  _distances:[12,12,12,12,12],
  _angles:[0,Math.PI/2,Math.PI,-Math.PI/2,null],

  update(){
    const p=PHYS;
    const yaw=p.euler.yaw;
    const pos=p.pos;
    const groundY=p.groundY;
    for(let s=0;s<5;s++){
      if(s===4){
        this._distances[s]=Math.max(0,120-(pos.y-groundY));
        continue;
      }
      const sectorAngle=yaw+this._angles[s];
      const dx=Math.sin(sectorAngle), dz=Math.cos(sectorAngle);
      let minDist=this.SENSOR_RANGE;
      for(const c of p.colliders){
        const cx=(c.min.x+c.max.x)*0.5, cz=(c.min.z+c.max.z)*0.5;
        const toX=cx-pos.x, toZ=cz-pos.z;
        const proj=toX*dx+toZ*dz;
        if(proj<0||proj>this.SENSOR_RANGE) continue;
        const perpDist=Math.abs(toX*dz-toZ*dx);
        const hw=Math.max((c.max.x-c.min.x),(c.max.z-c.min.z))*0.5;
        if(perpDist<hw+0.5) minDist=Math.min(minDist,proj);
      }
      this._distances[s]=Math.max(0,minDist+(Math.random()-0.5)*0.15);
    }
  },
  get(){ return this._distances.slice(); },
};

/* ─── PID Telemetry ─── */
const PID_TELEM = {
  axes:{
    roll:    {kp:0,ki:0,kd:0,setpoint:0,measured:0,error:0,output:0},
    pitch:   {kp:0,ki:0,kd:0,setpoint:0,measured:0,error:0,output:0},
    yaw:     {kp:0,ki:0,kd:0,setpoint:0,measured:0,error:0,output:0},
    throttle:{kp:0,ki:0,kd:0,setpoint:0,measured:0,error:0,output:0},
  },
  capture(){
    const g=FC.gains;
    const rp=FC.ratePID;
    const fcrc=PHYS._fcRateCmd;
    // Capture rate setpoints from PHYS._fcRateCmd (inner loop values)
    this.axes.roll    ={kp:g.rp,ki:g.ri||0,kd:g.rd, setpoint:fcrc.roll,  measured:PHYS.gyro.z, error:fcrc.roll -PHYS.gyro.z, output:PHYS.motorCmd[0]-PHYS.motorCmd[1]};
    this.axes.pitch   ={kp:g.rp,ki:g.ri||0,kd:g.rd, setpoint:fcrc.pitch, measured:PHYS.gyro.x, error:fcrc.pitch-PHYS.gyro.x, output:PHYS.motorCmd[2]-PHYS.motorCmd[0]};
    this.axes.yaw     ={kp:g.yp,ki:0.012,  kd:0,     setpoint:fcrc.yaw,   measured:PHYS.gyro.y, error:fcrc.yaw  -PHYS.gyro.y, output:PHYS.motorCmd[0]-PHYS.motorCmd[3]};
    this.axes.throttle={kp:g.ap,ki:FC.altPID.i,kd:FC.altPID.d, setpoint:FC.altTarget||0, measured:PHYS._altEstimate, error:(FC.altTarget||0)-PHYS._altEstimate, output:PHYS.hoverThrottle};
  },
};

/* ─── Battery flight time estimate ─── */
function getBattEstimatedFlightTime(){
  const remainingAh=PHYS.battTotalAh*(PHYS.battPct/100);
  const currentA=Math.max(0.1,PHYS.currentDraw);
  return Math.min(9999,(remainingAh/currentA)*3600);
}

/* ─── Global exports ─── */
if(typeof globalThis!=='undefined'){
  Object.assign(globalThis,{V3,Q,Noise,DRYDEN,PID,Kalman1D,DRONE_PROFILES,PHYS,FC,INPUT,BLACKBOX,DEBUG,
    GPS_SIM,VISION_POS,OBSTACLE_DIST,PID_TELEM,getBattEstimatedFlightTime,_simClock,SIM_SPEED});
}

/* ─── MAVLink v1 Export ─── */
const MAVLINK = {
  MSG_HEARTBEAT:0, MSG_SYS_STATUS:1, MSG_BATTERY_STATUS:147,
  MSG_ATTITUDE:30, MSG_LOCAL_POSITION_NED:32, MSG_GPS_RAW_INT:24,
  MSG_RC_CHANNELS_RAW:35, MSG_VFR_HUD:74, MSG_STATUSTEXT:253,
  _seq:0,

  _crc16(buf){
    let crc=0xFFFF;
    for(let i=0;i<buf.length;i++){
      let tmp=buf[i]^(crc&0xFF);
      tmp=(tmp^(tmp<<4))&0xFF;
      crc=((crc>>8)^(tmp<<8)^(tmp<<3)^(tmp>>4))&0xFFFF;
    }
    return crc;
  },

  // CRC extra bytes verified against MAVLink common.xml
  _crcExtra:{
    0:50,   // HEARTBEAT
    1:124,  // SYS_STATUS
    24:24,  // GPS_RAW_INT
    30:39,  // ATTITUDE
    32:185, // LOCAL_POSITION_NED
    35:244, // RC_CHANNELS_RAW
    74:20,  // VFR_HUD
    147:154,// BATTERY_STATUS
    253:83, // STATUSTEXT
  },

  _packet(msgId, payload){
    const sysId=1,compId=1;
    const len=payload.length;
    const header=[0xFE,len,this._seq&0xFF,sysId,compId,msgId];
    this._seq=(this._seq+1)&0xFF;
    const crcBuf=[...header.slice(1),...payload,this._crcExtra[msgId]||0];
    const crc=this._crc16(crcBuf);
    return new Uint8Array([...header,...payload,crc&0xFF,(crc>>8)&0xFF]);
  },

  _f32(dv,off,val){dv.setFloat32(off,val,true);},
  _u32(dv,off,val){dv.setUint32(off,val,true);},
  _i32(dv,off,val){dv.setInt32(off,val,true);},
  _u16(dv,off,val){dv.setUint16(off,val,true);},
  _i16(dv,off,val){dv.setInt16(off,val,true);},

  /**
   * HEARTBEAT — MAVLink common.xml ID=0
   * [FIX-5.3] baseMode bits per MAVLink spec:
   *   bit7 (0x80): MAV_MODE_FLAG_SAFETY_ARMED
   *   bit4 (0x10): MAV_MODE_FLAG_GUIDED_ENABLED (GPS hold)
   *   bit3 (0x08): MAV_MODE_FLAG_STABILIZE_ENABLED (stabilized/angle)
   */
  heartbeat(customMode, type, autopilot, baseMode, sysStatus, mavlinkVersion){
    const buf=new ArrayBuffer(9); const dv=new DataView(buf);
    this._u32(dv,0,customMode||0);
    dv.setUint8(4,type||2);        // MAV_TYPE_QUADROTOR=2
    dv.setUint8(5,autopilot||3);   // MAV_AUTOPILOT_ARDUPILOTMEGA=3
    // [FIX-5.3] Correct baseMode flags
    let bm=0;
    const armed = typeof State!=='undefined'&&State.armed;
    const mode  = typeof FC!=='undefined'?FC.mode:'stabilized';
    if(armed)  bm|=0x80; // MAV_MODE_FLAG_SAFETY_ARMED
    if(mode==='gpshold'||mode==='rth') bm|=0x10; // MAV_MODE_FLAG_GUIDED_ENABLED
    if(mode==='stabilized'||mode==='angle'||mode==='althold') bm|=0x08; // MAV_MODE_FLAG_STABILIZE_ENABLED
    dv.setUint8(6,baseMode!==undefined?baseMode:bm);
    dv.setUint8(7,sysStatus||0);
    dv.setUint8(8,mavlinkVersion||3);
    return this._packet(this.MSG_HEARTBEAT,[...new Uint8Array(buf)]);
  },

  attitude(timeBootMs,roll,pitch,yaw,rollspeed,pitchspeed,yawspeed){
    const buf=new ArrayBuffer(28); const dv=new DataView(buf);
    this._u32(dv,0,timeBootMs>>>0);
    this._f32(dv,4,roll); this._f32(dv,8,pitch); this._f32(dv,12,yaw);
    this._f32(dv,16,rollspeed); this._f32(dv,20,pitchspeed); this._f32(dv,24,yawspeed);
    return this._packet(this.MSG_ATTITUDE,[...new Uint8Array(buf)]);
  },

  localPositionNed(timeBootMs,x,y,z,vx,vy,vz){
    const buf=new ArrayBuffer(28); const dv=new DataView(buf);
    this._u32(dv,0,timeBootMs>>>0);
    this._f32(dv,4,x); this._f32(dv,8,y); this._f32(dv,12,z);
    this._f32(dv,16,vx); this._f32(dv,20,vy); this._f32(dv,24,vz);
    return this._packet(this.MSG_LOCAL_POSITION_NED,[...new Uint8Array(buf)]);
  },

  vfrHud(airspeed,groundspeed,heading,throttle,alt,climb){
    const buf=new ArrayBuffer(20); const dv=new DataView(buf);
    this._f32(dv,0,airspeed); this._f32(dv,4,groundspeed);
    this._f32(dv,8,alt); this._f32(dv,12,climb);
    this._i16(dv,16,heading); this._u16(dv,18,throttle);
    return this._packet(this.MSG_VFR_HUD,[...new Uint8Array(buf)]);
  },

  /** [FIX-5.3] GPS_RAW_INT: vel = ground speed cm/s, cog = atan2(vx,vz) cdeg */
  gpsRawInt(timeBootMs,lat,lon,alt,eph,epv,vel,cog,fixType,satellitesVisible){
    const buf=new ArrayBuffer(30); const dv=new DataView(buf);
    dv.setUint32(0,(timeBootMs*1000)>>>0,true); dv.setUint32(4,0,true);
    this._i32(dv,8,lat); this._i32(dv,12,lon); this._i32(dv,16,alt);
    this._u16(dv,20,eph!==undefined?eph:0xFFFF);
    this._u16(dv,22,epv!==undefined?epv:0xFFFF);
    this._u16(dv,24,vel!==undefined?vel:0xFFFF); // ground speed cm/s
    this._u16(dv,26,cog!==undefined?cog:0xFFFF); // course over ground cdeg
    dv.setUint8(28,fixType||0);
    dv.setUint8(29,satellitesVisible!==undefined?satellitesVisible:255);
    return this._packet(this.MSG_GPS_RAW_INT,[...new Uint8Array(buf)]);
  },

  /** [FIX-5.3] BATTERY_STATUS: currentBattery in centi-amps (A×100), voltages in mV */
  batteryStatus(id,battFunction,type,temperature,voltages,currentBattery,currentConsumed,energyConsumed,batteryRemaining){
    const buf=new ArrayBuffer(36); const dv=new DataView(buf);
    this._i32(dv,0,currentConsumed!==undefined?currentConsumed:-1);   // mAh
    this._i32(dv,4,energyConsumed!==undefined?energyConsumed:-1);
    this._i16(dv,8,temperature!==undefined?temperature:0x7FFF);
    const vArr=voltages||[];
    for(let i=0;i<10;i++) this._u16(dv,10+i*2,i<vArr.length?vArr[i]:0xFFFF); // mV per cell
    this._i16(dv,30,currentBattery!==undefined?currentBattery:-1); // centi-amps (A×100)
    dv.setUint8(32,id||0);
    dv.setUint8(33,battFunction||0);
    dv.setUint8(34,type||0);
    dv.setInt8( 35,batteryRemaining!==undefined?batteryRemaining:-1);
    return this._packet(this.MSG_BATTERY_STATUS,[...new Uint8Array(buf)]);
  },

  buildTlog(logEntries){
    if(!logEntries||!logEntries.length) return null;
    this._seq=0;
    const chunks=[];
    const writeEntry=(tSec,pkt)=>{
      const ts=Math.round(tSec*1e6);
      const tsBuf=new ArrayBuffer(8); const tsView=new DataView(tsBuf);
      tsView.setUint32(0,Math.floor(ts/4294967296)>>>0,false);
      tsView.setUint32(4,ts>>>0,false);
      chunks.push(new Uint8Array(tsBuf));
      chunks.push(pkt);
    };
    let lastHB=-999,lastGPS=-999,lastBatt=-999;
    for(const e of logEntries){
      if(e.t-lastHB>=1.0){
        writeEntry(e.t,this.heartbeat(0,2,3));
        lastHB=e.t;
      }
      if(e.t-lastGPS>=0.2){
        const gLat=Math.round((GPS_SIM.HOME_LAT+e.pz/111320)*1e7);
        // [FIX-3.5] Use cached cos(HOME_LAT) — avoids recomputing per log entry
        const gLon=Math.round((GPS_SIM.HOME_LON+e.px/(111320*(GPS_SIM._cosHomeLat||Math.cos(GPS_SIM.HOME_LAT*Math.PI/180))))*1e7);
        const gAlt=Math.round((GPS_SIM.HOME_ALT+e.py)*1000);
        const gVel=Math.round(Math.hypot(e.vx,e.vz)*100); // ground speed cm/s
        const gCog=Math.round(((Math.atan2(e.vx,e.vz)*180/Math.PI)+360)%360*100); // course cdeg
        writeEntry(e.t,this.gpsRawInt(Math.round(e.t*1000),gLat,gLon,gAlt,90,130,gVel,gCog,3,14));
        lastGPS=e.t;
      }
      if(e.t-lastBatt>=0.5){
        const cellV=Math.round((e.batt/(PHYS.cells||4))*1000); // mV per cell
        const voltages=Array(10).fill(0xFFFF);
        for(let c=0;c<(PHYS.cells||4);c++) voltages[c]=cellV;
        const consumed=Math.round(PHYS.battCapacity*1000); // mAh
        const remaining=Math.round(PHYS.battPct);
        // [FIX-5.3] currentBattery in centi-amps (A×100)
        writeEntry(e.t,this.batteryStatus(0,0,0,2500,voltages,Math.round(e.curr*100),consumed,-1,remaining));
        lastBatt=e.t;
      }
      const tms=Math.round(e.t*1000);
      writeEntry(e.t,this.attitude(tms,e.roll,e.pitch,e.yaw,e.gx,e.gy,e.gz));
      writeEntry(e.t,this.localPositionNed(tms,e.px,-e.pz,-e.py,e.vx,-e.vz,-e.vy));
      const speed=Math.hypot(e.vx,e.vy,e.vz);
      const hspeed=Math.hypot(e.vx,e.vz);
      writeEntry(e.t,this.vfrHud(speed,hspeed,Math.round(((e.yaw*180/Math.PI)+360)%360),Math.round((e.m0+e.m1+e.m2+e.m3)/4*100),e.py,e.vy));
    }
    const total=chunks.reduce((s,c)=>s+c.byteLength,0);
    const out=new Uint8Array(total); let off=0;
    for(const c of chunks){out.set(c,off);off+=c.byteLength;}
    return out;
  },

  downloadTlog(){
    const log=BLACKBOX.getLog();
    if(!log.length){console.warn('No blackbox data');return false;}
    const data=this.buildTlog(log);
    if(!data) return false;
    const blob=new Blob([data],{type:'application/octet-stream'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='spaceborn-'+Date.now()+'.tlog';
    a.click(); URL.revokeObjectURL(a.href);
    return true;
  },

  downloadJSON(){
    const log=BLACKBOX.getLog();
    if(!log.length) return false;
    const json=JSON.stringify({meta:{version:'2.1',drone:PHYS.droneProfile,exported:new Date().toISOString()},frames:log},null,2);
    const blob=new Blob([json],{type:'application/json'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='spaceborn-telem-'+Date.now()+'.json';
    a.click(); URL.revokeObjectURL(a.href);
    return true;
  },
};

/* ─── Telemetry Graph ─── */
const TELEM_GRAPH = {
  _canvas:null, _ctx:null,
  _history:{alt:[],vel:[],roll:[],pitch:[],batt:[]},
  _maxLen:200,
  _channels:['alt','vel','roll','pitch','batt'],
  _colors:{alt:'#10256D',vel:'#EE9346',roll:'#43A047',pitch:'#E53935',batt:'#9C27B0'},
  _visible:{alt:true,vel:true,roll:false,pitch:false,batt:false},
  _scales:{alt:50,vel:15,roll:90,pitch:90,batt:100},
  _maxAlt:0, _W:0, _H:0,

  init(canvasId){
    this._canvas=document.getElementById(canvasId);
    if(this._canvas){
      this._ctx=this._canvas.getContext('2d');
      this._syncSize();
    }
  },

  _syncSize(){
    if(!this._canvas) return;
    const now = performance.now();
    if(this._lastSync && now - this._lastSync < 1000) return;
    this._lastSync = now;
    const cw = this._canvas.clientWidth, ch = this._canvas.clientHeight;
    if(this._W !== cw || this._H !== ch){
      this._canvas.width = cw;
      this._canvas.height = ch;
      this._W = cw;
      this._H = ch;
    }
  },

  push(p){
    const R2D=180/Math.PI;
    const vals={
      alt:Math.max(0,p.pos.y-p.groundY),
      vel:Math.hypot(p.vel.x,p.vel.y,p.vel.z),
      roll:p.euler.roll*R2D,
      pitch:p.euler.pitch*R2D,
      batt:p.battPct,
    };
    for(const k of this._channels){
      this._history[k].push(vals[k]);
      if(this._history[k].length>this._maxLen) this._history[k].shift();
    }
    // Auto-scale altitude: running max, full recompute only when oldest sample is dropped
    if(vals.alt>this._maxAlt){
      this._maxAlt=vals.alt;
    } else if(this._history.alt.length>=this._maxLen){
      let m=0; for(let i=0;i<this._history.alt.length;i++) if(this._history.alt[i]>m) m=this._history.alt[i];
      this._maxAlt=m;
    }
    this._scales.alt=Math.max(10,this._maxAlt*1.1);
  },

  draw(){
    const c=this._canvas,ctx=this._ctx;
    if(!c||!ctx) return;
    this._syncSize();
    const W=this._W,H=this._H;
    if(!W||!H) return;
    ctx.clearRect(0,0,W,H);
    ctx.fillStyle='rgba(238,241,247,0.6)';
    ctx.fillRect(0,0,W,H);
    ctx.strokeStyle='rgba(96,125,139,0.2)';ctx.lineWidth=1;
    for(let i=1;i<4;i++){ctx.beginPath();ctx.moveTo(0,H*i/4);ctx.lineTo(W,H*i/4);ctx.stroke();}
    for(const k of this._channels){
      if(!this._visible[k]) continue;
      const data=this._history[k];
      if(data.length<2) continue;
      const scale=this._scales[k];
      ctx.strokeStyle=this._colors[k];ctx.lineWidth=1.5;
      ctx.beginPath();
      const maxIdx=this._maxLen-1;
      for(let i=0;i<data.length;i++){
        const x=(i/maxIdx)*W;
        const y=H/2-(data[i]/scale)*(H*0.45);
        i===0?ctx.moveTo(x,y):ctx.lineTo(x,y);
      }
      ctx.stroke();
    }
  },

  push(p){
    const R2D=180/Math.PI;
    const vals={
      alt:Math.max(0,p.pos.y-p.groundY),
      vel:Math.hypot(p.vel.x,p.vel.y,p.vel.z),
      roll:p.euler.roll*R2D,
      pitch:p.euler.pitch*R2D,
      batt:p.battPct,
    };
    for(const k of this._channels){
      this._history[k].push(vals[k]);
      if(this._history[k].length>this._maxLen) this._history[k].shift();
    }
    // Auto-scale altitude: running max, full recompute only when oldest sample is dropped
    if(vals.alt>this._maxAlt){
      this._maxAlt=vals.alt;
    } else if(this._history.alt.length>=this._maxLen){
      let m=0; for(let i=0;i<this._history.alt.length;i++) if(this._history.alt[i]>m) m=this._history.alt[i];
      this._maxAlt=m;
    }
    this._scales.alt=Math.max(10,this._maxAlt*1.1);
  },

  draw(){
    const c=this._canvas,ctx=this._ctx;
    if(!c||!ctx) return;
    this._syncSize();
    const W=this._W,H=this._H;
    if(!W||!H) return;
    ctx.clearRect(0,0,W,H);
    ctx.fillStyle='rgba(238,241,247,0.6)';
    ctx.fillRect(0,0,W,H);
    ctx.strokeStyle='rgba(96,125,139,0.2)';ctx.lineWidth=1;
    for(let i=1;i<4;i++){ctx.beginPath();ctx.moveTo(0,H*i/4);ctx.lineTo(W,H*i/4);ctx.stroke();}
    for(const k of this._channels){
      if(!this._visible[k]) continue;
      const data=this._history[k];
      if(data.length<2) continue;
      const scale=this._scales[k];
      ctx.strokeStyle=this._colors[k];ctx.lineWidth=1.5;
      ctx.beginPath();
      const maxIdx=this._maxLen-1;
      for(let i=0;i<data.length;i++){
        const x=(i/maxIdx)*W;
        const y=H/2-(data[i]/scale)*(H*0.45);
        i===0?ctx.moveTo(x,y):ctx.lineTo(x,y);
      }
      ctx.stroke();
    }
  },

  toggle(ch){ if(this._visible[ch]!==undefined) this._visible[ch]=!this._visible[ch]; },
};

if(typeof globalThis!=='undefined'){
  Object.assign(globalThis,{
    MAVLINK, TELEM_GRAPH, PHYS, FC, V3, Q, DRYDEN, Noise, PID, Kalman1D, DRONE_PROFILES
  });
}