<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Domain\System\SettingGateway;

function getJussExamBridgeSetting(SettingGateway $settingGateway, $name, $default = '')
{
    $value = $settingGateway->getSettingByScope('juss-examBridge', $name);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function getJussExamBridgeMaskedSecret($secret)
{
    if (empty($secret)) {
        return __('Not configured');
    }

    if (mb_strlen($secret) <= 6) {
        return str_repeat('*', mb_strlen($secret));
    }

    return mb_substr($secret, 0, 2) . str_repeat('*', mb_strlen($secret) - 4) . mb_substr($secret, -2);
}

function getJussExamBridgeRequestHeaders($server = null)
{
    if ($server === null) {
        $server = $_SERVER;
    }

    $headers = [];

    if (function_exists('getallheaders')) {
        $allHeaders = getallheaders();
        if (is_array($allHeaders)) {
            foreach ($allHeaders as $key => $value) {
                $headers[strtolower($key)] = trim((string) $value);
            }
        }
    }

    foreach ($server as $key => $value) {
        if (mb_strpos($key, 'HTTP_') === 0) {
            $header = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$header] = trim((string) $value);
        }
    }

    return $headers;
}

function buildJussExamBridgeCanonicalRequest($method, $path, $timestamp, $nonce, $body)
{
    $payloadHash = hash('sha256', (string) $body);

    return strtoupper((string) $method) . "\n"
        . (string) $path . "\n"
        . (string) $timestamp . "\n"
        . (string) $nonce . "\n"
        . $payloadHash;
}

function cleanupJussExamBridgeExpiredNonces($connection2)
{
    try {
        $sql = "DELETE FROM gibbonJussExamBridgeNonce WHERE expiresAt <= NOW()";
        $stmt = $connection2->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log('juss-examBridge nonce cleanup failed: '.$e->getMessage());
    }
}

function registerJussExamBridgeNonce($connection2, $nonce, $keyId, $requestTimestamp, $maxSkewSeconds)
{
    cleanupJussExamBridgeExpiredNonces($connection2);

    $expiresAt = date('Y-m-d H:i:s', strtotime($requestTimestamp . ' +' . (int) $maxSkewSeconds . ' seconds'));

    try {
        $sql = "INSERT INTO gibbonJussExamBridgeNonce
                (nonce, keyId, requestTimestamp, expiresAt)
                VALUES (:nonce, :keyId, :requestTimestamp, :expiresAt)";
        $stmt = $connection2->prepare($sql);
        $stmt->execute([
            'nonce' => $nonce,
            'keyId' => $keyId,
            'requestTimestamp' => $requestTimestamp,
            'expiresAt' => $expiresAt,
        ]);

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function verifyJussExamBridgeSignedRequest($container, $connection2, $rawBody, $expectedPath)
{
    $headers = getJussExamBridgeRequestHeaders();

    $keyId = $headers['x-bridge-keyid'] ?? '';
    $timestamp = $headers['x-bridge-timestamp'] ?? '';
    $nonce = $headers['x-bridge-nonce'] ?? '';
    $signature = $headers['x-bridge-signature'] ?? '';

    if ($keyId === '' || $timestamp === '' || $nonce === '' || $signature === '') {
        return ['ok' => false, 'status' => 401, 'error' => 'missing_headers'];
    }

    /** @var SettingGateway $settingGateway */
    $settingGateway = $container->get(SettingGateway::class);

    $configuredKeyId = getJussExamBridgeSetting($settingGateway, 'bridgeKeyId');
    $configuredSecret = getJussExamBridgeSetting($settingGateway, 'bridgeSharedSecret');
    $maxSkewSeconds = (int) getJussExamBridgeSetting($settingGateway, 'signatureMaxSkewSeconds', '300');

    if ($configuredKeyId === '' || $configuredSecret === '') {
        return ['ok' => false, 'status' => 503, 'error' => 'bridge_not_configured'];
    }

    if ($keyId !== $configuredKeyId) {
        return ['ok' => false, 'status' => 401, 'error' => 'invalid_key_id'];
    }

    $requestTime = strtotime($timestamp);
    if ($requestTime === false) {
        return ['ok' => false, 'status' => 401, 'error' => 'invalid_timestamp'];
    }

    if (abs(time() - $requestTime) > max(30, $maxSkewSeconds)) {
        return ['ok' => false, 'status' => 401, 'error' => 'timestamp_skew'];
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $canonical = buildJussExamBridgeCanonicalRequest($method, $path, $timestamp, $nonce, (string) $rawBody);
    $expectedSignature = hash_hmac('sha256', $canonical, $configuredSecret);

    if (!hash_equals($expectedSignature, $signature)) {
        return ['ok' => false, 'status' => 401, 'error' => 'invalid_signature'];
    }

    if ($path !== $expectedPath) {
        return ['ok' => false, 'status' => 401, 'error' => 'path_mismatch'];
    }

    $requestTimestamp = date('Y-m-d H:i:s', $requestTime);
    $nonceStored = registerJussExamBridgeNonce($connection2, $nonce, $keyId, $requestTimestamp, $maxSkewSeconds);
    if (!$nonceStored) {
        return ['ok' => false, 'status' => 409, 'error' => 'nonce_replay'];
    }

    return ['ok' => true, 'status' => 200];
}

function outputJussExamBridgeJson($statusCode, array $payload)
{
    http_response_code((int) $statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
}
