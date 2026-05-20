<?php 

require 'db.php';
session_start();

if(!isset($_SESSION['id'])){
    header('Location: ../index.php');
    exit;
}
$s = $db -> sessions -> updateOne(
    ['_id' => new MongoDB\BSON\ObjectId($_SESSION['id'])],
    [ "$set" =>['is_running' => false]]);
    
$_SESSION = [];
session_destroy();
header('Location: ../index.php');
exit;

?>