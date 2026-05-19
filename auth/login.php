<?php 
    require 'db.php';

    if($_SERVER['REQUEST_METHOD'] !== 'POST'){

        die('Invalid Request');
    }

    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($email === "" || $password === "") {

    die("Email and password are required.");
}

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email address.");
}

    $users = $db->users;

    $user = $users->findOne(['email' => $email]);

if (!$user) {
    die("User not found.");
}

if (!password_verify($password, $user['password'])) {
    die("Invalid password.");
}



?>