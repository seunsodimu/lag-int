<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

try {
    $apiKey = $_ENV['GOOGLE_API_KEY'] ?? null;
    $placeIds = $_ENV['GOOGLE_PLACE_IDS'] ?? null;
    
    if (!$apiKey) {
        throw new Exception('GOOGLE_API_KEY not configured in .env');
    }
    
    if (!$placeIds) {
        throw new Exception('GOOGLE_PLACE_IDS not configured in .env');
    }
    
    // Parse place IDs
    $ids = array_map('trim', explode(',', $placeIds));
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuration verified',
        'api_key_configured' => !empty($apiKey),
        'api_key_preview' => substr($apiKey, 0, 10) . '...' . substr($apiKey, -10),
        'place_ids_count' => count($ids),
        'place_ids' => $ids,
        'next_step' => 'Try loading http://localhost:8080/lag-int/google-reviews.php'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>