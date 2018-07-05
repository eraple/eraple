<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskTwo implements Task
{
    protected $description = 'I will do task two.';

    public static $position = 'event_just-an-event';

    public static $priority = 1;

    public function description()
    {
        return $this->description;
    }

    public function run(App $app, array $data = [])
    {
        echo 'task two completed.' . PHP_EOL;

        $data['name'] = $data['name'] . ' updated';

        return $data;
    }
}
