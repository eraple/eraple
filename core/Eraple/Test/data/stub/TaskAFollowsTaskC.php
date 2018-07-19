<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class TaskAFollowsTaskC extends Task
{
    protected static $name = 'a-follows-task-c';

    protected static $event = 'after-task-c-follows-task-b';

    public function run(App $app, array $data = [])
    {
    }
}
