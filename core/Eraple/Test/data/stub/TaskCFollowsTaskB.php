<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class TaskCFollowsTaskB extends Task
{
    protected static $name = 'task-c-follows-task-b';

    protected static $position = 'after_task-b-follows-task-a';

    public function run(App $app, array $data = [])
    {
    }
}
