<?php
/**
 * NetSuite Saved Search API Endpoint
 * 
 * This endpoint executes NetSuite saved searches via RestLet API with OAuth authentication.
 * 
 * Endpoint: POST /netsuite-saved-search-api.php
 * 
 * Request Headers:
 *   - Content-Type: application/json
 * 
 * Request Body:
 *   {
 *     "scriptID": "your_restlet_script_id",
 *     "searchID": "your_saved_search_id"
 *   }
 * 
 * Example cURL:
 *   curl --location 'http://localhost:8080/netsuite-saved-search-api.php' \
 *     --header 'Content-Type: application/json' \
 *     --data '{"scriptID": "customscript_my_restlet", "searchID": "my_search"}'
 * 
 * Success Response (200):
 *   {
 *     "success": true,
 *     "data": {
 *       "success": true,
 *       "data": [...search results...],
 *       "status_code": 200
 *     },
 *     "timestamp": "2024-01-15T10:30:00+00:00"
 *   }
 * 
 * Error Response (400/500):
 *   {
 *     "success": false,
 *     "error": "Error message",
 *     "timestamp": "2024-01-15T10:30:00+00:00"
 *   }
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\SavedSearchController;
use Laguna\Integration\Utils\Logger;

// Load environment variables from .env file
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize logger
$logger = Logger::getInstance();

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    // Handle POST request - execute saved search
    try {
        $controller = new SavedSearchController();
        $controller->executeSavedSearch();
    } catch (\Exception $e) {
        $logger->error('SavedSearch API endpoint error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error',
            'details' => $e->getMessage(),
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
    }
} else {
    // Handle GET request - show API documentation
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>NetSuite Saved Search API</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
                color: #333;
            }
            
            .container {
                max-width: 900px;
                margin: 0 auto;
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
                overflow: hidden;
            }
            
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 28px;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            
            .header p {
                opacity: 0.9;
                font-size: 14px;
            }
            
            .content {
                padding: 40px 30px;
            }
            
            section {
                margin-bottom: 40px;
            }
            
            section:last-child {
                margin-bottom: 0;
            }
            
            h2 {
                font-size: 20px;
                margin-bottom: 15px;
                color: #667eea;
                border-bottom: 2px solid #667eea;
                padding-bottom: 10px;
            }
            
            h3 {
                font-size: 16px;
                margin: 20px 0 10px;
                color: #333;
            }
            
            p {
                line-height: 1.6;
                margin-bottom: 12px;
                color: #555;
            }
            
            .info-box {
                background: #e3f2fd;
                border-left: 4px solid #2196f3;
                padding: 15px;
                margin: 15px 0;
                border-radius: 0 4px 4px 0;
            }
            
            .warning-box {
                background: #fff3e0;
                border-left: 4px solid #ff9800;
                padding: 15px;
                margin: 15px 0;
                border-radius: 0 4px 4px 0;
            }
            
            .success-box {
                background: #e8f5e8;
                border-left: 4px solid #4caf50;
                padding: 15px;
                margin: 15px 0;
                border-radius: 0 4px 4px 0;
            }
            
            code {
                background: #f8f9fa;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                color: #d63384;
            }
            
            pre {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 15px;
                overflow-x: auto;
                font-size: 12px;
                line-height: 1.4;
                margin: 12px 0;
            }
            
            .code-label {
                font-size: 12px;
                color: #666;
                margin-bottom: 5px;
                font-weight: 600;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }
            
            th, td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #e9ecef;
            }
            
            th {
                background: #f8f9fa;
                font-weight: 600;
                color: #333;
            }
            
            tr:hover {
                background: #f8f9fa;
            }
            
            .btn-group {
                display: flex;
                gap: 10px;
                margin: 20px 0;
                flex-wrap: wrap;
            }
            
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                border: none;
                cursor: pointer;
                font-size: 14px;
                transition: background 0.3s;
            }
            
            .btn:hover {
                background: #5a6fd8;
            }
            
            .btn-secondary {
                background: #6c757d;
            }
            
            .btn-secondary:hover {
                background: #5a6268;
            }
            
            .parameter-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .parameter-table th {
                background: #667eea;
                color: white;
            }
            
            .parameter-table td {
                padding: 10px;
            }
            
            .status-badge {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
            }
            
            .badge-ready {
                background: #d4edda;
                color: #155724;
            }
            
            .footer {
                background: #f8f9fa;
                border-top: 1px solid #e9ecef;
                padding: 20px 30px;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔍 NetSuite Saved Search API</h1>
                <p>Execute saved searches via RestLet with OAuth authentication</p>
            </div>
            
            <div class="content">
                <!-- Endpoint Status -->
                <section>
                    <h2>📊 Endpoint Status</h2>
                    <div class="success-box">
                        <p><span class="status-badge badge-ready">✓ READY</span></p>
                        <p>This API endpoint is fully functional and ready to receive saved search requests.</p>
                    </div>
                </section>
                
                <!-- Quick Start -->
                <section>
                    <h2>🚀 Quick Start</h2>
                    
                    <h3>Basic cURL Example:</h3>
                    <div class="code-label">POST Request</div>
                    <pre><code>curl --location 'http://localhost:8080/netsuite-saved-search-api.php' \
  --header 'Content-Type: application/json' \
  --data '{
    "scriptID": "customscript_my_restlet",
    "searchID": "my_saved_search"
  }'</code></pre>
                    
                    <h3>Using Postman:</h3>
                    <ol>
                        <li>Set method to <strong>POST</strong></li>
                        <li>Enter URL: <code><?php echo "http://{$_SERVER['HTTP_HOST']}/netsuite-saved-search-api.php"; ?></code></li>
                        <li>Go to <strong>Headers</strong> tab and add: <code>Content-Type: application/json</code></li>
                        <li>Go to <strong>Body</strong> tab, select <strong>raw</strong>, choose <strong>JSON</strong></li>
                        <li>Enter the JSON payload (see examples below)</li>
                        <li>Click <strong>Send</strong></li>
                    </ol>
                </section>
                
                <!-- Authentication -->
                <section>
                    <h2>🔐 Authentication</h2>
                    <p>This API endpoint requires authentication using one of two methods:</p>
                    
                    <h3>Method 1: Session Authentication (Web Interface Users)</h3>
                    <p>If you're logged into the web interface, your session cookie is automatically used.</p>
                    <div class="success-box">
                        <p><strong>For web users:</strong> Simply log in and the API will recognize your session.</p>
                    </div>
                    
                    <h3>Method 2: API Key Authentication (Programmatic Access)</h3>
                    <p>For external applications or scripts, use an API key in the Authorization header:</p>
                    
                    <div class="code-label">Header Format</div>
                    <pre><code>Authorization: Bearer YOUR_API_KEY_HERE</code></pre>
                    
                    <h3>Complete cURL Example with API Key:</h3>
                    <div class="code-label">POST Request with Authentication</div>
                    <pre><code>curl --location 'http://localhost:8080/netsuite-saved-search-api.php' \
  --header 'Authorization: Bearer YOUR_API_KEY_HERE' \
  --header 'Content-Type: application/json' \
  --data '{
    "scriptID": "customscript_my_restlet",
    "searchID": "my_saved_search"
  }'</code></pre>
                    
                    <h3>Postman with API Key:</h3>
                    <ol>
                        <li>Set method to <strong>POST</strong></li>
                        <li>Go to <strong>Headers</strong> tab</li>
                        <li>Add header: <code>Authorization: Bearer YOUR_API_KEY_HERE</code></li>
                        <li>Add header: <code>Content-Type: application/json</code></li>
                        <li>Go to <strong>Body</strong> tab, select <strong>raw</strong>, choose <strong>JSON</strong></li>
                        <li>Enter the JSON payload</li>
                    </ol>
                    
                    <h3>Configuring API Keys</h3>
                    <p>Add API keys to your <code>.env</code> file:</p>
                    <div class="code-label">.env Configuration</div>
                    <pre><code>SAVED_SEARCH_API_KEYS=your-first-key-here,your-second-key-here,third-key-here</code></pre>
                    
                    <div class="warning-box">
                        <p><strong>⚠️ Security:</strong> Keep API keys secret. Regenerate them if compromised. Use different keys for different applications.</p>
                    </div>
                </section>
                
                <!-- Request Format -->
                <section>
                    <h2>📝 Request Format</h2>
                    
                    <h3>Endpoint URL:</h3>
                    <pre><code>POST <?php echo "http://{$_SERVER['HTTP_HOST']}/netsuite-saved-search-api.php"; ?></code></pre>
                    
                    <h3>Required Headers:</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Header</th>
                                <th>Value</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>Content-Type</code></td>
                                <td><code>application/json</code></td>
                                <td>Indicates the request body is JSON</td>
                            </tr>
                            <tr>
                                <td><code>Authorization</code></td>
                                <td><code>Bearer YOUR_API_KEY</code></td>
                                <td>Required if not using session auth (optional for session users)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h3>Request Body Parameters:</h3>
                    <table class="parameter-table">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>scriptID</code></td>
                                <td>string</td>
                                <td>Yes</td>
                                <td>The NetSuite RestLet script ID (e.g., "customscript_my_restlet")</td>
                            </tr>
                            <tr>
                                <td><code>searchID</code></td>
                                <td>string</td>
                                <td>Yes</td>
                                <td>The NetSuite saved search ID to execute (e.g., "my_saved_search")</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h3>Example Requests:</h3>
                    
                    <h4>Example 1: Execute Customer Search</h4>
                    <div class="code-label">Request Body</div>
                    <pre><code>{
  "scriptID": "customscript_customer_search",
  "searchID": "custrec_search"
}</code></pre>
                    
                    <h4>Example 2: Execute Sales Order Search</h4>
                    <div class="code-label">Request Body</div>
                    <pre><code>{
  "scriptID": "customscript_so_search",
  "searchID": "salesorder_search"
}</code></pre>
                </section>
                
                <!-- Response Format -->
                <section>
                    <h2>✅ Response Format</h2>
                    
                    <h3>Success Response (HTTP 200):</h3>
                    <div class="code-label">Response Body</div>
                    <pre><code>{
  "success": true,
  "data": {
    "success": true,
    "data": [
      {
        "id": "123",
        "name": "Customer Name",
        "email": "customer@example.com"
      }
    ],
    "status_code": 200
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}</code></pre>
                    
                    <h3>Error Response (HTTP 400):</h3>
                    <div class="code-label">Response Body</div>
                    <pre><code>{
  "success": false,
  "error": "Missing required parameters: scriptID and searchID",
  "timestamp": "2024-01-15T10:30:00+00:00"
}</code></pre>
                    
                    <h3>Error Response (HTTP 401 - Unauthorized):</h3>
                    <div class="code-label">Response Body</div>
                    <pre><code>{
  "success": false,
  "error": "Authentication required. Use session login or API key via Authorization header",
  "timestamp": "2024-01-15T10:30:00+00:00"
}</code></pre>
                    
                    <h3>Error Response (HTTP 500):</h3>
                    <div class="code-label">Response Body</div>
                    <pre><code>{
  "success": false,
  "error": "Internal server error",
  "details": "NetSuite authentication failed",
  "timestamp": "2024-01-15T10:30:00+00:00"
}</code></pre>
                </section>
                
                <!-- HTTP Status Codes -->
                <section>
                    <h2>🔢 HTTP Status Codes</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Meaning</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>200</strong></td>
                                <td>OK</td>
                                <td>Request successful, search executed and results returned</td>
                            </tr>
                            <tr>
                                <td><strong>400</strong></td>
                                <td>Bad Request</td>
                                <td>Missing or invalid parameters (scriptID, searchID, JSON format)</td>
                            </tr>
                            <tr>
                                <td><strong>401</strong></td>
                                <td>Unauthorized</td>
                                <td>Request authentication failed - no valid session or API key provided</td>
                            </tr>
                            <tr>
                                <td><strong>500</strong></td>
                                <td>Server Error</td>
                                <td>Internal server error, OAuth failure with NetSuite, or unexpected exception</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                
                <!-- Important Notes -->
                <section>
                    <h2>⚠️ Important Notes</h2>
                    
                    <div class="info-box">
                        <h3>OAuth Authentication</h3>
                        <p>This endpoint automatically signs all requests with OAuth 1.0 using credentials from <code>config/credentials.php</code>. Make sure your NetSuite credentials are properly configured.</p>
                    </div>
                    
                    <div class="warning-box">
                        <h3>Parameter Validation</h3>
                        <p>Both <code>scriptID</code> and <code>searchID</code> must contain only alphanumeric characters and underscores, and be no longer than 50 characters. This is for security purposes.</p>
                    </div>
                    
                    <div class="info-box">
                        <h3>RestLet Script Requirements</h3>
                        <p>Your NetSuite RestLet script should:</p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>Accept a POST request with a JSON body containing <code>searchID</code></li>
                            <li>Execute the saved search using the provided ID</li>
                            <li>Return results as JSON</li>
                        </ul>
                    </div>
                    
                    <div class="warning-box">
                        <h3>Rate Limiting & Performance</h3>
                        <p>Consider the following:</p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>NetSuite API has rate limits (10 requests per second)</li>
                            <li>Large search results may timeout (60-second limit)</li>
                            <li>Consider implementing caching for frequently executed searches</li>
                        </ul>
                    </div>
                </section>
                
                <!-- Troubleshooting -->
                <section>
                    <h2>🔧 Troubleshooting</h2>
                    
                    <h3>Common Issues:</h3>
                    
                    <h4>Error: "Missing required parameters"</h4>
                    <p>Make sure your request body contains both <code>scriptID</code> and <code>searchID</code> fields in valid JSON format.</p>
                    
                    <h4>Error: "Invalid parameter format"</h4>
                    <p>Ensure that <code>scriptID</code> and <code>searchID</code> only contain alphanumeric characters and underscores (no spaces, hyphens, or special characters).</p>
                    
                    <h4>Error: "NetSuite authentication failed"</h4>
                    <p>Check that your NetSuite credentials in <code>config/credentials.php</code> are correct and up to date.</p>
                    
                    <h4>Error: "RestLet returned status 404"</h4>
                    <p>Verify that the <code>scriptID</code> and <code>deploy=1</code> parameter are correct in your NetSuite account.</p>
                    
                    <h3>Enable Debug Logging:</h3>
                    <p>Check the application logs for detailed error information:</p>
                    <pre><code>logs/app.log</code></pre>
                </section>
            </div>
            
            <div class="footer">
                <p>NetSuite Saved Search API | For support, check logs or review API documentation</p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>