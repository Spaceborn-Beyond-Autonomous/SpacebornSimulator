
const _setTxt = (el, txt) => { if (el && el.textContent !== String(txt)) el.textContent = txt; };

/* PLAN FLAGS — MAX tier
   Duration: Unlimited | All profiles + Custom
   All 6 envs | Full HUD | Full PID | Full export
   Gamepad | Waypoints | GLTF upload | Priority support */
const PLAN = {
  tier: 'MAX',
  sessionMinutes: <?= max(1, (int) ceil(($accessSeconds > 0 ? $accessSeconds : 2592000) / 60)) ?>,
  sessionSeconds: <?= $accessSeconds > 0 ? min($accessSeconds, 2592000) : 2592000 ?>,
  planExpiresAt: <?= (int) $accessExpiresAt ?>,
  droneProfiles: ['racing5','cinequad','micro2','explorer6'],
  environments: ['field','mountains','urban','indoor','desert','windy'],
  waypointMissions: true,
  pidTuning: 'full',
  dataExport: true,
  mavlinkLogs: 'download',
  customGLTF: true,
  joystickGamepad: true,
  hudLevel: 'full',
  nightMode: true,
  windScenario: true,
  support: 'priority',
  tierLabel: 'MAX',
  tierColor: '#EE9346',
};
