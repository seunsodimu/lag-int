<?php
/**
 * Google OAuth 2.0 Callback Handler
 * 
 * This script handles the OAuth 2.0 callback from Google to authenticate
 * with the Google Business Profile API and store the access token.
 * 
 * Access: http://localhost:8080/lag-int/oauth-callback.php
 */

require_once __DIR__ . '/../config/config.php';

use Laguna\Integration\Services\GoogleReviewsService;
use Laguna\Integration\Utils\Logger;

$logger = Logger::getInstance();

// Step 1: If no code, redirect to Google for authorization
if (!isset($_GET['code'])) {
    try {
        $clientId = $_ENV['GOOGLE_CLIENTID'] ?? null;
        
        if (!$clientId) {
            throw new Exception('GOOGLE_CLIENTID not configured in .env');
        }
        
        $redirectUri = 'http://localhost:8080/lag-int/oauth-callback.php';
        $scopes = 'https://www.googleapis.com/auth/business.manage';
        
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scopes,
            'access_type' => 'offline',
            'prompt' => 'consent' // Force consent screen to get refresh token
        ]);
        
        $logger->info('Redirecting to Google OAuth', ['redirect_uri' => $redirectUri]);
        header('Location: ' . $authUrl);
        exit;
    } catch (Exception $e) {
        $logger->error('OAuth initialization error', ['error' => $e->getMessage()]);
        $error = $e->getMessage();
    }
} else {
    // Step 2: Exchange authorization code for tokens
    try {
        $code = $_GET['code'];
        $clientId = $_ENV['GOOGLE_CLIENTID'] ?? null;
        $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? null;
        
        if (!$clientId || !$clientSecret) {
            throw new Exception('Google OAuth credentials not configured in .env');
        }
        
        $redirectUri = 'http://localhost:8080/lag-int/oauth-callback.php';
        
        $logger->info('Exchanging authorization code for tokens');
        
        // Exchange authorization code for tokens
        $tokenData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($tokenData),
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to connect to Google OAuth endpoint');
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['access_token'])) {
            $errorMsg = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
            throw new Exception('Token exchange failed: ' . $errorMsg);
        }
        
        // Step 3: Store tokens in service
        $service = new GoogleReviewsService();
        $service->storeToken(
            $data['access_token'],
            $data['refresh_token'] ?? null,
            $data['expires_in'] ?? 3600
        );
        
        $logger->info('OAuth tokens stored successfully', [
            'token_type' => $data['token_type'] ?? 'Bearer'
        ]);
        
        $success = true;
        $message = 'Authentication successful! Your Google Business Profile is now connected.';
    } catch (Exception $e) {
        $logger->error('OAuth token exchange failed', ['error' => $e->getMessage()]);
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Authentication - Laguna Integration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        .success-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .error-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dc3545;
        }
        .loading {
            text-align: center;
            padding: 3rem 1rem;
        }
        .spinner {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-key"></i> Google Authentication
            </h5>
        </div>
        <div class="card-body text-center">
            <?php if (isset($success) && $success): ?>
                <!-- Success State -->
                <div class="success-icon">
                    ✅
                </div>
                <h4 class="card-title mb-3">Success!</h4>
                <p class="card-text mb-4">
                    <?php echo htmlspecialchars($message); ?>
                </p>
                <p class="text-muted small mb-3">
                    You're now authenticated with Google Business Profile API.
                </p>
                <div class="d-grid gap-2">
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?page=reviews" class="btn btn-primary">
                        <i class="fas fa-star"></i> View Reviews Dashboard
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home"></i> Back to Dashboard
                    </a>
                </div>
                <hr class="my-3">
                <p class="text-muted small">
                    <i class="fas fa-info-circle"></i> Your tokens have been securely stored and will refresh automatically.
                </p>

            <?php elseif (isset($error)): ?>
                <!-- Error State -->
                <div class="error-icon">
                    ❌
                </div>
                <h4 class="card-title mb-3">Authentication Failed</h4>
                <div class="alert alert-danger alert-dismissible" role="alert">
                    <strong>Error:</strong>
                    <br>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <p class="text-muted small mb-3">
                    Please check your Google Cloud credentials and try again.
                </p>
                <div class="d-grid gap-2">
                    <button onclick="location.reload()" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Try Again
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </a>
                </div>
                <hr class="my-3">
                <details class="text-start">
                    <summary class="cursor-pointer text-muted small">
                        <i class="fas fa-bug"></i> Troubleshooting Tips
                    </summary>
                    <ul class="mt-2 small text-muted">
                        <li>Verify GOOGLE_CLIENTID in .env</li>
                        <li>Verify GOOGLE_CLIENT_SECRET in .env</li>
                        <li>Check redirect URI matches in Google Cloud Console</li>
                        <li>Ensure APIs are enabled in Google Cloud</li>
                        <li>Check logs/app.log for detailed errors</li>
                    </ul>
                </details>

            <?php else: ?>
                <!-- Loading/Redirect State -->
                <div class="loading">
                    <div class="spinner-border spinner-border-lg mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Redirecting to Google Authentication...</p>
                    <p class="text-muted small mt-3">
                        Please wait while we redirect you to Google to authorize access to your Business Profile reviews.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-muted small text-center">
            <p class="mb-0">
                <i class="fas fa-shield-alt"></i> Your data is secure. We never store your passwords.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-redirect on success after 2 seconds
        <?php if (isset($success) && $success): ?>
        setTimeout(() => {
            window.location.href = 'google-reviews.php';
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>