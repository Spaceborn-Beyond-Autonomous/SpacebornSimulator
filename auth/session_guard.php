<?php 

session_start();

if(!isset($_SESSION['id']) || !isset($_SESSION['email'])){
    header('Location: index.php');
    exit;
}

$name = $_SESSION['name'];


?>