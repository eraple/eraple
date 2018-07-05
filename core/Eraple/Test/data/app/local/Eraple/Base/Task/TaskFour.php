<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskFour implements Task
{
    protected $description = 'I will do task four.';

    public static $position = 'before_task-three';

    public static $priority = 1;

    public function description()
    {
        return $this->description;
    }

    public function run(App $app, array $data = [])
    {
        echo 'task four completed.' . PHP_EOL;
    }
}
