<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base;

use Eraple\App;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskOne;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskTwo;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskThree;

class Module extends \Eraple\Module
{
    protected static $name = 'base';

    protected static $version = '1.0.0';

    protected static $description = 'I will handle the base functions.';

    public function registerTasks(App $app)
    {
        $app->registerTask(TaskOne::class);
        $app->registerTask(TaskTwo::class);
        $app->registerTask(TaskThree::class);
    }
}

return Module::class;
