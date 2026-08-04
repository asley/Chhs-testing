<?php

require_once __DIR__ . '/../../../moduleFunctions.php';

function buildJussExamBridgeTcExamResultsRequest(
    string $tcexamBaseUrl,
    string $bridgeKeyId,
    string $bridgeSharedSecret,
    string $examId,
    string $classExternalId,
    int $page = 1,
    int $pageSize = 100,
    ?string $updatedAfter = null,
    ?string $timestamp = null,
    ?string $nonce = null
) {
    $baseUrl = rtrim(trim($tcexamBaseUrl), '/');
    $keyId = trim($bridgeKeyId);
    $sharedSecret = trim($bridgeSharedSecret);

    if ($baseUrl === '' || $keyId === '' || $sharedSecret === '') {
        return [
            'ok' => false,
            'error' => 'bridge_not_configured',
        ];
    }

    if (!isJussExamBridgeSafeTcExamBaseUrl($baseUrl)) {
        return [
            'ok' => false,
            'error' => 'unsafe_tcexam_base_url',
        ];
    }

    $examId = trim($examId);
    $classExternalId = trim($classExternalId);
    $page = max(1, $page);
    $pageSize = max(1, min(200, $pageSize));

    if ($examId === '' || $classExternalId === '') {
        return [
            'ok' => false,
            'error' => 'missing_pull_identifiers',
        ];
    }

    $query = [
        'examId' => $examId,
        'classExternalId' => $classExternalId,
        'status' => 'final',
        'page' => $page,
        'pageSize' => $pageSize,
    ];

    if ($updatedAfter !== null && trim($updatedAfter) !== '') {
        $query['updatedAfter'] = trim($updatedAfter);
    }

    $path = '/api/bridge/v1/results';
    $url = $baseUrl . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $timestamp = $timestamp ?: gmdate('Y-m-d\TH:i:s\Z');
    $nonce = $nonce ?: bin2hex(random_bytes(16));
    $rawBody = '';
    $canonical = buildJussExamBridgeCanonicalRequest('GET', $path, $timestamp, $nonce, $rawBody);
    $signature = hash_hmac('sha256', $canonical, $sharedSecret);

    return [
        'ok' => true,
        'method' => 'GET',
        'url' => $url,
        'path' => $path,
        'query' => $query,
        'headers' => [
            'Accept' => 'application/json',
            'X-Bridge-KeyId' => $keyId,
            'X-Bridge-Timestamp' => $timestamp,
            'X-Bridge-Nonce' => $nonce,
            'X-Bridge-Signature' => $signature,
        ],
        'body' => $rawBody,
        'bodyHash' => hash('sha256', $rawBody),
        'canonical' => $canonical,
    ];
}

function fetchJussExamBridgeTcExamResults(array $request, ?callable $transport = null, int $timeoutSeconds = 10)
{
    if (($request['ok'] ?? false) !== true) {
        return $request;
    }

    $transport = $transport ?: 'sendJussExamBridgeTcExamHttpRequest';
    $response = $transport(
        $request['method'],
        $request['url'],
        $request['headers'],
        $request['body'],
        $timeoutSeconds
    );

    $statusCode = (int) ($response['statusCode'] ?? 0);
    $body = (string) ($response['body'] ?? '');
    $transportError = trim((string) ($response['error'] ?? ''));

    if ($transportError !== '') {
        return [
            'ok' => false,
            'error' => 'tcexam_request_failed',
            'detail' => $transportError,
        ];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'invalid_tcexam_response',
            'httpStatus' => $statusCode,
        ];
    }

    if ($statusCode < 200 || $statusCode >= 300 || ($decoded['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'error' => 'tcexam_error_response',
            'httpStatus' => $statusCode,
            'tcexamError' => $decoded['error'] ?? null,
            'message' => $decoded['message'] ?? null,
        ];
    }

    $results = $decoded['results'] ?? null;
    if (!is_array($results)) {
        return [
            'ok' => false,
            'error' => 'invalid_tcexam_results',
            'httpStatus' => $statusCode,
        ];
    }

    if (count($results) > 200) {
        return [
            'ok' => false,
            'error' => 'too_many_tcexam_results',
            'httpStatus' => $statusCode,
            'maxRecords' => 200,
        ];
    }

    return [
        'ok' => true,
        'httpStatus' => $statusCode,
        'payload' => $decoded,
    ];
}

function sendJussExamBridgeTcExamHttpRequest(string $method, string $url, array $headers, string $body, int $timeoutSeconds)
{
    $curl = curl_init($url);
    if ($curl === false) {
        return [
            'statusCode' => 0,
            'body' => '',
            'error' => 'curl_init_failed',
        ];
    }

    $formattedHeaders = [];
    foreach ($headers as $name => $value) {
        $formattedHeaders[] = $name . ': ' . $value;
    }

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $formattedHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
    ]);

    if ($body !== '') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }

    $responseBody = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_errno($curl) !== 0 ? curl_error($curl) : '';
    curl_close($curl);

    return [
        'statusCode' => $statusCode,
        'body' => is_string($responseBody) ? $responseBody : '',
        'error' => $error,
    ];
}

function isJussExamBridgeSafeTcExamBaseUrl(string $baseUrl): bool
{
    $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
    $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));

    if ($scheme === 'https') {
        return $host !== '';
    }

    if ($scheme !== 'http' || $host === '') {
        return false;
    }

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    if (str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
        return true;
    }

    if (filter_var($host, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}
