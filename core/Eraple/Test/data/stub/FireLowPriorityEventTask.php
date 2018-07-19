<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class FireLowPriorityEventTask extends Task
{
    protected static $name = 'fire-low-priority-event-task';

    protected static $event = 'something-happened';

    protected static $priority = -1;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' low';

        return $data;
    }
}
