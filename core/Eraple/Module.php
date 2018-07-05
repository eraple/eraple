<?php

namespace Eraple;

interface Module
{
    public function description();

    public function registerTasks(App $app);
}
