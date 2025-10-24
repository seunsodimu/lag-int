# Google Business Profile API - Setup Guide

Quick start guide to enable review analytics for your business locations.

## 📋 Requirements

- Google Business Profile account with at least one location
- Google Cloud Project with APIs enabled
- OAuth 2.0 credentials (Client ID & Client Secret)
- Account ID from Google Business Profile

## 🚀 Step 1: Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (top left dropdown)
3. Name it "Laguna Integration Reviews"
4. Wait for creation to complete (1-2 minutes)

## 🔐 Step 2: Enable Required APIs

1. In Cloud Console, go to **APIs & Services** > **Library**
2. Search for "Google My Business API"
   - Click on it
   - Click **ENABLE**
3. Search for "Google My Business API v4"
   - Click on it  
   - Click **ENABLE**

## 🔑 Step 3: Create OAuth 2.0 Credentials

1. Go to **APIs & Services** > **Credentials**
2. Click **+ CREATE CREDENTIALS** > **OAuth client ID**
3. If prompted: "To create an OAuth client ID, you must first create a consent screen"
   - Click **CREATE CONSENT SCREEN**
   - Choose **External** user type
   - Click **CREATE**
4. On the consent screen form:
   - **App name**: "Laguna Integration"
   - **User support email**: Your email
   - **Developer contact**: Your email
   - Click **SAVE AND CONTINUE**
5. Skip "Scopes" (click SAVE AND CONTINUE)
6. Review and **SAVE AND CONTINUE**
7. Back to Credentials, click **+ CREATE CREDENTIALS** > **OAuth client ID**
8. Choose **Web application**
9. Set **Authorized redirect URIs**:
   - Add: `http://localhost:8080/lag-int/oauth-callback.php`
   - If using production: `https://yourdomain.com/lag-int/oauth-callback.php`
10. Click **CREATE**
11. Copy the Client ID and Client Secret shown
    - ⚠️ **IMPORTANT**: Store these securely, you'll need them next

## 👤 Step 4: Find Your Google Account ID

1. Go to [Google My Business](https://www.google.com/business/)
2. Look at the URL in your browser
3. It will look like: `https://www.google.com/business/manage/accounts/{ACCOUNT_ID}/locations`
4. Copy the `{ACCOUNT_ID}` part (just the number, no braces)

## 📝 Step 5: Update .env File

Edit `.env` in the project root and add/update these lines:

```bash
# Google Business Profile API
GOOGLE_CLIENTID=YOUR_CLIENT_ID_HERE.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET_HERE
GOOGLE_ACCOUNT_ID=YOUR_ACCOUNT_ID_HERE
```

**Example:**
```bash
GOOGLE_CLIENTID=123456789-abcdefghijklmnopqrstuvwxyz123abc.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-1234567890abcdefghijklmno
GOOGLE_ACCOUNT_ID=104253967208137706761
```

⚠️ **Security**: Never commit `.env` to version control!

## 🔄 Step 6: Initial OAuth Authentication

You need to get an initial access token and refresh token.

### Option A: Create OAuth Callback Script

Create `public/oauth-callback.php`:

```php
<?php
require_once __DIR__ . '/../config/config.php';

use Laguna\Integration\Services\GoogleReviewsService;
use Laguna\Integration\Utils\Logger;

$logger = Logger::getInstance();

// Step 1: Get authorization code
if (!isset($_GET['code'])) {
    // Redirect to Google for authorization
    $clientId = $_ENV['GOOGLE_CLIENTID'];
    $redirectUri = 'http://localhost:8080/lag-int/oauth-callback.php';
    $scopes = 'https://www.googleapis.com/auth/business.manage';
    
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => $scopes,
        'access_type' => 'offline'
    ]);
    
    header('Location: ' . $authUrl);
    exit;
}

// Step 2: Exchange code for tokens
$code = $_GET['code'];
$clientId = $_ENV['GOOGLE_CLIENTID'];
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'];
$redirectUri = 'http://localhost:8080/lag-int/oauth-callback.php';

$tokenData = [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => $code,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
];

$response = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($tokenData)
    ]
]));

$data = json_decode($response, true);

if (isset($data['access_token'])) {
    // Store tokens
    $service = new GoogleReviewsService();
    $service->storeToken(
        $data['access_token'],
        $data['refresh_token'],
        $data['expires_in']
    );
    
    $logger->info('OAuth tokens stored successfully');
    echo '<h1>✅ Authentication Successful!</h1>';
    echo '<p>Your Google Business Profile is now connected.</p>';
    echo '<p><a href="/lag-int/google-reviews.php">View Reviews Dashboard</a></p>';
} else {
    $logger->error('OAuth token exchange failed', ['error' => $data['error_description'] ?? 'Unknown']);
    echo '<h1>❌ Authentication Failed</h1>';
    echo '<p>Error: ' . htmlspecialchars($data['error_description'] ?? 'Unknown error') . '</p>';
}
```

### Step 6A: Run Authentication

1. Navigate to: `http://localhost:8080/lag-int/oauth-callback.php`
2. You'll be redirected to Google login
3. Sign in with your Google Business Profile account
4. Click "Allow" to grant permissions
5. You'll see confirmation message

## ✅ Step 7: Verify Setup

1. Go to `http://localhost:8080/lag-int/google-reviews.php`
2. You should see review data and charts
3. If error, check:
   - `.env` credentials are correct
   - OAuth token exists: `uploads/google_reviews_cache/oauth_token.json`
   - Location has reviews in Google My Business
   - Check logs: `logs/app.log`

## 🆘 Troubleshooting

### "Failed to parse dotenv file"
**Fix**: Quote .env values containing commas:
```bash
GOOGLE_PLACE_IDS="ChIJ..., ChIJ..."  # Correct (with quotes)
GOOGLE_PLACE_IDS=ChIJ..., ChIJ...    # Wrong (missing quotes)
```

### "No valid OAuth token found"
**Fix**: Run the OAuth callback script again (Step 6A)

### "Failed to refresh OAuth token"
**Cause**: Refresh token expired or invalid
**Fix**: 
1. Delete `uploads/google_reviews_cache/oauth_token.json`
2. Run OAuth callback script again

### "Empty location list"
**Cause**: No locations in Google Business Profile
**Fix**:
1. Add location in [Google My Business](https://www.google.com/business/)
2. Wait 5 minutes for sync
3. Refresh reviews dashboard

### "No reviews displayed"
**Cause**: Locations have no reviews yet
**Fix**: Reviews appear automatically as customers add them

## 📚 Next Steps

1. **Review Dashboard**: Visit `/lag-int/google-reviews.php`
2. **Share Analytics**: Access restricted to authenticated users
3. **Export Data**: Use "Export to Excel" button for reports
4. **Monitor Regularly**: Check reviews daily/weekly

## 🔧 Advanced Configuration

### Custom Redirect URI (Production)

Update for production deployment:

```bash
# In Google Cloud Console
Authorized redirect URIs: https://yourdomain.com/lag-int/oauth-callback.php

# In .env or environment
GOOGLE_OAUTH_REDIRECT=https://yourdomain.com/lag-int/oauth-callback.php
```

### Multiple Accounts

For multiple Google Business Profile accounts:

1. Add new OAuth credentials in Cloud Console
2. Create separate `.env` entry:
   ```bash
   GOOGLE_ACCOUNT_ID_2=...
   ```
3. Modify GoogleReviewsService to support account switching

### Token Encryption (Advanced)

For production, encrypt stored tokens:

```php
// Store encrypted token
$encrypted = openssl_encrypt(
    json_encode($tokenData),
    'AES-256-CBC',
    $_ENV['ENCRYPTION_KEY'],
    0,
    $_ENV['ENCRYPTION_IV']
);
file_put_contents($tokenFile, $encrypted);

// Retrieve and decrypt
$encrypted = file_get_contents($tokenFile);
$tokenData = json_decode(
    openssl_decrypt($encrypted, 'AES-256-CBC', $_ENV['ENCRYPTION_KEY'], 0, $_ENV['ENCRYPTION_IV']),
    true
);
```

## 📖 Resources

- [Google My Business API Reference](https://developers.google.com/my-business/reference/rest/v4)
- [OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Google Business Profile Help](https://support.google.com/business)

## ⏱️ Timeline

| Step | Time |
|------|------|
| Create Cloud Project | 2-3 min |
| Enable APIs | 1-2 min |
| Create OAuth Credentials | 3-5 min |
| Find Account ID | 1 min |
| Update .env | 2 min |
| OAuth Authentication | 2-3 min |
| **Total** | **~15 minutes** |

---

**Last Updated**: 2024

Need help? Check `logs/app.log` for detailed error messages.