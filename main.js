/* ============================================================
   SPACEBORN ROBOTICS — main.js
   Vanilla JS — No jQuery
   ============================================================ */

/* -------------------------------------------------------
   1. STICKY NAVBAR SCROLL SHADOW
   ------------------------------------------------------- */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  if (window.scrollY > 40) {
    navbar.classList.add('scrolled');
  } else {
    navbar.classList.remove('scrolled');
  }
});

/* -------------------------------------------------------
   2. MOBILE HAMBURGER MENU
   ------------------------------------------------------- */
const hamburger = document.getElementById('hamburger');
const navLinks  = document.getElementById('navLinks');
const navCta    = document.querySelector('.nav-cta');

hamburger.addEventListener('click', () => {
  navLinks.classList.toggle('open');
  navCta.style.display = navLinks.classList.contains('open') ? 'flex' : '';
  const spans = hamburger.querySelectorAll('span');
  if (navLinks.classList.contains('open')) {
    spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
    spans[1].style.opacity   = '0';
    spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
  } else {
    spans.forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
  }
});

// Close menu on link click
document.querySelectorAll('.nav-links a').forEach(link => {
  link.addEventListener('click', () => {
    navLinks.classList.remove('open');
    navCta.style.display = '';
    hamburger.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
  });
});

/* -------------------------------------------------------
   3. SMOOTH SCROLL FOR ALL ANCHOR LINKS
   ------------------------------------------------------- */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', e => {
    const target = document.querySelector(anchor.getAttribute('href'));
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});

/* -------------------------------------------------------
   4. MODAL OPEN / CLOSE / TAB SWITCHING
   ------------------------------------------------------- */
const modalOverlay = document.getElementById('modalOverlay');
const modalClose   = document.getElementById('modalClose');
const loginBtn     = document.getElementById('loginBtn');

function openModal(tab = 'login') {
  modalOverlay.classList.add('active');
  switchTab(tab);
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  modalOverlay.classList.remove('active');
  document.body.style.overflow = '';
}

window.openModal = openModal;

loginBtn.addEventListener('click', () => openModal('login'));

// Fix: use addEventListener instead of relying on element re-query
modalClose.addEventListener('click', function(e) {
  e.stopPropagation();
  closeModal();
});

modalOverlay.addEventListener('click', e => {
  if (e.target === modalOverlay) closeModal();
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModal();
});

function switchTab(tab) {
  const loginForm    = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const tabLogin     = document.getElementById('tabLogin');
  const tabRegister  = document.getElementById('tabRegister');
  const tagline      = document.getElementById('modalTagline');

  if (tab === 'login') {
    loginForm.classList.remove('hidden');
    registerForm.classList.add('hidden');
    tabLogin.classList.add('active');
    tabRegister.classList.remove('active');
    if (tagline) tagline.textContent = 'Welcome back, pilot.';
  } else {
    loginForm.classList.add('hidden');
    registerForm.classList.remove('hidden');
    tabLogin.classList.remove('active');
    tabRegister.classList.add('active');
    if (tagline) tagline.textContent = 'Join thousands of drone engineers.';
  }
}
window.switchTab = switchTab;

/* -------------------------------------------------------
   5. 
 FORMS — PHP BACKEND
      Forms submit to 
  /login.php and 
  /register.php
      Client-side: password match check + strength meter only
   ------------------------------------------------------- */
function showMsg(el, msg, type = 'success') {
  el.textContent = msg;
  el.className = 'form-msg ' + type;
}

// Client-side password match validation before PHP submission
document.getElementById('registerForm').addEventListener('submit', e => {
  const fd   = new FormData(e.target);
  const pwd  = fd.get('password');
  const cpwd = fd.get('confirm_password');
  const msgEl = document.getElementById('registerMsg');
  if (pwd !== cpwd) {
    e.preventDefault();
    showMsg(msgEl, 'Passwords do not match.', 'error');
  }
  // If passwords match, form submits normally to /register.php
});

/* -------------------------------------------------------
   5b. PASSWORD SHOW / HIDE TOGGLE
   ------------------------------------------------------- */
window.togglePwd = function(inputId, btn) {
  const input = document.getElementById(inputId);
  const isText = input.type === 'text';
  input.type = isText ? 'password' : 'text';
  btn.style.color = isText ? '' : 'var(--primary)';
};

/* -------------------------------------------------------
   5c. PASSWORD STRENGTH METER
   ------------------------------------------------------- */
const regPasswordInput = document.getElementById('regPassword');
if (regPasswordInput) {
  regPasswordInput.addEventListener('input', function() {
    const val = this.value;
    const bars = document.querySelectorAll('#pwdStrength .pwd-bar');
    bars.forEach(b => b.className = 'pwd-bar');

    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = ['weak', 'fair', 'good', 'strong'];
    for (let i = 0; i < score; i++) {
      bars[i].classList.add(levels[score - 1]);
    }
  });
}

/* -------------------------------------------------------
   6. PRICING TOGGLE
   ------------------------------------------------------- */
const pricingToggle = document.getElementById('pricingToggle');
const labelMonthly  = document.getElementById('labelMonthly');
const labelPayg     = document.getElementById('labelPayg');

pricingToggle.addEventListener('change', () => {
  const isPayg = pricingToggle.checked;
  labelMonthly.classList.toggle('active', !isPayg);
  labelPayg.classList.toggle('active', isPayg);

  document.querySelectorAll('.plan-price').forEach(el => {
    el.textContent = isPayg ? el.dataset.payg : el.dataset.monthly;
    // Restore the span for sub-text (rebuild after text overwrite)
    const raw = isPayg ? el.dataset.payg : el.dataset.monthly;
    const match = raw.match(/^(\$[\d]+)\s*(.*)$/);
    if (match) {
      el.innerHTML = match[1] + ' <span>' + match[2] + '</span>';
    }
  });
});
labelMonthly.classList.add('active');

/* -------------------------------------------------------
   7. INTERSECTION OBSERVER — FADE-IN ANIMATIONS
   ------------------------------------------------------- */
const fadeEls = document.querySelectorAll('.fade-in');
const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      setTimeout(() => {
        entry.target.classList.add('visible');
      }, (Array.from(fadeEls).indexOf(entry.target) % 4) * 80);
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });
fadeEls.forEach(el => observer.observe(el));

/* -------------------------------------------------------
   8. COUNTER ANIMATION FOR STAT NUMBERS
   ------------------------------------------------------- */
const counterEls = document.querySelectorAll('.stat-number[data-target]');
const counterObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      animateCounter(entry.target);
      counterObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.5 });
counterEls.forEach(el => counterObserver.observe(el));

function animateCounter(el) {
  const target = parseInt(el.dataset.target, 10);
  const suffix = target >= 1000 ? '+' : (target >= 90 ? 'B' : '+');
  let current = 0;
  const step = Math.ceil(target / 60);
  const interval = setInterval(() => {
    current = Math.min(current + step, target);
    el.textContent = current.toLocaleString() + suffix;
    if (current >= target) clearInterval(interval);
  }, 25);
}

/* -------------------------------------------------------
   9. HERO ANIMATED DOT GRID (Canvas)
   ------------------------------------------------------- */
(function initHeroCanvas() {
  const canvas = document.getElementById('heroCanvas');
  const ctx = canvas.getContext('2d');
  let W, H, dots = [];

  function resize() {
    W = canvas.width  = canvas.offsetWidth;
    H = canvas.height = canvas.offsetHeight;
    buildDots();
  }

  function buildDots() {
    dots = [];
    const spacing = 36;
    for (let x = 0; x < W; x += spacing) {
      for (let y = 0; y < H; y += spacing) {
        dots.push({
          x, y,
          ox: x, oy: y,
          vx: (Math.random() - 0.5) * 0.3,
          vy: (Math.random() - 0.5) * 0.3,
          r: Math.random() * 1.2 + 0.4
        });
      }
    }
  }

  function draw(t) {
    ctx.clearRect(0, 0, W, H);
    dots.forEach(d => {
      d.x = d.ox + Math.sin(t * 0.001 + d.oy * 0.015) * 6;
      d.y = d.oy + Math.cos(t * 0.001 + d.ox * 0.015) * 6;
      ctx.beginPath();
      ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
      ctx.fillStyle = '#10256D';
      ctx.fill();
    });
    requestAnimationFrame(draw);
  }

  window.addEventListener('resize', resize);
  resize();
  requestAnimationFrame(draw);
})();

/* -------------------------------------------------------
   10. DRONE CANVAS (Hero Sim Viewport)
   ------------------------------------------------------- */
(function initDroneCanvas() {
  const canvas = document.getElementById('droneCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let t = 0;

  // Drone state
  let droneX = 200, droneY = 110;
  let driftX = 0, driftY = 0;
  let yaw = 0;
  const armLen = 40;
  let motorSpin = 0;

  // Simulated telemetry
  let alt = 42.3, spd = 8.1, bat = 87;

  function drawDrone(cx, cy, angle) {
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(angle);

    // Arms
    ctx.strokeStyle = 'rgba(238,147,70,0.9)';
    ctx.lineWidth = 3;
    [[1,1],[1,-1],[-1,1],[-1,-1]].forEach(([dx, dy]) => {
      ctx.beginPath();
      ctx.moveTo(0, 0);
      ctx.lineTo(dx * armLen, dy * armLen * 0.7);
      ctx.stroke();
    });

    // Body
    ctx.fillStyle = '#10256D';
    ctx.beginPath();
    ctx.roundRect(-12, -8, 24, 16, 4);
    ctx.fill();

    // Motors + spinning props
    [[1,1],[1,-1],[-1,1],[-1,-1]].forEach(([dx, dy], i) => {
      const mx = dx * armLen, my = dy * armLen * 0.7;
      ctx.save();
      ctx.translate(mx, my);

      // Motor circle
      ctx.fillStyle = '#EE9346';
      ctx.beginPath();
      ctx.arc(0, 0, 7, 0, Math.PI * 2);
      ctx.fill();

      // Prop blur arcs
      ctx.save();
      ctx.rotate(motorSpin + i * Math.PI / 2);
      ctx.strokeStyle = 'rgba(255,255,255,0.5)';
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.arc(0, 0, 12, 0, Math.PI);
      ctx.stroke();
      ctx.beginPath();
      ctx.arc(0, 0, 12, Math.PI, Math.PI * 2);
      ctx.stroke();
      ctx.restore();
      ctx.restore();
    });

    // Nav LED
    ctx.fillStyle = '#28c840';
    ctx.beginPath();
    ctx.arc(0, 0, 4, 0, Math.PI * 2);
    ctx.fill();

    ctx.restore();
  }

  function drawGrid() {
    ctx.strokeStyle = 'rgba(16,37,109,0.12)';
    ctx.lineWidth = 1;
    const W = canvas.width, H = canvas.height;
    for (let x = 0; x < W; x += 40) {
      ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke();
    }
    for (let y = 0; y < H; y += 40) {
      ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke();
    }
  }

  function drawShadow(cx, cy) {
    const grad = ctx.createRadialGradient(cx, cy + 30, 5, cx, cy + 30, 40);
    grad.addColorStop(0, 'rgba(0,0,0,0.3)');
    grad.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = grad;
    ctx.beginPath();
    ctx.ellipse(cx, cy + 30, 35, 12, 0, 0, Math.PI * 2);
    ctx.fill();
  }

  function drawHorizonLine() {
    const W = canvas.width, H = canvas.height;
    ctx.strokeStyle = 'rgba(238,147,70,0.25)';
    ctx.lineWidth = 1;
    ctx.setLineDash([6, 6]);
    ctx.beginPath();
    ctx.moveTo(0, H / 2);
    ctx.lineTo(W, H / 2);
    ctx.stroke();
    ctx.setLineDash([]);
  }

  function drawAltitudeLine(cx, cy) {
    ctx.strokeStyle = 'rgba(40,200,64,0.4)';
    ctx.lineWidth = 1;
    ctx.setLineDash([4, 4]);
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.lineTo(cx, canvas.height / 2);
    ctx.stroke();
    ctx.setLineDash([]);
  }

  function frame() {
    t++;
    motorSpin += 0.25;

    driftX = Math.sin(t * 0.02) * 25 + Math.sin(t * 0.007) * 15;
    driftY = Math.cos(t * 0.015) * 15 + Math.sin(t * 0.03) * 8;
    yaw    = Math.sin(t * 0.01) * 0.08;

    const cx = droneX + driftX;
    const cy = droneY + driftY;

    const W = canvas.width, H = canvas.height;
    ctx.clearRect(0, 0, W, H);

    // BG gradient
    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#0d1b2a');
    bg.addColorStop(1, '#1a3a5c');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    drawGrid();
    drawHorizonLine();
    drawAltitudeLine(cx, cy);
    drawShadow(cx, cy);
    drawDrone(cx, cy, yaw);

    // Telemetry blip
    alt = 42.3 + Math.sin(t * 0.02) * 3.1;
    spd = 8.1 + Math.cos(t * 0.03) * 2.4;
    bat = Math.max(75, 87 - t * 0.002);

    const hudAlt = document.getElementById('hudAlt');
    const hudSpd = document.getElementById('hudSpd');
    const hudBat = document.getElementById('hudBat');
    if (hudAlt) hudAlt.textContent = alt.toFixed(1) + 'm';
    if (hudSpd) hudSpd.textContent = spd.toFixed(1) + 'm/s';
    if (hudBat) hudBat.textContent = bat.toFixed(0) + '%';

    requestAnimationFrame(frame);
  }
  frame();
})();
