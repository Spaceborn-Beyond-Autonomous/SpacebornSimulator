<?php 

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origin = $_ENV['APP_URL'] ?? '';
$isAllowed = false;

if ($origin) {
    if ($allowed_origin === '*' || ($allowed_origin && stripos($origin, $allowed_origin) !== false)) {
        $isAllowed = true;
    } elseif (preg_match('/\.onrender\.com$/i', parse_url($origin, PHP_URL_HOST))) {
        $isAllowed = true;
    }
}

if ($isAllowed) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try{
$client = new MongoDB\Client($_ENV['MONGODB_URI']);
$db = $client -> certanity;
}
catch(Exception $e) {

    echo 'Connection Failed : '. $e ;

}

?>