<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskEight implements Task
{
    protected $description = 'I will do task eight instead of four.';

    public static $position = 'replace_task-four';

    public static $priority = 2;

    public function description()
    {
        return $this->description;
    }

    public function run(App $app, array $data = [])
    {
        echo 'task eight instead of four completed.' . PHP_EOL;
    }
}
