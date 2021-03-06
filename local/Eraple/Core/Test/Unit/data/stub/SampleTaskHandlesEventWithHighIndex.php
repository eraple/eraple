<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class SampleTaskHandlesEventWithHighIndex extends Task
{
    protected static $name = 'sample-task-handles-event-with-high-index';

    protected static $events = 'something-happened';

    protected static $index = 1;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' high';

        return $data;
    }
}
