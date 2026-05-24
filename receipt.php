<?php
require 'auth/session_guard.php';
require 'auth/db.php';

$payment_id = $_GET['payment_id'] ?? '';
if (empty($payment_id)) {
    die("Missing payment ID.");
}

$email = $_SESSION['email'] ?? '';
$invoice = $db->invoices->findOne([
    'email' => $email,
    'payment_id' => $payment_id
]);

if (!$invoice) {
    die("Invoice not found.");
}

$date = '';
if (isset($invoice['created_at']) && $invoice['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
    $date = $invoice['created_at']->toDateTime()->format('j M Y, g:i A');
}
$amount = (float)($invoice['amount'] ?? 0);
$description = (string)($invoice['description'] ?? '');
$status = (string)($invoice['status'] ?? 'paid');
$name = htmlspecialchars($_SESSION['name'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Certanity — Receipt <?= htmlspecialchars($payment_id) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--primary:#10256D;--secondary:#EE9346;--accent:#28c840;--bg:#0e1117;--surface:#1a1f2e;--surface2:#212637;--border:rgba(255,255,255,0.06);--text:#e8eaf0;--text2:#8b92a8;--text3:#5a6078;--r:14px;}
    [data-theme="light"]{--bg:#e8eaf0;--surface:#eaecf4;--surface2:#f0f2f8;--border:rgba(0,0,0,0.06);--text:#1a1f35;--text2:#5a6078;--text3:#9099b8;}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;justify-content:center;align-items:flex-start;min-height:100vh;padding:40px 20px;transition:background .3s,color .3s;}

    .receipt{background:var(--surface);border-radius:var(--r);max-width:520px;width:100%;padding:36px 32px;box-shadow:0 8px 40px rgba(0,0,0,.3);position:relative;overflow:hidden;}
    .receipt::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--primary),var(--secondary));}

    .receipt-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;padding-bottom:20px;border-bottom:1px solid var(--border);}
    .receipt-logo{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;letter-spacing:-.02em;}
    .receipt-logo span{color:var(--secondary);}
    .receipt-badge{font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:.06em;}
    .receipt-badge.paid{background:rgba(40,200,64,.12);color:var(--accent);}
    .receipt-badge.pending{background:rgba(238,147,70,.12);color:var(--secondary);}
    .receipt-badge.failed{background:rgba(224,85,85,.12);color:#e05555;}

    .receipt-section{margin-bottom:22px;}
    .receipt-section-title{font-size:10px;font-weight:600;color:var(--text3);letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px;}
    .receipt-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;}
    .receipt-row-label{font-size:13px;color:var(--text2);}
    .receipt-row-value{font-size:13px;font-weight:600;color:var(--text);}
    .receipt-row-value.mono{font-family:monospace;font-size:12px;color:var(--text2);}

    .receipt-divider{height:1px;background:var(--border);margin:16px 0;}

    .receipt-total{display:flex;justify-content:space-between;align-items:baseline;padding:16px 0 0;}
    .receipt-total-label{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;}
    .receipt-total-amount{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:var(--secondary);}

    .receipt-footer{margin-top:28px;padding-top:20px;border-top:1px solid var(--border);text-align:center;}
    .receipt-footer p{font-size:11.5px;color:var(--text3);line-height:1.6;}

    .receipt-actions{display:flex;gap:10px;justify-content:center;margin-top:20px;}
    .btn{display:inline-flex;align-items:center;gap:6px;border-radius:9px;padding:8px 18px;font-size:12.5px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all .18s;text-decoration:none;border:none;}
    .btn-primary{background:var(--secondary);color:#fff;box-shadow:0 4px 14px rgba(238,147,70,.3);}
    .btn-primary:hover{opacity:.88;transform:translateY(-1px);}
    .btn-ghost{background:var(--surface2);color:var(--text2);box-shadow:3px 3px 8px rgba(0,0,0,.2);}
    .btn-ghost:hover{color:var(--text);}

    @media print{
      body{background:#fff;padding:20px;}
      .receipt{box-shadow:none;border:1px solid #ddd;}
      .receipt::before{display:none;}
      .receipt-actions{display:none!important;}
      :root{--bg:#fff;--surface:#fff;--surface2:#f5f5f5;--border:#ddd;--text:#222;--text2:#666;--text3:#999;}
    }
  </style>
  <script>(function(){var t=localStorage.getItem('sb_theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
</head>
<body>

<div class="receipt">
  <div class="receipt-header">
    <div class="receipt-logo">Space<span>born</span></div>
    <span class="receipt-badge <?= $status ?>"><?= ucfirst($status) ?></span>
  </div>

  <div class="receipt-section">
    <div class="receipt-section-title">Transaction Details</div>
    <div class="receipt-row">
      <span class="receipt-row-label">Payment ID</span>
      <span class="receipt-row-value mono"><?= htmlspecialchars($payment_id) ?></span>
    </div>
    <div class="receipt-row">
      <span class="receipt-row-label">Date</span>
      <span class="receipt-row-value"><?= htmlspecialchars($date) ?></span>
    </div>
    <div class="receipt-row">
      <span class="receipt-row-label">Description</span>
      <span class="receipt-row-value"><?= htmlspecialchars($description) ?></span>
    </div>
  </div>

  <div class="receipt-divider"></div>

  <div class="receipt-section">
    <div class="receipt-section-title">Billed To</div>
    <div class="receipt-row">
      <span class="receipt-row-label">Name</span>
      <span class="receipt-row-value"><?= $name ?></span>
    </div>
    <div class="receipt-row">
      <span class="receipt-row-label">Email</span>
      <span class="receipt-row-value"><?= htmlspecialchars($email) ?></span>
    </div>
  </div>

  <div class="receipt-divider"></div>

  <div class="receipt-total">
    <span class="receipt-total-label">Total</span>
    <span class="receipt-total-amount"><?= $amount < 0 ? '-' : '' ?>$<?= number_format(abs($amount), 2) ?></span>
  </div>

  <div class="receipt-footer">
    <p>Thank you for using Certanity Robotics.<br>This receipt was generated automatically.</p>
    <div class="receipt-actions">
      <button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>
      <a class="btn btn-ghost" href="billing.php">← Back to Billing</a>
    </div>
  </div>
</div>

</body>
</html>
