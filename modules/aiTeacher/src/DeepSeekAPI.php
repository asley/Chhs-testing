<?php

namespace Gibbon\Module\aiTeacher;

ini_set('display_errors', 1);
error_reporting(E_ALL);

class DeepSeekAPI {
    private $apiKey;
    private $apiUrl = 'https://api.deepseek.com/v1/chat/completions';

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function generateResponse(string $prompt, string $model = 'deepseek-chat', float $temperature = 0.7, int $maxTokens = 1024): ?string {
        $data = [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];

        error_log("[DeepSeek] Request Data: " . json_encode($data));

        $startTime = microtime(true);
        $result = $this->send($data);
        $duration = microtime(true) - $startTime;

        error_log("[DeepSeek] Request Duration: {$duration} seconds");

        if ($result['success']) {
            if (isset($result['response']['choices'][0]['message']['content'])) {
                return $result['response']['choices'][0]['message']['content'];
            } else {
                error_log("[DeepSeek] API 200 but missing content. Full response: " . print_r($result['response'], true));
                return "Error: The AI service returned a successful response but no content was found. This may be due to a malformed or incomplete reply. Please try again or reduce your prompt size.";
            }
        }

        $errorMessage = $result['error'] ?? 'Unknown error from DeepSeek API.';
        $curlErrno = $result['curl_errno'] ?? null;

        error_log("[DeepSeek] Error: {$errorMessage}");
        if (isset($result['response'])) {
            error_log("[DeepSeek] Full Response: " . print_r($result['response'], true));
        }

        if ($curlErrno === 28) {
            return "Error from AI Service: The request to the AI service timed out. Please try again later. (Details: {$errorMessage})";
        }
        // Return a string with the error message instead of null
        return "Error from AI Service: " . $errorMessage;
    }

    private function send(array $data): array {
        $ch = curl_init($this->apiUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,   // Reduced from 10 to 5 seconds
            CURLOPT_TIMEOUT => 60,         // Increased from 30 to 60 seconds
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ]
        ]);

        // Use secure SSL settings to match working terminal cURL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        // For debugging: log verbose cURL output
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('/tmp/curl_debug.txt', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorNumber = curl_errno($ch);
        $curlErrorMessage = curl_error($ch);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        curl_close($ch);

        if (isset($verbose) && is_resource($verbose)) {
            fclose($verbose);
        }

        error_log("[DeepSeek] cURL Stats: HTTP Code: {$httpcode}, Total Time: {$totalTime}s, Connect Time: {$connectTime}s");
    
        if ($curlErrorNumber) {
            // Log and return error array instead of throwing
            error_log("[DeepSeek] cURL Error ({$curlErrorNumber}): {$curlErrorMessage}. Total time: {$totalTime}s, Connect time: {$connectTime}s");
            
            // Add specific handling for timeout errors
            if ($curlErrorNumber === 28) {
                return [
                    'success' => false,
                    'error' => "Request timed out after {$totalTime} seconds. Please try again.",
                    'raw_response' => $response,
                    'curl_errno' => $curlErrorNumber
                ];
            }
            
            return [
                'success' => false,
                'error' => "cURL Error ({$curlErrorNumber}): {$curlErrorMessage}",
                'raw_response' => $response,
                'curl_errno' => $curlErrorNumber
            ];
        }
    
        if ($httpcode != 200) {
            // Log and return error array instead of throwing
            error_log("[DeepSeek] API Error: HTTP Code {$httpcode}. Response: {$response}");
            return [
                'success' => false,
                'error' => "API Error: HTTP Code {$httpcode}",
                'raw_response' => $response
            ];
        }
    
        $json = json_decode($response, true);
        if ($json === null) {
            error_log("[DeepSeek] Invalid JSON response: {$response}");
            return [
                'success' => false,
                'error' => 'Invalid JSON response from DeepSeek API',
                'raw_response' => $response
            ];
        }
        error_log("[DeepSeek] Raw Response: " . $response);
        return [
            'success' => true,
            'response' => $json
        ];
    }
}