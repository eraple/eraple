<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class TaskBFollowsTaskA extends Task
{
    protected static $name = 'b-follows-task-a';

    protected static $event = 'after-task-a-follows-task-c';

    public function run(App $app, array $data = [])
    {
    }
}
