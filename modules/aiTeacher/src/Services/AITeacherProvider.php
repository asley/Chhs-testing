<?php

namespace Gibbon\Module\aiTeacher\Services;

use Gibbon\Module\aiTeacher\DeepSeekAPI;
use Gibbon\Module\aiTeacher\OpenAIAPI;

class AITeacherProvider
{
    private $settings;
    private $providerName;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->providerName = !empty($settings['deepseek_api_key']) ? 'deepseek' : 'openai';
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function generate(string $prompt): string
    {
        if (!empty($this->settings['deepseek_api_key'])) {
            $api = new DeepSeekAPI($this->settings['deepseek_api_key']);
            $response = $api->generateResponse($prompt, 'deepseek-chat', 0.4, 3600);
            return $this->normalizeResponse($response);
        }

        if (!empty($this->settings['openai_api_key'])) {
            $this->providerName = 'openai';
            $api = new OpenAIAPI($this->settings['openai_api_key']);
            $response = $api->generateResponse($prompt, 'gpt-3.5-turbo', 0.4, 3600);
            return $this->normalizeResponse($response);
        }

        throw new \RuntimeException('No AI API key is configured.');
    }

    private function normalizeResponse(?string $response): string
    {
        $response = trim((string) $response);

        if ($response === '') {
            throw new \RuntimeException('The AI service returned an empty response.');
        }

        if (strpos($response, 'Error from AI Service:') === 0 || strpos($response, 'Error:') === 0) {
            throw new \RuntimeException($response);
        }

        return $response;
    }
}
