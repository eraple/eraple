<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Task;

class InvalidNameTask extends Task
{
    protected static $name = 'Sample Task';

    public function run(App $app, array $data = [])
    {
    }
}
