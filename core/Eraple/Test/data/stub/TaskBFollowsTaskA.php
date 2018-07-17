<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class TaskBFollowsTaskA extends Task
{
    protected static $name = 'task-b-follows-task-a';

    protected static $position = 'after_task-a-follows-task-c';

    public function run(App $app, array $data = [])
    {
    }
}
