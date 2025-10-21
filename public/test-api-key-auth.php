<?php
/**
 * Test API Key Authentication
 * 
 * Tests the API key authentication to ensure it's working correctly
 * Access: http://localhost:8080/test-api-key-auth.php?key=YOUR_KEY
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\SavedSearchController;
use Laguna\Integration\Utils\Logger;

// Load environment
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

date_default_timezone_set('America/New_York');
$logger = Logger::getInstance();

// Get the API key from query parameter or environment
$testKey = $_GET['key'] ?? null;
if (!$testKey) {
    // Try to get from environment
    $testKey = getenv('SAVED_SEARCH_API_KEYS') ?: ($_ENV['SAVED_SEARCH_API_KEYS'] ?? null);
    if ($testKey && strpos($testKey, ',') !== false) {
        $keys = explode(',', $testKey);
        $testKey = trim($keys[0]);
    } else {
        $testKey = trim($testKey ?? '');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Key Auth Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 1.8em;
        }
        .content {
            padding: 30px;
        }
        .section {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
        }
        .section-header {
            background: #f5f5f5;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
            color: #333;
        }
        .section-content {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group:last-child {
            margin-bottom: 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
        }
        button:hover {
            background: #5568d3;
        }
        .status-box {
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
        }
        .status-box.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .status-box.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .status-box.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            overflow-x: auto;
            margin-top: 10px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .copy-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em;
            margin-top: 5px;
        }
        .copy-btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 API Key Authentication Test</h1>
        </div>
        
        <div class="content">
            <div class="section">
                <div class="section-header">Test API Key with Bearer Token</div>
                <div class="section-content">
                    <form method="post">
                        <div class="form-group">
                            <label for="api_key">API Key:</label>
                            <input type="text" id="api_key" name="api_key" value="<?php echo htmlspecialchars($testKey); ?>" placeholder="Your API key here">
                        </div>
                        
                        <div class="form-group">
                            <label for="script_id">Script ID:</label>
                            <input type="text" id="script_id" name="script_id" value="customscript_test" placeholder="customscript_test">
                        </div>
                        
                        <div class="form-group">
                            <label for="search_id">Search ID:</label>
                            <input type="text" id="search_id" name="search_id" value="test_search" placeholder="test_search">
                        </div>
                        
                        <button type="submit">Test Authentication</button>
                    </form>
                    
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $apiKey = $_POST['api_key'] ?? '';
                        $scriptId = $_POST['script_id'] ?? '';
                        $searchId = $_POST['search_id'] ?? '';
                        
                        if (!$apiKey) {
                            echo '<div class="status-box error">❌ API key is required</div>';
                        } else if (!$scriptId || !$searchId) {
                            echo '<div class="status-box error">❌ Script ID and Search ID are required</div>';
                        } else {
                            // Make a test request with the API key
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/netsuite-saved-search-api.php');
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Authorization: Bearer ' . $apiKey,
                                'Content-Type: application/json'
                            ]);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                                'scriptID' => $scriptId,
                                'searchID' => $searchId
                            ]));
                            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                            
                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $error = curl_error($ch);
                            curl_close($ch);
                            
                            echo '<div class="status-box ' . ($httpCode === 200 ? 'success' : 'error') . '">';
                            echo '<strong>' . ($httpCode === 200 ? '✓ Success!' : '✗ Failed') . '</strong><br>';
                            echo 'HTTP Status: ' . $httpCode . '<br>';
                            
                            if ($httpCode === 401) {
                                echo 'Authentication FAILED - Check your API key';
                            } elseif ($httpCode === 400) {
                                echo 'Bad Request - Check your parameters';
                            } elseif ($httpCode === 200) {
                                echo 'Authentication PASSED!';
                            } else {
                                echo 'Unexpected status code';
                            }
                            echo '</div>';
                            
                            if ($error) {
                                echo '<div class="status-box error">cURL Error: ' . htmlspecialchars($error) . '</div>';
                            }
                            
                            if ($response) {
                                echo '<div class="status-box info"><strong>Response:</strong></div>';
                                echo '<div class="code-block">' . htmlspecialchars($response) . '</div>';
                            }
                        }
                    } ?>
                </div>
            </div>
            
            <div class="section">
                <div class="section-header">cURL Command</div>
                <div class="section-content">
                    <div class="code-block" id="curl-cmd">curl --location 'http://localhost:8080/netsuite-saved-search-api.php' \
  --header 'Authorization: Bearer <?php echo htmlspecialchars($testKey); ?>' \
  --header 'Content-Type: application/json' \
  --data '{
  "scriptID": "customscript_test",
  "searchID": "test_search"
}'</div>
                    <button class="copy-btn" onclick="copyToClipboard('curl-cmd')">📋 Copy to Clipboard</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function copyToClipboard(elementId) {
            const elem = document.getElementById(elementId);
            const text = elem.innerText;
            navigator.clipboard.writeText(text).then(() => {
                const btn = event.target;
                btn.innerText = '✓ Copied!';
                setTimeout(() => { btn.innerText = '📋 Copy to Clipboard'; }, 2000);
            });
        }
    </script>
</body>
</html>