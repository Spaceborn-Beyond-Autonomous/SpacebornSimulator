<?php
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    if ($amount > 0 && isset($_SESSION['email'])) {
        $usersCol = $db->users;
        $email = $_SESSION['email'];
        
        // Update DB
        $usersCol->updateOne(
            ['email' => $email],
            ['$inc' => ['wallet_balance' => $amount]]
        );
        
        // Update session
        $_SESSION['wallet_balance'] = ($_SESSION['wallet_balance'] ?? 0) + $amount;
    }
}

// Redirect back to billing
header('Location: ../billing.php');
exit;
