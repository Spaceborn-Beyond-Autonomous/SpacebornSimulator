<?php 

require '../vendor/autoload.php';
try{
$client = new MongoDB\Client("mongodb+srv://spar123:Hello%40123@testing.hnrldbw.mongodb.net/?appName=Testing");
$db = $client -> spaceborn;
}
catch(Exception $e) {

    echo 'Connection Failed : '. $e ;

}

?>