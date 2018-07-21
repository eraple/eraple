<?php

namespace Eraple\Test\Data\App\Local\Eraple\Base\Task;

use Eraple\Task;
use Eraple\App;

class TaskTwo extends Task
{
    protected static $name = 'task-two';

    protected static $description = 'I will do task two.';

    protected static $event = 'before-end';

    protected static $index = 0;

    protected static $services = ['key-two' => 'value-two'];

    public function run(App $app, array $data = [])
    {
    }
}
