<?php

declare(strict_types=1);

namespace CoenJacobs\OpenCodeProvider\Providers\Zen;

use CoenJacobs\OpenCodeProvider\Dependencies\CoenJacobs\WordPressAiProvider\ModelDirectory\AbstractModelMetadataDirectory;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

class ZenModelMetadataDirectory extends AbstractModelMetadataDirectory
{
    /** @var array<string, string> Map of model ID to API format (openai, anthropic, google). */
    private static array $modelApiFormats = [];

    /**
     * Returns the API format for a given model ID.
     */
    public static function getModelApiFormat(string $modelId): string
    {
        return self::$modelApiFormats[$modelId] ?? self::detectApiFormatFromId($modelId);
    }

    protected function getModelsApiUrl(): string
    {
        return 'https://opencode.ai/zen/v1/models';
    }

    /**
     * @param array<string, mixed> $rawModel
     * @return array<string, mixed>|null
     */
    protected function parseModelEntry(array $rawModel): ?array
    {
        $modelId = substr($rawModel['id'], 0, 200);
        $format = $this->detectApiFormat($rawModel);
        self::$modelApiFormats[$modelId] = $format;

        return [
            'id' => $modelId,
            'name' => $rawModel['name'] ?? $modelId,
            'api_format' => $format,
        ];
    }

    /**
     * @param list<array<string, mixed>> $models
     */
    protected function onModelsLoadedFromCache(array $models): void
    {
        foreach ($models as $model) {
            if (isset($model['id'])) {
                self::$modelApiFormats[$model['id']] = $model['api_format']
                    ?? self::detectApiFormatFromId($model['id']);
            }
        }
    }

    /**
     * @param array<string, mixed> $modelData
     * @return list<SupportedOption>
     */
    protected function buildSupportedOptions(array $modelData): array
    {
        $format = self::getModelApiFormat($modelData['id'] ?? '');

        $options = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
        ];

        if ($format === 'openai') {
            $options[] = new SupportedOption(OptionEnum::topP());
            $options[] = new SupportedOption(OptionEnum::frequencyPenalty());
            $options[] = new SupportedOption(OptionEnum::presencePenalty());
            $options[] = new SupportedOption(OptionEnum::stopSequences());
        }

        if ($format === 'anthropic') {
            $options[] = new SupportedOption(OptionEnum::topP());
            $options[] = new SupportedOption(OptionEnum::topK());
            $options[] = new SupportedOption(OptionEnum::stopSequences());
        }

        if ($format === 'google') {
            $options[] = new SupportedOption(OptionEnum::topP());
            $options[] = new SupportedOption(OptionEnum::topK());
            $options[] = new SupportedOption(OptionEnum::stopSequences());
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $model
     */
    private function detectApiFormat(array $model): string
    {
        if (isset($model['api_format'])) {
            return $model['api_format'];
        }

        return self::detectApiFormatFromId($model['id']);
    }

    private static function detectApiFormatFromId(string $modelId): string
    {
        if (strpos($modelId, 'claude-') === 0) {
            return 'anthropic';
        }

        if (strpos($modelId, 'gemini-') === 0) {
            return 'google';
        }

        return 'openai';
    }
}
