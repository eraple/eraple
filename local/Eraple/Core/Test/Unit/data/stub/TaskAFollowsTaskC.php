<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class TaskAFollowsTaskC extends Task
{
    protected static $name = 'a-follows-task-c';

    protected static $event = 'after:run-task:c-follows-task-b';

    public function run(App $app, array $data = [])
    {
    }
}
