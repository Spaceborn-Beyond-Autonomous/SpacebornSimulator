<?php
require 'auth/db.php';
$u = $db->users->findOne();
var_dump($u['email'], $u['sub_id']);
$sub = $db->subscriptions->findOne(['id' => 0]);
var_dump($sub);
