<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

class ServiceANeedsServiceB implements SampleServiceInterface
{
    public function __construct(ServiceBNeedsServiceC $serviceBNeedsServiceC) { }
}
