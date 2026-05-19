<?php 

require 'vendor/autoload.php';
try{
$client = new MongoDB\Client("mongodb://localhost:27017/");
$db = $client -> spaceborn;
}
catch(Exception $e) {

    echo 'Connection Failed : '. $e ;

}

?>