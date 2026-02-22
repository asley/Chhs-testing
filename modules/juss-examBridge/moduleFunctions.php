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
    header('X-Bridge-Version: ' . getJussExamBridgeModuleVersion());
    echo json_encode($payload);
}

function getJussExamBridgeModuleVersion()
{
    static $resolvedVersion = null;

    if ($resolvedVersion !== null) {
        return $resolvedVersion;
    }

    $defaultVersion = 'unknown';
    $versionFile = __DIR__ . '/version.php';

    if (!is_file($versionFile)) {
        $resolvedVersion = $defaultVersion;
        return $resolvedVersion;
    }

    $moduleVersion = null;
    $version = null;

    require $versionFile;

    if (is_string($moduleVersion) && $moduleVersion !== '') {
        $resolvedVersion = $moduleVersion;
        return $resolvedVersion;
    }

    if (is_string($version) && $version !== '') {
        $resolvedVersion = $version;
        return $resolvedVersion;
    }

    $resolvedVersion = $defaultVersion;
    return $resolvedVersion;
}

function getJussExamBridgeServicePersonID($container, $connection2)
{
    /** @var SettingGateway $settingGateway */
    $settingGateway = $container->get(SettingGateway::class);

    $candidates = [];

    $explicit = getJussExamBridgeSetting($settingGateway, 'bridgeServicePersonID');
    if ($explicit !== '' && ctype_digit((string) $explicit)) {
        $candidates[] = (int) $explicit;
    }

    $orgAdmin = $settingGateway->getSettingByScope('System', 'organisationAdministrator');
    if ($orgAdmin !== false && $orgAdmin !== null && ctype_digit((string) $orgAdmin)) {
        $candidates[] = (int) $orgAdmin;
    }

    $candidates[] = 1;

    foreach ($candidates as $candidate) {
        try {
            $stmt = $connection2->prepare('SELECT gibbonPersonID FROM gibbonPerson WHERE gibbonPersonID = :gibbonPersonID LIMIT 1');
            $stmt->execute(['gibbonPersonID' => $candidate]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                return $candidate;
            }
        } catch (PDOException $e) {
            continue;
        }
    }

    try {
        $fallbackStmt = $connection2->prepare("SELECT gibbonPersonID FROM gibbonPerson WHERE canLogin = 'Y' ORDER BY gibbonPersonID ASC LIMIT 1");
        $fallbackStmt->execute();
        $fallback = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($fallback)) {
            return (int) $fallback['gibbonPersonID'];
        }
    } catch (PDOException $e) {
        return 1;
    }

    return 1;
}
