<?php
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\auth\db.php';
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\includes\simulator_launch.php';

$now = time();
$db->users->updateOne(
    ['email' => 'maiadarshkumar@gmail.com'],
    ['$set' => [
        'sub_id' => 2,
        'sub_started' => true,
        'sub_activated_at' => new MongoDB\BSON\UTCDateTime($now * 1000),
        'sub_expires_at' => new MongoDB\BSON\UTCDateTime(($now + 3600) * 1000)
    ]]
);

$u = $db->users->findOne(['email' => 'maiadarshkumar@gmail.com']);
var_dump(sb_paid_plan_state($u, true));
