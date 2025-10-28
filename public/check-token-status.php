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
        throw new Exception('No OAuth token found. Please authenticate first by visiting the oauth-callback page.');
    }
    
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    
    // Check token expiry
    $expiresAt = $tokenData['expires_at'] ?? null;
    $now = time();
    $isExpired = $expiresAt ? ($expiresAt <= $now) : false;
    $expiresIn = $expiresAt ? ($expiresAt - $now) : null;
    
    // Try to decode the JWT token to see what scopes it has
    $accessToken = $tokenData['access_token'] ?? null;
    $tokenParts = $accessToken ? explode('.', $accessToken) : [];
    
    $output = [
        'success' => true,
        'token_status' => [
            'has_access_token' => !empty($tokenData['access_token']),
            'has_refresh_token' => !empty($tokenData['refresh_token']),
            'is_expired' => $isExpired,
            'expires_at' => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : 'Unknown',
            'expires_in_seconds' => $expiresIn,
            'expires_in_hours' => $expiresIn ? round($expiresIn / 3600, 2) : null
        ],
        'environment' => [
            'google_client_id' => $_ENV['GOOGLE_CLIENTID'] ? substr($_ENV['GOOGLE_CLIENTID'], 0, 20) . '...' : 'Not set',
            'google_account_id' => $_ENV['GOOGLE_ACCOUNT_ID'] ?? 'Not set'
        ],
        'next_steps' => []
    ];
    
    if ($isExpired) {
        $output['next_steps'][] = '⚠️  Token is EXPIRED. Please refresh it.';
        $output['next_steps'][] = 'Run: php find-google-account-id.php (to trigger refresh)';
    } else if ($tokenData['expires_at'] - time() < 300) {
        $output['next_steps'][] = '⚠️  Token expires soon (< 5 min). Consider refreshing.';
    } else {
        $output['next_steps'][] = '✅ Token is valid and active.';
    }
    
    // Check if refresh token exists
    if (empty($tokenData['refresh_token'])) {
        $output['next_steps'][] = '❌ No refresh token found. Will need to re-authenticate when token expires.';
    } else {
        $output['next_steps'][] = '✅ Refresh token is available.';
    }
    
    $output['next_steps'][] = 'If accounts are not found, you may need to:';
    $output['next_steps'][] = '1. Create a Google Business Profile (https://business.google.com)';
    $output['next_steps'][] = '2. Re-authenticate to ensure the OAuth was done with the right account';
    $output['next_steps'][] = '3. Verify the OAuth scopes include "business.manage"';
    
    echo json_encode($output, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $logger->error('Error checking token status', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>