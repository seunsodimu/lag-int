<?php
/**
 * Google Business Profile Reviews Dashboard
 * Displays review ratings over time and location comparison
 */

require_once __DIR__ . '/../config/config.php';

use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Controllers\GoogleReviewsController;
use Laguna\Integration\Utils\UrlHelper;
use Laguna\Integration\Utils\Logger;

// Check authentication
try {
    $auth = new AuthMiddleware();
    $auth->requireAuth();
} catch (Exception $e) {
    header('Location: ' . UrlHelper::url('login.php'));
    exit;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    try {
        $controller = new GoogleReviewsController();
        
        switch ($_GET['action']) {
            case 'get-reviews':
                $controller->getReviews();
                break;
            case 'get-location-comparison':
                $controller->getLocationComparison();
                break;
            case 'get-rating-trend':
                $controller->getRatingTrend();
                break;
            case 'export':
                $controller->exportReviews();
                break;
            case 'clear-cache':
                $controller->clearCache();
                break;
            default:
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

$logger = Logger::getInstance();
$basePath = UrlHelper::url('');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Reviews Analytics - Laguna Integration</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4472C4;
            --success: #70AD47;
            --warning: #FFC000;
            --danger: #C5504E;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .header-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, #1F4E78 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .stat-card h5 {
            font-size: 0.875rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .chart-container h5 {
            margin-bottom: 1.5rem;
            color: #333;
            font-weight: 600;
        }
        
        .location-comparison-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .location-name {
            font-weight: 600;
            color: #333;
        }
        
        .rating-badge {
            background: #4472C4;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .rating-bar {
            background: #e0e0e0;
            height: 8px;
            border-radius: 4px;
            margin: 0.5rem 0;
            overflow: hidden;
        }
        
        .rating-fill {
            background: linear-gradient(90deg, var(--success), var(--primary));
            height: 100%;
            border-radius: 4px;
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        
        .review-row {
            padding: 1rem 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .review-row:last-child {
            border-bottom: none;
        }
        
        .review-rating {
            display: inline-block;
            background: #FFC000;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.875rem;
        }
        
        .review-comment {
            color: #666;
            margin-top: 0.5rem;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .spinner-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .spinner-overlay.active {
            display: flex;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .filter-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .star-rating {
            color: #FFC000;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-gradient">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0"><i class="fas fa-star"></i> Google Reviews Analytics</h1>
                    <p class="mb-0 mt-2 opacity-75">Track review ratings over time and compare locations</p>
                </div>
                <div>
                    <a href="<?php echo UrlHelper::url('index.php'); ?>" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Alerts -->
        <div id="alertContainer"></div>

        <!-- Statistics Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h5>Total Reviews</h5>
                    <div class="stat-value"><span id="totalReviews">0</span></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--success);">
                    <h5>Average Rating</h5>
                    <div class="stat-value" style="color: var(--success);">
                        <i class="fas fa-star star-rating"></i><span id="averageRating">0.0</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--warning);">
                    <h5>Active Locations</h5>
                    <div class="stat-value" style="color: var(--warning);"><span id="locationCount">0</span></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--danger);">
                    <h5>Last Updated</h5>
                    <div style="color: var(--danger); font-size: 0.95rem; margin-top: 0.5rem;" id="lastUpdated">-</div>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="chart-container">
            <h5>Rating Trend Over Time</h5>
            <div style="position: relative; height: 300px;">
                <canvas id="ratingTrendChart"></canvas>
            </div>
        </div>

        <!-- Location Comparison -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5>Location Comparison</h5>
                    <div id="locationComparison"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5>Rating Distribution</h5>
                    <div style="position: relative; height: 300px;">
                        <canvas id="ratingDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters & Actions -->
        <div class="filter-section">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" id="fromDate" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" id="toDate" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Min Rating</label>
                    <select id="minRating" class="form-select">
                        <option value="">All Ratings</option>
                        <option value="1">1+ Stars</option>
                        <option value="2">2+ Stars</option>
                        <option value="3">3+ Stars</option>
                        <option value="4">4+ Stars</option>
                        <option value="5">5 Stars</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" onclick="loadReviews()">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-3 d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="refreshData()">
                <i class="fas fa-sync"></i> Refresh Data
            </button>
            <button class="btn btn-outline-success" onclick="exportData()">
                <i class="fas fa-download"></i> Export to Excel
            </button>
            <button class="btn btn-outline-danger" onclick="clearCache()">
                <i class="fas fa-trash"></i> Clear Cache
            </button>
        </div>

        <!-- Reviews Table -->
        <div class="table-container mb-4">
            <h5 class="mb-3">Recent Reviews</h5>
            <div id="reviewsTable"></div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="text-center">
            <div class="spinner-border text-light mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-light">Loading reviews...</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        const basePath = '<?php echo UrlHelper::url('google-reviews.php'); ?>';
        let allReviews = [];
        let allAnalytics = {};
        let ratingTrendChart = null;
        let ratingDistributionChart = null;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadReviews();
        });

        function showLoading(show = true) {
            document.getElementById('loadingSpinner').classList.toggle('active', show);
        }

        function showAlert(message, type = 'success') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.getElementById('alertContainer').insertAdjacentHTML('beforeend', alertHtml);
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                if (alerts.length > 0) {
                    alerts[0].remove();
                }
            }, 5000);
        }

        function loadReviews() {
            showLoading(true);
            
            $.ajax({
                url: basePath + '?action=get-reviews',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        allReviews = response.data;
                        allAnalytics = response.analytics;
                        
                        updateStatistics();
                        updateCharts();
                        updateLocationComparison();
                        buildReviewsTable();
                        
                        showAlert('Reviews loaded successfully!', 'success');
                    } else {
                        showAlert('Error: ' + response.error, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    const errorMsg = xhr.responseJSON?.error || error || 'Unknown error';
                    showAlert('Failed to load reviews: ' + errorMsg, 'danger');
                },
                complete: function() {
                    showLoading(false);
                }
            });
        }

        function updateStatistics() {
            document.getElementById('totalReviews').textContent = allAnalytics.total || 0;
            document.getElementById('averageRating').textContent = (allAnalytics.averageRating || 0).toFixed(1);
            document.getElementById('locationCount').textContent = Object.keys(allAnalytics.byLocation || {}).length;
            document.getElementById('lastUpdated').textContent = new Date().toLocaleString();
        }

        function updateCharts() {
            const timeline = allAnalytics.timeline || [];
            
            if (timeline.length > 0) {
                updateRatingTrendChart(timeline);
            }
            
            updateRatingDistributionChart();
        }

        function updateRatingTrendChart(timeline) {
            const ctx = document.getElementById('ratingTrendChart');
            const dates = timeline.map(d => d.date);
            const averages = timeline.map(d => d.average);
            const counts = timeline.map(d => d.count);

            if (ratingTrendChart) {
                ratingTrendChart.destroy();
            }

            ratingTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Average Rating',
                        data: averages,
                        borderColor: '#4472C4',
                        backgroundColor: 'rgba(68, 114, 196, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#4472C4',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 5,
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function updateRatingDistributionChart() {
            const ratingCounts = [0, 0, 0, 0, 0]; // 1-5 stars
            
            allReviews.forEach(review => {
                const rating = parseInt(review.rating);
                if (rating >= 1 && rating <= 5) {
                    ratingCounts[rating - 1]++;
                }
            });

            const ctx = document.getElementById('ratingDistributionChart');
            const colors = ['#C5504E', '#E89B89', '#FFC000', '#70AD47', '#4472C4'];

            if (ratingDistributionChart) {
                ratingDistributionChart.destroy();
            }

            ratingDistributionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                    datasets: [{
                        label: 'Number of Reviews',
                        data: ratingCounts,
                        backgroundColor: colors,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function updateLocationComparison() {
            $.ajax({
                url: basePath + '?action=get-location-comparison',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const html = response.data.map(location => `
                            <div class="location-comparison-card">
                                <div class="flex-grow-1">
                                    <div class="location-name">${escapeHtml(location.location)}</div>
                                    <div class="rating-bar">
                                        <div class="rating-fill" style="width: ${location.ratingPercentage}%"></div>
                                    </div>
                                    <small class="text-muted">${location.reviewCount} reviews</small>
                                </div>
                                <div class="rating-badge">
                                    ${location.averageRating.toFixed(1)} / 5
                                </div>
                            </div>
                        `).join('');
                        document.getElementById('locationComparison').innerHTML = html || '<p class="text-muted">No locations found</p>';
                    }
                }
            });
        }

        function buildReviewsTable() {
            const minRating = parseInt(document.getElementById('minRating').value) || 0;
            const filtered = allReviews.filter(r => parseInt(r.rating) >= minRating);

            let html = '<div style="overflow-x: auto;"><table class="table table-hover">';
            html += '<thead class="table-light"><tr>';
            html += '<th>Location</th><th>Date</th><th>Rating</th><th>Reviewer</th><th>Comment</th>';
            html += '</tr></thead><tbody>';

            filtered.slice(0, 50).forEach(review => {
                const ratingColor = review.rating >= 4 ? 'success' : review.rating >= 3 ? 'warning' : 'danger';
                html += `
                    <tr>
                        <td>${escapeHtml(review.locationName)}</td>
                        <td>${escapeHtml(review.datePosted)}</td>
                        <td><span class="badge bg-${ratingColor}">⭐ ${review.rating}</span></td>
                        <td>${escapeHtml(review.reviewer)}</td>
                        <td><small>${escapeHtml(review.comment.substring(0, 100))}</small></td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
            document.getElementById('reviewsTable').innerHTML = html;
        }

        function refreshData() {
            loadReviews();
        }

        function exportData() {
            showLoading(true);
            window.location.href = basePath + '?action=export';
            setTimeout(() => showLoading(false), 1000);
        }

        function clearCache() {
            if (!confirm('Are you sure you want to clear the cache? This will require re-fetching data from Google.')) {
                return;
            }

            $.ajax({
                url: basePath + '?action=clear-cache',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Cache cleared. Refreshing...', 'success');
                        setTimeout(() => loadReviews(), 1000);
                    } else {
                        showAlert('Error clearing cache', 'danger');
                    }
                },
                error: function() {
                    showAlert('Error clearing cache', 'danger');
                }
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>