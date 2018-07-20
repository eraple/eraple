<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;

class NotExtendedAbstractTask
{
    protected static $name = 'not-extended-abstract-task';

    public function run(App $app, array $data = [])
    {
    }
}
