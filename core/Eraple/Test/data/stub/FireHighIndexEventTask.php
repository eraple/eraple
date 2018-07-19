<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class FireHighIndexEventTask extends Task
{
    protected static $name = 'fire-high-index-event-task';

    protected static $event = 'something-happened';

    protected static $index = 1;

    public function run(App $app, array $data = [])
    {
        $data['key'] = $data['key'] . ' high';

        return $data;
    }
}
