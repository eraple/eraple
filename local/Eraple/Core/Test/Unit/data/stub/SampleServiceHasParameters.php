<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

class SampleServiceHasParameters implements SampleServiceInterface
{
    public $name;

    public $sampleServiceForPreferences;

    public function __construct(string $name, SampleServiceForServicesArgumentInterface $sampleServiceForPreferences)
    {
        $this->name = $name;
        $this->sampleServiceForPreferences = $sampleServiceForPreferences;
    }
}
