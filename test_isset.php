<?php
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\auth\db.php';
$d = current($db->users->find()->toArray());
var_dump(isset($d['sub_started']));
var_dump($d['sub_started']);
