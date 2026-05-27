<?php
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\auth\db.php';
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\includes\simulator_launch.php';

$email = 'maiadarshkumar@gmail.com';
$user = $db->users->findOne(['email' => $email]);
var_dump($user['sub_started']);

$now = time();
$db->users->updateOne(['email' => $email], ['$set' => ['sub_started' => false, 'sub_activated_at' => null, 'sub_expires_at' => null]]);

$user = $db->users->findOne(['email' => $email]);
$state = sb_paid_plan_state($user, true);
var_dump($state['remaining_seconds']);

$user2 = $db->users->findOne(['email' => $email]);
var_dump($user2['sub_started']);
