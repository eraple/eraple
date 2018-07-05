<?php

namespace Eraple;

interface Task
{
    public function description();

    public function run(App $app, array $data = []);
}
