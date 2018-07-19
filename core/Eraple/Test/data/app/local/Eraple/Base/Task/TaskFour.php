<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskFour extends Task
{
    protected static $name = 'task-four';

    protected static $description = 'I will do task four.';

    protected static $event = 'before_task-three';

    protected static $priority = 1;

    public function run(App $app, array $data = [])
    {
        echo 'task four completed.' . PHP_EOL;
    }
}
