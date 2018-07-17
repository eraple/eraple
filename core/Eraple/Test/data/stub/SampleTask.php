<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class SampleTask extends Task
{
    protected static $name = 'sample-task';

    public function run(App $app, array $data = [])
    {
    }
}
