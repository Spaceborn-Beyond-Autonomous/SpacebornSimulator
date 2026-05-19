<?php 
    require 'db.php';

    if($_SERVER['REQUEST_METHOD'] !== 'POST'){

        die('Invalid Request');
    }

    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($email === "" || $pass === "") {

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

if (!password_verify($pass, $user['password'])) {
    die("Invalid password.");
}

if($user['is_verified'] !== true){
    die('Please verify your email');
}

session_start();

$_SESSION['email'] = $email;


$sessCol = $db -> sessions;

$result = $sessCol ->insertOne(['email' => $email , "is_running" => true]);

$_SESSION['id'] = (string)$result -> getInsertedId();

header('Location: ../dashboard.php');
exit();

?>