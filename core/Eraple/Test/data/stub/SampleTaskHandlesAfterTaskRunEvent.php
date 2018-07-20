<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class SampleTaskHandlesAfterTaskRunEvent extends Task
{
    protected static $name = 'sample-task-handles-after-task-run-event';

    protected static $event = 'after-task-sample-task-handles-event';

    protected static $index = 0;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' after';

        return $data;
    }
}
