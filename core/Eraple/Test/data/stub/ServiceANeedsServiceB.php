<?php

namespace Eraple\Test\Data\Stub;

class ServiceANeedsServiceB implements SampleServiceInterface
{
    public function __construct(ServiceBNeedsServiceC $serviceBNeedsServiceC) { }
}
