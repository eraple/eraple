<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class FireAfterEventTask extends Task
{
    protected static $name = 'fire-after-event-task';

    protected static $position = 'event_after_something-happened';

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' after';

        return $data;
    }
}
