<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base;

use Eraple\App;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskOne;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskTwo;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskThree;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskFour;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskFive;
use Eraple\Test\Data\App\Local\Eraple\Base\Task\TaskSix;

class Module implements \Eraple\Module
{
    protected $description = 'I will handle the base functions.';

    public function description()
    {
        return $this->description;
    }

    public function registerTasks(App $app)
    {
        $app->registerTask('task-one', TaskOne::class);
        $app->registerTask('task-two', TaskTwo::class);
        $app->registerTask('task-three', TaskThree::class);
        $app->registerTask('task-four', TaskFour::class);
        $app->registerTask('task-five', TaskFive::class);
        $app->registerTask('task-six', TaskSix::class);
    }
}

App::instance()->registerModule('base', Module::class);
