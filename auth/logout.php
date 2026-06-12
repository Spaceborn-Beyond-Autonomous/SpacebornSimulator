<?php

require_once __DIR__ . '/session_config.php';
sb_configure_session();
session_start();

if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/db.php';

$db->sessions->updateOne(
    ['_id' => new MongoDB\BSON\ObjectId($_SESSION['id'])],
    ['$set' => ['is_running' => false]]
);

$_SESSION = [];
session_regenerate_id(true);
session_destroy();

header('Location: ../index.php');
exit;