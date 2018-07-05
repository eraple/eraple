<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskOne implements Task
{
    protected $description = 'I will do task one.';

    public static $position = 'after_task-seven';

    public static $priority = 0;

    public function description()
    {
        return $this->description;
    }

    public function run(App $app, array $data = [])
    {
        echo 'task one completed.' . PHP_EOL;

        $data = $app->fireEvent('just-an-event', ['name' => 'amit sidhpura']);

        print_r($data);
    }
}
