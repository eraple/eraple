<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class SampleTaskHandlesReplaceTaskEvent extends Task
{
    protected static $name = 'sample-task-handles-replace-task-event';

    protected static $event = 'replace:run-task:sample-task-handles-event';

    protected static $index = 0;

    public function run(App $app, array $data = [])
    {
        $data = $app->fire('before:run-task:sample-task-handles-event', $data);

        $data['key'] = $data['key'] . ' on replaced';

        $data = $app->fire('after:run-task:sample-task-handles-event', $data);

        return $data;
    }
}
