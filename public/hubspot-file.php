<?php
/**
 * HubSpot File Retrieval Endpoint
 * 
 * Retrieves files from HubSpot's private file storage using the signed-url-redirect link.
 * This is an unauthenticated endpoint that accepts a HubSpot file URL and returns the file.
 * 
 * Usage:
 * GET /hubspot-file.php?url=https://api-na1.hubspot.com/form-integrations/v1/uploaded-files/signed-url-redirect/204519916410?portalId=...
 */

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize logger
$logger = Logger::getInstance();

// Set JSON response headers by default
header('Content-Type: application/json');

try {
    // Get the HubSpot file URL from query parameter
    $hubspotUrl = $_GET['url'] ?? null;
    
    if (!$hubspotUrl) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required parameter: url'
        ]);
        $logger->warning('HubSpot file endpoint called without URL parameter');
        exit;
    }
    
    // Validate URL format
    if (!filter_var($hubspotUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid URL format'
        ]);
        $logger->warning('HubSpot file endpoint called with invalid URL', ['url' => $hubspotUrl]);
        exit;
    }
    
    // Extract file ID from URL path
    // URL format: https://api-na1.hubspot.com/form-integrations/v1/uploaded-files/signed-url-redirect/204519916410?...
    if (!preg_match('/signed-url-redirect\/(\d+)/', $hubspotUrl, $matches)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Could not extract file ID from URL'
        ]);
        $logger->warning('HubSpot file endpoint - invalid URL format', ['url' => $hubspotUrl]);
        exit;
    }
    
    $fileId = $matches[1];
    
    $logger->info('HubSpot file endpoint - extracting file', [
        'file_id' => $fileId,
        'source_url' => substr($hubspotUrl, 0, 100) // Log first 100 chars
    ]);
    
    // Load credentials
    $credentials = require __DIR__ . '/../config/credentials.php';
    $bearerToken = $credentials['hubspot']['access_token'] ?? null;
    
    if (!$bearerToken) {
        throw new \Exception('HubSpot bearer token not configured');
    }
    
    // Initialize Guzzle client
    $client = new Client([
        'timeout' => 30,
    ]);
    
    // Step 1: Get signed URL from HubSpot API
    $apiUrl = "https://api.hubapi.com/files/v3/files/{$fileId}/signed-url";
    
    $logger->info('HubSpot file endpoint - requesting signed URL', [
        'api_url' => $apiUrl,
        'file_id' => $fileId
    ]);
    
    $apiResponse = $client->request('GET', $apiUrl, [
        'headers' => [
            'Authorization' => "Bearer {$bearerToken}",
            'Accept' => 'application/json'
        ]
    ]);
    
    $apiResponseBody = json_decode($apiResponse->getBody()->getContents(), true);
    
    if (!isset($apiResponseBody['url'])) {
        throw new \Exception('No signed URL returned from HubSpot API');
    }
    
    $signedUrl = $apiResponseBody['url'];
    $fileName = $apiResponseBody['name'] ?? 'file';
    $fileExtension = $apiResponseBody['extension'] ?? '';
    $fileType = $apiResponseBody['type'] ?? 'DOCUMENT';
    $fileSize = $apiResponseBody['size'] ?? 0;
    
    $logger->info('HubSpot file endpoint - got signed URL', [
        'file_id' => $fileId,
        'file_name' => $fileName,
        'file_size' => $fileSize
    ]);
    
    // Step 2: Download the actual file from the signed URL
    $fileResponse = $client->request('GET', $signedUrl, [
        'stream' => true,
        'headers' => [
            'Accept' => '*/*'
        ]
    ]);
    
    // Get file content
    $fileContent = $fileResponse->getBody()->getContents();
    
    if (empty($fileContent)) {
        throw new \Exception('Downloaded file is empty');
    }
    
    $logger->info('HubSpot file endpoint - file downloaded successfully', [
        'file_id' => $fileId,
        'file_name' => $fileName,
        'downloaded_size' => strlen($fileContent)
    ]);
    
    // Determine MIME type based on extension
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
    ];
    
    $mimeType = $mimeTypes[strtolower($fileExtension)] ?? 'application/octet-stream';
    
    // Send file with appropriate headers (inline display instead of download)
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . basename($fileName . '.' . $fileExtension) . '"');
    header('Content-Length: ' . strlen($fileContent));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $fileContent;
    
    $logger->info('HubSpot file endpoint - file sent successfully', [
        'file_id' => $fileId,
        'file_name' => $fileName,
        'mime_type' => $mimeType
    ]);
    
} catch (RequestException $e) {
    http_response_code($e->getResponse() ? $e->getResponse()->getStatusCode() : 500);
    
    $errorMessage = 'Request error: ' . $e->getMessage();
    $responseBody = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
    
    $logger->error($errorMessage, [
        'exception' => get_class($e),
        'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null,
        'response_body' => $responseBody
    ]);
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve file from HubSpot: ' . $e->getMessage(),
        'details' => $responseBody
    ]);
    
} catch (\Exception $e) {
    http_response_code(500);
    
    $logger->error('HubSpot file endpoint error: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
