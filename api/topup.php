<?php
require_once __DIR__ . '/../auth/session_guard.php';

// Wallet credits require a verified Razorpay payment — see payments/verify_topup.php
header('Location: ../billing.php?payment=invalid');
exit;
