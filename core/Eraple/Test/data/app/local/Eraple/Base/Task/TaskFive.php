<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskFive implements Task
{
    protected $description = 'I will do task five.';

    public static $position = 'after_task-three';

    public static $priority = 2;

    public function description()
    {
        return $this->description;
    }

    public function run(App $app, array $data = [])
    {
        echo 'task five completed.' . PHP_EOL;
    }
}
