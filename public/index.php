<?php
// FRONT CONTROLLER
require getenv('PROJECT_ROOT') . '/vendor/autoload.php';
use App\Bootstrap\SlimBootstrap;
use App\Bootstrap\DIContainerBootstrap;

//
$container = DIContainerBootstrap::create();
$app = SlimBootstrap::createApp($container);
$app->run();
