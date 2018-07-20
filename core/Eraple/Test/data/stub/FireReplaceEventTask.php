<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class FireReplaceEventTask extends Task
{
    protected static $name = 'fire-replace-event-task';

    protected static $event = 'replace-task-fire-event-task';

    protected static $index = 0;

    public function run(App $app, array $data = [])
    {
        $data = $app->fire('before-task-fire-event-task', $data);

        $data['key'] = $data['key'] . ' on replaced';

        $data = $app->fire('after-task-fire-event-task', $data);

        return $data;
    }
}
