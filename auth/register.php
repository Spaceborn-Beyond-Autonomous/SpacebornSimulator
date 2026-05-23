<?php

require 'db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";

if ($name === "" || $email === "" || $password === "" || $confirmPassword === "") {
    die("All fields are required.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email address.");
}

if (strlen($password) < 8) {
    die("Password must be at least 8 characters.");
}

if ($password !== $confirmPassword) {
    die("Passwords do not match.");
}

$users = $db->users;

$existingUser = $users->findOne(['email' => $email]);

if ($existingUser) {
    die("Email already registered.");
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$verificationToken = bin2hex(random_bytes(32));

$basis_minutes = (float)($_ENV['PLAN_BASIS_MINUTES'] ?? 60);
$now = new DateTime();
$activated_at = new MongoDB\BSON\UTCDateTime($now);
$now_expires = clone $now;
$now_expires->modify("+" . (int)$basis_minutes . " minutes");
$expires_at = new MongoDB\BSON\UTCDateTime($now_expires);

$result = $users->insertOne([
    'name' => $name,
    'email' => $email,
    'password' => $hashedPassword,
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    'org_id' => '',
    'auth_provid' => 0,
    'sub_id' => 1,
    'sub_started' => false,
    'sub_activated_at' => null,
    'sub_expires_at' => null,
    'wallet_balance' => 50.0,
    'is_verified' => false,
    'verification_token' => $verificationToken
]);

if ($result->getInsertedCount() > 0) {
    // Generate the verification link using APP_URL if set, else fallback to current host
    $appUrl = $_ENV['APP_URL'] ?? '';
    if (!empty($appUrl)) {
        $verifyLink = rtrim($appUrl, '/') . "/auth/verify.php?token=" . $verificationToken;
    } else {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $verifyLink = "$protocol://$host$uri/verify.php?token=$verificationToken";
    }
    
    // Log it to the console/error log as requested
    error_log("VERIFICATION LINK FOR $email: " . $verifyLink);
    
    // Output it in the response for easy access
    echo "Account created successfully. \n\nVerification Link (pasted below for testing):\n" . $verifyLink;
    
} else {
    die("Registration failed.");
}
?>