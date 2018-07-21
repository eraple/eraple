<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskOne extends Task
{
    protected static $name = 'task-one';

    protected static $description = 'I will do task one.';

    protected static $event = 'start';

    protected static $index = 0;

    protected static $services = ['key-one' => 'value-one'];

    public function run(App $app, array $data = [])
    {
    }
}
