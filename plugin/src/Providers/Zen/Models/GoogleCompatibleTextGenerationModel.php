<?php

declare(strict_types=1);

namespace CoenJacobs\OpenCodeProvider\Providers\Zen\Models;

use CoenJacobs\OpenCodeProvider\Providers\Zen\ZenProvider;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use Generator;
use RuntimeException;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Text generation model using the Google Gemini generateContent API format.
 *
 * Handles Gemini models available through OpenCode Zen.
 */
class GoogleCompatibleTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface
{
    /**
     * @param Message[] $prompt
     */
    public function streamGenerateTextResult(array $prompt): Generator
    {
        throw new RuntimeException('Streaming is not yet implemented.');
    }

    public function generateTextResult(array $prompt): GenerativeAiResult
    {
        $params = $this->prepareGenerateTextParams($prompt);
        $modelId = $this->metadata()->getId();

        $request = new Request(
            HttpMethodEnum::POST(),
            ZenProvider::url('models/' . $modelId . ':generateContent'),
            ['Content-Type' => 'application/json'],
            $params,
            $this->getRequestOptions(),
        );

        $request = $this->getRequestAuthentication()->authenticateRequest($request);
        $response = $this->getHttpTransporter()->send($request);
        ResponseUtil::throwIfNotSuccessful($response);

        return $this->parseResponseToGenerativeAiResult($response);
    }

    /**
     * @param Message[] $prompt
     */
    protected function prepareGenerateTextParams(array $prompt): array
    {
        $config = $this->getConfig();
        $contents = [];

        foreach ($prompt as $message) {
            $role = $message->getRole()->isUser() ? 'user' : 'model';
            $text = $this->extractText($message);
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $text]],
            ];
        }

        $params = [
            'contents' => $contents,
        ];

        $systemInstruction = $config->getSystemInstruction();
        if ($systemInstruction !== null) {
            $params['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        $generationConfig = [];

        $maxTokens = $config->getMaxTokens();
        if ($maxTokens !== null) {
            $generationConfig['maxOutputTokens'] = $maxTokens;
        }

        $temperature = $config->getTemperature();
        if ($temperature !== null) {
            $generationConfig['temperature'] = $temperature;
        }

        $topP = $config->getTopP();
        if ($topP !== null) {
            $generationConfig['topP'] = $topP;
        }

        $topK = $config->getTopK();
        if ($topK !== null) {
            $generationConfig['topK'] = $topK;
        }

        $stopSequences = $config->getStopSequences();
        if ($stopSequences !== null) {
            $generationConfig['stopSequences'] = $stopSequences;
        }

        if (!empty($generationConfig)) {
            $params['generationConfig'] = $generationConfig;
        }

        return $params;
    }

    /**
     * Parse the Google Gemini generateContent API response into a GenerativeAiResult.
     */
    protected function parseResponseToGenerativeAiResult(Response $response): GenerativeAiResult
    {
        $data = $response->getData();

        $candidates = [];
        foreach ($data['candidates'] ?? [] as $candidate) {
            $text = '';
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }

            $finishReason = $this->mapFinishReason($candidate['finishReason'] ?? 'STOP');

            $candidates[] = new Candidate(
                new ModelMessage([new MessagePart($text)]),
                $finishReason,
            );
        }

        $usageMetadata = $data['usageMetadata'] ?? [];
        $promptTokens = $usageMetadata['promptTokenCount'] ?? 0;
        $completionTokens = ($usageMetadata['candidatesTokenCount'] ?? 0)
            + ($usageMetadata['thoughtsTokenCount'] ?? 0);

        return new GenerativeAiResult(
            '',
            $candidates,
            new TokenUsage(
                $promptTokens,
                $completionTokens,
                $promptTokens + $completionTokens,
            ),
            $this->providerMetadata(),
            $this->metadata(),
        );
    }

    private function extractText(Message $message): string
    {
        $text = '';

        foreach ($message->getParts() as $part) {
            if ($part->getType()->isText()) {
                $text .= $part->getText();
            }
        }

        return $text;
    }

    private function mapFinishReason(string $reason): FinishReasonEnum
    {
        switch ($reason) {
            case 'STOP':
                return FinishReasonEnum::stop();
            case 'MAX_TOKENS':
                return FinishReasonEnum::length();
            case 'SAFETY':
            case 'RECITATION':
            case 'BLOCKLIST':
            case 'PROHIBITED_CONTENT':
            case 'SPII':
                return FinishReasonEnum::contentFilter();
            default:
                return FinishReasonEnum::stop();
        }
    }
}
