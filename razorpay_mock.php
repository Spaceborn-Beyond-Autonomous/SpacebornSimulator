<?php
require_once __DIR__ . '/auth/session_guard.php';

$plan_id = (int)($_GET['plan_id'] ?? 0);
if ($plan_id < 2 || $plan_id > 3) {
    // Only PRO (2) and MAX (3) can be upgraded to. BASIS (1) is the base tier.
    header("Location: billing.php");
    exit;
}

$plans = [
    2 => ['name' => 'PRO', 'price' => (float)($_ENV['PLAN_PRO_PRICE'] ?? 5.0), 'period' => '1 day', 'desc' => '24-Hour Premium Drone Access'],
    3 => ['name' => 'MAX', 'price' => (float)($_ENV['PLAN_MAX_PRICE'] ?? 20.0), 'period' => '1 month', 'desc' => 'Unlimited 30-Day Pro Access']
];

$plan = $plans[$plan_id];
$plan_name = $plan['name'];
$price = $plan['price'];
$desc = $plan['desc'];

$email = $_SESSION['email'] ?? 'pilot@spaceborn.com';
$phone = $_SESSION['phone'] ?? '+91 98765 43210';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Razorpay Secure Checkout</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --rp-dark: #090e1a;
      --rp-surface: #12192c;
      --rp-surface-light: #1c263f;
      --rp-primary: #3395FF;
      --rp-primary-dark: #1a75d2;
      --rp-success: #10b981;
      --rp-danger: #ef4444;
      --rp-text: #f8fafc;
      --rp-text-muted: #94a3b8;
      --rp-border: rgba(255, 255, 255, 0.08);
      --r-lg: 18px;
      --r-md: 12px;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: linear-gradient(135deg, #020617 0%, #0f172a 100%);
      color: var(--rp-text);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
      overflow-x: hidden;
    }

    /* Background decorative glowing circles */
    .glow-circle {
      position: absolute;
      width: 400px;
      height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(51, 149, 255, 0.15) 0%, rgba(51, 149, 255, 0) 70%);
      z-index: -1;
      filter: blur(40px);
    }
    .glow-1 { top: -100px; left: -100px; }
    .glow-2 { bottom: -100px; right: -100px; }

    /* Main Gateway Card */
    .checkout-container {
      background: rgba(18, 25, 44, 0.7);
      backdrop-filter: blur(16px);
      border: 1px solid var(--rp-border);
      width: 100%;
      max-width: 800px;
      border-radius: var(--r-lg);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px rgba(51, 149, 255, 0.1);
      display: grid;
      grid-template-columns: 1.1fr 1.3fr;
      overflow: hidden;
      animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Left pane (Merchant and Order details) */
    .merchant-pane {
      background: rgba(9, 14, 26, 0.6);
      padding: 40px 30px;
      border-right: 1px solid var(--rp-border);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .merchant-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 30px;
    }

    .merchant-logo {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, var(--rp-primary), #a855f7);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 18px;
      color: white;
      box-shadow: 0 4px 12px rgba(51, 149, 255, 0.3);
    }

    .merchant-name {
      font-family: 'Outfit', sans-serif;
      font-weight: 600;
      font-size: 16px;
      letter-spacing: 0.5px;
    }

    .order-details {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      margin-bottom: 40px;
    }

    .plan-badge {
      display: inline-block;
      align-self: flex-start;
      padding: 4px 10px;
      background: rgba(51, 149, 255, 0.12);
      border: 1px solid rgba(51, 149, 255, 0.2);
      color: var(--rp-primary);
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 12px;
    }

    .order-desc {
      font-size: 14px;
      color: var(--rp-text-muted);
      margin-bottom: 8px;
    }

    .amount-display {
      font-family: 'Outfit', sans-serif;
      font-size: 38px;
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: baseline;
      gap: 2px;
    }

    .amount-display span.currency {
      font-size: 20px;
      font-weight: 500;
      color: var(--rp-primary);
    }

    .amount-display span.period {
      font-size: 14px;
      font-weight: 400;
      color: var(--rp-text-muted);
    }

    .merchant-footer {
      font-size: 12px;
      color: var(--rp-text-muted);
    }

    .merchant-footer a {
      color: var(--rp-primary);
      text-decoration: none;
    }

    /* Right pane (Payment Gateway interface) */
    .payment-pane {
      padding: 40px 30px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .payment-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }

    .rzp-secure-logo {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: bold;
      color: var(--rp-text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .rzp-secure-logo svg {
      color: var(--rp-primary);
    }

    .sandbox-tag {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.2);
      color: var(--rp-danger);
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    /* Navigation Tabs */
    .payment-tabs {
      display: flex;
      background: var(--rp-dark);
      padding: 4px;
      border-radius: var(--r-md);
      margin-bottom: 20px;
      border: 1px solid var(--rp-border);
    }

    .tab-btn {
      flex: 1;
      background: transparent;
      border: none;
      color: var(--rp-text-muted);
      padding: 10px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }

    .tab-btn svg {
      width: 16px;
      height: 16px;
    }

    .tab-btn.active {
      background: var(--rp-surface-light);
      color: #fff;
    }

    /* Tab Content Boxes */
    .tab-content {
      display: none;
      min-height: 180px;
      animation: fadeIn 0.3s ease;
    }

    .tab-content.active {
      display: block;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(5px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .form-group {
      margin-bottom: 14px;
    }

    .form-group label {
      display: block;
      font-size: 11px;
      color: var(--rp-text-muted);
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .input-field {
      width: 100%;
      background: var(--rp-dark);
      border: 1px solid var(--rp-border);
      color: #fff;
      padding: 12px 14px;
      border-radius: 8px;
      font-size: 13.5px;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .input-field:focus {
      border-color: var(--rp-primary);
      box-shadow: 0 0 10px rgba(51, 149, 255, 0.15);
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    /* Bank selection list */
    .bank-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }

    .bank-btn {
      background: var(--rp-dark);
      border: 1px solid var(--rp-border);
      color: var(--rp-text-muted);
      padding: 12px;
      border-radius: 8px;
      font-size: 12px;
      text-align: left;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .bank-btn:hover {
      border-color: var(--rp-primary);
      color: #fff;
      background: rgba(51, 149, 255, 0.05);
    }

    /* Simulator Action Section */
    .action-section {
      margin-top: 25px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .btn-pay {
      width: 100%;
      padding: 14px;
      border-radius: var(--r-md);
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s;
      border: none;
      text-decoration: none;
    }

    .btn-success {
      background: linear-gradient(135deg, var(--rp-success) 0%, #059669 100%);
      color: white;
      box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    }

    .btn-cancel {
      background: transparent;
      border: 1px solid var(--rp-border);
      color: var(--rp-text-muted);
    }

    .btn-cancel:hover {
      background: rgba(239, 68, 68, 0.08);
      color: var(--rp-danger);
      border-color: rgba(239, 68, 68, 0.2);
    }

    @media (max-width: 768px) {
      .checkout-container {
        grid-template-columns: 1fr;
      }
      .merchant-pane {
        border-right: none;
        border-bottom: 1px solid var(--rp-border);
        padding: 30px;
      }
      .payment-pane {
        padding: 30px;
      }
    }
  </style>
</head>
<body>

<div class="glow-circle glow-1"></div>
<div class="glow-circle glow-2"></div>

<div class="checkout-container">
  <!-- Left Side: Order details -->
  <div class="merchant-pane">
    <div class="merchant-header">
      <div class="merchant-logo">S</div>
      <div class="merchant-name">SpaceBorn Drones</div>
    </div>

    <div class="order-details">
      <span class="plan-badge"><?= htmlspecialchars($plan_name) ?> Plan Upgrade</span>
      <div class="order-desc"><?= htmlspecialchars($desc) ?></div>
      <div class="amount-display">
        <span class="currency">$</span>
        <span><?= number_format($price, 2) ?></span>
        <span class="period">/<?= $plan['period'] ?></span>
      </div>
    </div>

    <div class="merchant-footer">
      Support: <a href="mailto:support@spaceborn.com">support@spaceborn.com</a>
    </div>
  </div>

  <!-- Right Side: Razorpay simulator interface -->
  <div class="payment-pane">
    <div>
      <div class="payment-header">
        <div class="rzp-secure-logo">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          Razorpay Secure
        </div>
        <div class="sandbox-tag">Sandbox Mock</div>
      </div>

      <!-- Navigation Tabs -->
      <div class="payment-tabs">
        <button class="tab-btn active" onclick="switchTab('card')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
            <line x1="1" y1="10" x2="23" y2="10"></line>
          </svg>
          Card
        </button>
        <button class="tab-btn" onclick="switchTab('upi')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
          UPI ID
        </button>
        <button class="tab-btn" onclick="switchTab('netbanking')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9,22 9,12 15,12 15,22"></polyline>
          </svg>
          Netbanking
        </button>
      </div>

      <!-- Card Tab Content -->
      <div id="tab-card" class="tab-content active">
        <div class="form-group">
          <label>Card Number</label>
          <input type="text" class="input-field" value="4111 2222 3333 4444" placeholder="Card Number" readonly>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Expiry Date</label>
            <input type="text" class="input-field" value="12/29" placeholder="MM/YY" readonly>
          </div>
          <div class="form-group">
            <label>CVV</label>
            <input type="password" class="input-field" value="123" placeholder="CVV" readonly>
          </div>
        </div>
        <div class="form-group">
          <label>Card Holder</label>
          <input type="text" class="input-field" value="Pilot Candidate" readonly>
        </div>
      </div>

      <!-- UPI Tab Content -->
      <div id="tab-upi" class="tab-content">
        <div class="form-group">
          <label>Enter Virtual Payment Address (VPA)</label>
          <input type="text" class="input-field" value="<?= htmlspecialchars(strstr($email, '@', true)) ?>@okaxis" placeholder="username@upi" readonly>
        </div>
        <div style="background: rgba(51, 149, 255, 0.05); padding: 12px; border-radius: 8px; border: 1px dashed rgba(51, 149, 255, 0.2); font-size: 12px; color: var(--rp-text-muted); line-height: 1.4;">
          A mock payment request will be sent to your virtual payment address. Select pay on your app to verify.
        </div>
      </div>

      <!-- Netbanking Tab Content -->
      <div id="tab-netbanking" class="tab-content">
        <label style="display: block; font-size: 11px; color: var(--rp-text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Popular Banks</label>
        <div class="bank-grid">
          <button class="bank-btn">🏦 State Bank of India</button>
          <button class="bank-btn">🏦 HDFC Bank</button>
          <button class="bank-btn">🏦 ICICI Bank</button>
          <button class="bank-btn">🏦 Axis Bank</button>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="action-section">
      <!-- Successful Payment Form -->
      <form id="successForm" method="GET" action="razorpay_callback.php">
        <input type="hidden" name="status" value="success">
        <input type="hidden" name="plan_id" value="<?= $plan_id ?>">
        <input type="hidden" name="payment_id" value="pay_<?= bin2hex(random_bytes(6)) ?>">
        <button type="submit" class="btn-pay btn-success">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="20,6 9,17 4,12"></polyline>
          </svg>
          Simulate Successful Payment
        </button>
      </form>

      <!-- Failure / Cancel Button -->
      <a href="billing.php?msg=payment_failed" class="btn-pay btn-cancel">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
        Cancel & Return to Merchant
      </a>
    </div>
  </div>
</div>

<script>
  function switchTab(tabId) {
    // Deactivate all tabs
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    // Find clicked tab button
    const activeBtn = Array.from(document.querySelectorAll('.tab-btn')).find(btn => btn.textContent.toLowerCase().includes(tabId));
    if (activeBtn) activeBtn.classList.add('active');

    // Activate corresponding tab content
    const activeContent = document.getElementById('tab-' + tabId);
    if (activeContent) activeContent.classList.add('active');
  }
</script>

</body>
</html>
