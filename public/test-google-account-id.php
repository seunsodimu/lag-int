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
    
    // Format the account ID correctly
    $formattedAccountId = $accountIdFromEnv;
    if (!str_starts_with($formattedAccountId, 'accounts/')) {
        $formattedAccountId = 'accounts/' . $accountIdFromEnv;
    }
    
    $logger->info('Testing Google Business Profile account', [
        'original_id' => $accountIdFromEnv,
        'formatted_id' => $formattedAccountId
    ]);
    
    // Test 1: Get account details
    $url1 = 'https://businessprofiles.googleapis.com/v1/' . $formattedAccountId;
    
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
    
    $logger->info('Testing endpoint: ' . $url1);
    
    $response1 = @file_get_contents($url1, false, $context);
    
    if ($response1 === false) {
        $error = error_get_last();
        $errorMsg = $error['message'] ?? 'Unknown error';
        
        $logger->error('Account lookup failed', [
            'url' => $url1,
            'error' => $errorMsg
        ]);
        
        echo json_encode([
            'success' => false,
            'error' => $errorMsg,
            'account_id_tested' => $formattedAccountId,
            'troubleshooting' => [
                'This account ID might not exist or might not be accessible with your OAuth token',
                'Try the following:',
                '1. Log in to Google Business Profile: https://business.google.com',
                '2. In the top-left menu, you should see your Business Profile name',
                '3. Check the URL - it should contain your account ID (numeric ID)',
                '4. Update GOOGLE_ACCOUNT_ID in .env to: accounts/{that_numeric_id}',
                'Current tested ID: ' . $formattedAccountId
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    $data1 = json_decode($response1, true);
    
    if (isset($data1['error'])) {
        throw new Exception('Google API Error: ' . json_encode($data1['error']));
    }
    
    // Test 2: Get locations under this account
    $url2 = 'https://businessprofiles.googleapis.com/v1/' . $formattedAccountId . '/locations';
    
    $logger->info('Testing locations endpoint: ' . $url2);
    
    $response2 = @file_get_contents($url2, false, $context);
    
    $locations = [];
    if ($response2 !== false) {
        $data2 = json_decode($response2, true);
        if (!isset($data2['error'])) {
            $locations = $data2['locations'] ?? [];
        }
    }
    
    echo json_encode([
        'success' => true,
        'account_details' => [
            'tested_account_id' => $formattedAccountId,
            'account_name' => $data1['displayName'] ?? 'Unknown',
            'account_type' => $data1['accountType'] ?? 'Unknown'
        ],
        'locations_found' => count($locations),
        'locations' => array_map(function($loc) {
            return [
                'name' => $loc['name'] ?? 'Unknown',
                'displayName' => $loc['displayName'] ?? 'Unknown',
                'type' => $loc['type'] ?? 'Unknown'
            ];
        }, array_slice($locations, 0, 10)), // Limit to first 10
        'current_env_value' => $accountIdFromEnv,
        'next_steps' => [
            'This account ID appears to be working!',
            'If you see locations above, your configuration is correct',
            'The Google Reviews feature should now work'
        ]
    ], JSON_PRETTY_PRINT);
    
    $logger->info('Account test successful', [
        'account_id' => $formattedAccountId,
        'locations_count' => count($locations)
    ]);
    
} catch (Exception $e) {
    $logger->error('Error testing Google account', ['error' => $e->getMessage()]);
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