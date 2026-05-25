<?php
require 'auth/db.php';
$user = $db->users->findOne();
var_dump($user['sub_started'], $user['sub_expires_at']);
