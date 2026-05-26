<?php
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\auth\db.php';
$email = 'maiadarshkumar@gmail.com';
$user = $db->users->findOne(['email' => $email]);
$sub_started = isset($user['sub_started']) ? (bool)$user['sub_started'] : false;
var_dump($sub_started);
