<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskEight extends Task
{
    protected static $name = 'task-eight';

    protected static $description = 'I will do task eight instead of four.';

    protected static $position = 'replace_task-four';

    protected static $priority = 2;

    public function run(App $app, array $data = [])
    {
        echo 'task eight instead of four completed.' . PHP_EOL;
    }
}
