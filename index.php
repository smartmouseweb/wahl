<?php
include('./config.php');

use Service\Router;
use Controller\ContactController;
use Controller\CollectionController;

$router = new Router;

$router->addRedirect('GET', '/save/', '/');
$router->addRoute('GET', '/', function () { echo ContactController::index(); });
$router->addRoute('GET', '/create/', function () { echo ContactController::create(); });
$router->addRoute('GET', '/edit/{id}', function ($id) { echo ContactController::edit($id); });
$router->addRoute('POST', '/save/', function () { echo ContactController::save(); });
$router->addRoute('GET', '/delete/{id}', function ($id) { echo ContactController::delete($id); });
$router->addRoute('GET', '/export-xml/', function () { echo ContactController::exportXml(); });
$router->addRoute('GET', '/export-json/', function () { echo ContactController::exportJson(); });

$router->addRedirect('GET', '/groups/save/', '/');
$router->addRoute('GET', '/groups/', function () { echo CollectionController::index(); });
$router->addRoute('GET', '/groups/create/', function () { echo CollectionController::create(); });
$router->addRoute('GET', '/groups/edit/{id}', function ($id) { echo CollectionController::edit($id); });
$router->addRoute('POST', '/groups/save/', function () { echo CollectionController::save(); });
$router->addRoute('GET', '/groups/delete/{id}', function ($id) { echo CollectionController::delete($id); });

$router->matchRoute();

?>
