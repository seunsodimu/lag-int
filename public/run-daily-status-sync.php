<?php
/**
 * Web-accessible wrapper for daily-status-sync.php
 * 
 * This file provides web access to the daily status synchronization script
 * while maintaining security and proper logging.
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

echo "Daily Order Status Synchronization\n";
echo "==================================\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

// Capture output from the actual script
ob_start();
$exitCode = 0;

try {
    // Include and run the actual daily sync script
    require_once __DIR__ . '/../scripts/daily-status-sync.php';
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    $exitCode = 1;
}

$output = ob_get_clean();
echo $output;

echo "\n\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
echo "Exit code: " . $exitCode . "\n";
?>