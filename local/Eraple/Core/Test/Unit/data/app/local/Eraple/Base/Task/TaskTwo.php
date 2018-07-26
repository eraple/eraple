<?php

namespace Eraple\Core\Test\Unit\Data\App\Local\Eraple\Base\Task;

use Eraple\Core\Task;
use Eraple\Core\App;

class TaskTwo extends Task
{
    protected static $name = 'task-two';

    protected static $description = 'I will do task two.';

    protected static $events = 'before-end';

    protected static $index = 0;

    protected static $services = ['key-two' => 'value-two'];

    public function run(App $app, array $data = [])
    {
    }
}
