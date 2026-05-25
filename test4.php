<?php
require 'auth/db.php';
$sub = $db->subscriptions->findOne(['id' => 0]);
var_dump($sub);
