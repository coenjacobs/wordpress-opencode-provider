<?php

declare(strict_types=1);

namespace CoenJacobs\OpenCodeProvider\Providers\Zen;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

class ZenModelMetadataDirectory implements ModelMetadataDirectoryInterface
{
    /** @var array<string, string> Map of model ID to API format (openai, anthropic, google). */
    private static array $modelApiFormats = [];

    /** @var array<string, ModelMetadata>|null Cached model metadata map. */
    private ?array $modelMetadataMap = null;

    /**
     * @return list<ModelMetadata>
     */
    public function listModelMetadata(): array
    {
        return array_values($this->getModelMetadataMap());
    }

    public function hasModelMetadata(string $modelId): bool
    {
        return isset($this->getModelMetadataMap()[$modelId]);
    }

    public function getModelMetadata(string $modelId): ModelMetadata
    {
        $map = $this->getModelMetadataMap();

        if (!isset($map[$modelId])) {
            throw new InvalidArgumentException(
                sprintf('Model metadata not found for model ID "%s".', $modelId)
            );
        }

        return $map[$modelId];
    }

    /**
     * Returns the API format for a given model ID.
     */
    public static function getModelApiFormat(string $modelId): string
    {
        return self::$modelApiFormats[$modelId] ?? self::detectApiFormatFromId($modelId);
    }

    /**
     * @return array<string, ModelMetadata>
     */
    private function getModelMetadataMap(): array
    {
        if ($this->modelMetadataMap !== null) {
            return $this->modelMetadataMap;
        }

        $enabledModels = get_option('opencode_zen_enabled_models', []);
        if (!is_array($enabledModels) || empty($enabledModels)) {
            $this->modelMetadataMap = [];
            return $this->modelMetadataMap;
        }

        $allModels = $this->fetchAllModels();
        $this->modelMetadataMap = [];

        foreach ($allModels as $model) {
            $modelId = $model['id'];

            if (!in_array($modelId, $enabledModels, true)) {
                continue;
            }

            $format = self::getModelApiFormat($modelId);

            $this->modelMetadataMap[$modelId] = new ModelMetadata(
                $modelId,
                $model['name'] ?? $modelId,
                [
                    CapabilityEnum::textGeneration(),
                    CapabilityEnum::chatHistory(),
                ],
                $this->buildSupportedOptions($format),
            );
        }

        return $this->modelMetadataMap;
    }

    /**
     * Fetch all models from the API (with transient cache).
     *
     * @return list<array{id: string, name?: string}>
     */
    public function fetchAllModels(): array
    {
        $cached = get_transient('opencode_zen_models_raw');
        if ($cached !== false && is_array($cached)) {
            // Populate format cache from stored data.
            foreach ($cached as $model) {
                if (isset($model['id'])) {
                    self::$modelApiFormats[$model['id']] = self::detectApiFormatFromId($model['id']);
                }
            }
            return $cached;
        }

        $response = wp_remote_get('https://opencode.ai/zen/v1/models', [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [];
        }

        $modelList = $data['data'] ?? $data;
        if (!is_array($modelList)) {
            return [];
        }

        $models = [];
        foreach ($modelList as $model) {
            if (!isset($model['id'])) {
                continue;
            }

            $format = $this->detectApiFormat($model);
            self::$modelApiFormats[$model['id']] = $format;

            $models[] = [
                'id' => $model['id'],
                'name' => $model['name'] ?? $model['id'],
            ];
        }

        set_transient('opencode_zen_models_raw', $models, 10 * MINUTE_IN_SECONDS);

        return $models;
    }

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

    /**
     * @return list<SupportedOption>
     */
    private function buildSupportedOptions(string $format): array
    {
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
}
