<?php

namespace Eraple\Core\Test\Unit\Data\App\Local\Eraple\Base\Task;

use Eraple\Core\Task;
use Eraple\Core\App;

class TaskThree extends Task
{
    protected static $name = 'task-three';

    protected static $description = 'I will do task three.';

    protected static $event = 'end';

    protected static $index = 0;

    protected static $services = ['key-three' => 'value-three'];

    public function run(App $app, array $data = [])
    {
    }
}
