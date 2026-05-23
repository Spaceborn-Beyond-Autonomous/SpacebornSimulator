<?php 

require '../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

try{
$client = new MongoDB\Client($_ENV['MONGODB_URI']);
$db = $client -> spaceborn;
}
catch(Exception $e) {

    echo 'Connection Failed : '. $e ;

}

?>