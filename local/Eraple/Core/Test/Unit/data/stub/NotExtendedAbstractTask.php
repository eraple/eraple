<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;

class NotExtendedAbstractTask
{
    protected static $name = 'not-extended-abstract-task';

    public function run(App $app, array $data = [])
    {
    }
}
