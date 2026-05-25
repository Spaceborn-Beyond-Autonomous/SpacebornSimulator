<?php
$_SESSION['email'] = 'maiadarshkumar@gmail.com';
$_SESSION['name'] = 'User';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require 'billing.php';
$html = ob_get_clean();

echo "--- Banner Name ---\n";
preg_match('/<div class="plan-banner-name">(.*?)<\/div>/', $html, $m);
echo $m[1] ?? 'NOT FOUND';
echo "\n--- Badge Current ---\n";
preg_match('/<div class="plan-card-badge badge-current">(.*?)<\/div>/', $html, $m);
echo $m[1] ?? 'NOT FOUND';
echo "\n--- Basic Button ---\n";
preg_match('/Upgrade to BASIC/', $html, $m);
echo $m[0] ?? 'NOT FOUND';
echo "\n";
