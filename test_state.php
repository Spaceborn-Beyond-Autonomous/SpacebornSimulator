<?php
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\auth\db.php';
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\includes\simulator_launch.php';
$u = $db->users->findOne(['email' => 'maiadarshkumar@gmail.com']);
var_dump(sb_paid_plan_state($u, true));
