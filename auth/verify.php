<?php
require 'db.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Invalid verification link.");
}

$users = $db->users;
$user = $users->findOne(['verification_token' => $token]);

if (!$user) {
    die("Invalid or expired verification token.");
}

// Update the user to set is_verified to true and remove the token
$result = $users->updateOne(
    ['_id' => $user['_id']],
    [
        '$set' => ['is_verified' => true],
        '$unset' => ['verification_token' => '']
    ]
);

if ($result->getModifiedCount() > 0) {
    echo "<h2>Email verified successfully!</h2>";
    echo "<p>You can now <a href='../index.php'>login</a>.</p>";
} else {
    echo "Failed to verify email. Please try again or contact support.";
}
?>
