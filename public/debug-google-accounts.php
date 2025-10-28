<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Laguna\Integration\Utils\Logger;

header('Content-Type: application/json');

$logger = Logger::getInstance();

try {
    // Get the cached token
    $tokenFile = __DIR__ . '/../uploads/google_reviews_cache/oauth_token.json';
    
    if (!file_exists($tokenFile)) {
        throw new Exception('No OAuth token found. Please authenticate first.');
    }
    
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    $accessToken = $tokenData['access_token'] ?? null;
    
    if (!$accessToken) {
        throw new Exception('No access token found in cache.');
    }
    
    $logger->info('Found access token for Google API debugging');
    
    // Test 1: Try to get accounts using the new API
    $url = 'https://businessprofiles.googleapis.com/v1/accounts';
    
    $logger->info('Testing Google Business Profile API', ['url' => $url]);
    
    // Use cURL (more reliable)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception('Failed to fetch accounts: ' . $curlError);
        }
    } else {
        // Fallback to file_get_contents
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $accessToken,
                'timeout' => 30
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new Exception('Failed to fetch accounts: ' . ($error['message'] ?? 'Unknown error'));
        }
    }
    
    $data = json_decode($response, true);
    
    $logger->info('Got response from Google Business Profile API', ['data' => $data]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully fetched Google accounts',
        'accounts' => $data['accounts'] ?? [],
        'current_account_id_in_env' => $_ENV['GOOGLE_ACCOUNT_ID'] ?? 'Not set',
        'debug_info' => [
            'api_endpoint' => $url,
            'token_expiry' => isset($tokenData['expires_at']) ? date('Y-m-d H:i:s', $tokenData['expires_at']) : 'Unknown'
        ]
    ], JSON_PRETTY_PRINT);
    
    $logger->info('Google accounts debugging completed successfully');
    
} catch (Exception $e) {
    $logger->error('Error debugging Google accounts', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'current_account_id_in_env' => $_ENV['GOOGLE_ACCOUNT_ID'] ?? 'Not set'
    ], JSON_PRETTY_PRINT);
}
?>