<?php

declare(strict_types=1);

namespace CoenJacobs\OpenCodeProvider\Providers\Zen\Models;

use CoenJacobs\OpenCodeProvider\Providers\Zen\ZenProvider;
use CoenJacobs\OpenCodeProvider\Dependencies\CoenJacobs\WordPressAiProvider\Models\AnthropicCompatible\TextGenerationModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

/**
 * Anthropic-compatible text generation model for OpenCode Zen.
 */
class AnthropicCompatibleTextGenerationModel extends TextGenerationModel
{
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        return new Request($method, ZenProvider::url($path), $headers, $data, $this->getRequestOptions());
    }
}
