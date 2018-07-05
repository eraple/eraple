<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskSeven implements Task
{
    protected $description = 'I will do task seven instead of five.';

    public static $position = 'replace_task-five';

    public static $priority = 1;

    public function description()
    {
        return $this->description;
    }

    public function run(App $app, array $data = [])
    {
        echo 'task seven instead of five completed.' . PHP_EOL;
    }
}
