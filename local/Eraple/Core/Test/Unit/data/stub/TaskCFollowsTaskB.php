<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class TaskCFollowsTaskB extends Task
{
    protected static $name = 'c-follows-task-b';

    protected static $events = 'after:run-task:b-follows-task-a';

    public function run(App $app, array $data = [])
    {
    }
}
