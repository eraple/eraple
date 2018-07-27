<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class SampleTask extends Task
{
    protected static $name = 'sample-task';

    protected static $description = 'Sample task description.';

    public function run(App $app, array $data = [])
    {
    }
}
