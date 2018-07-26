<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class SampleTaskHandlesBeforeTaskRunEvent extends Task
{
    protected static $name = 'sample-task-handles-before-task-run-event';

    protected static $events = 'before:run-task:sample-task-handles-event';

    protected static $index = 0;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' before';

        return $data;
    }
}
