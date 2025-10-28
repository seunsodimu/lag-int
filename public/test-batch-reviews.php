<?php
/**
 * Test My Business API v4 Batch Reviews Endpoint
 * Verifies batch review fetching works correctly
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Laguna\Integration\Services\GoogleReviewsService;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Utils\UrlHelper;

// Set timezone
date_default_timezone_set('America/New_York');

// Require authentication
$auth = new AuthMiddleware();
$currentUser = $auth->requireAuth();
if (!$currentUser) {
    exit; // Middleware handles redirect
}

$logger = Logger::getInstance();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Google My Business API v4 - Batch Reviews Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1f2937; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
        .section { margin: 20px 0; }
        .status { padding: 15px; border-radius: 4px; margin: 10px 0; }
        .success { background: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
        .error { background: #fee2e2; border-left: 4px solid #ef4444; color: #7f1d1d; }
        .info { background: #dbeafe; border-left: 4px solid #3b82f6; color: #1e40af; }
        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; color: #92400e; }
        .code { background: #f3f4f6; border: 1px solid #d1d5db; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto; }
        .review-item { background: #f9fafb; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 3px solid #3b82f6; }
        .location-summary { background: #f3f4f6; padding: 12px; margin: 8px 0; border-radius: 4px; }
        .metric { display: inline-block; margin-right: 20px; }
        .metric-value { font-weight: bold; color: #3b82f6; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div style="margin-bottom: 20px;">
            <a href="<?php echo UrlHelper::url('google-reviews.php'); ?>" class="btn" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">← Back to Google Reviews</a>
            <a href="<?php echo UrlHelper::url('index.php'); ?>" class="btn" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;">🏠 Dashboard</a>
        </div>
        
        <h1>🔍 Google My Business API v4 - Batch Reviews Test</h1>
        
        <?php
        try {
            echo '<div class="section">';
            echo '<h2>1. Service Initialization</h2>';
            
            $service = new GoogleReviewsService();
            echo '<div class="status success">✓ GoogleReviewsService initialized successfully</div>';
            
            echo '</div>';
            echo '<div class="section">';
            echo '<h2>2. Fetching Locations</h2>';
            
            $locations = $service->getAllLocations();
            
            if (empty($locations)) {
                echo '<div class="status warning">⚠ No locations found for this account</div>';
            } else {
                echo '<div class="status success">✓ Found ' . count($locations) . ' location(s)</div>';
                echo '<table>';
                echo '<tr><th>#</th><th>Location Name</th><th>Address</th><th>Phone</th></tr>';
                
                foreach ($locations as $idx => $location) {
                    echo '<tr>';
                    echo '<td>' . ($idx + 1) . '</td>';
                    echo '<td><strong>' . htmlspecialchars($location['displayName'] ?? 'N/A') . '</strong></td>';
                    echo '<td>' . htmlspecialchars($location['address']['addressLines'][0] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($location['phoneNumbers'][0] ?? 'N/A') . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            }
            
            echo '</div>';
            echo '<div class="section">';
            echo '<h2>3. Batch Reviews Fetch</h2>';
            echo '<p><strong>Endpoint:</strong> <code>POST /v4/accounts/{accountId}/locations:batchGetReviews</code></p>';
            echo '<p><strong>Locations to fetch:</strong> ' . count($locations) . '</p>';
            
            $analytics = $service->getAllReviewsWithAnalytics();
            
            echo '<div class="status success">✓ Batch request completed successfully</div>';
            
            echo '<div class="metric">Total Reviews: <span class="metric-value">' . $analytics['totalReviews'] . '</span></div>';
            echo '<div class="metric">Average Rating: <span class="metric-value">' . round($analytics['averageRating'], 2) . '/5⭐</span></div>';
            echo '<div class="metric">Locations: <span class="metric-value">' . count($analytics['byLocation']) . '</span></div>';
            
            echo '</div>';
            
            if (!empty($analytics['byLocation'])) {
                echo '<div class="section">';
                echo '<h2>4. Reviews by Location</h2>';
                
                foreach ($analytics['byLocation'] as $locName => $locData) {
                    echo '<div class="location-summary">';
                    echo '<strong>' . htmlspecialchars($locName) . '</strong><br>';
                    echo 'Reviews: ' . $locData['reviewCount'] . ' | ';
                    echo 'Avg Rating: ' . round($locData['averageRating'], 2) . '/5⭐';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            if (!empty($analytics['allReviews'])) {
                echo '<div class="section">';
                echo '<h2>5. Recent Reviews (Latest 5)</h2>';
                
                $recentReviews = array_slice($analytics['allReviews'], 0, 5);
                
                foreach ($recentReviews as $review) {
                    $ratingStars = str_repeat('⭐', intval($review['rating']));
                    echo '<div class="review-item">';
                    echo '<div><strong>' . htmlspecialchars($review['author']) . '</strong> - ' . htmlspecialchars($review['location']) . '</div>';
                    echo '<div>' . $ratingStars . ' (' . $review['rating'] . '/5)</div>';
                    echo '<div style="margin-top: 5px;">"' . htmlspecialchars(substr($review['text'], 0, 150)) . '..."</div>';
                    echo '<div style="color: #6b7280; font-size: 12px; margin-top: 5px;">' . htmlspecialchars($review['date']) . '</div>';
                    if (!empty($review['reply_text'])) {
                        echo '<div style="background: #e0f2fe; padding: 8px; margin-top: 8px; border-radius: 3px;">';
                        echo '<strong>Reply:</strong> ' . htmlspecialchars($review['reply_text']);
                        echo '</div>';
                    }
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            echo '<div class="section">';
            echo '<h2>✅ Success!</h2>';
            echo '<div class="status success">';
            echo 'Google My Business API v4 batch endpoint is working correctly!<br><br>';
            echo 'Benefits of batch endpoint:<br>';
            echo '• Single API call instead of one per location<br>';
            echo '• Much faster for accounts with multiple locations<br>';
            echo '• Reduced quota usage<br>';
            echo '</div>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="section">';
            echo '<h2>❌ Error</h2>';
            echo '<div class="status error">';
            echo 'Error: ' . htmlspecialchars($e->getMessage());
            echo '</div>';
            
            echo '<h3>Troubleshooting Steps:</h3>';
            echo '<ol>';
            echo '<li>Verify OAuth token is valid - visit <a href="http://localhost:8080/lag-int/google-reviews.php">google-reviews.php</a> to re-authenticate</li>';
            echo '<li>Check GOOGLE_ACCOUNT_ID format in .env (should be: <code>accounts/7053966710603229742</code>)</li>';
            echo '<li>Verify the account has at least one location with reviews</li>';
            echo '<li>Check application logs in <code>logs/</code> directory</li>';
            echo '<li>Make sure OAuth credentials are set in .env:</li>';
            echo '<ul>';
            echo '<li>GOOGLE_CLIENTID</li>';
            echo '<li>GOOGLE_CLIENT_SECRET</li>';
            echo '<li>GOOGLE_ACCOUNT_ID</li>';
            echo '</ul>';
            echo '</ol>';
            echo '</div>';
            
            $logger->error('Batch reviews test failed', ['error' => $e->getMessage()]);
        }
        ?>
    </div>
</body>
</html>