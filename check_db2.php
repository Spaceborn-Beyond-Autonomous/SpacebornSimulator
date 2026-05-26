<?php
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\auth\db.php';
$u = $db->users->findOne(['email' => 'maiadarshkumar@gmail.com']);
var_dump($u);
