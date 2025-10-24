<?php

namespace Laguna\Integration\Controllers;

use Laguna\Integration\Services\GoogleReviewsService;
use Laguna\Integration\Utils\Logger;
use Exception;

class GoogleReviewsController {
    private $reviewsService;
    private $logger;
    
    public function __construct() {
        $this->reviewsService = new GoogleReviewsService();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Get all reviews with analytics
     */
    public function getReviews() {
        try {
            header('Content-Type: application/json');
            
            $data = $this->reviewsService->getAllReviewsWithAnalytics();
            
            $this->logger->info('Retrieved reviews with analytics', [
                'total' => $data['totalReviews'],
                'locations' => count($data['byLocation'])
            ]);
            
            echo json_encode([
                'success' => true,
                'data' => $data['allReviews'],
                'analytics' => [
                    'total' => $data['totalReviews'],
                    'averageRating' => $data['averageRating'],
                    'byLocation' => $data['byLocation'],
                    'timeline' => $data['timeline']
                ]
            ]);
            exit;
        } catch (Exception $e) {
            $this->logger->error('Error retrieving reviews', ['error' => $e->getMessage()]);
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * Get location comparison
     */
    public function getLocationComparison() {
        try {
            header('Content-Type: application/json');
            
            $comparison = $this->reviewsService->getLocationComparison();
            
            $this->logger->info('Retrieved location comparison', [
                'locations' => count($comparison)
            ]);
            
            echo json_encode([
                'success' => true,
                'data' => $comparison
            ]);
            exit;
        } catch (Exception $e) {
            $this->logger->error('Error getting location comparison', ['error' => $e->getMessage()]);
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * Get rating trend
     */
    public function getRatingTrend() {
        try {
            header('Content-Type: application/json');
            
            $startDate = $_GET['startDate'] ?? null;
            $endDate = $_GET['endDate'] ?? null;
            
            $trend = $this->reviewsService->getRatingTrend($startDate, $endDate);
            
            $this->logger->info('Retrieved rating trend', [
                'dataPoints' => count($trend)
            ]);
            
            echo json_encode([
                'success' => true,
                'data' => $trend
            ]);
            exit;
        } catch (Exception $e) {
            $this->logger->error('Error getting rating trend', ['error' => $e->getMessage()]);
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * Export reviews to spreadsheet
     */
    public function exportReviews() {
        try {
            $data = $this->reviewsService->getAllReviewsWithAnalytics();
            $filepath = $this->reviewsService->exportToSpreadsheet($data['allReviews']);
            
            $this->logger->info('Exported reviews to spreadsheet', [
                'filepath' => $filepath,
                'count' => count($data['allReviews'])
            ]);
            
            // Send file to browser
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Content-Length: ' . filesize($filepath));
            
            readfile($filepath);
            exit;
        } catch (Exception $e) {
            $this->logger->error('Error exporting reviews', ['error' => $e->getMessage()]);
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        try {
            header('Content-Type: application/json');
            
            $this->reviewsService->clearCache();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
}