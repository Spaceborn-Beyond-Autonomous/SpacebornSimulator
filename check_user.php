<?php
require_once __DIR__ . '/auth/db.php';
$user = $db->users->findOne();
print_r(array_keys((array)$user));
