<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class SampleTaskHandlesAfterTaskRunEvent extends Task
{
    protected static $name = 'sample-task-handles-after-task-run-event';

    protected static $event = 'after:run-task:sample-task-handles-event';

    protected static $index = 0;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' after';

        return $data;
    }
}
