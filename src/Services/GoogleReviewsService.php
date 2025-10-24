<?php

namespace Laguna\Integration\Services;

use Exception;
use Laguna\Integration\Utils\Logger;

/**
 * Google Business Profile Reviews Service
 * Uses OAuth 2.0 to fetch reviews from Google Business Profile API
 * Provides analytics on review ratings over time and location comparison
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
    
    public function __construct() {
        $this->clientId = $_ENV['GOOGLE_CLIENTID'] ?? null;
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? null;
        $this->accountId = $_ENV['GOOGLE_ACCOUNT_ID'] ?? null;
        $this->logger = Logger::getInstance();
        $this->tokenCacheDir = dirname(dirname(__DIR__)) . '/uploads/google_reviews_cache';
        
        if (!$this->clientId || !$this->clientSecret || !$this->accountId) {
            throw new Exception('Google OAuth credentials not configured in .env');
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
        $tokenData = [
            'access_token' => $data['access_token'],
            'refresh_token' => $refreshToken,
            'expires_at' => time() + ($data['expires_in'] ?? 3600)
        ];
        
        file_put_contents(
            $this->tokenCacheDir . '/oauth_token.json',
            json_encode($tokenData)
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
            $url = self::API_BASE . "/accounts/{$this->accountId}/locations";
            
            $this->logger->info('Fetching locations', ['account_id' => $this->accountId]);
            
            $response = $this->makeApiRequest($url, 'GET', null, $accessToken);
            $data = json_decode($response, true);
            
            $locations = $data['locations'] ?? [];
            $this->logger->info('Retrieved locations', ['count' => count($locations)]);
            
            return $locations;
        } catch (Exception $e) {
            $this->logger->error('Error fetching locations', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get all reviews from all locations with analytics
     */
    public function getAllReviewsWithAnalytics() {
        try {
            $locations = $this->getAllLocations();
            $allReviews = [];
            $locationData = [];
            
            foreach ($locations as $location) {
                try {
                    $reviews = $this->getLocationReviews($location);
                    $allReviews = array_merge($allReviews, $reviews);
                    
                    // Calculate location analytics
                    $ratings = array_map(function($r) { return $r['rating']; }, $reviews);
                    $locationData[$location['name']] = [
                        'displayName' => $location['displayName'] ?? 'Unknown',
                        'reviewCount' => count($reviews),
                        'averageRating' => count($ratings) > 0 ? array_sum($ratings) / count($ratings) : 0,
                        'reviews' => $reviews
                    ];
                } catch (Exception $e) {
                    $this->logger->error('Error fetching reviews for location', [
                        'location' => $location['name'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return [
                'allReviews' => $this->sortReviewsByDate($allReviews),
                'byLocation' => $locationData,
                'totalReviews' => count($allReviews),
                'averageRating' => $this->calculateOverallAverageRating($allReviews),
                'timeline' => $this->buildTimeline($allReviews)
            ];
        } catch (Exception $e) {
            $this->logger->error('Error fetching reviews with analytics', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get reviews for a specific location
     */
    private function getLocationReviews($location) {
        $accessToken = $this->getAccessToken();
        $locationName = $location['name']; // Format: accounts/{accountId}/locations/{locationId}
        $url = self::API_BASE . "/{$locationName}/reviews";
        
        $this->logger->debug('Fetching reviews for location', ['location' => $locationName]);
        
        $response = $this->makeApiRequest($url, 'GET', null, $accessToken);
        $data = json_decode($response, true);
        
        $reviews = [];
        $rawReviews = $data['reviews'] ?? [];
        
        foreach ($rawReviews as $review) {
            $reviews[] = $this->transformReview($review, $location);
        }
        
        return $reviews;
    }
    
    /**
     * Transform API review to application format
     */
    private function transformReview($review, $location) {
        $timestamp = strtotime($review['createTime'] ?? 'now');
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($timestamp);
        
        $month = (int)$dateTime->format('m');
        $year = (int)$dateTime->format('Y');
        $quarter = ceil($month / 3);
        
        return [
            'id' => $review['name'] ?? '',
            'locationName' => $location['displayName'] ?? 'Unknown',
            'rating' => (int)($review['starRating'] ?? 0),
            'reviewer' => $review['reviewer']['displayName'] ?? 'Anonymous',
            'comment' => $review['comment'] ?? '',
            'datePosted' => $dateTime->format('D, M d, Y h:i A'),
            'timestamp' => $timestamp,
            'quarterWithYear' => "Q{$quarter} {$year}",
            'quarter' => "Q{$quarter}",
            'year' => (string)$year,
            'month' => $dateTime->format('M'),
            'monthNum' => (int)$dateTime->format('m'),
            'reply' => $review['reviewReply'] ?? null
        ];
    }
    
    /**
     * Sort reviews by date (newest first)
     */
    private function sortReviewsByDate($reviews) {
        usort($reviews, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        return $reviews;
    }
    
    /**
     * Calculate overall average rating
     */
    private function calculateOverallAverageRating($reviews) {
        if (empty($reviews)) {
            return 0;
        }
        $ratings = array_map(function($r) { return $r['rating']; }, $reviews);
        return round(array_sum($ratings) / count($ratings), 2);
    }
    
    /**
     * Build timeline data for charts
     * Groups reviews by date and calculates daily average rating
     */
    private function buildTimeline($reviews) {
        $timeline = [];
        
        foreach ($reviews as $review) {
            $date = date('Y-m-d', $review['timestamp']);
            
            if (!isset($timeline[$date])) {
                $timeline[$date] = [
                    'date' => $date,
                    'ratings' => [],
                    'count' => 0
                ];
            }
            
            $timeline[$date]['ratings'][] = $review['rating'];
            $timeline[$date]['count']++;
        }
        
        // Calculate averages
        $result = [];
        foreach ($timeline as $data) {
            $result[] = [
                'date' => $data['date'],
                'average' => round(array_sum($data['ratings']) / $data['count'], 2),
                'count' => $data['count']
            ];
        }
        
        // Sort by date
        usort($result, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        return $result;
    }
    
    /**
     * Get location comparison data
     */
    public function getLocationComparison() {
        try {
            $data = $this->getAllReviewsWithAnalytics();
            
            $comparison = [];
            foreach ($data['byLocation'] as $locationName => $stats) {
                $comparison[] = [
                    'location' => $stats['displayName'],
                    'reviewCount' => $stats['reviewCount'],
                    'averageRating' => round($stats['averageRating'], 2),
                    'ratingPercentage' => round(($stats['averageRating'] / 5) * 100, 1)
                ];
            }
            
            return $comparison;
        } catch (Exception $e) {
            $this->logger->error('Error getting location comparison', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get rating trend for a specific period
     */
    public function getRatingTrend($startDate = null, $endDate = null) {
        try {
            $data = $this->getAllReviewsWithAnalytics();
            $timeline = $data['timeline'];
            
            if ($startDate) {
                $startDate = strtotime($startDate);
            }
            if ($endDate) {
                $endDate = strtotime($endDate);
            }
            
            $filtered = array_filter($timeline, function($item) use ($startDate, $endDate) {
                $timestamp = strtotime($item['date']);
                
                if ($startDate && $timestamp < $startDate) {
                    return false;
                }
                if ($endDate && $timestamp > $endDate) {
                    return false;
                }
                
                return true;
            });
            
            return array_values($filtered);
        } catch (Exception $e) {
            $this->logger->error('Error getting rating trend', ['error' => $e->getMessage()]);
            throw $e;
        }
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
    
    /**
     * Make HTTP request
     */
    private function makeHttpRequest($url, $method = 'GET', $body = null, $headers = []) {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 30
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new Exception('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
        }
        
        return $response;
    }
    
    /**
     * Export reviews to spreadsheet
     */
    public function exportToSpreadsheet($reviews) {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set headers
            $headers = ['Location', 'Date Posted', 'Rating', 'Reviewer', 'Comment', 'Quarter'];
            $sheet->fromArray($headers);
            
            // Style headers
            $headerStyle = $sheet->getStyle('A1:F1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $headerStyle->getFill()->getStartColor()->setARGB('FF4472C4');
            $headerStyle->getFont()->getColor()->setARGB('FFFFFFFF');
            
            // Add data rows
            $row = 2;
            foreach ($reviews as $review) {
                $sheet->setCellValue("A$row", $review['locationName']);
                $sheet->setCellValue("B$row", $review['datePosted']);
                $sheet->setCellValue("C$row", $review['rating']);
                $sheet->setCellValue("D$row", $review['reviewer']);
                $sheet->setCellValue("E$row", $review['comment']);
                $sheet->setCellValue("F$row", $review['quarterWithYear']);
                $row++;
            }
            
            // Auto-size columns
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Freeze header row
            $sheet->freezePane('A2');
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = 'google_reviews_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filepath = $this->tokenCacheDir . '/' . $filename;
            
            $writer->save($filepath);
            
            return $filepath;
        } catch (Exception $e) {
            $this->logger->error('Error exporting reviews to spreadsheet', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Clear cache and tokens
     */
    public function clearCache() {
        $files = glob($this->tokenCacheDir . '/*.json');
        foreach ($files as $file) {
            if (basename($file) !== 'oauth_token.json') {
                @unlink($file);
            }
        }
        $this->logger->info('Google reviews cache cleared');
    }
    
    /**
     * Clear all data including tokens
     */
    public function clearAllData() {
        $files = glob($this->tokenCacheDir . '/*');
        foreach ($files as $file) {
            @unlink($file);
        }
        $this->logger->info('All Google reviews data cleared');
    }
}