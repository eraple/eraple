<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class TaskCFollowsTaskB extends Task
{
    protected static $name = 'c-follows-task-b';

    protected static $position = 'after-task-b-follows-task-a';

    public function run(App $app, array $data = [])
    {
    }
}
