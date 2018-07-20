<?php

namespace Eraple\Test\Data\Stub;

class SampleServiceHasParameters implements SampleServiceInterface
{
    public $name;

    public $sampleServiceForPreferences;

    public function __construct(string $name, SampleServiceForPreferencesInterface $sampleServiceForPreferences)
    {
        $this->name = $name;
        $this->sampleServiceForPreferences = $sampleServiceForPreferences;
    }
}
