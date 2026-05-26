<?php
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\auth\db.php';
$db->users->updateOne(['email' => 'maiadarshkumar@gmail.com'], ['$set' => ['wallet_balance' => 0.0]]);
