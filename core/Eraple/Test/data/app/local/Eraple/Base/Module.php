<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base;

use Eraple\App;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskOne;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskTwo;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskThree;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskFour;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskFive;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskSix;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskSeven;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskEight;

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
        $app->registerTask(TaskFour::class);
        $app->registerTask(TaskFive::class);
        $app->registerTask(TaskSix::class);
        $app->registerTask(TaskSeven::class);
        $app->registerTask(TaskEight::class);
    }
}

return Module::class;
