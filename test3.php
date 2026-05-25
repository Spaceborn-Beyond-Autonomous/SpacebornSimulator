<?php
require 'auth/db.php';

// Temporarily set the user's sub_id to 0 to see what billing.php would evaluate.
$db->users->updateOne(['email' => 'maiadarshkumar@gmail.com'], ['$set' => ['sub_id' => 0]]);

$user = $db->users->findOne(['email' => 'maiadarshkumar@gmail.com']);
var_dump("Updated sub_id to:", $user['sub_id']);
