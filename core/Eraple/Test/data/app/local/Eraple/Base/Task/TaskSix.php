<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskSix implements Task
{
    protected $description = 'I will do task six.';

    public static $position = 'event_before_just-an-event';

    public static $priority = 2;

    public function description()
    {
        return $this->description;
    }

    public function run(App $app, array $data = [])
    {
        echo 'task six completed.' . PHP_EOL;

        $data['name'] = 'updated ' . $data['name'];

        return $data;
    }
}
