<?php

namespace Laguna\Integration\Services;

use Laguna\Integration\Utils\Logger;
use Exception;

/**
 * RingCentral Service
 * 
 * Handles authentication and analytics data retrieval from RingCentral API.
 */
class RingCentralService {
    private $logger;
    private $config;
    private $accessToken;

    private $cacheDir;

    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->cacheDir = __DIR__ . '/../../uploads/cache/ringcentral';
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    /**
     * Authenticate with RingCentral using JWT
     * 
     * @return string Access token
     * @throws Exception
     */
    public function authenticate() {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $rcConfig = $this->config['ringcentral'] ?? [];
        $serverUrl = $rcConfig['server_url'] ?? '';
        $clientId = $rcConfig['client_id'] ?? '';
        $clientSecret = $rcConfig['client_secret'] ?? '';
        $jwt = $rcConfig['user_jwt'] ?? '';

        if (empty($serverUrl) || empty($clientId) || empty($clientSecret) || empty($jwt)) {
            throw new Exception('RingCentral configuration is incomplete');
        }

        $authHeader = base64_encode($clientId . ":" . $clientSecret);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serverUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $authHeader
        ]);

        $data = [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('RingCentral auth request failed', ['error' => $error]);
            throw new Exception('RingCentral auth request failed: ' . $error);
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200 || !isset($result['access_token'])) {
            $this->logger->error('RingCentral auth failed', [
                'http_code' => $httpCode,
                'response' => $result
            ]);
            throw new Exception('RingCentral authentication failed: ' . ($result['error_description'] ?? 'Unknown error'));
        }

        $this->accessToken = $result['access_token'];
        return $this->accessToken;
    }

    /**
     * Fetch analytics aggregation data with 24-hour caching
     * 
     * @param array $params Request parameters
     * @param bool $useCache Whether to use caching
     * @return array
     * @throws Exception
     */
    public function fetchAnalyticsAggregation($params, $useCache = true) {
        $cacheKey = md5(json_encode($params));
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';
        $cacheTTL = 86400; // 24 hours in seconds

        if ($useCache && file_exists($cacheFile)) {
            $fileTime = filemtime($cacheFile);
            if ((time() - $fileTime) < $cacheTTL) {
                $cachedData = json_decode(file_get_contents($cacheFile), true);
                if ($cachedData) {
                    return $cachedData;
                }
            }
        }

        $token = $this->authenticate();

        $url = 'https://platform.ringcentral.com/analytics/calls/v1/accounts/~/aggregation/fetch';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('RingCentral analytics request failed', ['error' => $error]);
            throw new Exception('RingCentral analytics request failed: ' . $error);
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            $this->logger->error('RingCentral analytics request failed', [
                'http_code' => $httpCode,
                'response' => $result
            ]);
            throw new Exception('RingCentral analytics request failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        if ($useCache) {
            file_put_contents($cacheFile, json_encode($result));
        }

        return $result;
    }
}
