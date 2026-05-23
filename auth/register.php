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

$result = $users->insertOne([
    'name' => $name,
    'email' => $email,
    'password' => $hashedPassword,
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    'org_id' => '',
    'auth_provid' => 0,
    'sub_id' => 1,
    'expires_at' => '',
    'wallet_balance' => 50.0,
    'is_verified' => false,
    'verification_token' => $verificationToken
]);

if ($result->getInsertedCount() > 0) {
    // Generate the verification link based on the current host
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $verifyLink = "$protocol://$host$uri/verify.php?token=$verificationToken";
    
    // Log it to the console/error log as requested
    error_log("VERIFICATION LINK FOR $email: " . $verifyLink);
    
    // Output it in the response for easy access
    echo "Account created successfully. \n\nVerification Link (pasted below for testing):\n" . $verifyLink;
    
} else {
    die("Registration failed.");
}
?>