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
        throw new Exception('No OAuth token found. Please authenticate first by visiting /lag-int/google-reviews.php');
    }
    
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    $accessToken = $tokenData['access_token'] ?? null;
    
    if (!$accessToken) {
        throw new Exception('No access token found in cache.');
    }
    
    $accountIdFromEnv = $_ENV['GOOGLE_ACCOUNT_ID'] ?? null;
    
    if (!$accountIdFromEnv) {
        throw new Exception('GOOGLE_ACCOUNT_ID not set in .env');
    }
    
    // Test with the v4 My Business API
    $api_base = 'https://mybusiness.googleapis.com/v4';
    
    // Format account ID - v4 API uses accounts/{id} format
    $accountIdFormatted = $accountIdFromEnv;
    if (!str_starts_with($accountIdFormatted, 'accounts/')) {
        $accountIdFormatted = 'accounts/' . $accountIdFormatted;
    }
    
    $logger->info('Testing Google My Business API v4', [
        'original_id' => $accountIdFromEnv,
        'formatted_id' => $accountIdFormatted
    ]);
    
    // Test 1: Get locations
    $url = $api_base . '/' . $accountIdFormatted . '/locations';
    
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 30
        ]
    ]);
    
    $logger->info('Testing endpoint: ' . $url);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        $errorMsg = $error['message'] ?? 'Unknown error';
        
        $logger->error('Locations lookup failed', [
            'url' => $url,
            'error' => $errorMsg
        ]);
        
        echo json_encode([
            'success' => false,
            'error' => $errorMsg,
            'account_id_tested' => $accountIdFormatted,
            'api_endpoint' => 'https://mybusiness.googleapis.com/v4',
            'troubleshooting' => [
                'This account ID might not exist or might not be accessible with your OAuth token',
                'The OAuth token might be invalid or lack the required permissions',
                'Make sure GOOGLE_CLIENTID and GOOGLE_CLIENT_SECRET are correct',
                'Try re-authenticating at /lag-int/google-reviews.php'
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        throw new Exception('Google API Error: ' . json_encode($data['error']));
    }
    
    $locations = $data['locations'] ?? [];
    
    echo json_encode([
        'success' => true,
        'message' => 'Google My Business API v4 is working!',
        'account_id_tested' => $accountIdFormatted,
        'locations_count' => count($locations),
        'locations' => array_map(function($loc) {
            return [
                'name' => $loc['name'] ?? 'Unknown',
                'displayName' => $loc['displayName'] ?? 'Unknown',
                'type' => $loc['type'] ?? 'Unknown'
            ];
        }, array_slice($locations, 0, 10)),
        'next_step' => count($locations) > 0 
            ? 'API is working correctly. Try loading http://localhost:8080/lag-int/google-reviews.php'
            : 'No locations found. Make sure your account has at least one location'
    ], JSON_PRETTY_PRINT);
    
    $logger->info('API test successful', [
        'account_id' => $accountIdFormatted,
        'locations_count' => count($locations)
    ]);
    
} catch (Exception $e) {
    $logger->error('Error testing API', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'troubleshooting' => [
            'Make sure your OAuth token is still valid',
            'You may need to re-authenticate at /lag-int/google-reviews.php',
            'Then run this test again'
        ]
    ], JSON_PRETTY_PRINT);
}
?>