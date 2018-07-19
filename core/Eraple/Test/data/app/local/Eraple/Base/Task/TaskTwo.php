<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskTwo extends Task
{
    protected static $name = 'task-two';

    protected static $description = 'I will do task two.';

    protected static $event = 'event_just-an-event';

    protected static $priority = 1;

    public function run(App $app, array $data = [])
    {
        echo 'task two completed.' . PHP_EOL;

        $data['name'] = $data['name'] . ' updated';

        return $data;
    }
}
