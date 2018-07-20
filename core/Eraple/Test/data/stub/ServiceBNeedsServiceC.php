<?php

namespace Eraple\Test\Data\Stub;

class ServiceBNeedsServiceC implements SampleServiceInterface
{
    public function __construct(ServiceCNeedsServiceA $serviceCNeedsServiceA) { }
}
