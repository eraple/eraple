<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class SampleTaskHandlesEvent extends Task
{
    protected static $name = 'sample-task-handles-event';

    protected static $events = 'something-happened';

    protected static $index = 0;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' on';

        return $data;
    }
}
