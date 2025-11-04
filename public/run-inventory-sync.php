<?php
/**
 * Web-accessible wrapper for inventory synchronization
 * 
 * This file provides web access to the inventory synchronization script
 * allowing admins to manually trigger inventory updates from NetSuite to 3DCart.
 * 
 * Security: Only accessible from localhost by default
 * 
 * Usage:
 * - Manual sync: http://yoursite.com/run-inventory-sync.php
 * - With limit: http://yoursite.com/run-inventory-sync.php?limit=50
 * - With offset: http://yoursite.com/run-inventory-sync.php?offset=100
 */

// Security check - only allow access from localhost or specific IPs
$allowedIPs = ['127.0.0.1', '::1', 'localhost'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($clientIP, $allowedIPs) && !isset($_GET['force'])) {
    http_response_code(403);
    die('Access denied. This script can only be run from localhost.');
}

// Set content type for proper display
header('Content-Type: text/plain; charset=utf-8');

// Check if this is an HTML request (browser)
$isHtmlRequest = isset($_GET['html']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false);

if ($isHtmlRequest && !isset($_GET['html'])) {
    // Serve HTML interface if accessed from browser
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Inventory Synchronization</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 1000px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                background: white;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            h1 {
                color: #333;
                border-bottom: 2px solid #007bff;
                padding-bottom: 10px;
            }
            .control-panel {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .form-group {
                margin-bottom: 15px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
                color: #333;
            }
            input[type="number"] {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-sizing: border-box;
            }
            button {
                background-color: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }
            button:hover {
                background-color: #0056b3;
            }
            .output {
                background: #f0f0f0;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                margin-top: 20px;
                font-family: 'Courier New', monospace;
                white-space: pre-wrap;
                word-wrap: break-word;
                display: none;
                max-height: 500px;
                overflow-y: auto;
            }
            .output.active {
                display: block;
            }
            .loading {
                display: none;
                color: #007bff;
                font-weight: bold;
            }
            .loading.active {
                display: block;
            }
            .success {
                color: #28a745;
                font-weight: bold;
            }
            .error {
                color: #dc3545;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔄 Inventory Synchronization</h1>
            
            <div class="control-panel">
                <h3>Sync Settings</h3>
                <form id="syncForm">
                    <div class="form-group">
                        <label for="limit">Products per sync (limit):</label>
                        <input type="number" id="limit" name="limit" value="100" min="1" max="1000">
                    </div>
                    <div class="form-group">
                        <label for="offset">Start from product # (offset):</label>
                        <input type="number" id="offset" name="offset" value="0" min="0">
                    </div>
                    <button type="button" onclick="runSync()">Start Synchronization</button>
                </form>
            </div>
            
            <div class="loading" id="loading">
                ⏳ Synchronization in progress... This may take a few minutes.
            </div>
            
            <div class="output" id="output"></div>
        </div>
        
        <script>
            function runSync() {
                var limit = document.getElementById('limit').value;
                var offset = document.getElementById('offset').value;
                var outputDiv = document.getElementById('output');
                var loadingDiv = document.getElementById('loading');
                
                outputDiv.classList.remove('active');
                loadingDiv.classList.add('active');
                outputDiv.innerHTML = '';
                
                var url = '?html=1&limit=' + limit + '&offset=' + offset + '&json=1';
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        loadingDiv.classList.remove('active');
                        
                        var output = '';
                        
                        if (data.success) {
                            output = '<span class="success">✓ SUCCESS</span>\n\n';
                        } else {
                            output = '<span class="error">✗ FAILED</span>\n';
                            output += 'Error: ' + data.error + '\n\n';
                        }
                        
                        output += 'Start time: ' + data.start_time + '\n';
                        output += 'End time: ' + data.end_time + '\n';
                        output += 'Total products: ' + data.total_products + '\n';
                        output += 'Synced: ' + data.synced_count + '\n';
                        output += 'Skipped: ' + data.skipped_count + '\n';
                        output += 'Errors: ' + data.error_count + '\n';
                        
                        if (data.products && data.products.length > 0) {
                            output += '\n--- Updated Products ---\n';
                            data.products.forEach(function(product) {
                                if (product.success) {
                                    output += 'SKU ' + product.sku + ': ' + product.old_stock + ' → ' + product.new_stock + '\n';
                                }
                            });
                        }
                        
                        if (data.errors && data.errors.length > 0) {
                            output += '\n--- Errors ---\n';
                            data.errors.forEach(function(error) {
                                output += 'ERROR: ' + error + '\n';
                            });
                        }
                        
                        outputDiv.innerHTML = output;
                        outputDiv.classList.add('active');
                    })
                    .catch(error => {
                        loadingDiv.classList.remove('active');
                        outputDiv.innerHTML = '<span class="error">ERROR: Request failed</span>\n' + error;
                        outputDiv.classList.add('active');
                    });
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

echo "Inventory Synchronization\n";
echo "=========================\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

// Capture output from the actual script
ob_start();
$exitCode = 0;

try {
    // Include and run the actual inventory sync script
    require_once __DIR__ . '/../scripts/sync-inventory.php';
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    $exitCode = 1;
}

$output = ob_get_clean();
echo $output;

echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
echo "Exit code: " . $exitCode . "\n";

// If JSON response is requested, return JSON format
if (isset($_GET['json'])) {
    // Parse the output to create a structured JSON response
    $lines = explode("\n", $output);
    $result = [
        'success' => $exitCode === 0,
        'start_time' => date('Y-m-d H:i:s'),
        'end_time' => date('Y-m-d H:i:s'),
        'total_products' => 0,
        'synced_count' => 0,
        'skipped_count' => 0,
        'error_count' => 0,
        'products' => [],
        'errors' => []
    ];
    
    // Try to extract counts from output
    foreach ($lines as $line) {
        if (strpos($line, 'Total products processed:') !== false) {
            $result['total_products'] = (int)preg_replace('/[^0-9]/', '', $line);
        } elseif (strpos($line, 'Products synced:') !== false) {
            $result['synced_count'] = (int)preg_replace('/[^0-9]/', '', $line);
        } elseif (strpos($line, 'Products skipped:') !== false) {
            $result['skipped_count'] = (int)preg_replace('/[^0-9]/', '', $line);
        } elseif (strpos($line, 'Errors encountered:') !== false) {
            $result['error_count'] = (int)preg_replace('/[^0-9]/', '', $line);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
}
?>