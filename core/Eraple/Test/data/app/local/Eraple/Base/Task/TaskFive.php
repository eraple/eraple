<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskFive extends Task
{
    protected static $name = 'task-five';

    protected static $description = 'I will do task five.';

    protected static $event = 'after_task-three';

    protected static $index = 2;

    public function run(App $app, array $data = [])
    {
        echo 'task five completed.' . PHP_EOL;
    }
}
