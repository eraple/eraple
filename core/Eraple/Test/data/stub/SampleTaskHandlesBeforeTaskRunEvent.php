<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class SampleTaskHandlesBeforeTaskRunEvent extends Task
{
    protected static $name = 'sample-task-handles-before-task-run-event';

    protected static $event = 'before-task-sample-task-handles-event';

    protected static $index = 0;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' before';

        return $data;
    }
}
