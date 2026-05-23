/**
 * SPACEBORN Simulation Core v2.0 — Rebuilt rigid-body quadcopter + cascaded FC
 * Body frame (Y-up, nose +Z): X=right, Y=up/thrust, Z=forward
 * Motors [0..3] = FR, FL, BL, BR (quad-X, 45° arms)
 *
 * v2.0 Improvements:
 * - Full quaternion-based rigid body dynamics (no Euler singularities)
 * - Proper Euler rigid body equation: dω/dt = I⁻¹(τ - ω × Iω)
 * - Gyroscopic precession from spinning propellers
 * - Counter-rotating propeller reaction torques
 * - Nonlinear motor thrust curve with RPM saturation
 * - Asymmetric motor spin-up vs spin-down dynamics
 * - Multi-layer aerodynamics: drag, induced drag, rotor downwash, ground effect
 * - Dryden turbulence model for realistic wind gusts
 * - Sensor simulation: IMU noise, gyro drift, Kalman-filtered barometric altitude
 * - Improved battery model: voltage sag, internal resistance, OCV discharge curve
 * - ISA atmosphere: density correction by altitude
 * - Sub-stepping (4 physics substeps per frame) for stability
 * - Adaptive PID autotune based on drone mass/inertia/kT
 * - Improved cascaded angle→rate→motor PID with adaptive gain scheduling
 * - Translational damping for stable hover
 */
'use strict';

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
};

/* ─── Quaternion Math ─── */
const Q = {
  id:   () => ({ w:1, x:0, y:0, z:0 }),
  norm: (q) => { const l=Math.hypot(q.w,q.x,q.y,q.z)||1; return {w:q.w/l,x:q.x/l,y:q.y/l,z:q.z/l}; },
  conj: (q) => ({ w:q.w, x:-q.x, y:-q.y, z:-q.z }),
  mul:  (a,b) => ({
    w: a.w*b.w - a.x*b.x - a.y*b.y - a.z*b.z,
    x: a.w*b.x + a.x*b.w + a.y*b.z - a.z*b.y,
    y: a.w*b.y - a.x*b.z + a.y*b.w + a.z*b.x,
    z: a.w*b.z + a.x*b.y - a.y*b.x + a.z*b.w,
  }),
  /** Rotate vector by quaternion (world frame) */
  rotVec: (q, v) => {
    const u  = { x:q.x, y:q.y, z:q.z };
    const uv = V3.cross(u, v);
    const uuv= V3.cross(u, uv);
    return { x:v.x+2*(q.w*uv.x+uuv.x), y:v.y+2*(q.w*uv.y+uuv.y), z:v.z+2*(q.w*uv.z+uuv.z) };
  },
  /** Rotate vector by inverse quaternion (body frame → world inverse) */
  invRotVec: (q, v) => Q.rotVec(Q.conj(q), v),
  /** Integrate quaternion by angular velocity (body frame), dt seconds */
  integrate: (q, omega, dt) => {
    const wx=omega.x*dt*0.5, wy=omega.y*dt*0.5, wz=omega.z*dt*0.5;
    return Q.norm({
      w: q.w - q.x*wx - q.y*wy - q.z*wz,
      x: q.x + q.w*wx + q.y*wz - q.z*wy,
      y: q.y + q.w*wy + q.z*wx - q.x*wz,
      z: q.z + q.w*wz + q.x*wy - q.y*wx,
    });
  },
  /** Extract Euler angles — Y-up: pitch=X, yaw=Y, roll=Z */
  toEuler: (q) => {
    const sinp = 2*(q.w*q.x - q.y*q.z);
    const pitch = Math.abs(sinp)>=1 ? Math.sign(sinp)*(Math.PI/2) : Math.asin(sinp);
    const sinr  = 2*(q.w*q.z + q.x*q.y);
    const cosr  = 1 - 2*(q.y*q.y + q.z*q.z);
    const roll  = Math.atan2(sinr, cosr);
    const siny  = 2*(q.w*q.y + q.z*q.x);
    const cosy  = 1 - 2*(q.x*q.x + q.z*q.z);
    const yaw   = Math.atan2(siny, cosy);
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
  fbm(x,y,z,oct=4,pers=0.5,lac=2.0){
    let v=0,amp=1,freq=1,max=0;
    for(let i=0;i<oct;i++){v+=this.n(x*freq,y*freq,z*freq)*amp;max+=amp;amp*=pers;freq*=lac;}
    return v/max;
  },
};
Noise._init();

/* ─── Dryden Wind Turbulence Model ─── */
const DRYDEN = {
  _u:0, _v:0, _w:0, _dw:0,
  intensity: 0,
  update(dt, altAGL) {
    if(this.intensity<=0.001){this._u=this._v=this._w=0;return;}
    const h=Math.max(0.5, altAGL);
    const Lu=h/Math.pow(0.177+0.000823*h,1.2);
    const sigma=this.intensity*6.5;
    const Vu=8+this.intensity*12;
    const au=Vu/Lu, aw=Vu/h;
    const inv=1/Math.sqrt(dt);
    const wu=(Math.random()-0.5)*2*inv, wv=(Math.random()-0.5)*2*inv, ww=(Math.random()-0.5)*2*inv;
    this._u+=(-au*this._u+sigma*Math.sqrt(2*au)*wu)*dt;
    this._v+=(-au*this._v+sigma*0.85*Math.sqrt(2*au)*wv)*dt;
    this._dw+=(-2*aw*this._dw-aw*aw*this._w+sigma*0.7*Math.sqrt(3)*aw*aw*ww)*dt;
    this._w+=this._dw*dt;
    const mx=sigma*3.5;
    this._u=Math.max(-mx,Math.min(mx,this._u));
    this._v=Math.max(-mx,Math.min(mx,this._v));
    this._w=Math.max(-mx,Math.min(mx,this._w));
  },
  get(){return {x:this._v, y:this._w, z:this._u};}
};

/* ─── PID Controller — derivative-on-measurement, filtered D, anti-windup ─── */
class PID {
  constructor(p,i,d,iLimit=50,dCutoffHz=30){
    this.p=p; this.i=i; this.d=d;
    this.iLimit=iLimit; this.dCutoffHz=dCutoffHz;
    this.iErr=0; this.prevMeas=0; this.dFilt=0; this._first=true;
  }
  reset(){this.iErr=0; this.prevMeas=0; this.dFilt=0; this._first=true;}
  update(setpoint, measured, dt){
    if(dt<=0) return 0;
    if(this._first){this.prevMeas=measured; this._first=false;}
    const err=setpoint-measured;
    this.iErr=Math.max(-this.iLimit, Math.min(this.iLimit, this.iErr+err*dt));
    const dMeas=(measured-this.prevMeas)/dt;
    const alpha=1-Math.exp(-2*Math.PI*this.dCutoffHz*dt);
    this.dFilt+=alpha*(dMeas-this.dFilt);
    this.prevMeas=measured;
    return this.p*err + this.i*this.iErr - this.d*this.dFilt;
  }
}

/* ─── Kalman Filter 1D ─── */
class Kalman1D {
  constructor(Q=0.001, R=0.1){this.Q=Q; this.R=R; this.x=0; this.P=1; this._init=false;}
  update(z){
    if(!this._init){this.x=z; this._init=true; return z;}
    this.P+=this.Q;
    const K=this.P/(this.P+this.R);
    this.x+=K*(z-this.x);
    this.P*=(1-K);
    return this.x;
  }
}

/* ─── Drone Profiles ─── */
const DRONE_PROFILES = {
  racing5: {
    label:'5" Racing Quad', mass:1.24,
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
    label:'Explorer 6" Hover', mass:1.68,
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
  // Quad-X: FR(CW)=+1, FL(CCW)=-1, BL(CW)=-1, BR(CCW)=+1
  motorDir:[1,-1,-1,1],
  motorLabels:['FR','FL','BL','BR'],

  pos:V3.zero(), vel:V3.zero(), acc:V3.zero(),
  quat:Q.id(), angVel:V3.zero(),
  gyro:V3.zero(), accelBody:V3.zero(),

  motorRPM:[0,0,0,0],
  motorCmd:[0,0,0,0],
  motorCmdFiltered:[0,0,0,0],

  grounded:true, crashed:false,
  homePos:null, groundY:0, colliders:[],

  battPct:100, battVoltage:16.8, battCapacity:0, currentDraw:0,
  euler:{roll:0, pitch:0, yaw:0},
  windVec:V3.zero(), windGust:0,
  hoverThrottle:0.5,
  turbulenceIntensity:0,

  _kAlt: new Kalman1D(0.005, 0.08),
  _kVy:  new Kalman1D(0.01, 0.15),
  _altEstimate: 0,
  _gyroDrift:{x:0.002, y:0.001, z:0.0015},
  _prevPos:V3.zero(), _prevQuat:Q.id(),

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
    const rpmH=Math.sqrt((this.mass*this.GRAVITY)/(4*this.kT));
    this.hoverRPM=rpmH;
    this.hoverThrottle=Math.max(0.02,Math.min(0.92,rpmH/this.maxRPM));
  },

  reset(pos){
    this.pos=pos||{x:0,y:0.15,z:0};
    this._prevPos=V3.clone(this.pos);
    this.vel=V3.zero(); this.acc=V3.zero();
    this.quat=Q.id(); this._prevQuat=Q.id();
    this.angVel=V3.zero(); this.gyro=V3.zero(); this.accelBody=V3.zero();
    this.motorRPM=[0,0,0,0]; this.motorCmd=[0,0,0,0]; this.motorCmdFiltered=[0,0,0,0];
    this.grounded=true; this.crashed=false;
    this.euler={roll:0,pitch:0,yaw:0};
    this.battPct=100; this.battVoltage=4.2*this.cells; this.battCapacity=0; this.currentDraw=0;
    this._kAlt=new Kalman1D(0.005,0.08); this._kVy=new Kalman1D(0.01,0.15);
    if(typeof State!=='undefined') State.motorDamage=[0,0,0,0];
    this._recomputeHover();
  },

  saveHome(){ this.homePos=V3.clone(this.pos); },

  _sanitize(){
    const bad=v=>!Number.isFinite(v);
    if(bad(this.pos.x)||bad(this.pos.y)||bad(this.pos.z)){
      this.reset({x:0,y:0.2,z:0});
      if(typeof FC!=='undefined') FC.resetPIDs();
      return false;
    }
    if(bad(this.angVel.x)||bad(this.angVel.y)||bad(this.angVel.z)){this.angVel=V3.zero();this.quat=Q.id();}
    return true;
  },

  /** ISA atmosphere: air density by altitude */
  _airDensity(alt){
    const T0=288.15,L=0.0065,P0=101325,R=287.058,g=9.80665;
    const h=Math.max(0,alt); const T=T0-L*h;
    return (P0*Math.pow(T/T0,g/(L*R)))/(R*T);
  },

  /** Nonlinear motor thrust with high-RPM saturation */
  _motorThrust(rpm){
    const T=this.kT*rpm*rpm;
    const sat=1-Math.max(0,(rpm/this.maxRPM-0.85)*0.4);
    return T*sat;
  },

  step(dtFull){
    dtFull=Math.max(0.0005,Math.min(0.04,dtFull));
    if(!this._sanitize()) return;
    this._prevPos=V3.clone(this.pos);
    this._prevQuat={...this.quat};
    if(this.crashed){this._crashSettle(dtFull); return;}
    // 4 physics substeps for stability
    const SUB=4, dt=dtFull/SUB;
    for(let s=0;s<SUB;s++) this._substep(dt);
    this._updateSensors(dtFull);
  },

  _substep(dt){
    const fullV=this.cells*4.2;
    const internalR=0.025*this.cells;
    const rawVF=Math.max(0.5,Math.min(1,this.battVoltage/fullV));

    // Motor dynamics: asymmetric spin-up vs spin-down
    for(let i=0;i<4;i++){
      const dmg=(typeof State!=='undefined')?State.motorDamage[i]:0;
      const target=this.motorCmd[i]*(1-dmg)*rawVF;
      const escA=1-Math.exp(-dt/(this.escDelay+0.001));
      this.motorCmdFiltered[i]+=(target-this.motorCmdFiltered[i])*escA;
      const tRPM=this.motorCmdFiltered[i]*this.maxRPM;
      const tau=tRPM>this.motorRPM[i]?this.motorTau:this.motorTau*1.6;
      const rpmA=1-Math.exp(-dt/tau);
      this.motorRPM[i]+=(tRPM-this.motorRPM[i])*rpmA;
      this.motorRPM[i]=Math.max(0,Math.min(this.maxRPM,this.motorRPM[i]));
    }

    const T=this.motorRPM.map(r=>this._motorThrust(r));
    const totalThrust=T[0]+T[1]+T[2]+T[3];
    const L=this.armLen;

    // Torques in body frame (Quad-X: [FR=0, FL=1, BL=2, BR=3])
    const tauPitch=L*(T[2]+T[3]-T[0]-T[1]);  // +: nose up
    const tauRoll =L*(T[0]+T[3]-T[1]-T[2]);  // +: right bank
    const tauYaw=this.kQ*(
      this.motorDir[0]*this.motorRPM[0]**2 + this.motorDir[1]*this.motorRPM[1]**2 +
      this.motorDir[2]*this.motorRPM[2]**2 + this.motorDir[3]*this.motorRPM[3]**2
    );

    // Gyroscopic precession from net propeller angular momentum
    const omegaNet=this.motorDir.reduce((s,d,i)=>s+d*this.motorRPM[i],0)*(2*Math.PI/60);
    const Hgyro=this.propInertia*omegaNet;
    const tauGyroPitch= this.angVel.z*Hgyro;
    const tauGyroRoll =-this.angVel.x*Hgyro;

    // Euler rigid body equation: dω/dt = I⁻¹(τ - ω×(Iω))
    const {Ixx,Iyy,Izz}=this;
    const wx=this.angVel.x, wy=this.angVel.y, wz=this.angVel.z;
    const gyroX=wy*(Izz*wz) - wz*(Iyy*wy);
    const gyroY=wz*(Ixx*wx) - wx*(Izz*wz);
    const gyroZ=wx*(Iyy*wy) - wy*(Ixx*wx);
    const ad=this.angDrag;
    const alphaPitch=(tauPitch+tauGyroPitch-gyroX)/Ixx - ad*wx*Math.abs(wx);
    const alphaRoll =(tauRoll +tauGyroRoll -gyroZ)/Izz - ad*wz*Math.abs(wz);
    const alphaYaw  =(tauYaw              -gyroY)/Iyy - ad*0.7*wy*Math.abs(wy);

    this.angVel.x+=alphaPitch*dt;
    this.angVel.z+=alphaRoll *dt;
    this.angVel.y+=alphaYaw  *dt;

    // Soft rate clamping
    const mr=this.maxRate;
    const softClamp=(v,max)=>Math.abs(v)>max?Math.sign(v)*(max+(Math.abs(v)-max)*0.08):v;
    this.angVel.x=softClamp(this.angVel.x,mr.pitch);
    this.angVel.z=softClamp(this.angVel.z,mr.roll);
    this.angVel.y=softClamp(this.angVel.y,mr.yaw);

    this.quat=Q.integrate(this.quat,this.angVel,dt);
    this.euler=Q.toEuler(this.quat);

    // ── Translational dynamics ──────────────────────────────────────
    let thrustW=Q.rotVec(this.quat,{x:0,y:totalThrust,z:0});

    // Ground effect (increased lift near ground)
    const h=this.pos.y-this.groundY;
    const rotD=(this.droneVisual.rotorRadius||0.09)*2;
    const geH=rotD*1.8;
    if(h>0&&h<geH){const gr=h/rotD; thrustW=V3.scale(thrustW,1+0.18/(gr*gr+0.01));}

    this.airDens=this._airDensity(this.pos.y);
    const vel=this.vel;
    const vMag=V3.len(vel);

    // Aerodynamic drag (quadratic, Cd varies with tilt)
    let drag=V3.zero();
    if(vMag>0.01){
      const qDyn=0.5*this.airDens*vMag*vMag;
      const Cdeff=this.dragCd*(1+0.15*Math.sin(Math.abs(this.euler.pitch))+0.15*Math.sin(Math.abs(this.euler.roll)));
      drag=V3.scale(V3.norm(vel),-qDyn*this.dragArea*Cdeff);
    }

    // Translational damping from rotor edgewise drag (helps hover stability)
    const transDamp=V3.scale(vel,-this.mass*0.16);

    // Dryden turbulence + configured wind
    DRYDEN.intensity=this.turbulenceIntensity;
    DRYDEN.update(dt,Math.max(0.5,h));
    const gust=DRYDEN.get();
    const totalWind=V3.add(this.windVec,gust);
    const relWind=V3.sub(totalWind,vel);
    const rwMag=V3.len(relWind);
    let windForce=V3.zero();
    if(rwMag>0.01){
      windForce=V3.scale(V3.norm(relWind),0.5*this.airDens*rwMag*rwMag*this.dragArea*this.dragCd*0.72);
    }

    const gravity={x:0,y:-this.mass*this.GRAVITY,z:0};
    const fNet=V3.add(V3.add(V3.add(V3.add(thrustW,gravity),drag),windForce),transDamp);

    this.acc=V3.scale(fNet,1/this.mass);
    this.accelBody=Q.invRotVec(this.quat,this.acc);

    // Velocity Verlet integration
    let v2=V3.add(vel,V3.scale(this.acc,dt));
    const vl=V3.len(v2); if(vl>32) v2=V3.scale(V3.norm(v2),32);
    let newPos=V3.add(this.pos,V3.scale(V3.add(vel,v2),0.5*dt));
    this.vel=v2;

    // Ground collision
    const minY=this.groundY+0.13;
    if(newPos.y<minY){
      const impact=Math.abs(this.vel.y);
      if(impact>4.5&&(typeof State!=='undefined')&&State.armed){
        this._doCrash(impact); newPos.y=minY;
      } else {
        newPos.y=minY;
        if(this.vel.y<0) this.vel.y=-this.vel.y*0.18;
        const fric=Math.exp(-7*dt);
        this.vel.x*=fric; this.vel.z*=fric;
        const af=Math.exp(-18*dt);
        this.angVel.x*=af; this.angVel.z*=af; this.angVel.y*=af;
        // Gravity-align on ground
        this.angVel.x-=this.euler.pitch*8*dt;
        this.angVel.z-=this.euler.roll *8*dt;
        this.grounded=true;
      }
    } else { this.grounded=false; }

    // World boundary
    newPos.x=Math.max(-250,Math.min(250,newPos.x));
    newPos.z=Math.max(-250,Math.min(250,newPos.z));
    newPos.y=Math.min(180,newPos.y);
    this.pos=newPos;

    // Colliders
    const hit=this._checkColliders(newPos);
    if(hit){
      const spd=V3.len(this.vel);
      if(spd>2.5&&(typeof State!=='undefined')&&State.armed){this._doCrash(spd);}
      else{
        const n=hit.normal||{x:0,y:1,z:0};
        const vn=V3.dot(this.vel,n);
        if(vn<0) this.vel=V3.add(this.vel,V3.scale(n,-1.3*vn));
        this.pos=V3.add(newPos,V3.scale(n,0.05));
      }
    }

    // Battery model: power = T * v_induced, current = P/V
    const rotorA=Math.PI*(this.droneVisual.rotorRadius||0.09)**2*4+0.001;
    const v_ind=Math.sqrt(totalThrust/(2*this.airDens*rotorA));
    const P_mech=totalThrust*v_ind*1.18;  // includes motor/ESC losses
    this.currentDraw=P_mech/Math.max(this.battVoltage,1);
    const internalDrop=this.currentDraw*internalR;
    const ahStep=(this.currentDraw*dt)/3600;
    this.battCapacity=Math.min(this.battTotalAh,this.battCapacity+ahStep);
    this.battPct=Math.max(0,100*(1-this.battCapacity/this.battTotalAh));
    const soc=this.battPct/100;
    const ocv=3.3+0.85*soc-0.2*(1-soc)*(1-soc);
    this.battVoltage=Math.max(this.cells*3.3,Math.min(this.cells*4.2,this.cells*ocv-internalDrop));
  },

  _updateSensors(dt){
    // IMU: gyro with realistic (but lower) noise — high noise feeds D-term oscillation
    const n=0.0012;
    const d=this._gyroDrift;
    this.gyro={
      x:this.angVel.x+(Math.random()-0.5)*n*2+d.x,
      y:this.angVel.y+(Math.random()-0.5)*n*2+d.y,
      z:this.angVel.z+(Math.random()-0.5)*n*2+d.z,
    };
    // Barometric altitude with Kalman smoothing
    const bNoise=0.04+this.turbulenceIntensity*0.25;
    const rawAlt=(this.pos.y-this.groundY)+(Math.random()-0.5)*bNoise;
    this._altEstimate=this._kAlt.update(rawAlt);
    this.euler=Q.toEuler(this.quat);
  },

  _checkColliders(pos){
    for(const c of this.colliders){
      if(pos.x>c.min.x&&pos.x<c.max.x&&pos.y>c.min.y&&pos.y<c.max.y&&pos.z>c.min.z&&pos.z<c.max.z) return c;
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
    this.angVel.x*=0.90; this.angVel.z*=0.90; this.angVel.y*=0.93;
    this.quat=Q.integrate(this.quat,this.angVel,dt);
    this.euler=Q.toEuler(this.quat);
    if(this.pos.y>this.groundY+0.13){
      this.vel.y-=this.GRAVITY*dt; this.pos.y+=this.vel.y*dt;
    } else {
      this.pos.y=this.groundY+0.13; this.vel=V3.zero();
    }
    for(let i=0;i<4;i++) this.motorRPM[i]*=0.90;
  },
};

/* ─── Flight Controller ─── */
const FC = {
  mode:'stabilized',
  motorMixGain:0.13,
  maxAngleRate:3.2,
  maxAltVelRate:2.5,

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
  posNPID:   new PID(0.65, 0.015,0.18, 0.35, 8),
  posEPID:   new PID(0.65, 0.015,0.18, 0.35, 8),

  altTarget:null, posTarget:null, rthPhase:0, rthClimbAlt:10,
  gains:{rp:0.042, ri:0.000, rd:0.0018, yp:0.065, ap:1.6, angleP:2.2},

  _adaptiveGainFactor(){
    // Reduce authority at high tilt to avoid over-correction
    const tilt = Math.sqrt(PHYS.euler.pitch**2 + PHYS.euler.roll**2);
    const tiltFactor = Math.max(0.45, 1.0 - tilt * 0.28);
    // Extra damping when angular rates are near zero (hover buzz suppression)
    const rateAmp = Math.hypot(PHYS.angVel.x, PHYS.angVel.z);
    const rateFactor = Math.min(1.0, 0.55 + rateAmp * 2.5);
    return tiltFactor * rateFactor;
  },

  autoTuneFromPhysics(){
    const m=PHYS.mass, L=PHYS.armLen, Ixx=PHYS.Ixx, Iyy=PHYS.Iyy;
    const kT=PHYS.kT, maxRPM=PHYS.maxRPM;
    const maxTorque=kT*maxRPM*maxRPM*L;

    // Rate P: target ~30% of critically-damped limit; D kept small (noise amplification)
    // Critical: rp * (maxTorque / Ixx) < (2π * bandwidth_hz)^2 / motorMixGain
    const rp = Math.min(0.12, Math.max(0.025, Ixx * 3.5 / (maxTorque + 0.001)));
    const rd = rp * 0.038;   // D≈4% of P — just enough to damp, not oscillate
    const ri = 0.0;           // Rate I off — cascaded angle loop handles steady-state
    const yp = Math.min(0.12, Math.max(0.03, Iyy * 2.8 / (kT * maxRPM * maxRPM * L * 4 + 0.001)));

    // Angle P: bandwidth ~2 Hz feels stable; avoid > 3 or it fights rate loop
    const angleP = Math.max(1.8, Math.min(3.0, 1.8 + 1.8 * L));

    // Alt P: conservative, scales with mass (heavier = needs more authority)
    const ap = Math.max(1.0, Math.min(2.2, 0.9 + 0.45 * m));

    // motorMixGain: keep low for heavy/large drones, modest for small
    this.motorMixGain = Math.max(0.09, Math.min(0.16, 0.08 + L * 0.22));
    // maxAngleRate: slower for cinematic/heavy, faster only for micro
    this.maxAngleRate = Math.max(2.0, Math.min(4.0, 2.0 + (0.34 - m * 0.04) * 5));

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
    // Rate loop
    this.ratePID.pitch.p=g.rp; this.ratePID.pitch.i=g.ri||0; this.ratePID.pitch.d=g.rd;
    this.ratePID.roll.p =g.rp; this.ratePID.roll.i =g.ri||0; this.ratePID.roll.d =g.rd;
    this.ratePID.yaw.p  =g.yp; this.ratePID.yaw.i  =0.012;
    // Angle loop — D always zero (rate loop damps)
    if(g.angleP!=null){ this.anglePID.pitch.p=g.angleP; this.anglePID.roll.p=g.angleP; }
    this.anglePID.pitch.d=0; this.anglePID.roll.d=0;
    // Alt loop
    this.altPID.p=g.ap;
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
    if(m==='althold'||m==='gpshold'||m==='rth') this.altTarget=PHYS.pos.y-PHYS.groundY;
    if(m==='gpshold'||m==='rth') this.posTarget={x:PHYS.pos.x,z:PHYS.pos.z};
    if(m==='rth'){
      this.rthPhase=0;
      this.posTarget=PHYS.homePos?{x:PHYS.homePos.x,z:PHYS.homePos.z}:{x:0,z:0};
    }
  },

  update(dt, input){
    if(PHYS.crashed||((typeof State!=='undefined')&&!State.armed)) return [0,0,0,0];
    let thrCmd=input.throttle, pitchSP=0, rollSP=0;
    const maxTilt=PHYS.maxTiltRad;
    let yawRateCmd=input.yaw*PHYS.maxRate.yaw;
    const e=PHYS.euler;

    if(this.mode==='acro'){
      const mr=PHYS.maxRate;
      return this._rateLoop(dt,thrCmd,input.pitch*mr.pitch,input.roll*mr.roll,yawRateCmd);
    }
    if(this.mode==='stabilized'||this.mode==='angle'){
      pitchSP=input.pitch*maxTilt*0.60;
      rollSP =input.roll *maxTilt*0.60;
    }
    if(this.mode==='althold'||this.mode==='gpshold'||this.mode==='rth'){
      const stickDead=0.045;
      const hov=PHYS.hoverThrottle;
      // Pass pitch/roll stick through in althold so W/A/S/D move the drone
      if(this.mode==='althold'){
        pitchSP=input.pitch*maxTilt*0.60;
        rollSP =input.roll *maxTilt*0.60;
      }
      if(Math.abs(input.throttle-0.5)<stickDead){
        if(this.altTarget==null) this.altTarget=PHYS.pos.y-PHYS.groundY;
        // If we just came out of manual stick, reset altVelPID to avoid D-term spike
        if(this._altManualLastFrame){
          this.altVelPID.reset();
          this.altPID.reset();
          this._altManualLastFrame=false;
        }
        const velSP=Math.max(-this.maxAltVelRate,Math.min(this.maxAltVelRate,
          this.altPID.update(this.altTarget,PHYS._altEstimate,dt)));
        thrCmd=Math.max(0,Math.min(0.97,hov+this.altVelPID.update(velSP,PHYS.vel.y,dt)));
      } else {
        this._altManualLastFrame=true;
        this.altTarget=PHYS.pos.y-PHYS.groundY;
        // Proper linear mapping: stick center=hoverThrottle, down=0, up=0.97
        const t=input.throttle;
        thrCmd=t<=0.5 ? (t/0.5)*hov : hov+((t-0.5)/0.5)*(0.97-hov);
      }
    }
    if(this.mode==='gpshold'){
      if(Math.abs(input.pitch)<0.08&&Math.abs(input.roll)<0.08){
        if(this.posTarget==null) this.posTarget={x:PHYS.pos.x,z:PHYS.pos.z};
        const dN=this.posTarget.z-PHYS.pos.z, dE=this.posTarget.x-PHYS.pos.x;
        const cy=Math.cos(e.yaw),sy=Math.sin(e.yaw);
        pitchSP=Math.max(-maxTilt*0.5,Math.min(maxTilt*0.5,this.posNPID.update(0,-(cy*dN+sy*dE),dt)));
        rollSP =Math.max(-maxTilt*0.5,Math.min(maxTilt*0.5,this.posEPID.update(0,-(-sy*dN+cy*dE),dt)));
      } else {
        this.posTarget={x:PHYS.pos.x,z:PHYS.pos.z};
        pitchSP=input.pitch*maxTilt*0.65; rollSP=input.roll*maxTilt*0.65;
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
          pitchSP=Math.max(-maxTilt*0.6,Math.min(maxTilt*0.6,-(cy*dN+sy*dE)*0.14*spd));
          rollSP =Math.max(-maxTilt*0.6,Math.min(maxTilt*0.6,-(-sy*dN+cy*dE)*0.14*spd));
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
      } else return [0,0,0,0];
    }
    return this._angleLoop(dt,thrCmd,pitchSP,rollSP,yawRateCmd);
  },

  _angleLoop(dt,thrCmd,pitchSP,rollSP,yawRateCmd){
    const e=PHYS.euler, cap=this.maxAngleRate, af=this._adaptiveGainFactor();
    const pitchRateCmd=Math.max(-cap,Math.min(cap,this.anglePID.pitch.update(pitchSP,e.pitch,dt)*af));
    const rollRateCmd =Math.max(-cap,Math.min(cap,this.anglePID.roll.update(rollSP, e.roll, dt)*af));
    return this._rateLoop(dt,thrCmd,pitchRateCmd,rollRateCmd,yawRateCmd);
  },

  _rateLoop(dt,thrCmd,pitchRateCmd,rollRateCmd,yawRateCmd){
    const g=PHYS.gyro;
    const af=this._adaptiveGainFactor();
    let pitchOut=this.ratePID.pitch.update(pitchRateCmd,g.x,dt) * af;
    let rollOut  =this.ratePID.roll.update(rollRateCmd, g.z,dt) * af;
    let yawOut   =this.ratePID.yaw.update(yawRateCmd,  g.y,dt);
    const mg=this.motorMixGain;
    const lim=0.18;           // was 0.28 — tighter authority prevents over-correction spikes
    const ylim=0.12;
    pitchOut=Math.max(-lim, Math.min(lim, pitchOut*mg));
    rollOut =Math.max(-lim, Math.min(lim, rollOut *mg));
    yawOut  =Math.max(-ylim,Math.min(ylim,yawOut  *mg));
    if(typeof DEBUG!=='undefined') DEBUG.recordPID(pitchRateCmd,g.x,rollRateCmd,g.z,yawRateCmd,g.y);
    const hover=PHYS.hoverThrottle;
    const modeAlt=this.mode==='althold'||this.mode==='gpshold'||this.mode==='rth';
    // In manual modes: centre stick = hover, full up = hover+40%, full down = 0
    let base = modeAlt ? thrCmd : hover + (Math.max(0,Math.min(1,thrCmd))-0.5)*2*0.38;
    base=Math.max(0.02, Math.min(0.97, base));
    const m=[
      base - pitchOut + rollOut  + yawOut,
      base - pitchOut - rollOut  - yawOut,
      base + pitchOut - rollOut  + yawOut,
      base + pitchOut + rollOut  - yawOut,
    ];
    return this._mixMotors(m);
  },

  _mixMotors(m){
    let out=m.slice();
    const max=Math.max(...out);
    if(max>1) out=out.map(v=>v/max);
    return out.map(v=>Math.max(0,Math.min(1,v)));
  },
};

/* ─── Input Handler — Mode 2 RC Layout ───────────────────────────────
 *
 *  KEYBOARD — Mode 2 (left stick = Throttle/Yaw, right stick = Pitch/Roll)
 *  ─────────────────────────────────────────────────────────────────────
 *  Left stick (Throttle / Yaw):
 *    W / S        → Throttle Up / Down   (replaces Shift/Ctrl)
 *    A / D        → Yaw Left / Right     (replaces Q/E)
 *
 *  Right stick (Pitch / Roll):
 *    ↑ / ↓        → Pitch Forward / Back
 *    ← / →        → Roll Left / Right
 *
 *  Legacy / alternate throttle:
 *    Shift / Ctrl → Throttle Up / Down   (still works)
 *
 *  Action hotkeys:
 *    Space        → Arm / Disarm toggle
 *    T            → Takeoff
 *    G            → GPS Hold toggle
 *    H            → Hover (Alt Hold)
 *    R            → Return To Home
 *    X / Escape   → Emergency Stop
 *    F            → FPV camera toggle
 *    C / Tab      → Cycle camera
 *    M            → Add waypoint
 *    1–5          → Flight mode  1=Stabilized 2=Angle 3=Acro 4=AltHold 5=GPSHold
 *    [ / ]        → Sim speed down / up
 *    P            → Pause / Resume
 *
 *  GAMEPAD — standard dual-stick RC layout
 *    Left X  → Yaw    Left Y  → Throttle (inverted)
 *    Right X → Roll   Right Y → Pitch    (inverted)
 * ─────────────────────────────────────────────────────────────────────
 */
const INPUT = {
  _keys:{}, _thrRaw:0, sensitivity:0.26, expo:0.38, deadband:0.05,
  _gamepad:null, pitch:0, roll:0, yaw:0, throttle:0,
  // Virtual joystick values set by on-screen sticks (index.html)
  _vjLeft:{x:0,y:0}, _vjRight:{x:0,y:0}, _vjActive:false,

  init(){
    window.addEventListener('keydown',(e)=>{
      if(e.target.tagName==='INPUT'||e.target.tagName==='SELECT') return;
      this._keys[e.code]=true;

      // ── Single-shot action keys ────────────────────────────────────
      if(e.repeat) return;
      switch(e.code){
        case 'Space':
          e.preventDefault();
          if(typeof toggleArm==='function') toggleArm();
          break;
        case 'KeyT':
          if(typeof takeoff==='function') takeoff();
          break;
        case 'KeyR':
          if(typeof returnHome==='function') returnHome();
          break;
        case 'KeyH':
          if(typeof doHover==='function') doHover();
          break;
        case 'KeyG':
          if(typeof setFlightMode==='function') setFlightMode('gpshold');
          break;
        case 'KeyX':
        case 'Escape':
          if(typeof emergStop==='function') emergStop();
          break;
        case 'KeyF':
          // Toggle between FPV and previous cam
          if(typeof setCamera==='function'){
            if(typeof _camMode_global!=='undefined'&&_camMode_global==='fpv') setCamera('third');
            else setCamera('fpv');
          }
          break;
        case 'KeyC':
        case 'Tab':
          e.preventDefault();
          if(typeof cycleCamera==='function') cycleCamera();
          break;
        case 'KeyM':
          if(typeof addWaypoint==='function') addWaypoint();
          break;
        // Flight mode shortcuts 1–5
        case 'Digit1': if(typeof setFlightMode==='function') setFlightMode('stabilized'); break;
        case 'Digit2': if(typeof setFlightMode==='function') setFlightMode('angle');      break;
        case 'Digit3': if(typeof setFlightMode==='function') setFlightMode('acro');       break;
        case 'Digit4': if(typeof setFlightMode==='function') setFlightMode('althold');    break;
        case 'Digit5': if(typeof setFlightMode==='function') setFlightMode('gpshold');    break;
      }
    });
    window.addEventListener('keyup',(e)=>{this._keys[e.code]=false;});
    window.addEventListener('blur',()=>{
      this._keys={};
      this.pitch=0; this.roll=0; this.yaw=0;
    });
    window.addEventListener('gamepadconnected',(e)=>{
      this._gamepad=e.gamepad;
      if(typeof UI!=='undefined') UI.toast('🎮 Controller: '+e.gamepad.id.substring(0,28));
    });
    window.addEventListener('gamepaddisconnected',()=>{
      this._gamepad=null;
      if(typeof UI!=='undefined') UI.toast('🎮 Controller disconnected');
    });
  },

  _expo(v){const e=this.expo; return v*(1-e)+Math.sign(v)*v*v*e;},
  _deadzone(v,d){if(Math.abs(v)<d)return 0;return(v-Math.sign(v)*d)/(1-d);},

  update(dt){
    const K=this._keys, s=this.sensitivity;
    let gpActive=false;

    // ── Gamepad (highest priority) ───────────────────────────────────
    if(this._gamepad){
      const gp=navigator.getGamepads()[this._gamepad.index];
      if(gp&&gp.axes.length>=4){
        gpActive=true;
        // Left stick: Y=throttle (inverted), X=yaw
        const rawThrY = this._deadzone(-gp.axes[1], 0.05);
        this._thrRaw = Math.max(0, Math.min(1, (rawThrY+1)*0.5));
        this.yaw   = this._expo(this._deadzone(gp.axes[0],  0.05)) * s;
        // Right stick: Y=pitch (inverted), X=roll
        this.pitch = this._expo(this._deadzone(-gp.axes[3], 0.05)) * s;
        this.roll  = this._expo(this._deadzone( gp.axes[2], 0.05)) * s;
      }
    }

    // ── Virtual on-screen joysticks ──────────────────────────────────
    if(!gpActive && this._vjActive){
      // Left VJ: Y=throttle (up=-1..down=+1), X=yaw
      const thrRate = -this._vjLeft.y * 0.9 * s;
      this._thrRaw = Math.max(0, Math.min(1, this._thrRaw + thrRate * dt));
      this.yaw = this._expo(this._vjLeft.x) * s;
      // Right VJ: Y=pitch, X=roll
      const tau = 1 - Math.exp(-dt*8);
      const tPitch = this._expo(this._vjRight.y) * s;
      const tRoll  = this._expo(this._vjRight.x) * s;
      this.pitch += (tPitch - this.pitch) * tau;
      this.roll  += (tRoll  - this.roll)  * tau;
      gpActive = true;
    }

    // ── Keyboard (Mode 2) ─────────────────────────────────────────────
    if(!gpActive){
      // Left stick: W/S = throttle, A/D = yaw
      if(K['KeyW']||K['ShiftLeft']||K['ShiftRight'])
        this._thrRaw = Math.min(1, this._thrRaw + dt*0.85*s);
      else if(K['KeyS']||K['ControlLeft']||K['ControlRight'])
        this._thrRaw = Math.max(0, this._thrRaw - dt*0.85*s);

      const yt = ((K['KeyA']?-1:0) + (K['KeyD']?1:0)) * s;

      // Right stick: Arrow keys = pitch/roll
      const pt = ((K['ArrowUp']?1:0)   + (K['ArrowDown']?-1:0))  * s;
      const rt = ((K['ArrowRight']?1:0) + (K['ArrowLeft']?-1:0))  * s;

      const tau = 1 - Math.exp(-dt*6);
      this.pitch += (this._expo(pt) - this.pitch) * tau;
      this.roll  += (this._expo(rt) - this.roll)  * tau;
      this.yaw   += (this._expo(yt) - this.yaw)   * tau;
    }

    this.throttle = this._thrRaw;

    // Sync throttle slider
    const slEl = document.getElementById('throttle-slider');
    if(slEl && !slEl._dragging){
      slEl.value = Math.round(this._thrRaw*100);
      const tv = document.getElementById('thr-val');
      if(tv) tv.textContent = Math.round(this._thrRaw*100)+'%';
    }

    // Update on-screen stick visualizer
    if(typeof _updateStickViz==='function') _updateStickViz();
  },

  get(){
    return {
      throttle: Math.max(0, Math.min(1, this.throttle)),
      pitch:    Math.max(-1, Math.min(1, this.pitch)),
      roll:     Math.max(-1, Math.min(1, this.roll)),
      yaw:      Math.max(-1, Math.min(1, this.yaw)),
    };
  },
};

/* ─── Blackbox Logger ─── */
const BLACKBOX = {
  _log:[], _max:6000, recording:false,
  start(){this._log=[];this.recording=true;},
  stop(){this.recording=false;},
  tick(t){
    if(!this.recording) return;
    const p=PHYS;
    this._log.push({
      t,px:p.pos.x,py:p.pos.y,pz:p.pos.z,
      roll:p.euler.roll,pitch:p.euler.pitch,yaw:p.euler.yaw,
      gx:p.gyro.x,gy:p.gyro.y,gz:p.gyro.z,
      vx:p.vel.x,vy:p.vel.y,vz:p.vel.z,
      m0:p.motorCmd[0],m1:p.motorCmd[1],m2:p.motorCmd[2],m3:p.motorCmd[3],
      rpm0:p.motorRPM[0],rpm1:p.motorRPM[1],rpm2:p.motorRPM[2],rpm3:p.motorRPM[3],
      batt:p.battVoltage,curr:p.currentDraw,
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
    const alts=this._log.map(e=>e.py);
    const vels=this._log.map(e=>Math.hypot(e.vx,e.vy,e.vz));
    return {
      duration:(this._log[this._log.length-1].t-this._log[0].t).toFixed(1),
      samples:this._log.length,
      maxAlt:Math.max(...alts).toFixed(1),
      avgVel:(vels.reduce((a,b)=>a+b,0)/vels.length).toFixed(1),
      maxVel:Math.max(...vels).toFixed(1),
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

/* ─── Global exports ─── */
if(typeof globalThis!=='undefined'){
  Object.assign(globalThis,{V3,Q,Noise,DRYDEN,PID,Kalman1D,DRONE_PROFILES,PHYS,FC,INPUT,BLACKBOX,DEBUG});
}

/* ─── MAVLink v1/v2 Export ─── */
const MAVLINK = {
  // MAVLink message IDs
  MSG_HEARTBEAT: 0,
  MSG_SYS_STATUS: 1,
  MSG_ATTITUDE: 30,
  MSG_LOCAL_POSITION_NED: 32,
  MSG_RC_CHANNELS_RAW: 35,
  MSG_VFR_HUD: 74,
  MSG_STATUSTEXT: 253,

  _seq: 0,

  /** CRC-16/MCRF4XX as used in MAVLink */
  _crc16(buf) {
    let crc = 0xFFFF;
    for (let i = 0; i < buf.length; i++) {
      let tmp = buf[i] ^ (crc & 0xFF);
      tmp = (tmp ^ (tmp << 4)) & 0xFF;
      crc = ((crc >> 8) ^ (tmp << 8) ^ (tmp << 3) ^ (tmp >> 4)) & 0xFFFF;
    }
    return crc;
  },

  /** MAVLink extra CRC seeds per message ID */
  _crcExtra: {
    0: 50,   // HEARTBEAT
    1: 124,  // SYS_STATUS
    30: 39,  // ATTITUDE
    32: 185, // LOCAL_POSITION_NED
    35: 244, // RC_CHANNELS_RAW
    74: 20,  // VFR_HUD
    253: 83, // STATUSTEXT
  },

  /** Build a MAVLink v1 packet */
  _packet(msgId, payload) {
    const sysId = 1, compId = 1;
    const len = payload.length;
    const header = [0xFE, len, this._seq & 0xFF, sysId, compId, msgId];
    this._seq = (this._seq + 1) & 0xFF;
    const crcBuf = [...header.slice(1), ...payload, this._crcExtra[msgId] || 0];
    const crc = this._crc16(crcBuf);
    return new Uint8Array([...header, ...payload, crc & 0xFF, (crc >> 8) & 0xFF]);
  },

  /** Write float32 little-endian into DataView at offset */
  _f32(dv, off, val) { dv.setFloat32(off, val, true); },
  /** Write uint32 LE */
  _u32(dv, off, val) { dv.setUint32(off, val, true); },
  /** Write int32 LE */
  _i32(dv, off, val) { dv.setInt32(off, val, true); },
  /** Write uint16 LE */
  _u16(dv, off, val) { dv.setUint16(off, val, true); },
  /** Write int16 LE */
  _i16(dv, off, val) { dv.setInt16(off, val, true); },

  /** HEARTBEAT (28 bytes payload → ID 0) */
  heartbeat(customMode, type, autopilot, baseMode, sysStatus, mavlinkVersion) {
    const buf = new ArrayBuffer(9);
    const dv = new DataView(buf);
    this._u32(dv, 0, customMode || 0);
    dv.setUint8(4, type || 2);        // MAV_TYPE_QUADROTOR
    dv.setUint8(5, autopilot || 3);   // MAV_AUTOPILOT_ARDUPILOTMEGA
    dv.setUint8(6, baseMode || 0x04); // MAV_MODE_FLAG_SAFETY_ARMED?
    dv.setUint8(7, sysStatus || 0);
    dv.setUint8(8, mavlinkVersion || 3);
    return this._packet(this.MSG_HEARTBEAT, [...new Uint8Array(buf)]);
  },

  /** ATTITUDE (28 bytes payload → ID 30) */
  attitude(timeBootMs, roll, pitch, yaw, rollspeed, pitchspeed, yawspeed) {
    const buf = new ArrayBuffer(28);
    const dv = new DataView(buf);
    this._u32(dv, 0, timeBootMs >>> 0);
    this._f32(dv, 4, roll);
    this._f32(dv, 8, pitch);
    this._f32(dv, 12, yaw);
    this._f32(dv, 16, rollspeed);
    this._f32(dv, 20, pitchspeed);
    this._f32(dv, 24, yawspeed);
    return this._packet(this.MSG_ATTITUDE, [...new Uint8Array(buf)]);
  },

  /** LOCAL_POSITION_NED (28 bytes payload → ID 32) */
  localPositionNed(timeBootMs, x, y, z, vx, vy, vz) {
    const buf = new ArrayBuffer(28);
    const dv = new DataView(buf);
    this._u32(dv, 0, timeBootMs >>> 0);
    this._f32(dv, 4, x);
    this._f32(dv, 8, y);
    this._f32(dv, 12, z);
    this._f32(dv, 16, vx);
    this._f32(dv, 20, vy);
    this._f32(dv, 24, vz);
    return this._packet(this.MSG_LOCAL_POSITION_NED, [...new Uint8Array(buf)]);
  },

  /** VFR_HUD (20 bytes payload → ID 74) */
  vfrHud(airspeed, groundspeed, heading, throttle, alt, climb) {
    const buf = new ArrayBuffer(20);
    const dv = new DataView(buf);
    this._f32(dv, 0, airspeed);
    this._f32(dv, 4, groundspeed);
    this._f32(dv, 8, alt);
    this._f32(dv, 12, climb);
    this._i16(dv, 16, heading);
    this._u16(dv, 18, throttle);
    return this._packet(this.MSG_VFR_HUD, [...new Uint8Array(buf)]);
  },

  /** Build a .tlog binary from blackbox data */
  buildTlog(logEntries) {
    if (!logEntries || !logEntries.length) return null;
    this._seq = 0;
    const chunks = [];
    // Prepend timestamp as 64-bit µsec (QGC tlog format: 8-byte big-endian µsec + mavlink packet)
    const writeEntry = (tSec, pkt) => {
      const ts = Math.round(tSec * 1e6);
      const tsBuf = new ArrayBuffer(8);
      const tsView = new DataView(tsBuf);
      // High 32 bits
      tsView.setUint32(0, Math.floor(ts / 4294967296) >>> 0, false);
      // Low 32 bits
      tsView.setUint32(4, ts >>> 0, false);
      chunks.push(new Uint8Array(tsBuf));
      chunks.push(pkt);
    };

    // Heartbeat every second
    let lastHB = -999;
    for (const e of logEntries) {
      if (e.t - lastHB >= 1.0) {
        writeEntry(e.t, this.heartbeat(0, 2, 3, 0x04, 0, 3));
        lastHB = e.t;
      }
      const tms = Math.round(e.t * 1000);
      writeEntry(e.t, this.attitude(tms, e.roll, e.pitch, e.yaw, e.gx, e.gy, e.gz));
      writeEntry(e.t, this.localPositionNed(tms, e.px, -e.pz, -e.py, e.vx, -e.vz, -e.vy));
      const speed = Math.hypot(e.vx, e.vy, e.vz);
      const hspeed = Math.hypot(e.vx, e.vz);
      writeEntry(e.t, this.vfrHud(speed, hspeed, Math.round(((e.yaw*180/Math.PI)+360)%360), Math.round((e.m0+e.m1+e.m2+e.m3)/4*100), e.py, e.vy));
    }

    // Concat all chunks
    const total = chunks.reduce((s, c) => s + c.byteLength, 0);
    const out = new Uint8Array(total);
    let off = 0;
    for (const c of chunks) { out.set(c, off); off += c.byteLength; }
    return out;
  },

  /** Download .tlog */
  downloadTlog() {
    const log = BLACKBOX.getLog();
    if (!log.length) { console.warn('No blackbox data'); return false; }
    const data = this.buildTlog(log);
    if (!data) return false;
    const blob = new Blob([data], { type: 'application/octet-stream' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'spaceborn-' + Date.now() + '.tlog';
    a.click();
    URL.revokeObjectURL(a.href);
    return true;
  },

  /** Download JSON telemetry */
  downloadJSON() {
    const log = BLACKBOX.getLog();
    if (!log.length) return false;
    const json = JSON.stringify({ meta: { version: '2.0', drone: PHYS.droneProfile, exported: new Date().toISOString() }, frames: log }, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'spaceborn-telem-' + Date.now() + '.json';
    a.click();
    URL.revokeObjectURL(a.href);
    return true;
  },
};

/* ─── Telemetry Graph ─── */
const TELEM_GRAPH = {
  _canvas: null, _ctx: null,
  _history: { alt:[], vel:[], roll:[], pitch:[], batt:[] },
  _maxLen: 200,
  _channels: ['alt','vel','roll','pitch','batt'],
  _colors: { alt:'#10256D', vel:'#EE9346', roll:'#43A047', pitch:'#E53935', batt:'#9C27B0' },
  _scales: { alt:50, vel:15, roll:90, pitch:90, batt:100 },
  _visible: { alt:true, vel:true, roll:false, pitch:false, batt:false },

  init(canvasId) {
    this._canvas = document.getElementById(canvasId);
    if (this._canvas) this._ctx = this._canvas.getContext('2d');
  },

  push(p) {
    const R2D = 180/Math.PI;
    const vals = {
      alt:  Math.max(0, p.pos.y - p.groundY),
      vel:  Math.hypot(p.vel.x, p.vel.y, p.vel.z),
      roll: p.euler.roll * R2D,
      pitch:p.euler.pitch * R2D,
      batt: p.battPct,
    };
    for (const k of this._channels) {
      this._history[k].push(vals[k]);
      if (this._history[k].length > this._maxLen) this._history[k].shift();
    }
  },

  draw() {
    const c = this._canvas, ctx = this._ctx;
    if (!c || !ctx) return;
    const W = c.width, H = c.height;
    ctx.clearRect(0, 0, W, H);
    // Background
    ctx.fillStyle = 'rgba(238,241,247,0.6)';
    ctx.fillRect(0, 0, W, H);
    // Grid
    ctx.strokeStyle = 'rgba(96,125,139,0.2)'; ctx.lineWidth = 1;
    for (let i = 1; i < 4; i++) {
      ctx.beginPath(); ctx.moveTo(0, H*i/4); ctx.lineTo(W, H*i/4); ctx.stroke();
    }
    // Channels
    for (const k of this._channels) {
      if (!this._visible[k]) continue;
      const data = this._history[k];
      if (data.length < 2) continue;
      const scale = this._scales[k];
      ctx.strokeStyle = this._colors[k]; ctx.lineWidth = 1.5;
      ctx.beginPath();
      data.forEach((v, i) => {
        const x = (i / (this._maxLen - 1)) * W;
        const y = H/2 - (v / scale) * (H * 0.45);
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
      });
      ctx.stroke();
    }
  },

  toggle(ch) {
    if (this._visible[ch] !== undefined) this._visible[ch] = !this._visible[ch];
  },
};

if (typeof globalThis !== 'undefined') {
  Object.assign(globalThis, { MAVLINK, TELEM_GRAPH });
}
