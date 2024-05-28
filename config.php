<?php
declare(strict_types=1);

use Service\DB;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

$envData = parse_ini_file('config.env');
foreach ($envData as $key => $value)
{
    putenv($key.'='.$value);
}

require_once './vendor/autoload.php';

if (isset($_GET['debug']) && !defined('DEBUG'))
	define('DEBUG', $_GET['debug']);

DB::init();
?>
