<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class SampleTaskHandlesEventWithHighIndex extends Task
{
    protected static $name = 'sample-task-handles-event-with-high-index';

    protected static $event = 'something-happened';

    protected static $index = 1;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' high';

        return $data;
    }
}
