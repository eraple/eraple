<?php

namespace Eraple\Test\Data\Stub;

class ServiceCNeedsServiceA implements SampleServiceInterface
{
    public function __construct(ServiceANeedsServiceB $serviceANeedsServiceB) { }
}
