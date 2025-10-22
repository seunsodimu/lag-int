<?php

namespace Laguna\Integration\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

/**
 * Task and Opportunity Controller
 * 
 * Handles creation of Tasks and Opportunities in NetSuite via REST API.
 * Provides OAuth-authenticated access to create and manage these records.
 * 
 * Authentication Methods:
 * 1. Session-based: For web interface users with active login sessions
 * 2. API Key-based: For programmatic access via HTTP Authorization header
 */
class TaskOpportunityController {
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
        
        // Initialize HTTP client for REST API calls
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
            $this->logger->warning('No API keys configured for TaskOpportunity endpoint - authentication will fail');
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
                $this->logger->info('TaskOpportunity API authenticated via session', [
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
            $this->logger->info('TaskOpportunity API key auth check: No Authorization header provided');
            return false;
        }
        
        $this->logger->info('TaskOpportunity API key auth check: Authorization header found', [
            'header_present' => true,
            'valid_keys_count' => count($this->validApiKeys),
            'valid_keys_loaded' => !empty($this->validApiKeys)
        ]);
        
        // Extract Bearer token
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $tokenPreview = substr($token, 0, 8) . '***';
            
            $this->logger->info('TaskOpportunity API key auth check: Bearer token extracted', [
                'token_preview' => $tokenPreview,
                'token_length' => strlen($token),
                'valid_keys_count' => count($this->validApiKeys)
            ]);
            
            // Validate the API key
            if (in_array($token, $this->validApiKeys, true)) {
                $this->logger->info('TaskOpportunity API authenticated via API key', [
                    'api_key' => $tokenPreview
                ]);
                
                return [
                    'auth_method' => 'api_key',
                    'api_key' => $tokenPreview
                ];
            } else {
                $this->logger->warning('TaskOpportunity API key validation failed - token not in valid keys list', [
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
     * Create a new Task in NetSuite
     * 
     * Expects POST request with task details:
     * {
     *   "title": "string",
     *   "message": "string",
     *   "priority": "HIGH|MEDIUM|LOW",
     *   "status": "NOTSTART|IN_PROGRESS|COMPLETED",
     *   "duedate": "YYYY-MM-DD",
     *   "startdate": "YYYY-MM-DD",
     *   "company": integer (customer ID),
     *   "transaction": integer (optional, related transaction ID),
     *   "timedevent": boolean (optional)
     * }
     * 
     * Supports two authentication methods:
     * 1. Session: Active logged-in user session
     * 2. API Key: Bearer token in Authorization header
     * 
     * @return void (outputs JSON response)
     */
    public function createTask() {
        try {
            // Verify authentication first
            $auth = $this->verifyAuthentication();
            if (!$auth) {
                $this->respondWithError('Authentication required. Use session login or API key via Authorization header', 401);
                $this->logger->warning('Task creation request rejected - no authentication');
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
            $requiredFields = ['title', 'company', 'custevent1', 'priority', 'duedate', 'status', 'transaction'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $this->respondWithError("Missing required parameter: {$field}", 400);
                    $this->logger->warning('Task creation request missing required field', ['field' => $field]);
                    return;
                }
            }
            
            $this->logger->info('Creating task in NetSuite', [
                'title' => $data['title'],
                'company' => $data['company'],
                'has_message' => !empty($data['message']),
                'has_duedate' => !empty($data['duedate']),
                'priority' => $data['priority'] ?? 'N/A'
            ]);
            
            // Call NetSuite to create task
            $result = $this->callNetSuiteCreateTask($data);
            
            // Return the result
            $this->respondWithSuccess($result);
            
        } catch (\Exception $e) {
            $this->logger->error('Task creation controller error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->respondWithError('Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create a new Opportunity in NetSuite
     * 
     * Expects POST request with opportunity details:
     * {
     *   "entity": {
     *     "id": integer (customer ID)
     *   },
     *   "title": "string",
     *   "trandate": "YYYY-MM-DD",
     *   "expectedclosedate": "YYYY-MM-DD",
     *   "location": integer (optional),
     *   "department": integer (optional),
     *   "probability": decimal (0-100),
     *   "projectedtotaltedTotal": decimal (optional, or use projectedTotal)
     * }
     * 
     * Supports two authentication methods:
     * 1. Session: Active logged-in user session
     * 2. API Key: Bearer token in Authorization header
     * 
     * @return void (outputs JSON response)
     */
    public function createOpportunity() {
        try {
            // Verify authentication first
            $auth = $this->verifyAuthentication();
            if (!$auth) {
                $this->respondWithError('Authentication required. Use session login or API key via Authorization header', 401);
                $this->logger->warning('Opportunity creation request rejected - no authentication');
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
            if (empty($data['entity']['id'])) {
                $this->respondWithError('Missing required parameter: entity.id (customer ID)', 400);
                $this->logger->warning('Opportunity creation request missing customer ID');
                return;
            }
            
            if (empty($data['title'])) {
                $this->respondWithError('Missing required parameter: title', 400);
                $this->logger->warning('Opportunity creation request missing title');
                return;
            }
            
            $this->logger->info('Creating opportunity in NetSuite', [
                'title' => $data['title'],
                'customer_id' => $data['entity']['id'],
                'has_trandate' => !empty($data['trandate']),
                'has_expectedclosedate' => !empty($data['expectedclosedate']),
                'probability' => $data['probability'] ?? 'N/A'
            ]);
            
            // Call NetSuite to create opportunity
            $result = $this->callNetSuiteCreateOpportunity($data);
            
            // Return the result
            $this->respondWithSuccess($result);
            
        } catch (\Exception $e) {
            $this->logger->error('Opportunity creation controller error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->respondWithError('Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Call NetSuite REST API to create a task
     * 
     * @param array $taskData The task data to create
     * @return array Response from NetSuite
     */
    private function callNetSuiteCreateTask($taskData) {
        $netsuiteCreds = $this->credentials['netsuite'];
        $baseUrl = rtrim($netsuiteCreds['base_url'], '/') . '/services/rest/record/' . $netsuiteCreds['rest_api_version'];
        
        $url = $baseUrl . '/task';
        
        try {
            // OAuth signature should NOT include JSON body - only method, URL, and oauth params
            $authHeader = $this->generateOAuthHeader('POST', $url);
            
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Prefer' => 'transient' // For quick processing without waiting for full sync
                ],
                'json' => $taskData,
                'verify' => false // Disable SSL verification for development
            ]);
            
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            
            $this->logger->info('Task creation response from NetSuite', [
                'status_code' => $statusCode,
                'response_length' => strlen($responseBody)
            ]);
            
            if ($statusCode >= 200 && $statusCode < 300) {
                $result = json_decode($responseBody, true);
                $this->logger->info('Task created successfully', [
                    'task_id' => $result['id'] ?? 'unknown'
                ]);
                
                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'data' => $result
                ];
            } else {
                $this->logger->warning('Task creation failed from NetSuite', [
                    'status_code' => $statusCode,
                    'response' => $responseBody
                ]);
                
                return [
                    'success' => false,
                    'status_code' => $statusCode,
                    'error' => 'NetSuite returned error status',
                    'details' => json_decode($responseBody, true)
                ];
            }
            
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            
            $this->logger->error('Task creation request exception', [
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'response' => $responseBody
            ]);
            
            return [
                'success' => false,
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'details' => json_decode($responseBody, true)
            ];
        }
    }
    
    /**
     * Call NetSuite REST API to create an opportunity
     * 
     * @param array $opportunityData The opportunity data to create
     * @return array Response from NetSuite
     */
    private function callNetSuiteCreateOpportunity($opportunityData) {
        $netsuiteCreds = $this->credentials['netsuite'];
        $baseUrl = rtrim($netsuiteCreds['base_url'], '/') . '/services/rest/record/' . $netsuiteCreds['rest_api_version'];
        
        $url = $baseUrl . '/opportunity';
        
        try {
            // OAuth signature should NOT include JSON body - only method, URL, and oauth params
            $authHeader = $this->generateOAuthHeader('POST', $url);
            
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Prefer' => 'transient' // For quick processing without waiting for full sync
                ],
                'json' => $opportunityData,
                'verify' => false // Disable SSL verification for development
            ]);
            
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            
            $this->logger->info('Opportunity creation response from NetSuite', [
                'status_code' => $statusCode,
                'response_length' => strlen($responseBody)
            ]);
            
            if ($statusCode >= 200 && $statusCode < 300) {
                $result = json_decode($responseBody, true);
                $this->logger->info('Opportunity created successfully', [
                    'opportunity_id' => $result['id'] ?? 'unknown'
                ]);
                
                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'data' => $result
                ];
            } else {
                $this->logger->warning('Opportunity creation failed from NetSuite', [
                    'status_code' => $statusCode,
                    'response' => $responseBody
                ]);
                
                return [
                    'success' => false,
                    'status_code' => $statusCode,
                    'error' => 'NetSuite returned error status',
                    'details' => json_decode($responseBody, true)
                ];
            }
            
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            
            $this->logger->error('Opportunity creation request exception', [
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'response' => $responseBody
            ]);
            
            return [
                'success' => false,
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'details' => json_decode($responseBody, true)
            ];
        }
    }
    
    /**
     * Generate OAuth 1.0 signature for NetSuite API
     * (Same implementation as NetSuiteService)
     * 
     * Important: For REST API with JSON body, body parameters are NOT included in signature.
     * OAuth signature only includes: HTTP method, URL, and OAuth parameters.
     */
    private function generateOAuthHeader($method, $url, $params = []) {
        $signatureMethod = $this->credentials['netsuite']['signature_method'] ?? 'HMAC-SHA256';
        
        $oauthParams = [
            'oauth_consumer_key' => $this->credentials['netsuite']['consumer_key'],
            'oauth_token' => $this->credentials['netsuite']['token_id'],
            'oauth_signature_method' => $signatureMethod,
            'oauth_timestamp' => time(),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_version' => '1.0'
        ];
        
        // IMPORTANT: For REST API with JSON body, DO NOT include body params in signature
        // Only OAuth parameters are included in the signature base string
        ksort($oauthParams);
        
        $paramString = http_build_query($oauthParams, '', '&', PHP_QUERY_RFC3986);
        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        
        $this->logger->debug('OAuth signature generation', [
            'method' => $method,
            'url' => $url,
            'signature_method' => $signatureMethod,
            'oauth_timestamp' => $oauthParams['oauth_timestamp'],
            'base_string_length' => strlen($baseString)
        ]);
        
        $signingKey = rawurlencode($this->credentials['netsuite']['consumer_secret']) . '&' . rawurlencode($this->credentials['netsuite']['token_secret']);
        
        if ($signatureMethod === 'HMAC-SHA256') {
            $signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));
        } else {
            $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
        }
        
        $oauthParams['oauth_signature'] = $signature;
        
        $authHeader = 'OAuth realm="' . $this->credentials['netsuite']['account_id'] . '"';
        foreach ($oauthParams as $key => $value) {
            $authHeader .= ', ' . $key . '="' . rawurlencode($value) . '"';
        }
        
        return $authHeader;
    }
    
    /**
     * Send JSON success response
     */
    private function respondWithSuccess($data) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    }
    
    /**
     * Send JSON error response
     */
    private function respondWithError($message, $statusCode = 400) {
        header('Content-Type: application/json', true, $statusCode);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'status_code' => $statusCode
        ]);
    }
}