<?php
/**
 * Debug Google My Business API - Locations Endpoint
 * Captures raw HTTP response to diagnose parsing issues
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
    <title>Debug - Google Locations API Response</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #4ec9b0; }
        .section { background: #252526; padding: 15px; margin: 15px 0; border-left: 3px solid #007acc; }
        .success { border-left-color: #4ec9b0; }
        .error { border-left-color: #f48771; }
        .info { border-left-color: #9cdcfe; }
        pre { background: #1e1e1e; overflow-x: auto; padding: 10px; border: 1px solid #3e3e42; }
        .label { color: #ce9178; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Google Locations API Response</h1>
        
        <?php
        try {
            // Get token from cache
            $tokenFile = __DIR__ . '/../uploads/google_reviews_cache/oauth_token.json';
            if (!file_exists($tokenFile)) {
                echo '<div class="section error">';
                echo '<strong>❌ Error:</strong> No OAuth token found. Please authenticate first.<br>';
                echo '<a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '">← Back</a>';
                echo '</div>';
                exit;
            }
            
            $tokenData = json_decode(file_get_contents($tokenFile), true);
            $accessToken = $tokenData['access_token'] ?? null;
            
            if (!$accessToken) {
                echo '<div class="section error">';
                echo '<strong>❌ Error:</strong> No access token in cache.';
                echo '</div>';
                exit;
            }
            
            // Get account ID
            $accountId = $_ENV['GOOGLE_ACCOUNT_ID'] ?? null;
            if (!$accountId) {
                echo '<div class="section error">';
                echo '<strong>❌ Error:</strong> GOOGLE_ACCOUNT_ID not set in .env';
                echo '</div>';
                exit;
            }
            
            $accountIdFormatted = str_starts_with($accountId, 'accounts/') 
                ? $accountId 
                : "accounts/{$accountId}";
            
            $url = "https://mybusiness.googleapis.com/v4/{$accountIdFormatted}/locations";
            
            echo '<div class="section info">';
            echo '<span class="label">Endpoint:</span> ' . htmlspecialchars($url) . '<br>';
            echo '<span class="label">Token:</span> ' . htmlspecialchars(substr($accessToken, 0, 20) . '...') . '<br>';
            echo '</div>';
            
            // Make request using cURL with detailed output
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            
            // Capture verbose output
            $verboseHandle = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verboseHandle);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            rewind($verboseHandle);
            $verboseOutput = stream_get_contents($verboseHandle);
            fclose($verboseHandle);
            
            // Display HTTP status
            echo '<div class="section ' . ($httpCode === 200 ? 'success' : 'error') . '">';
            echo '<span class="label">HTTP Status:</span> ' . htmlspecialchars($httpCode) . '<br>';
            if ($curlError) {
                echo '<span class="label">cURL Error:</span> ' . htmlspecialchars($curlError) . '<br>';
            }
            echo '</div>';
            
            // Display raw response
            echo '<div class="section">';
            echo '<span class="label">Raw Response Body:</span><br>';
            echo '<pre>' . htmlspecialchars($response) . '</pre>';
            echo '</div>';
            
            // Try to parse JSON
            echo '<div class="section">';
            echo '<span class="label">JSON Parse Result:</span><br>';
            $parsed = json_decode($response, true);
            if ($parsed === null && json_last_error() !== JSON_ERROR_NONE) {
                echo '<strong style="color: #f48771;">❌ JSON Parse Failed:</strong> ' . htmlspecialchars(json_last_error_msg()) . '<br>';
                echo 'Response is not valid JSON!';
            } else {
                echo '<pre>' . htmlspecialchars(json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
            }
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="section error">';
            echo '<strong>❌ Exception:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>