<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskThree extends Task
{
    protected static $name = 'task-three';

    protected static $description = 'I will do task three.';

    protected static $event = 'event_start';

    protected static $priority = 1;

    public function run(App $app, array $data = [])
    {
        echo 'task three completed.' . PHP_EOL;
    }
}
