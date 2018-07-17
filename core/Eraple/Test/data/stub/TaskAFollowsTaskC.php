<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class TaskAFollowsTaskC extends Task
{
    protected static $name = 'task-a-follows-task-c';

    protected static $position = 'after_task-c-follows-task-b';

    public function run(App $app, array $data = [])
    {
    }
}
