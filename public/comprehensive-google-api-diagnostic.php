<?php
/**
 * Comprehensive Google API Diagnostic
 * Tests all API endpoints and provides troubleshooting guidance
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;

$auth = new AuthMiddleware();
$auth->requireAuth();

$logger = Logger::getInstance();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Google API Comprehensive Diagnostic</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #1f2937;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .test-name {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        .result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }
        .pass { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .fail { background: #fee2e2; color: #7f1d1d; border-left: 4px solid #ef4444; }
        .info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; padding: 10px; margin: 10px 0; }
        .warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        pre {
            background: #f3f4f6;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #d1d5db;
        }
        .next-steps {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .next-steps h3 {
            color: #92400e;
            margin-top: 0;
        }
        .next-steps li {
            margin: 8px 0;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Google API Comprehensive Diagnostic</h1>
        
        <?php
        try {
            // Step 1: Check token
            echo '<div class="test-section">';
            echo '<div class="test-name">Step 1: OAuth Token Validation</div>';
            
            $tokenFile = __DIR__ . '/../uploads/google_reviews_cache/oauth_token.json';
            if (!file_exists($tokenFile)) {
                echo '<div class="result fail">❌ Token not found at: ' . htmlspecialchars($tokenFile) . '</div>';
                throw new Exception('OAuth token not found. Please authenticate first.');
            }
            
            $tokenData = json_decode(file_get_contents($tokenFile), true);
            if (!$tokenData) {
                echo '<div class="result fail">❌ Token file is not valid JSON</div>';
                throw new Exception('Token file corrupted');
            }
            
            $accessToken = $tokenData['access_token'] ?? null;
            if (!$accessToken) {
                echo '<div class="result fail">❌ No access_token in cache</div>';
                throw new Exception('No access token');
            }
            
            echo '<div class="result pass">✅ OAuth token found</div>';
            echo '<div class="info">';
            echo '<strong>Token:</strong> ' . htmlspecialchars(substr($accessToken, 0, 30) . '...') . '<br>';
            if (isset($tokenData['expires_at'])) {
                $expiresIn = $tokenData['expires_at'] - time();
                echo '<strong>Expires in:</strong> ' . ($expiresIn > 0 ? intval($expiresIn / 60) . ' minutes' : '❌ EXPIRED') . '<br>';
            }
            if (isset($tokenData['refresh_token'])) {
                echo '<strong>Refresh token:</strong> Available ✓<br>';
            } else {
                echo '<strong>Refresh token:</strong> ❌ Not available<br>';
            }
            echo '</div>';
            
            echo '</div>';
            
            // Step 2: Check configuration
            echo '<div class="test-section">';
            echo '<div class="test-name">Step 2: Configuration Validation</div>';
            
            $accountId = $_ENV['GOOGLE_ACCOUNT_ID'] ?? null;
            $clientId = $_ENV['GOOGLE_CLIENTID'] ?? null;
            $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? null;
            
            echo '<div class="info">';
            echo '<strong>Account ID:</strong> ' . htmlspecialchars($accountId ?? 'NOT SET') . '<br>';
            echo '<strong>Client ID:</strong> ' . htmlspecialchars(substr($clientId ?? '', 0, 20) . '...') . '<br>';
            echo '<strong>Client Secret:</strong> ' . (strlen($clientSecret ?? '') > 0 ? '✓ Set' : '❌ Not set') . '<br>';
            echo '</div>';
            
            if (!$accountId || !$clientId || !$clientSecret) {
                echo '<div class="result fail">❌ Missing required configuration</div>';
                throw new Exception('Configuration incomplete');
            }
            
            echo '<div class="result pass">✅ All configuration present</div>';
            echo '</div>';
            
            // Step 3: Test endpoints
            echo '<div class="test-section">';
            echo '<div class="test-name">Step 3: API Endpoint Tests</div>';
            
            $endpoints = [
                [
                    'name' => 'Google My Business API v4 - Locations',
                    'url' => 'https://mybusiness.googleapis.com/v4/' . (str_starts_with($accountId, 'accounts/') ? $accountId : 'accounts/' . $accountId) . '/locations',
                    'method' => 'GET',
                    'expected' => 'locations array or error object'
                ],
                [
                    'name' => 'Business Profiles API v1 - Accounts',
                    'url' => 'https://businessprofiles.googleapis.com/v1/accounts',
                    'method' => 'GET',
                    'expected' => 'accounts array'
                ],
                [
                    'name' => 'Business Profiles API v1 - Locations',
                    'url' => 'https://businessprofiles.googleapis.com/v1/' . (str_starts_with($accountId, 'accounts/') ? $accountId : 'accounts/' . $accountId) . '/locations',
                    'method' => 'GET',
                    'expected' => 'locations array'
                ]
            ];
            
            foreach ($endpoints as $test) {
                echo '<div style="margin: 20px 0; padding: 15px; background: #f9fafb; border-radius: 4px;">';
                echo '<strong>' . htmlspecialchars($test['name']) . '</strong><br>';
                echo '<small style="color: #6b7280;">URL: ' . htmlspecialchars($test['url']) . '</small><br>';
                
                try {
                    // Test with cURL
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $test['url']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $accessToken,
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    
                    echo '<div class="info">';
                    echo '<strong>HTTP Status:</strong> ' . htmlspecialchars($httpCode) . '<br>';
                    
                    if ($curlError) {
                        echo '<strong>cURL Error:</strong> ' . htmlspecialchars($curlError) . '<br>';
                    }
                    
                    // Try to parse response
                    $parsed = json_decode($response, true);
                    
                    if ($parsed === null && json_last_error() !== JSON_ERROR_NONE) {
                        echo '<strong>Response Type:</strong> <span style="color: #f59e0b;">⚠ Not JSON</span><br>';
                        echo '<strong>First 200 chars:</strong><br>';
                        echo '<pre>' . htmlspecialchars(substr($response, 0, 200)) . '</pre>';
                        echo '<div class="result fail">❌ Response is not JSON</div>';
                    } else {
                        echo '<strong>Response Type:</strong> JSON ✓<br>';
                        
                        if (isset($parsed['error'])) {
                            echo '<div class="result fail">❌ API Error: ' . htmlspecialchars(json_encode($parsed['error'], JSON_PRETTY_PRINT)) . '</div>';
                        } elseif ($httpCode == 200) {
                            echo '<div class="result pass">✅ Success (HTTP 200)</div>';
                            echo '<strong>Response Keys:</strong> ' . htmlspecialchars(implode(', ', array_keys($parsed))) . '<br>';
                            if (isset($parsed['locations'])) {
                                echo '<strong>Locations Found:</strong> ' . count($parsed['locations']) . '<br>';
                            } elseif (isset($parsed['accounts'])) {
                                echo '<strong>Accounts Found:</strong> ' . count($parsed['accounts']) . '<br>';
                            }
                        } else {
                            echo '<div class="result fail">❌ HTTP ' . htmlspecialchars($httpCode) . '</div>';
                        }
                    }
                    
                    echo '</div>';
                    
                } catch (Exception $e) {
                    echo '<div class="result fail">❌ Exception: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                
                echo '</div>';
            }
            
            echo '</div>';
            
            // Step 4: Summary and recommendations
            echo '<div class="test-section">';
            echo '<div class="test-name">Step 4: Recommendations</div>';
            
            echo '<div class="next-steps">';
            echo '<h3>What to do next:</h3>';
            echo '<ol>';
            echo '<li><strong>Check Google Cloud Console:</strong> Verify that "Google My Business API" or "Business Profiles API" is enabled</li>';
            echo '<li><strong>Verify Account ID:</strong> Confirm ' . htmlspecialchars($accountId) . ' is a valid account ID in your Google Business Profile</li>';
            echo '<li><strong>Check Permissions:</strong> The OAuth token must have scope: <code>https://www.googleapis.com/auth/business.manage</code></li>';
            echo '<li><strong>Account Verification:</strong> Go to https://business.google.com and verify your account exists and has locations</li>';
            echo '<li><strong>Re-authenticate:</strong> Try re-authenticating to get a fresh token: <a href="oauth-callback.php">oauth-callback.php</a></li>';
            echo '<li><strong>Check Logs:</strong> Review <code>logs/app.log</code> for detailed error messages</li>';
            echo '</ol>';
            echo '</div>';
            
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="test-section">';
            echo '<div class="result fail">❌ Fatal Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<p>Please complete the OAuth authentication first: <a href="oauth-callback.php">Start OAuth Flow</a></p>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>