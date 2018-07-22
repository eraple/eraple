<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

class ServiceBNeedsServiceC implements SampleServiceInterface
{
    public function __construct(ServiceCNeedsServiceA $serviceCNeedsServiceA) { }
}
