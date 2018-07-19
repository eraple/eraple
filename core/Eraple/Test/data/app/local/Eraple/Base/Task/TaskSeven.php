<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskSeven extends Task
{
    protected static $name = 'task-seven';

    protected static $description = 'I will do task seven instead of five.';

    protected static $event = 'replace_task-five';

    protected static $index = 1;

    public function run(App $app, array $data = [])
    {
        echo 'task seven instead of five completed.' . PHP_EOL;
    }
}
