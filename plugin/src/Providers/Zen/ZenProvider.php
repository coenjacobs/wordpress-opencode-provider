<?php

declare(strict_types=1);

namespace CoenJacobs\OpenCodeProvider\Providers\Zen;

use CoenJacobs\OpenCodeProvider\Plugin;
use CoenJacobs\OpenCodeProvider\Providers\Zen\Models\AnthropicCompatibleTextGenerationModel;
use CoenJacobs\OpenCodeProvider\Providers\Zen\Models\GoogleCompatibleTextGenerationModel;
use CoenJacobs\OpenCodeProvider\Providers\Zen\Models\OpenAiCompatibleTextGenerationModel;
use CoenJacobs\OpenCodeProvider\Dependencies\CoenJacobs\WordPressAiProvider\Provider\ApiKeyProviderAvailability;
use RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

class ZenProvider extends AbstractApiProvider
{
    protected static function baseUrl(): string
    {
        return 'https://opencode.ai/zen/v1';
    }

    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'opencode-zen',
            'OpenCode Zen',
            ProviderTypeEnum::cloud(),
            'https://opencode.ai/zen',
            RequestAuthenticationMethod::apiKey(),
        );
    }

    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new ApiKeyProviderAvailability(Plugin::providerConfig());
    }

    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new ZenModelMetadataDirectory(Plugin::providerConfig());
    }

    /**
     * Create the appropriate model class based on the model's API format.
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        foreach ($modelMetadata->getSupportedCapabilities() as $capability) {
            if ($capability->isTextGeneration()) {
                $format = ZenModelMetadataDirectory::getModelApiFormat($modelMetadata->getId());

                switch ($format) {
                    case 'anthropic':
                        return new AnthropicCompatibleTextGenerationModel($modelMetadata, $providerMetadata);
                    case 'google':
                        return new GoogleCompatibleTextGenerationModel($modelMetadata, $providerMetadata);
                    default:
                        return new OpenAiCompatibleTextGenerationModel($modelMetadata, $providerMetadata);
                }
            }
        }

        throw new RuntimeException(
            sprintf('No supported capabilities found for model "%s".', $modelMetadata->getId())
        );
    }
}
