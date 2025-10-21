<?php

namespace Laguna\Integration\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

/**
 * Saved Search Controller
 * 
 * Handles NetSuite saved search queries via RestLet API.
 * Provides OAuth-authenticated access to saved searches in NetSuite.
 * 
 * Authentication Methods:
 * 1. Session-based: For web interface users with active login sessions
 * 2. API Key-based: For programmatic access via HTTP Authorization header
 */
class SavedSearchController {
    private $netSuiteService;
    private $logger;
    private $credentials;
    private $client;
    private $config;
    
    // Valid API keys (can be stored in environment variables or config)
    private $validApiKeys = [];
    
    public function __construct() {
        $this->netSuiteService = new NetSuiteService();
        $this->logger = Logger::getInstance();
        $this->credentials = require __DIR__ . '/../../config/credentials.php';
        $this->config = require __DIR__ . '/../../config/config.php';
        
        // Load valid API keys from environment or config
        $this->loadValidApiKeys();
        
        // Initialize HTTP client for RestLet calls
        $this->client = new Client([
            'timeout' => 60,
            'verify' => false, // Disable SSL verification for development - CHANGE IN PRODUCTION
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }
    
    /**
     * Load valid API keys from environment variables or configuration
     */
    private function loadValidApiKeys() {
        // Try multiple sources for API keys (in priority order)
        $envKeys = null;
        $loadedFrom = null;
        
        // 1. Try $_ENV (populated by phpdotenv in bootstrap)
        if (!empty($_ENV['SAVED_SEARCH_API_KEYS'])) {
            $envKeys = $_ENV['SAVED_SEARCH_API_KEYS'];
            $loadedFrom = '$_ENV';
            $this->logger->info('API keys loaded from $_ENV', ['keys_length' => strlen($envKeys)]);
        }
        // 2. Try getenv() (older phpdotenv or server env)
        elseif ($getEnvResult = getenv('SAVED_SEARCH_API_KEYS')) {
            $envKeys = $getEnvResult;
            $loadedFrom = 'getenv()';
            $this->logger->info('API keys loaded from getenv()', ['keys_length' => strlen($envKeys)]);
        }
        // 3. Try $_SERVER
        elseif (!empty($_SERVER['SAVED_SEARCH_API_KEYS'])) {
            $envKeys = $_SERVER['SAVED_SEARCH_API_KEYS'];
            $loadedFrom = '$_SERVER';
            $this->logger->info('API keys loaded from $_SERVER', ['keys_length' => strlen($envKeys)]);
        }
        // 4. Fallback: Read from .env file directly
        else {
            $envFile = __DIR__ . '/../../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    // Skip comments and empty lines
                    if (empty($line) || strpos(trim($line), '#') === 0) {
                        continue;
                    }
                    if (strpos($line, 'SAVED_SEARCH_API_KEYS=') === 0) {
                        $envKeys = substr($line, strlen('SAVED_SEARCH_API_KEYS='));
                        $envKeys = trim($envKeys, '\'"');
                        $loadedFrom = '.env file (direct read)';
                        $this->logger->info('API keys loaded from .env file', ['keys_length' => strlen($envKeys)]);
                        break;
                    }
                }
            } else {
                $this->logger->warning('.env file not found at: ' . $envFile);
            }
        }
        
        // Parse the keys
        if ($envKeys) {
            $this->validApiKeys = array_filter(array_map('trim', explode(',', $envKeys)));
            $this->logger->info('API keys parsed and ready', [
                'key_count' => count($this->validApiKeys),
                'loaded_from' => $loadedFrom,
                'first_key_preview' => count($this->validApiKeys) > 0 ? substr($this->validApiKeys[0], 0, 8) . '***' : 'N/A'
            ]);
        } else {
            $this->logger->warning('No API keys found in any configuration source');
        }
        
        // Development fallback: add a default demo key if no keys configured
        if (empty($this->validApiKeys) && $this->config['app']['debug']) {
            $this->validApiKeys = ['demo-key-for-testing-only'];
            $this->logger->info('Using demo API key (development mode)', ['key_count' => 1]);
        }
        
        if (empty($this->validApiKeys)) {
            $this->logger->warning('No API keys configured for SavedSearch endpoint - authentication will fail');
        }
    }
    
    /**
     * Verify authentication - supports both session and API key methods
     * 
     * @return array|false Authentication info if successful, false if not authenticated
     */
    public function verifyAuthentication() {
        // Check session-based authentication
        $sessionAuth = $this->verifySessionAuth();
        if ($sessionAuth) {
            return $sessionAuth;
        }
        
        // Check API key authentication
        $apiKeyAuth = $this->verifyApiKeyAuth();
        if ($apiKeyAuth) {
            return $apiKeyAuth;
        }
        
        return false;
    }
    
    /**
     * Verify session-based authentication
     * 
     * @return array|false User info if authenticated, false otherwise
     */
    private function verifySessionAuth() {
        if (session_status() !== PHP_SESSION_ACTIVE) { 
            @session_start(); 
        }
        
        $sessionId = $_SESSION['session_id'] ?? $_COOKIE['session_id'] ?? null;
        
        if (!$sessionId) {
            return false;
        }
        
        // Try to validate the session (if database is available)
        try {
            $authService = new \Laguna\Integration\Services\AuthService();
            $user = $authService->validateSession($sessionId);
            
            if ($user) {
                $this->logger->info('SavedSearch API authenticated via session', [
                    'user_id' => $user['id'],
                    'username' => $user['username']
                ]);
                
                return [
                    'auth_method' => 'session',
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ];
            }
        } catch (\Exception $e) {
            // Database might not be available or auth service failed
            $this->logger->debug('Session validation failed', ['error' => $e->getMessage()]);
        }
        
        return false;
    }
    
    /**
     * Verify API key authentication
     * 
     * @return array|false API key info if authenticated, false otherwise
     */
    private function verifyApiKeyAuth() {
        // Check Authorization header for Bearer token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader)) {
            $this->logger->info('API key auth check: No Authorization header provided');
            return false;
        }
        
        $this->logger->info('API key auth check: Authorization header found', [
            'header_present' => true,
            'valid_keys_count' => count($this->validApiKeys),
            'valid_keys_loaded' => !empty($this->validApiKeys)
        ]);
        
        // Extract Bearer token
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $tokenPreview = substr($token, 0, 8) . '***';
            
            $this->logger->info('API key auth check: Bearer token extracted', [
                'token_preview' => $tokenPreview,
                'token_length' => strlen($token),
                'valid_keys_count' => count($this->validApiKeys)
            ]);
            
            // Validate the API key
            if (in_array($token, $this->validApiKeys, true)) {
                $this->logger->info('SavedSearch API authenticated via API key', [
                    'api_key' => $tokenPreview
                ]);
                
                return [
                    'auth_method' => 'api_key',
                    'api_key' => $tokenPreview
                ];
            } else {
                $this->logger->warning('API key validation failed - token not in valid keys list', [
                    'provided_key' => $tokenPreview,
                    'provided_key_length' => strlen($token),
                    'valid_keys_count' => count($this->validApiKeys),
                    'valid_keys_loaded' => !empty($this->validApiKeys)
                ]);
                
                // Log the first valid key for debugging (only first 16 chars)
                if (!empty($this->validApiKeys)) {
                    $this->logger->info('Expected key format (first key preview)', [
                        'preview' => substr($this->validApiKeys[0], 0, 16) . '***'
                    ]);
                }
            }
        } else {
            $this->logger->warning('Authorization header not in Bearer format', [
                'header_value' => substr($authHeader, 0, 50) . '...'
            ]);
        }
        
        return false;
    }
    
    /**
     * Execute a saved search via NetSuite RestLet
     * 
     * Expects POST request with:
     * {
     *   "scriptID": "string",
     *   "searchID": "string"
     * }
     * 
     * Supports two authentication methods:
     * 1. Session: Active logged-in user session
     * 2. API Key: Bearer token in Authorization header
     * 
     * @return void (outputs JSON response)
     */
    public function executeSavedSearch() {
        try {
            // Verify authentication first
            $auth = $this->verifyAuthentication();
            if (!$auth) {
                $this->respondWithError('Authentication required. Use session login or API key via Authorization header', 401);
                $this->logger->warning('SavedSearch request rejected - no authentication');
                return;
            }
            
            // Get request data
            $rawData = file_get_contents('php://input');
            
            if (empty($rawData)) {
                $this->respondWithError('Empty request body', 400);
                return;
            }
            
            $data = json_decode($rawData, true);
            if ($data === null) {
                $this->respondWithError('Invalid JSON in request body', 400);
                return;
            }
            
            // Validate required parameters
            $scriptID = $data['scriptID'] ?? null;
            $searchID = $data['searchID'] ?? null;
            
            if (empty($scriptID) || empty($searchID)) {
                $this->respondWithError('Missing required parameters: scriptID and searchID', 400);
                $this->logger->warning('SavedSearch request missing parameters', [
                    'has_scriptID' => isset($data['scriptID']),
                    'has_searchID' => isset($data['searchID'])
                ]);
                return;
            }
            
            // Validate parameter formats (alphanumeric and underscores only for safety)
            if (!$this->isValidScriptID($scriptID) || !$this->isValidSearchID($searchID)) {
                $this->respondWithError('Invalid parameter format: scriptID and searchID must be alphanumeric', 400);
                $this->logger->warning('SavedSearch request with invalid parameter format', [
                    'scriptID' => $scriptID,
                    'searchID' => $searchID
                ]);
                return;
            }
            
            $this->logger->info('Executing SavedSearch via RestLet', [
                'scriptID' => $scriptID,
                'searchID' => $searchID
            ]);
            
            // Call the RestLet
            $result = $this->callRestLet($scriptID, $searchID);
            
            // Return the result
            $this->respondWithSuccess($result);
            
        } catch (\Exception $e) {
            $this->logger->error('SavedSearch controller error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->respondWithError('Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Call the NetSuite RestLet with OAuth authentication
     * 
     * @param string $scriptID The RestLet script ID
     * @param string $searchID The saved search ID
     * @return array Response from RestLet
     */
    private function callRestLet($scriptID, $searchID) {
        $netsuiteCreds = $this->credentials['netsuite'];
        $restletBaseUrl = $netsuiteCreds['restlet_base_url'];
        
        // Build RestLet URL with script and deploy parameters
        $restletUrl = $restletBaseUrl . '?script=' . urlencode($scriptID) . '&deploy=1';
        
        // Prepare request body
        $requestBody = [
            'searchID' => $searchID
        ];
        
        try {
            // Generate OAuth header using NetSuiteService
            $authHeader = $this->generateRestLetOAuthHeader('POST', $restletUrl);
            
            $this->logger->info('Calling RestLet', [
                'url' => $restletUrl,
                'request_body' => $requestBody
            ]);
            
            // Make the request to RestLet
            $response = $this->client->request('POST', $restletUrl, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'transient'
                ],
                'json' => $requestBody
            ]);
            
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            
            $this->logger->info('RestLet response received', [
                'status_code' => $statusCode,
                'response_length' => strlen($responseBody)
            ]);
            
            // Parse response
            if ($statusCode === 200) {
                $result = json_decode($responseBody, true);
                if ($result === null) {
                    // If not JSON, return as raw response
                    return [
                        'success' => true,
                        'data' => $responseBody,
                        'content_type' => 'text/plain'
                    ];
                }
                
                return [
                    'success' => true,
                    'data' => $result,
                    'status_code' => $statusCode
                ];
            } else {
                $this->logger->warning('RestLet returned non-200 status', [
                    'status_code' => $statusCode,
                    'response' => $responseBody
                ]);
                
                return [
                    'success' => false,
                    'error' => 'RestLet returned status ' . $statusCode,
                    'status_code' => $statusCode,
                    'response' => $responseBody
                ];
            }
            
        } catch (RequestException $e) {
            $this->logger->error('RestLet request failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null,
                'details' => $responseBody
            ];
        }
    }
    
    /**
     * Generate OAuth 1.0 header for RestLet API call
     * This is similar to the NetSuiteService method but tailored for RestLets
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Full RestLet URL
     * @return string OAuth Authorization header
     */
    private function generateRestLetOAuthHeader($method, $url) {
        $netsuiteCreds = $this->credentials['netsuite'];
        $signatureMethod = $netsuiteCreds['signature_method'] ?? 'HMAC-SHA256';
        
        // Build OAuth parameters
        $oauthParams = [
            'oauth_consumer_key' => $netsuiteCreds['consumer_key'],
            'oauth_token' => $netsuiteCreds['token_id'],
            'oauth_signature_method' => $signatureMethod,
            'oauth_timestamp' => time(),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_version' => '1.0'
        ];
        
        // For RestLet, we include script and deploy as signature base parameters
        $signatureParams = array_merge($oauthParams, [
            'script' => preg_match('/script=([^&]+)/', $url, $m) ? $m[1] : '',
            'deploy' => '1'
        ]);
        
        ksort($signatureParams);
        
        // Create parameter string
        $paramString = http_build_query($signatureParams, '', '&', PHP_QUERY_RFC3986);
        
        // Create signature base string (without query params since they're in the signature)
        $baseUrl = preg_replace('/\?.*$/', '', $url);
        $baseString = strtoupper($method) . '&' . rawurlencode($baseUrl) . '&' . rawurlencode($paramString);
        
        // Create signing key
        $signingKey = rawurlencode($netsuiteCreds['consumer_secret']) . '&' . rawurlencode($netsuiteCreds['token_secret']);
        
        // Generate signature
        if ($signatureMethod === 'HMAC-SHA256') {
            $signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));
        } else {
            $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
        }
        
        $oauthParams['oauth_signature'] = $signature;
        
        // Build authorization header
        $authHeader = 'OAuth realm="' . $netsuiteCreds['account_id'] . '"';
        foreach ($oauthParams as $key => $value) {
            $authHeader .= ', ' . $key . '="' . rawurlencode($value) . '"';
        }
        
        return $authHeader;
    }
    
    /**
     * Validate scriptID parameter format
     */
    private function isValidScriptID($scriptID) {
        // Allow alphanumeric and underscores
        return preg_match('/^[a-zA-Z0-9_]+$/', $scriptID) && strlen($scriptID) <= 50;
    }
    
    /**
     * Validate searchID parameter format
     */
    private function isValidSearchID($searchID) {
        // Allow alphanumeric and underscores
        return preg_match('/^[a-zA-Z0-9_]+$/', $searchID) && strlen($searchID) <= 50;
    }
    
    /**
     * Send success response
     */
    private function respondWithSuccess($data) {
        http_response_code(200);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Send error response
     */
    private function respondWithError($message, $statusCode = 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}