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

$result = $users->insertOne([
    'name' => $name,
    'email' => $email,
    'password' => $hashedPassword,
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    'org_id' => '',
    'auth_provid' => 0,
    'sub_id' => 1,
    'expires_at' => '',
    'is_verified' => 0
]);

if ($result->getInsertedCount() > 0) {
    echo "Account created successfully , Check your email for verification link";
    
} else {
    die("Registration failed.");
}
?>