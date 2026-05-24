<?php 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id']) || !isset($_SESSION['email'])) {
    $current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
    $app_root_dir = str_replace('\\', '/', dirname(__DIR__));
    
    $relative_path = '';
    if ($current_dir !== $app_root_dir) {
        $diff = substr($current_dir, strlen($app_root_dir));
        $levels = substr_count(trim($diff, '/'), '/');
        $relative_path = str_repeat('../', $levels + 1);
    } else {
        $relative_path = './';
    }
    
    header('Location: ' . $relative_path . 'index.php');
    exit;
}

$name = $_SESSION['name'] ?? '';

?>