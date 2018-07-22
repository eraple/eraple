<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

class ServiceCNeedsServiceA implements SampleServiceInterface
{
    public function __construct(ServiceANeedsServiceB $serviceANeedsServiceB) { }
}
