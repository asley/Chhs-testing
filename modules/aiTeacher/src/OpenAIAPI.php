<?php

namespace Gibbon\Module\aiTeacher;

class OpenAIAPI {
    private $apiKey;
    private $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function generateResponse(string $prompt, string $model = 'gpt-3.5-turbo') {
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
            // You can add other parameters like temperature, max_tokens, etc.
        ];

        $result = $this->send($data);

        if ($result['success'] && isset($result['response']['choices'][0]['message']['content'])) {
            return $result['response']['choices'][0]['message']['content'];
        } else {
            $errorMessage = $result['error'] ?? 'Unknown API error or malformed response';
            if (isset($result['response']['error']['message'])) {
                $errorMessage = $result['response']['error']['message'];
            }
            error_log("OpenAI API Error in generateResponse: " . $errorMessage . " - Full Response: " . print_r($result['response'] ?? $result, true));
            return null;
        }
    }

    private function send($data) {
        $ch = curl_init($this->apiUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // OpenAI can sometimes take longer
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'error' => "cURL Error: $error_msg"
            ];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
             $errorBody = json_decode($response, true);
            return [
                'success' => false,
                'error' => "HTTP Error Code: $httpCode",
                'response' => $errorBody
            ];
        }

        return [
            'success' => true,
            'response' => json_decode($response, true)
        ];
    }
}