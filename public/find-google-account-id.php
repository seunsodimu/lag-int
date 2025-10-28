<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Services\GoogleReviewsService;

header('Content-Type: application/json');

$logger = Logger::getInstance();

try {
    // Initialize service - this will automatically refresh expired tokens
    $service = new GoogleReviewsService();
    
    $tokenFile = __DIR__ . '/../uploads/google_reviews_cache/oauth_token.json';
    
    if (!file_exists($tokenFile)) {
        throw new Exception('No OAuth token found. Please authenticate first by visiting /lag-int/google-reviews.php');
    }
    
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    $accessToken = $tokenData['access_token'] ?? null;
    
    if (!$accessToken) {
        throw new Exception('No access token found in cache.');
    }
    
    // Make request to get accounts
    $url = 'https://businessprofiles.googleapis.com/v1/accounts';
    
    $logger->info('Fetching Google Business Profile accounts');
    
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];
    
    // Use cURL (more reliable)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
                'header' => implode("\r\n", $headers),
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
    
    if (isset($data['error'])) {
        throw new Exception('Google API Error: ' . $data['error']['message']);
    }
    
    $accounts = $data['accounts'] ?? [];
    
    if (empty($accounts)) {
        echo json_encode([
            'success' => false,
            'error' => 'No Google Business Profile accounts found',
            'current_account_in_env' => $_ENV['GOOGLE_ACCOUNT_ID'] ?? 'Not set',
            'note' => 'Make sure you have a Google Business Profile and the OAuth token has the correct permissions'
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Extract account details
    $accountsList = [];
    foreach ($accounts as $account) {
        $accountsList[] = [
            'name' => $account['name'] ?? 'Unknown',
            'accountNumber' => $account['accountNumber'] ?? 'Unknown',
            'type' => $account['type'] ?? 'Unknown',
            'displayName' => $account['displayName'] ?? 'Unknown'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Found ' . count($accounts) . ' Google Business Profile account(s)',
        'accounts' => $accountsList,
        'current_account_in_env' => $_ENV['GOOGLE_ACCOUNT_ID'] ?? 'Not set',
        'instructions' => [
            'If you see multiple accounts, use the "name" field (format: accounts/123456789)',
            'Update your .env file with: GOOGLE_ACCOUNT_ID=<name_value>',
            'Example: GOOGLE_ACCOUNT_ID=accounts/123456789'
        ]
    ], JSON_PRETTY_PRINT);
    
    $logger->info('Successfully retrieved Google Business Profile accounts', ['count' => count($accounts)]);
    
} catch (Exception $e) {
    $logger->error('Error fetching Google accounts', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'current_account_in_env' => $_ENV['GOOGLE_ACCOUNT_ID'] ?? 'Not set',
        'troubleshooting' => [
            'Make sure you have authenticated first',
            'Check that the OAuth token has not expired',
            'Verify the OAuth credentials in .env are correct'
        ]
    ], JSON_PRETTY_PRINT);
}
?>