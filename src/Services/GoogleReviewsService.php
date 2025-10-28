<?php

namespace Laguna\Integration\Services;

use Exception;
use Laguna\Integration\Utils\Logger;

/**
 * Google Reviews Service
 * Uses Google My Business API v4 with OAuth 2.0
 * Fetches reviews from accounts and locations
 */
class GoogleReviewsService {
    private $clientId;
    private $clientSecret;
    private $accountId;
    private $logger;
    private $tokenCacheDir;
    private const API_BASE = 'https://mybusiness.googleapis.com/v4';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPES = 'https://www.googleapis.com/auth/business.manage';
    private const CACHE_DURATION = 86400; // 24 hours
    
    public function __construct() {
        $this->clientId = $_ENV['GOOGLE_CLIENTID'] ?? null;
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? null;
        $this->accountId = $_ENV['GOOGLE_ACCOUNT_ID'] ?? null;
        $this->logger = Logger::getInstance();
        $this->tokenCacheDir = dirname(dirname(__DIR__)) . '/uploads/google_reviews_cache';
        
        if (!$this->clientId || !$this->clientSecret) {
            throw new Exception('Google OAuth credentials (GOOGLE_CLIENTID, GOOGLE_CLIENT_SECRET) not configured in .env');
        }
        
        if (!$this->accountId) {
            throw new Exception('GOOGLE_ACCOUNT_ID not configured in .env');
        }
        
        // Create cache directory
        if (!is_dir($this->tokenCacheDir)) {
            mkdir($this->tokenCacheDir, 0755, true);
        }
    }
    
    /**
     * Get valid access token (refresh if needed)
     */
    private function getAccessToken() {
        $tokenFile = $this->tokenCacheDir . '/oauth_token.json';
        
        // Load cached token
        if (file_exists($tokenFile)) {
            $tokenData = json_decode(file_get_contents($tokenFile), true);
            
            // Check if token is still valid (with 5 min buffer)
            if (isset($tokenData['expires_at']) && $tokenData['expires_at'] > time() + 300) {
                $this->logger->debug('Using cached OAuth token');
                return $tokenData['access_token'];
            }
            
            // Token expired, try to refresh
            if (isset($tokenData['refresh_token'])) {
                $this->logger->info('Refreshing OAuth token');
                return $this->refreshToken($tokenData['refresh_token']);
            }
        }
        
        throw new Exception('No valid OAuth token found. Please authenticate first.');
    }
    
    /**
     * Refresh OAuth token using refresh token
     */
    private function refreshToken($refreshToken) {
        $tokenData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $response = $this->makeHttpRequest(
            self::TOKEN_URL,
            'POST',
            json_encode($tokenData),
            ['Content-Type: application/json']
        );
        
        $data = json_decode($response, true);
        
        if (!isset($data['access_token'])) {
            throw new Exception('Failed to refresh OAuth token: ' . ($data['error_description'] ?? 'Unknown error'));
        }
        
        // Cache the new token
        $newTokenData = [
            'access_token' => $data['access_token'],
            'refresh_token' => $refreshToken,
            'expires_at' => time() + ($data['expires_in'] ?? 3600)
        ];
        
        file_put_contents(
            $this->tokenCacheDir . '/oauth_token.json',
            json_encode($newTokenData)
        );
        
        $this->logger->info('OAuth token refreshed');
        return $data['access_token'];
    }
    
    /**
     * Store OAuth token after authentication
     */
    public function storeToken($accessToken, $refreshToken, $expiresIn = 3600) {
        $tokenData = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => time() + $expiresIn
        ];
        
        file_put_contents(
            $this->tokenCacheDir . '/oauth_token.json',
            json_encode($tokenData)
        );
        
        $this->logger->info('OAuth token stored');
    }
    
    /**
     * Get all locations for the account
     */
    public function getAllLocations() {
        try {
            $accessToken = $this->getAccessToken();
            
            // Format account ID
            $accountIdFormatted = str_starts_with($this->accountId, 'accounts/') 
                ? $this->accountId 
                : "accounts/{$this->accountId}";
            
            $url = self::API_BASE . "/{$accountIdFormatted}/locations";
            
            $this->logger->info('Fetching locations', [
                'account_id' => $this->accountId,
                'formatted_account_id' => $accountIdFormatted,
                'url' => $url
            ]);
            
            $response = $this->makeApiRequest($url, 'GET', null, $accessToken);
            $data = json_decode($response, true);
            
            if (isset($data['error'])) {
                throw new Exception('Google API Error: ' . $data['error']['message']);
            }
            
            if (!isset($data['locations'])) {
                $this->logger->warning('No locations found in response', [
                    'account_id' => $accountIdFormatted,
                    'response' => $data
                ]);
                return [];
            }
            
            $locations = $data['locations'] ?? [];
            $this->logger->info('Retrieved locations', ['count' => count($locations)]);
            
            return $locations;
        } catch (Exception $e) {
            $this->logger->error('Error fetching locations', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get reviews for a specific location
     */
    public function getLocationReviews($location) {
        try {
            $accessToken = $this->getAccessToken();
            
            // Format account ID
            $accountIdFormatted = str_starts_with($this->accountId, 'accounts/') 
                ? $this->accountId 
                : "accounts/{$this->accountId}";
            
            $locationId = $location['name'] ?? null;
            if (!$locationId) {
                throw new Exception('Location must have a name property');
            }
            
            // Build URL for reviews endpoint
            $url = self::API_BASE . "/{$accountIdFormatted}/{$locationId}/reviews";
            
            $this->logger->info('Fetching reviews for location', [
                'location_id' => $locationId,
                'url' => $url
            ]);
            
            $response = $this->makeApiRequest($url, 'GET', null, $accessToken);
            $data = json_decode($response, true);
            
            if (isset($data['error'])) {
                throw new Exception('Google API Error: ' . $data['error']['message']);
            }
            
            $reviews = $data['reviews'] ?? [];
            
            // Transform reviews to standard format
            $transformedReviews = array_map(function($review) use ($location) {
                return $this->transformReview($review, $location['displayName'] ?? 'Unknown Location');
            }, $reviews);
            
            $this->logger->info('Retrieved reviews for location', [
                'location' => $locationId,
                'count' => count($transformedReviews)
            ]);
            
            return $transformedReviews;
            
        } catch (Exception $e) {
            $this->logger->error('Error fetching location reviews', [
                'location' => $location['name'] ?? 'Unknown',
                'error' => $e->getMessage()
            ]);
            // Return empty array instead of throwing to allow other locations to be processed
            return [];
        }
    }
    
    /**
     * Get all reviews with analytics using batch endpoint (more efficient)
     */
    public function getAllReviewsWithAnalytics() {
        try {
            $accessToken = $this->getAccessToken();
            $locations = $this->getAllLocations();
            
            if (empty($locations)) {
                $this->logger->warning('No locations found for batch review fetch');
                return [
                    'allReviews' => [],
                    'byLocation' => [],
                    'totalReviews' => 0,
                    'averageRating' => 0,
                    'timeline' => []
                ];
            }
            
            // Format account ID
            $accountIdFormatted = str_starts_with($this->accountId, 'accounts/') 
                ? $this->accountId 
                : "accounts/{$this->accountId}";
            
            // Build location names array for batch request
            $locationNames = array_map(function($location) {
                return $location['name'];
            }, $locations);
            
            // Call batch endpoint
            $url = self::API_BASE . "/{$accountIdFormatted}/locations:batchGetReviews";
            
            $payload = [
                'locationNames' => $locationNames,
                'pageSize' => 100,
                'orderBy' => 'updateTime desc',
                'ignoreRatingOnlyReviews' => false
            ];
            
            $this->logger->info('Fetching reviews from batch endpoint', [
                'account_id' => $accountIdFormatted,
                'location_count' => count($locationNames),
                'url' => $url
            ]);
            
            $response = $this->makeApiRequest($url, 'POST', json_encode($payload), $accessToken);
            $data = json_decode($response, true);
            
            if (isset($data['error'])) {
                throw new Exception('Google API Error: ' . $data['error']['message']);
            }
            
            // Process batch results
            $allReviews = [];
            $byLocation = [];
            
            if (isset($data['locationReviews'])) {
                foreach ($data['locationReviews'] as $locationReview) {
                    $locationName = $locationReview['name'] ?? null;
                    if (!$locationName) continue;
                    
                    // Find the location display name
                    $displayName = 'Unknown Location';
                    foreach ($locations as $location) {
                        if ($location['name'] === $locationName) {
                            $displayName = $location['displayName'] ?? 'Unknown Location';
                            break;
                        }
                    }
                    
                    // Transform and aggregate reviews
                    if (isset($locationReview['reviews']) && is_array($locationReview['reviews'])) {
                        $reviews = array_map(function($review) use ($displayName) {
                            return $this->transformReview($review, $displayName);
                        }, $locationReview['reviews']);
                        
                        $allReviews = array_merge($allReviews, $reviews);
                        
                        if (!empty($reviews)) {
                            $ratings = array_map(function($r) { return $r['rating'] ?? 0; }, $reviews);
                            $byLocation[$displayName] = [
                                'reviewCount' => count($reviews),
                                'averageRating' => count($ratings) > 0 ? array_sum($ratings) / count($ratings) : 0,
                                'reviews' => $reviews
                            ];
                        }
                    }
                }
            }
            
            $this->logger->info('Batch review fetch completed', [
                'total_reviews' => count($allReviews),
                'locations_with_reviews' => count($byLocation)
            ]);
            
            return [
                'allReviews' => $this->sortReviewsByDate($allReviews),
                'byLocation' => $byLocation,
                'totalReviews' => count($allReviews),
                'averageRating' => $this->calculateOverallAverageRating($allReviews),
                'timeline' => $this->buildTimeline($allReviews)
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Error fetching all reviews', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Transform API review to standard format
     */
    private function transformReview($review, $locationName) {
        $time = isset($review['reviewReply']['updateTime']) 
            ? strtotime($review['reviewReply']['updateTime']) 
            : (isset($review['createTime']) ? strtotime($review['createTime']) : 0);
        
        return [
            'author' => $review['reviewer']['displayName'] ?? 'Anonymous',
            'rating' => $review['reviewRating'] ?? 0,
            'text' => $review['comment'] ?? '',
            'date' => $time > 0 ? date('Y-m-d', $time) : 'Unknown',
            'time' => $time,
            'location' => $locationName,
            'profile_photo_url' => $review['reviewer']['profilePhotoUrl'] ?? '',
            'reply_text' => $review['reviewReply']['comment'] ?? null
        ];
    }
    
    /**
     * Sort reviews by date (newest first)
     */
    private function sortReviewsByDate($reviews) {
        usort($reviews, function($a, $b) {
            return ($b['time'] ?? 0) - ($a['time'] ?? 0);
        });
        return $reviews;
    }
    
    /**
     * Calculate overall average rating
     */
    private function calculateOverallAverageRating($reviews) {
        if (empty($reviews)) return 0;
        $ratings = array_map(function($r) { return $r['rating'] ?? 0; }, $reviews);
        return array_sum($ratings) / count($ratings);
    }
    
    /**
     * Build timeline of reviews
     */
    private function buildTimeline($reviews) {
        $timeline = [];
        foreach ($reviews as $review) {
            $quarter = 'Q' . ceil(intval(date('m', $review['time'])) / 3);
            $year = date('Y', $review['time']);
            $key = "$year-$quarter";
            
            if (!isset($timeline[$key])) {
                $timeline[$key] = ['count' => 0, 'averageRating' => 0, 'totalRating' => 0];
            }
            
            $timeline[$key]['count']++;
            $timeline[$key]['totalRating'] += $review['rating'] ?? 0;
        }
        
        // Calculate averages
        foreach ($timeline as &$entry) {
            $entry['averageRating'] = $entry['count'] > 0 ? $entry['totalRating'] / $entry['count'] : 0;
        }
        
        return $timeline;
    }
    
    /**
     * Get location comparison
     */
    public function getLocationComparison() {
        $reviews = $this->getAllReviewsWithAnalytics();
        return $reviews['byLocation'] ?? [];
    }
    
    /**
     * Get rating trend within date range
     */
    public function getRatingTrend($startDate = null, $endDate = null) {
        $reviews = $this->getAllReviewsWithAnalytics();
        $timeline = $reviews['timeline'] ?? [];
        
        // Filter by date if provided
        if ($startDate || $endDate) {
            $filtered = [];
            foreach ($timeline as $key => $data) {
                if ($startDate && $key < $startDate) continue;
                if ($endDate && $key > $endDate) continue;
                $filtered[$key] = $data;
            }
            return $filtered;
        }
        
        return $timeline;
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        try {
            $files = glob($this->tokenCacheDir . '/*.json');
            foreach ($files as $file) {
                if (basename($file) !== 'oauth_token.json') {
                    unlink($file);
                }
            }
            $this->logger->info('Cache cleared');
            return true;
        } catch (Exception $e) {
            $this->logger->error('Error clearing cache', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Export reviews to spreadsheet
     */
    public function exportToSpreadsheet($reviews) {
        try {
            require_once __DIR__ . '/../../vendor/autoload.php';
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set headers
            $headers = ['Date', 'Location', 'Author', 'Rating', 'Review Text', 'Reply'];
            foreach ($headers as $col => $header) {
                $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
            }
            
            // Add data
            $row = 2;
            foreach ($reviews as $review) {
                $sheet->setCellValueByColumnAndRow(1, $row, $review['date'] ?? '');
                $sheet->setCellValueByColumnAndRow(2, $row, $review['location'] ?? '');
                $sheet->setCellValueByColumnAndRow(3, $row, $review['author'] ?? '');
                $sheet->setCellValueByColumnAndRow(4, $row, $review['rating'] ?? '');
                $sheet->setCellValueByColumnAndRow(5, $row, $review['text'] ?? '');
                $sheet->setCellValueByColumnAndRow(6, $row, $review['reply_text'] ?? '');
                $row++;
            }
            
            // Auto-size columns
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Generate file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = 'reviews_' . date('Y-m-d_His') . '.xlsx';
            $filepath = $this->tokenCacheDir . '/' . $filename;
            $writer->save($filepath);
            
            $this->logger->info('Reviews exported to spreadsheet', ['filename' => $filename]);
            
            return $filepath;
            
        } catch (Exception $e) {
            $this->logger->error('Error exporting to spreadsheet', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Make HTTP request using cURL (more reliable than file_get_contents)
     */
    private function makeHttpRequest($url, $method = 'GET', $body = null, $headers = []) {
        // Try cURL first (more reliable)
        if (function_exists('curl_init')) {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            
            // Add headers
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            
            // Add body for POST/PUT
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            
            // SSL verification (disabled for development - XAMPP CA bundle issue)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($response === false) {
                throw new Exception('HTTP request failed (cURL): ' . $curlError);
            }
            
            return $response;
        }
        
        // Fallback to file_get_contents if cURL not available
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new Exception('HTTP request failed (file_get_contents): ' . ($error['message'] ?? 'Unknown error'));
        }
        
        return $response;
    }
    
    /**
     * Make authenticated API request
     */
    private function makeApiRequest($url, $method = 'GET', $body = null, $accessToken) {
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
        
        return $this->makeHttpRequest($url, $method, $body, $headers);
    }
}
?>