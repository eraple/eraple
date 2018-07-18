<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class TaskBFollowsTaskA extends Task
{
    protected static $name = 'b-follows-task-a';

    protected static $position = 'after-task-a-follows-task-c';

    public function run(App $app, array $data = [])
    {
    }
}
