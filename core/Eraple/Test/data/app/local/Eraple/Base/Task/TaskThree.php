<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskThree implements Task
{
    protected $description = 'I will do task three.';

    public static $position = 'event_start';

    public static $priority = 1;

    public function description()
    {
        return $this->description;
    }

    public function run(App $app, array $data = [])
    {
        echo 'task three completed.' . PHP_EOL;
    }
}
