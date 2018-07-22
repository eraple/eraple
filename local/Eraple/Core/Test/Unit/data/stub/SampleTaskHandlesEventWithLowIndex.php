<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class SampleTaskHandlesEventWithLowIndex extends Task
{
    protected static $name = 'sample-task-handles-event-with-low-index';

    protected static $event = 'something-happened';

    protected static $index = -1;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' low';

        return $data;
    }
}
