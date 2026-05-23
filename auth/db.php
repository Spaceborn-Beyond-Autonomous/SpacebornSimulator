<?php 

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Enable CORS from APP_URL if specified
if (isset($_ENV['APP_URL']) && !empty($_ENV['APP_URL'])) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed_origin = $_ENV['APP_URL'];
    if ($origin && ($allowed_origin === '*' || stripos($origin, $allowed_origin) !== false)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    }
}
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try{
$client = new MongoDB\Client($_ENV['MONGODB_URI']);
$db = $client -> spaceborn;
}
catch(Exception $e) {

    echo 'Connection Failed : '. $e ;

}

?>