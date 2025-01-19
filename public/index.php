<?php
namespace Theosche\RoomReservation;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/../src/exceptionHandler.php';

$viewsDir = __DIR__ . '/../views/';
$backendDir = __DIR__ . '/../backend/';

$router = new Router();
$router->add('GET', '/', $viewsDir . 'reservation.php');
$router->add('GET', 'reservation', $viewsDir . 'reservation.php');
$router->add('GET', 'reservation.php', $viewsDir . 'reservation.php');

$router->add('GET', 'login', $viewsDir . 'login.php'); 
$router->add('GET', 'login.php', $viewsDir . 'login.php');
$router->add('POST', 'login', $viewsDir . 'login.php'); 
$router->add('POST', 'login.php', $viewsDir . 'login.php');

$router->add('GET', 'availability.php', $backendDir . 'availability.php');
$router->add('GET', 'getevent.php', $backendDir . 'getevent.php');
$router->add('POST', 'form.php', $backendDir . 'form.php');

$router->add('GET', 'admin', $viewsDir . 'admin/admin.php', true);
$router->add('GET', 'admin.php', $viewsDir . 'admin/admin.php', true); 
$router->add('GET', 'admin-single.php', $viewsDir . 'admin/admin-single.php', true);
$router->add('GET', 'setup.php', $viewsDir . 'admin/setup.php', true);
$router->add('POST', 'setup.php', $viewsDir . 'admin/setup.php', true, $viewsDir . 'admin/admin.php');
$router->add('GET', 'update-structure.php', $viewsDir . 'admin/update-structure.php', true);
$router->add('POST', 'update-structure.php', $viewsDir . 'admin/update-structure.php', true, $viewsDir . 'admin/admin.php'); 

$router->add('POST', 'admin-form.php', $backendDir . 'admin/admin-form.php', true, $viewsDir . 'admin/admin.php');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$router->dispatch($path); 


?>