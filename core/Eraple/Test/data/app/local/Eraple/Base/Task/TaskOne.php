<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskOne extends Task
{
    protected static $name = 'task-one';

    protected static $description = 'I will do task one.';

    protected static $event = 'after_task-seven';

    protected static $priority = 0;

    public function run(App $app, array $data = [])
    {
        echo 'task one completed.' . PHP_EOL;

        $data = $app->fire('just-an-event', ['name' => 'amit sidhpura']);

        print_r($data);
    }
}
