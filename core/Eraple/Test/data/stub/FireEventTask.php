<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class FireEventTask extends Task
{
    protected static $name = 'fire-event-task';

    protected static $event = 'something-happened';

    protected static $index = 0;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' on';

        return $data;
    }
}
