<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskSix extends Task
{
    protected static $name = 'task-six';

    protected static $description = 'I will do task six.';

    protected static $event = 'event_before_just-an-event';

    protected static $priority = 2;

    public function run(App $app, array $data = [])
    {
        echo 'task six completed.' . PHP_EOL;

        $data['name'] = 'updated ' . $data['name'];

        return $data;
    }
}
