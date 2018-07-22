<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

class SampleServiceHasParameters implements SampleServiceInterface
{
    public $name;

    public $sampleServiceForServicesArgument;

    public function __construct(string $name, SampleServiceForServicesArgumentInterface $sampleServiceForServicesArgument)
    {
        $this->name = $name;
        $this->sampleServiceForServicesArgument = $sampleServiceForServicesArgument;
    }

    public function methodHasParameters(string $name, SampleServiceForServicesArgumentInterface $sampleServiceForServicesArgument)
    {
        return [
            'name'                             => $name,
            'sampleServiceForServicesArgument' => $sampleServiceForServicesArgument
        ];
    }
}
