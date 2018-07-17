<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class FireBeforeEventTask extends Task
{
    protected static $name = 'fire-before-event-task';

    protected static $position = 'event_before_something-happened';

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' before';

        return $data;
    }
}
