<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['id']) && isset($_SESSION['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- =====================================================
       PRIMARY SEO META TAGS
  ====================================================== -->
  <title>Certanity Robotics — Browser-Based Drone Simulator | MAVLink, PID Tuning, Zero Install</title>
  <meta name="description" content="India's first browser-based 3D drone simulator. Physics-accurate flight dynamics, MAVLink-compatible telemetry, PID tuning, and 5 real-world scenarios. No install needed. Start for $1." />
  <meta name="keywords" content="drone simulator, browser drone simulator, MAVLink simulator, PID tuning drone, drone engineering India, UAV simulator online, certanity robotics, drone flight simulator, free drone simulator India" />
  <meta name="author" content="Certanity Robotics" />
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />

  <!-- Canonical URL — prevents duplicate content penalties -->
  <link rel="canonical" href="https://certanity.com/" />

  <!-- =====================================================
       OPEN GRAPH (Facebook, LinkedIn, WhatsApp, Discord)
  ====================================================== -->
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="Certanity Robotics" />
  <meta property="og:title" content="Certanity Robotics — Build. Simulate. Fly." />
  <meta property="og:description" content="Democratising drone engineering for India &amp; the world. Physics-accurate, MAVLink-compatible, browser-native. No install. No hardware. Start for $1." />
  <meta property="og:url" content="https://certanity.com/" />
  <meta property="og:image" content="https://certanity.com/assets/og-image.jpg" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:image:alt" content="Certanity Robotics — Browser-Based Drone Simulator" />
  <meta property="og:locale" content="en_IN" />

  <!-- =====================================================
       TWITTER / X CARD
  ====================================================== -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:site" content="@certanity" />
  <meta name="twitter:creator" content="@certanity" />
  <meta name="twitter:title" content="Certanity Robotics — Browser-Based Drone Simulator" />
  <meta name="twitter:description" content="India's first browser-based drone simulator. Physics-accurate, MAVLink-compatible, zero install. Start for $1." />
  <meta name="twitter:image" content="https://certanity.com/assets/og-image.jpg" />
  <meta name="twitter:image:alt" content="Certanity Robotics Drone Simulator Screenshot" />

  <!-- =====================================================
       FAVICON (add your actual favicon files to /assets/)
  ====================================================== -->
  <link rel="icon" type="image/png" href="assets/logo-iso.png" />
  <link rel="apple-touch-icon" href="assets/logo-iso.png" />
  <link rel="manifest" href="/site.webmanifest" />
  <meta name="theme-color" content="#10256D" />

  <!-- =====================================================
       GEO / REGIONAL TAGS (India-first product)
  ====================================================== -->
  <meta name="geo.region" content="IN" />
  <meta name="geo.placename" content="India" />

  <!-- =====================================================
       STRUCTURED DATA — JSON-LD
       WebApplication schema for rich search results
  ====================================================== -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": "Certanity Robotics",
    "url": "https://certanity.com",
    "description": "India's first browser-based drone simulator. Physics-accurate flight dynamics, MAVLink-compatible telemetry, PID tuning, and 5 real-world scenarios. No install required.",
    "applicationCategory": "EducationalApplication",
    "applicationSubCategory": "EngineeringSimulation",
    "operatingSystem": "Web Browser",
    "browserRequirements": "Requires JavaScript. Works on Chrome, Firefox, Edge, Safari.",
    "inLanguage": "en-IN",
    "offers": {
      "@type": "Offer",
      "price": "1.00",
      "priceCurrency": "USD",
      "description": "Pay-as-you-go hourly access to the drone simulator",
      "availability": "https://schema.org/InStock"
    },
    "creator": {
      "@type": "Organization",
      "name": "Certanity Robotics",
      "url": "https://certanity.com",
      "email": "partnerships@certanityrobotics.com",
      "sameAs": [
        "https://www.linkedin.com/company/certanity-robotics",
        "https://twitter.com/certanity",
        "https://github.com/certanity"
      ]
    },
    "featureList": [
      "Physics-accurate flight dynamics",
      "MAVLink protocol support (6 streams at 50Hz)",
      "PID tuning with real-time feedback",
      "JSON, CSV and .tlog export",
      "5 simulated scenarios",
      "15+ learning modules",
      "Zero installation required"
    ]
  }
  </script>

  <!-- Organization schema for Knowledge Panel -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "Certanity Robotics",
    "url": "https://certanity.com",
    "logo": "https://certanity.com/assets/logo.png",
    "description": "Certanity Robotics builds browser-based drone simulation tools to democratise drone engineering education across India.",
    "contactPoint": {
      "@type": "ContactPoint",
      "email": "partnerships@certanityrobotics.com",
      "contactType": "customer support"
    },
    "sameAs": [
      "https://www.linkedin.com/company/certanity-robotics",
      "https://twitter.com/certanity",
      "https://github.com/certanity"
    ]
  }
  </script>

  <!-- FAQPage schema — earns rich accordion results in Google -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
      {
        "@type": "Question",
        "name": "What is Certanity Robotics?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Certanity Robotics is India's first browser-based drone simulator. It offers physics-accurate flight dynamics, MAVLink-compatible telemetry at 50Hz, PID tuning, and 5 simulated scenarios — all without any installation."
        }
      },
      {
        "@type": "Question",
        "name": "Does Certanity require installation?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "No. Certanity runs entirely in your web browser. No downloads, no plugins, no hardware required. Just open the website and start flying."
        }
      },
      {
        "@type": "Question",
        "name": "How much does Certanity cost?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Certanity starts at $1 per hour on a pay-as-you-go basis. Monthly subscription plans are also available."
        }
      },
      {
        "@type": "Question",
        "name": "Does Certanity support MAVLink?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Yes. Certanity supports full MAVLink protocol with 6 simultaneous data streams running at 50Hz, compatible with ArduPilot and PX4 workflows."
        }
      }
    ]
  }
  </script>

  <!-- =====================================================
       PERFORMANCE & FONTS
  ====================================================== -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
</head>
<body>

<!-- Section: Navigation -->
<header class="navbar" id="navbar">
  <div class="nav-container">
    <div class="nav-logo">
      <img src="assets/logo-black.png" alt="Certanity Logo" width="48" height="48">
      <span>CERTANITY ROBOTICS</span>
    </div>
    <nav class="nav-links" id="navLinks">
      <a href="#features">Features</a>
      <a href="#pricing">Pricing</a>
      <a href="#how-it-works">How It Works</a>
      <a href="#roadmap">Roadmap</a>
      <a href="#about">About</a>
    </nav>
    <div class="nav-cta">
      <?php if ($isLoggedIn): ?>
        <a href="dashboard.php" class="btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Go to Dashboard</a>
        <a href="auth/logout.php" class="btn-outlined" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
      <?php else: ?>
        <button class="btn-outlined" id="loginBtn">Sign In</button>
        <button class="btn-primary" onclick="document.getElementById('pricing').scrollIntoView({behavior:'smooth'})">Get Started</button>
      <?php endif; ?>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<!-- Section: Hero -->
<section class="hero" id="hero">
  <canvas id="heroCanvas"></canvas>
  <div class="hero-content">
    <div class="hero-left">
      <div class="badge-chip neu-inset">India's First Browser-Based Drone Simulator</div>
      <h1 class="hero-headline">BUILD · SIMULATE<br>FLY · REVOLUTIONISE</h1>
      <p class="hero-sub">Democratising Drone Engineering for India &amp; the World. Physics-accurate, MAVLink-compatible, browser-native. No install. No hardware. Start for <strong>$1</strong>.</p>
      <div class="hero-btns">
        <button class="btn-primary btn-large" onclick="document.getElementById('pricing').scrollIntoView({behavior:'smooth'})">Get Started — $1</button>
        <button class="btn-outlined btn-large" id="watchDemoBtn">Watch Demo</button>
      </div>
    </div>
    <div class="hero-right">
      <div class="simulator-card neu-raised">
        <div class="sim-topbar">
          <span class="sim-dot red"></span>
          <span class="sim-dot yellow"></span>
          <span class="sim-dot green"></span>
          <span class="sim-title">Certanity Simulator — v2.4</span>
        </div>
        <div class="sim-viewport">
          <canvas id="droneCanvas" width="400" height="220"></canvas>
          <div class="sim-overlay-grid"></div>
          <div class="hud-badges">
            <div class="hud-badge neu-inset">
              <span class="hud-label">ALT</span>
              <span class="hud-val" id="hudAlt">42.3m</span>
            </div>
            <div class="hud-badge neu-inset">
              <span class="hud-label">SPD</span>
              <span class="hud-val" id="hudSpd">8.1m/s</span>
            </div>
            <div class="hud-badge neu-inset">
              <span class="hud-label">BAT</span>
              <span class="hud-val" id="hudBat">87%</span>
            </div>
            <div class="hud-badge neu-inset">
              <span class="hud-label">GPS</span>
              <span class="hud-val">LOCK</span>
            </div>
          </div>
        </div>
        <div class="sim-controls">
          <div class="sim-ctrl-row">
            <span class="ctrl-badge">WASD</span>
            <span class="ctrl-badge">HOVER</span>
            <span class="ctrl-badge">LAND</span>
            <span class="ctrl-badge">WPT</span>
          </div>
          <div class="mavlink-indicator">
            <span class="mavlink-dot pulse"></span>
            MAVLink Active — 50Hz
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="hero-stats">
    <div class="stat-chip neu-raised">50Hz Telemetry</div>
    <div class="stat-chip neu-raised">6 MAVLink Streams</div>
    <div class="stat-chip neu-raised">5 Scenarios</div>
    <div class="stat-chip neu-raised">15+ Modules</div>
  </div>
</section>

<!-- Section: Problem -->
<section class="problem-section" id="problem">
  <div class="container">
    <h2 class="section-title">The Problem We Solved</h2>
    <div class="problem-cards">
      <div class="problem-card neu-raised fade-in">
        <div class="problem-icon"></div>
        <h3>Cost of Failure</h3>
        <p>A single crash can cost ₹5,000–₹1,00,000. Testing in real air is brutally expensive for students and startups.</p>
      </div>
      <div class="problem-card neu-raised fade-in">
        <div class="problem-icon"></div>
        <h3>PID Tuning Hell</h3>
        <p>Dozens of flights, crashes, and repairs just to tune a drone. There had to be a better way.</p>
      </div>
      <div class="problem-card neu-raised fade-in">
        <div class="problem-icon"></div>
        <h3>Access Gap</h3>
        <p>Students in tier-2 cities locked out of real practice. No simulators, no hardware, no chance.</p>
      </div>
    </div>
    <div class="comparison-wrapper neu-raised fade-in">
      <table class="comparison-table">
        <thead>
          <tr>
            <th class="col-gap">THE GAP</th>
            <th class="col-others">OTHERS</th>
            <th class="col-certanity">CERTANITY</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Physics Accuracy</td>
            <td><span class="tbl-no">✗</span> Simplified</td>
            <td class="certanity-col"><span class="tbl-yes">✓</span> Per-tick integrator</td>
          </tr>
          <tr>
            <td>MAVLink Support</td>
            <td><span class="tbl-no">✗</span> None</td>
            <td class="certanity-col"><span class="tbl-yes">✓</span> Full 6-stream</td>
          </tr>
          <tr>
            <td>Real PID Export</td>
            <td><span class="tbl-no">✗</span> View-only</td>
            <td class="certanity-col"><span class="tbl-yes">✓</span> JSON + CSV + .tlog</td>
          </tr>
          <tr>
            <td>Price Barrier</td>
            <td><span class="tbl-no">✗</span> $200–$1,000/yr</td>
            <td class="certanity-col"><span class="tbl-yes">✓</span> From $1/hr</td>
          </tr>
          <tr>
            <td>India-First Design</td>
            <td><span class="tbl-no">✗</span> Western-centric</td>
            <td class="certanity-col"><span class="tbl-yes">✓</span> Built for India</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- Section: How It Works -->
<section class="how-section" id="how-it-works">
  <div class="container">
    <h2 class="section-title">From Simulation to Real Flight — In 6 Steps</h2>
    <div class="steps-flow">
      <div class="step-card neu-raised fade-in">
        <div class="step-num">1</div>
        <h4>Subscribe to MAX</h4>
        <p>Open simulator directly in your browser. Zero install.</p>
      </div>
      <div class="step-arrow">→</div>
      <div class="step-card neu-raised fade-in">
        <div class="step-num">2</div>
        <h4>Choose Profile & Scenario</h4>
        <p>Pick drone model, environment, and difficulty.</p>
      </div>
      <div class="step-arrow">→</div>
      <div class="step-card neu-raised fade-in">
        <div class="step-num">3</div>
        <h4>Fly & Tune PID</h4>
        <p>Live 4-axis PID panel, windup protection, derivative filter.</p>
      </div>
      <div class="step-arrow">→</div>
      <div class="step-card neu-raised fade-in">
        <div class="step-num">4</div>
        <h4>Export Telemetry</h4>
        <p>Download JSON + CSV telemetry bundle from your flight.</p>
      </div>
      <div class="step-arrow">→</div>
      <div class="step-card neu-raised fade-in">
        <div class="step-num">5</div>
        <h4>Download MAVLink .tlog</h4>
        <p>Full MAVLink log file, identical to real Pixhawk output.</p>
      </div>
      <div class="step-arrow">→</div>
      <div class="step-card neu-raised fade-in">
        <div class="step-num">6</div>
        <h4>Paste into FC</h4>
        <p>Copy PID values into Betaflight, ArduPilot, or PX4.</p>
      </div>
    </div>
  </div>
</section>

<!-- Section: Features -->
<section class="features-section" id="features">
  <div class="container">
    <h2 class="section-title">15+ Core Modules. All In-Browser.</h2>
    <p class="section-sub">WebGL / Three.js powered. Zero install. Zero GPU requirement beyond a modern laptop.</p>
    <div class="features-grid">
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>3D Rendering</h4><p>Procedural quadcopter mesh, load custom .gltf/.glb models</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Physics Engine</h4><p>Custom per-tick integrator with wind &amp; motor failure simulation</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Flight Controls</h4><p>WASD keyboard, touch controls, hover/land/waypoint modes</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Drone Profiles</h4><p>DJI Matrice, Mavic 3, PX4 F450, Nano Quad + Custom</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Scenarios</h4><p>Normal, Wind, GPS Denied, Motor Failure, Obstacle Avoidance</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Waypoint Missions</h4><p>Click-to-place, auto-fly, configurable speed multiplier</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Telemetry HUD</h4><p>GCS-style: altitude, speed, battery, GPS lock, status</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>MAVLink Stream</h4><p>HEARTBEAT, ATTITUDE, GPS, BATTERY at correct Hz</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Cameras</h4><p>FPV, follow-cam, free-look, gimbal simulation</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Environments</h4><p>Daytime, Sunset, Studio, Desert, Arctic, Night</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Obstacles</h4><p>Dynamic avoidance field with repulsion force physics</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Audio</h4><p>Spatial motor audio, RPM-scaled pitch, wind/crash cues</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>PID Tuning</h4><p>4-axis live panel, windup protection, derivative filter</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Joystick</h4><p>Gamepad API support, deadzone, axis mapping</p></div>
      <div class="feature-card neu-raised fade-in"><span class="feat-icon"></span><h4>Config Panel</h4><p>KV, cell count, prop size, companion computer settings</p></div>
    </div>
  </div>
</section>

<!-- Section: Pricing -->
<section class="pricing-section" id="pricing">
  <div class="container">
    <h2 class="section-title">Plans Built for Every Builder</h2>
    <div class="pricing-toggle-wrap">
      <span class="toggle-label" id="labelMonthly">Monthly</span>
      <label class="toggle-switch">
        <input type="checkbox" id="pricingToggle" />
        <span class="toggle-slider neu-inset"></span>
      </label>
      <span class="toggle-label" id="labelPayg">Pay-as-you-go</span>
    </div>
    <div class="pricing-cards">
      <div class="pricing-card neu-raised fade-in">
        <div class="plan-name">BASIC</div>
        <div class="plan-price" data-monthly="$1 / 1 Hour" data-payg="$1 / 1 Hour">$1 <span>/ 1 Hour</span></div>
        <ul class="plan-features">
          <li><span class="feat-yes">✓</span> 1-hour access window</li>
          <li><span class="feat-yes">✓</span> 1 drone profile</li>
          <li><span class="feat-yes">✓</span> Normal flight only</li>
          <li><span class="feat-yes">✓</span> Daytime environment</li>
          <li><span class="feat-yes">✓</span> Basic HUD</li>
          <li><span class="feat-no">✗</span> PID export</li>
          <li><span class="feat-no">✗</span> MAVLink stream</li>
        </ul>
        <?php if ($isLoggedIn): ?>
          <a href="billing.php" class="btn-outlined plan-cta" style="text-decoration:none; display:block; text-align:center;">Select Plan</a>
        <?php else: ?>
          <button class="btn-outlined plan-cta" onclick="openModal('login')">Start Here</button>
        <?php endif; ?>
      </div>
      <div class="pricing-card neu-raised elevated fade-in">
        <div class="plan-name">PRO</div>
        <div class="plan-price" data-monthly="$5 / 1 Day" data-payg="$5 / 1 Day">$5 <span>/ 1 Day</span></div>
        <ul class="plan-features">
          <li><span class="feat-yes">✓</span> 24-hour access</li>
          <li><span class="feat-yes">✓</span> 2 drone profiles</li>
          <li><span class="feat-yes">✓</span> 2 scenarios</li>
          <li><span class="feat-yes">✓</span> 3 environments</li>
          <li><span class="feat-yes">✓</span> View-only PID</li>
          <li><span class="feat-yes">✓</span> Read-only MAVLink</li>
          <li><span class="feat-no">✗</span> Full data export</li>
        </ul>
        <?php if ($isLoggedIn): ?>
          <a href="billing.php" class="btn-outlined plan-cta" style="text-decoration:none; display:block; text-align:center;">Select Plan</a>
        <?php else: ?>
          <button class="btn-outlined plan-cta" onclick="openModal('login')">Get Started</button>
        <?php endif; ?>
      </div>
      <div class="pricing-card neu-raised featured fade-in">
        <div class="plan-badge">MOST POPULAR</div>
        <div class="plan-name">MAX</div>
        <div class="plan-price" data-monthly="$20 / Month" data-payg="$20 / Month">$20 <span>/ Month</span></div>
        <ul class="plan-features">
          <li><span class="feat-yes">✓</span> Unlimited 30 days</li>
          <li><span class="feat-yes">✓</span> All 4 profiles + custom</li>
          <li><span class="feat-yes">✓</span> All 5 scenarios</li>
          <li><span class="feat-yes">✓</span> All 6 environments</li>
          <li><span class="feat-yes">✓</span> Full PID tuning</li>
          <li><span class="feat-yes">✓</span> Full data export</li>
          <li><span class="feat-yes">✓</span> MAVLink logs + .tlog</li>
          <li><span class="feat-yes">✓</span> Custom GLTF upload</li>
          <li><span class="feat-yes">✓</span> Full HUD + joystick</li>
          <li><span class="feat-yes">✓</span> Priority email support</li>
        </ul>
        <?php if ($isLoggedIn): ?>
          <a href="billing.php" class="btn-primary plan-cta" style="text-decoration:none; display:block; text-align:center;">Select Plan</a>
        <?php else: ?>
          <button class="btn-primary plan-cta" onclick="openModal('login')">Get Started</button>
        <?php endif; ?>
        <p class="plan-callout">Less than one cup of coffee per day. Less than one cheap propeller set.</p>
      </div>
    </div>
  </div>
</section>

<!-- Section: Telemetry Data -->
<section class="data-section" id="data">
  <div class="container">
    <h2 class="section-title">Real Data. Real Engineering.</h2>
    <p class="section-sub">Every simulated flight generates the same data structures as a real Pixhawk.</p>
    <div class="data-table-wrap neu-raised fade-in">
      <table class="data-table">
        <thead>
          <tr>
            <th>Data Type</th>
            <th>What It Contains</th>
            <th>Update Rate</th>
            <th>Real-World Use</th>
          </tr>
        </thead>
        <tbody>
          <tr><td><strong>ATTITUDE</strong></td><td>Roll, pitch, yaw angles + rates</td><td>50 Hz</td><td>Betaflight tuning, PX4 state estimation</td></tr>
          <tr><td><strong>HEARTBEAT</strong></td><td>System type, autopilot, mode flags</td><td>1 Hz</td><td>QGroundControl connection health</td></tr>
          <tr><td><strong>GPS_RAW_INT</strong></td><td>Lat, lon, alt, fix type, satellites</td><td>5 Hz</td><td>ROS localization, ArduPilot nav</td></tr>
          <tr><td><strong>BATTERY_STATUS</strong></td><td>Voltage, current, consumed mAh</td><td>2 Hz</td><td>Mission planner battery alerts</td></tr>
          <tr><td><strong>VISION_POSITION</strong></td><td>Local position estimate, covariance</td><td>30 Hz</td><td>Jetson-based visual odometry</td></tr>
          <tr><td><strong>OBSTACLE_DISTANCE</strong></td><td>360° proximity sensor array</td><td>10 Hz</td><td>Collision avoidance systems</td></tr>
          <tr><td><strong>PID TELEMETRY</strong></td><td>P, I, D gains + error + output per axis</td><td>50 Hz</td><td>PID optimization, Blackbox analysis</td></tr>
          <tr><td><strong>FLIGHT LOG</strong></td><td>Full mission record, events, anomalies</td><td>On export</td><td>Post-flight analysis, debugging</td></tr>
        </tbody>
      </table>
    </div>
    <div class="compatible-row">
      <span class="compat-label">Compatible With:</span>
      <div class="compat-pills">
        <span class="compat-pill neu-raised">Betaflight</span>
        <span class="compat-pill neu-raised">ArduPilot</span>
        <span class="compat-pill neu-raised">PX4</span>
        <span class="compat-pill neu-raised">QGroundControl</span>
        <span class="compat-pill neu-raised">ROS</span>
        <span class="compat-pill neu-raised">Jetson</span>
      </div>
    </div>
  </div>
</section>

<!-- Section: Roadmap -->
<section class="roadmap-section" id="roadmap">
  <div class="container">
    <h2 class="section-title">What's Coming Next</h2>
    <div class="roadmap-grid">
      <div class="roadmap-card neu-raised fade-in">
        <span class="rm-tag high">HIGH · Q2 2025</span>
        <h4>Multi-drone Swarm</h4>
        <p>Coordinate 2–8 drones in formation with shared telemetry streams.</p>
      </div>
      <div class="roadmap-card neu-raised fade-in">
        <span class="rm-tag high">HIGH · Q2 2025</span>
        <h4>ROS2 Bridge</h4>
        <p>Real-time topic publishing over WebSocket to your ROS2 environment.</p>
      </div>
      <div class="roadmap-card neu-raised fade-in">
        <span class="rm-tag high">HIGH · Q3 2025</span>
        <h4>Hardware-in-the-Loop (HITL)</h4>
        <p>Connect a real flight controller to the browser sim for closed-loop testing.</p>
      </div>
      <div class="roadmap-card neu-raised fade-in">
        <span class="rm-tag med">MED · Q3–Q4 2025</span>
        <h4>Terrain Replay</h4>
        <p>Import real-world satellite elevation data and replay missions over it.</p>
      </div>
      <div class="roadmap-card neu-raised fade-in">
        <span class="rm-tag med">MED · Q3–Q4 2025</span>
        <h4>AI Autopilot</h4>
        <p>Train lightweight RL agents in-browser using simulated flight data.</p>
      </div>
      <div class="roadmap-card neu-raised fade-in">
        <span class="rm-tag med">MED · Q3–Q4 2025</span>
        <h4>Collaborative Sessions</h4>
        <p>Multi-user simulation rooms with shared airspace and voice comms.</p>
      </div>
      <div class="roadmap-card neu-raised fade-in">
        <span class="rm-tag med">MED · Q4 2025</span>
        <h4>Custom Map Import</h4>
        <p>Load custom terrain, city blocks, or warehouse layouts via GLB/GeoJSON.</p>
      </div>
      <div class="roadmap-card neu-raised fade-in">
        <span class="rm-tag low">LOW · Q1–Q2 2026</span>
        <h4>Battery Degradation</h4>
        <p>Simulate aged cell chemistry and capacity fade for realistic endurance planning.</p>
      </div>
      <div class="roadmap-card neu-raised fade-in">
        <span class="rm-tag low">LOW · Q1–Q2 2026</span>
        <h4>Payload Drop</h4>
        <p>Physics-accurate payload release with configurable mass and drop ballistics.</p>
      </div>
      <div class="roadmap-card neu-raised fade-in">
        <span class="rm-tag low">LOW · Q2 2026</span>
        <h4>AR Mode</h4>
        <p>Overlay simulation HUD onto your real-world camera feed via WebXR.</p>
      </div>
    </div>
  </div>
</section>

<!-- Section: Vision / About -->
<section class="about-section" id="about">
  <div class="container">
    <blockquote class="vision-quote">"We believe the next generation of drone engineers should not be gatekept by geography or wealth. The simulation layer is the great equaliser."</blockquote>
    <div class="pipeline-wrap">
      <div class="pipeline-step">DREAM</div>
      <div class="pipeline-arrow">→</div>
      <div class="pipeline-step">SIMULATE</div>
      <div class="pipeline-arrow">→</div>
      <div class="pipeline-step">TUNE</div>
      <div class="pipeline-arrow">→</div>
      <div class="pipeline-step">EXPORT</div>
      <div class="pipeline-arrow">→</div>
      <div class="pipeline-step">BUILD</div>
      <div class="pipeline-arrow">→</div>
      <div class="pipeline-step">FLY</div>
      <div class="pipeline-arrow">→</div>
      <div class="pipeline-step">ITERATE</div>
    </div>
    <div class="stats-grid">
      <div class="stat-box neu-inset fade-in">
        <div class="stat-number" data-target="1000">0</div>
        <div class="stat-desc">Engineering colleges in India</div>
      </div>
      <div class="stat-box neu-inset fade-in">
        <div class="stat-number" data-target="90">0</div>
        <div class="stat-desc">Billion dollar drone market by 2030</div>
      </div>
      <div class="stat-box neu-inset fade-in">
        <div class="stat-number" data-target="100">0</div>
        <div class="stat-desc">Defence PSUs actively hiring</div>
      </div>
      <div class="stat-box neu-inset fade-in">
        <div class="stat-number" data-target="50">0</div>
        <div class="stat-desc">Drone startups in India ecosystem</div>
      </div>
    </div>
    <p class="about-brands">ISRO · DRDO · Garuda Aerospace · ideaForge · Throttle Aerospace — all need sim-trained engineers.</p>
  </div>
</section>

<!-- Section: Footer -->
<footer class="site-footer">
  <div class="footer-top">
    <div class="footer-brand">
      <div class="nav-logo footer-logo">
        <img src="assets/logo-white.png" alt="Certanity Logo" width="40" height="40">
        <span style="color:#fff">CERTANITY ROBOTICS</span>
      </div>
      <p class="footer-tagline">Build the future. Simulate it first.</p>
    </div>
    <div class="footer-links">
      <a href="#features">Features</a>
      <a href="#pricing">Pricing</a>
      <a href="#roadmap">Roadmap</a>
      <a href="#">Blog</a>
      <a href="#">Docs</a>
      <a href="#">Privacy</a>
      <a href="#">Terms</a>
    </div>
    <div class="footer-contact">
      <p>partnerships@certanityrobotics.com</p>
      <div class="social-icons">
        <a href="#" class="social-btn neu-inset" aria-label="LinkedIn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
        </a>
        <a href="#" class="social-btn neu-inset" aria-label="GitHub">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>
        </a>
        <a href="#" class="social-btn neu-inset" aria-label="Twitter/X">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        </a>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© 2025 Certanity Robotics · All Rights Reserved</p>
  </div>
</footer>

<!-- Login / Register Modal -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal-card neu-raised" id="modalCard">

    <!-- Close button -->
    <button class="modal-close" id="modalClose" aria-label="Close modal">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M1 1L13 13M13 1L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>

    <!-- Modal Header -->
    <div class="modal-header">
      <div class="modal-logo">
        <img src="assets/logo-black.png" alt="Certanity Logo" width="40" height="40">
        <span class="modal-logo-text">CERTANITY</span>
      </div>
      <p class="modal-tagline" id="modalTagline">Sign in or create an account to continue.</p>
    </div>

    <!-- Auth Form -->
    <div class="auth-form" style="padding-top: 1rem; padding-bottom: 2rem;">
      <button type="button" class="btn-google neu-raised" onclick="window.location.href='auth/google.php'" style="margin-top: 1rem;">
        <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
        Continue with Google
      </button>
    </div>

  </div>
</div>

<script>
/* ============================================================
   CERTANITY ROBOTICS — Inlined from main.js
   ============================================================ */

const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 40);
});

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

document.querySelectorAll('.nav-links a').forEach(link => {
  link.addEventListener('click', () => {
    navLinks.classList.remove('open');
    navCta.style.display = '';
    hamburger.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
  });
});

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', e => {
    const target = document.querySelector(anchor.getAttribute('href'));
    if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
});

const modalOverlay = document.getElementById('modalOverlay');
const modalClose   = document.getElementById('modalClose');
const loginBtn     = document.getElementById('loginBtn');

function openModal(tab = 'login') {
  if (!modalOverlay) return;
  modalOverlay.classList.add('active');
  switchTab(tab);
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  if (!modalOverlay) return;
  modalOverlay.classList.remove('active');
  document.body.style.overflow = '';
}
window.openModal = openModal;

if (loginBtn) loginBtn.addEventListener('click', () => openModal('login'));
if (modalClose) modalClose.addEventListener('click', e => { e.stopPropagation(); closeModal(); });
if (modalOverlay) modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function switchTab(tab) {
  const tagline = document.getElementById('modalTagline');
  if (tagline) tagline.textContent = tab === 'login' ? 'Welcome back, pilot.' : 'Join thousands of drone engineers.';
}
window.switchTab = switchTab;

const pricingToggle = document.getElementById('pricingToggle');
const labelMonthly  = document.getElementById('labelMonthly');
const labelPayg     = document.getElementById('labelPayg');
if (pricingToggle) {
  pricingToggle.addEventListener('change', () => {
    const isPayg = pricingToggle.checked;
    labelMonthly.classList.toggle('active', !isPayg);
    labelPayg.classList.toggle('active', isPayg);
    document.querySelectorAll('.plan-price').forEach(el => {
      const raw = isPayg ? el.dataset.payg : el.dataset.monthly;
      const match = raw.match(/^(\$[\d]+)\s*(.*)$/);
      if (match) el.innerHTML = match[1] + ' <span>' + match[2] + '</span>';
    });
  });
  labelMonthly.classList.add('active');
}

const fadeEls = document.querySelectorAll('.fade-in');
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      setTimeout(() => entry.target.classList.add('visible'),
        (Array.from(fadeEls).indexOf(entry.target) % 4) * 80);
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });
fadeEls.forEach(el => observer.observe(el));

const counterEls = document.querySelectorAll('.stat-number[data-target]');
const counterObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) { animateCounter(entry.target); counterObserver.unobserve(entry.target); }
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

(function initHeroCanvas() {
  const canvas = document.getElementById('heroCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, dots = [];
  function resize() {
    W = canvas.width  = canvas.offsetWidth;
    H = canvas.height = canvas.offsetHeight;
    dots = [];
    const spacing = 36;
    for (let x = 0; x < W; x += spacing)
      for (let y = 0; y < H; y += spacing)
        dots.push({ x, y, ox: x, oy: y, r: Math.random() * 1.2 + 0.4 });
  }
  function draw(t) {
    ctx.clearRect(0, 0, W, H);
    dots.forEach(d => {
      d.x = d.ox + Math.sin(t * 0.001 + d.oy * 0.015) * 6;
      d.y = d.oy + Math.cos(t * 0.001 + d.ox * 0.015) * 6;
      ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
      ctx.fillStyle = '#10256D'; ctx.fill();
    });
    requestAnimationFrame(draw);
  }
  window.addEventListener('resize', resize);
  resize();
  requestAnimationFrame(draw);
})();

(function initDroneCanvas() {
  const canvas = document.getElementById('droneCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let t = 0, motorSpin = 0;
  const droneX = 200, droneY = 110, armLen = 40;

  function drawDrone(cx, cy, angle) {
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(angle);
    ctx.strokeStyle = 'rgba(238,147,70,0.9)'; ctx.lineWidth = 3;
    [[1,1],[1,-1],[-1,1],[-1,-1]].forEach(([dx, dy]) => {
      ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(dx * armLen, dy * armLen * 0.7); ctx.stroke();
    });
    ctx.fillStyle = '#10256D'; ctx.beginPath(); ctx.roundRect(-12, -8, 24, 16, 4); ctx.fill();
    [[1,1],[1,-1],[-1,1],[-1,-1]].forEach(([dx, dy], i) => {
      ctx.save(); ctx.translate(dx * armLen, dy * armLen * 0.7);
      ctx.fillStyle = '#EE9346'; ctx.beginPath(); ctx.arc(0, 0, 7, 0, Math.PI * 2); ctx.fill();
      ctx.save(); ctx.rotate(motorSpin + i * Math.PI / 2);
      ctx.strokeStyle = 'rgba(255,255,255,0.5)'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.arc(0, 0, 12, 0, Math.PI); ctx.stroke();
      ctx.beginPath(); ctx.arc(0, 0, 12, Math.PI, Math.PI * 2); ctx.stroke();
      ctx.restore(); ctx.restore();
    });
    ctx.fillStyle = '#28c840'; ctx.beginPath(); ctx.arc(0, 0, 4, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }

  function frame() {
    t++; motorSpin += 0.25;
    const driftX = Math.sin(t * 0.02) * 25 + Math.sin(t * 0.007) * 15;
    const driftY = Math.cos(t * 0.015) * 15 + Math.sin(t * 0.03) * 8;
    const yaw    = Math.sin(t * 0.01) * 0.08;
    const cx = droneX + driftX, cy = droneY + driftY;
    const W = canvas.width, H = canvas.height;
    ctx.clearRect(0, 0, W, H);
    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#0d1b2a'); bg.addColorStop(1, '#1a3a5c');
    ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = 'rgba(16,37,109,0.12)'; ctx.lineWidth = 1;
    for (let x = 0; x < W; x += 40) { ctx.beginPath(); ctx.moveTo(x,0); ctx.lineTo(x,H); ctx.stroke(); }
    for (let y = 0; y < H; y += 40) { ctx.beginPath(); ctx.moveTo(0,y); ctx.lineTo(W,y); ctx.stroke(); }
    ctx.strokeStyle = 'rgba(238,147,70,0.25)'; ctx.lineWidth = 1; ctx.setLineDash([6,6]);
    ctx.beginPath(); ctx.moveTo(0, H/2); ctx.lineTo(W, H/2); ctx.stroke(); ctx.setLineDash([]);
    ctx.strokeStyle = 'rgba(40,200,64,0.4)'; ctx.lineWidth = 1; ctx.setLineDash([4,4]);
    ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx, H/2); ctx.stroke(); ctx.setLineDash([]);
    const grad = ctx.createRadialGradient(cx, cy+30, 5, cx, cy+30, 40);
    grad.addColorStop(0, 'rgba(0,0,0,0.3)'); grad.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = grad; ctx.beginPath(); ctx.ellipse(cx, cy+30, 35, 12, 0, 0, Math.PI*2); ctx.fill();
    drawDrone(cx, cy, yaw);
    const alt = 42.3 + Math.sin(t * 0.02) * 3.1;
    const spd = 8.1  + Math.cos(t * 0.03) * 2.4;
    const bat = Math.max(75, 87 - t * 0.002);
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
</script>
</body>
</html>