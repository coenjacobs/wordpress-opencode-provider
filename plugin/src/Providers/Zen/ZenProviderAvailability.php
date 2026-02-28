<?php

declare(strict_types=1);

namespace CoenJacobs\OpenCodeProvider\Providers\Zen;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

class ZenProviderAvailability implements ProviderAvailabilityInterface
{
    public function isConfigured(): bool
    {
        return ZenSettings::getActiveApiKey() !== '';
    }
}
