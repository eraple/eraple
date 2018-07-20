<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class SampleTaskHandlesReplaceTaskEvent extends Task
{
    protected static $name = 'sample-task-handles-replace-task-event';

    protected static $event = 'replace-task-sample-task-handles-event';

    protected static $index = 0;

    public function run(App $app, array $data = [])
    {
        $data = $app->fire('before-task-sample-task-handles-event', $data);

        $data['key'] = $data['key'] . ' on replaced';

        $data = $app->fire('after-task-sample-task-handles-event', $data);

        return $data;
    }
}
