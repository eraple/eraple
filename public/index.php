<?php

/* Require composer autoload file. */
require_once dirname(__FILE__) . '/../vendor/autoload.php';

/* Run application. */
$rootPath = dirname(__FILE__) . '/../';
\Eraple\App::instance($rootPath)->run();
