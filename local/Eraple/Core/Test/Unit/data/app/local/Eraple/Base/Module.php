<?php

namespace Eraple\Core\Test\Unit\Data\App\Local\Eraple\Base;

use Eraple\Core\Test\Unit\Data\App\Local\Eraple\Base\Task\TaskOne;
use Eraple\Core\Test\Unit\Data\App\Local\Eraple\Base\Task\TaskTwo;
use Eraple\Core\Test\Unit\Data\App\Local\Eraple\Base\Task\TaskThree;

class Module extends \Eraple\Core\Module
{
    protected static $name = 'base';

    protected static $version = '1.0.0';

    protected static $description = 'I will handle the base functions.';

    protected static $tasks = [
        TaskOne::class,
        TaskTwo::class,
        TaskThree::class
    ];
}

return Module::class;
