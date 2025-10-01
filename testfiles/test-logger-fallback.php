<?php
/**
 * Test script to verify logger fallback functionality
 * This script tests the enhanced logger's ability to handle permission issues
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Utils\Logger;

echo "Testing Logger Fallback Functionality\n";
echo "=====================================\n\n";

try {
    // Get logger instance
    $logger = Logger::getInstance();
    
    // Test logging
    $logger->info("Test log message from fallback test script", [
        'test_type' => 'fallback_verification',
        'timestamp' => date('Y-m-d H:i:s'),
        'server_info' => [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'temp_dir' => sys_get_temp_dir(),
            'current_user' => get_current_user(),
            'script_path' => __FILE__
        ]
    ]);
    
    echo "✅ Logger test successful!\n";
    echo "Log message written successfully.\n\n";
    
    // Show potential log locations
    echo "Potential log file locations:\n";
    echo "1. Primary: " . __DIR__ . '/../logs/app-' . date('Y-m-d') . '.log' . "\n";
    echo "2. Fallback 1: " . sys_get_temp_dir() . '/lag-int-' . date('Y-m-d') . '.log' . "\n";
    echo "3. Fallback 2: " . __DIR__ . '/../app-' . date('Y-m-d') . '.log' . "\n";
    echo "4. Fallback 3: /tmp/lag-int-" . date('Y-m-d') . ".log\n\n";
    
    // Check which log files exist
    $logPaths = [
        'Primary' => __DIR__ . '/../logs/app-' . date('Y-m-d') . '.log',
        'Fallback 1' => sys_get_temp_dir() . '/lag-int-' . date('Y-m-d') . '.log',
        'Fallback 2' => __DIR__ . '/../app-' . date('Y-m-d') . '.log',
        'Fallback 3' => '/tmp/lag-int-' . date('Y-m-d') . '.log'
    ];
    
    echo "Existing log files:\n";
    foreach ($logPaths as $name => $path) {
        if (file_exists($path)) {
            $size = filesize($path);
            $modified = date('Y-m-d H:i:s', filemtime($path));
            echo "✅ $name: $path (Size: {$size} bytes, Modified: $modified)\n";
        } else {
            echo "❌ $name: $path (Not found)\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Logger test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";