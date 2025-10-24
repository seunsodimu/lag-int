# Google Business Profile API Integration

## Overview

This integration provides comprehensive review analytics for your Google Business Profile locations. It displays:
- **Rating trends over time** - Track how ratings change day by day
- **Location comparison** - Compare average ratings across multiple locations
- **Review distribution** - Visualize the breakdown of 1-5 star ratings
- **Individual reviews** - Browse all reviews with filtering and export

## Architecture

### Components

```
GoogleReviewsService (src/Services/GoogleReviewsService.php)
├── OAuth 2.0 Token Management
├── Business Profile API Client
├── Analytics Engine
│   ├── Timeline builder
│   ├── Location comparison
│   └── Rating trends
└── Excel Export

GoogleReviewsController (src/Controllers/GoogleReviewsController.php)
├── /get-reviews (Analytics data)
├── /get-location-comparison (Location stats)
├── /get-rating-trend (Time series)
├── /export (Excel download)
└── /clear-cache (Cache management)

public/google-reviews.php (Dashboard UI)
├── Chart.js visualizations
├── Bootstrap 5 UI
├── Real-time filtering
└── Excel export
```

### Data Flow

```
1. User requests /lag-int/google-reviews.php
2. Page loads and calls /get-reviews endpoint
3. Service retrieves OAuth token (cached or refreshed)
4. Fetches all locations from Google Business Profile
5. For each location, fetches reviews via API
6. Calculates analytics:
   - Daily average ratings
   - Location comparisons
   - Review distributions
7. Returns JSON to frontend
8. Frontend renders with Chart.js
```

## Configuration

### Required Environment Variables (in `.env`)

```bash
# OAuth 2.0 Credentials
GOOGLE_CLIENTID=YOUR_CLIENT_ID.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET
GOOGLE_ACCOUNT_ID=YOUR_ACCOUNT_ID
```

### How to Get Credentials

1. **Create OAuth 2.0 Credentials**
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create new OAuth 2.0 Web Application credentials
   - Set redirect URI to: `http://localhost:8080/lag-int/oauth-callback.php`
   - Copy Client ID and Client Secret

2. **Find Your Account ID**
   - Go to [Google My Business](https://www.google.com/business/)
   - Your Account ID is in the URL: `/accounts/{ACCOUNT_ID}/locations`

3. **Enable Required APIs**
   - Google My Business API
   - Google My Business API v4

## API Reference

### Service Methods

#### `getAllReviewsWithAnalytics()`
Returns all reviews with comprehensive analytics.

**Returns:**
```php
[
    'allReviews' => [...reviews...],
    'byLocation' => [
        'location_name' => [
            'displayName' => 'Store Name',
            'reviewCount' => 45,
            'averageRating' => 4.2,
            'reviews' => [...]
        ]
    ],
    'totalReviews' => 125,
    'averageRating' => 4.15,
    'timeline' => [
        [
            'date' => '2024-01-15',
            'average' => 4.3,
            'count' => 2
        ]
    ]
]
```

#### `getLocationComparison()`
Get summary statistics for each location.

**Returns:**
```php
[
    [
        'location' => 'New York Store',
        'reviewCount' => 45,
        'averageRating' => 4.2,
        'ratingPercentage' => 84.0
    ]
]
```

#### `getRatingTrend($startDate, $endDate)`
Get rating trend for a date range.

**Parameters:**
- `$startDate` (string, optional): Start date (Y-m-d format)
- `$endDate` (string, optional): End date (Y-m-d format)

**Returns:**
```php
[
    [
        'date' => '2024-01-15',
        'average' => 4.3,
        'count' => 2
    ]
]
```

#### `storeToken($accessToken, $refreshToken, $expiresIn)`
Store OAuth token after authentication.

**Parameters:**
- `$accessToken`: OAuth access token
- `$refreshToken`: OAuth refresh token
- `$expiresIn`: Token expiration in seconds (default: 3600)

#### `exportToSpreadsheet($reviews)`
Export reviews to Excel format.

**Returns:** File path to generated XLSX

## Authentication Flow

### Initial OAuth Setup

The OAuth flow works as follows:

1. **Get Authorization Code** (One-time setup)
   - Create a script that redirects to Google OAuth
   - User grants permission
   - Get authorization code

2. **Exchange for Tokens**
   - Trade authorization code for access/refresh tokens
   - Store refresh token securely

3. **Use Refresh Token**
   - Access token expires after 1 hour
   - Service automatically refreshes using refresh token
   - No user interaction needed

### Token Storage

Tokens are cached in: `uploads/google_reviews_cache/oauth_token.json`

**Security Notes:**
- Keep this directory secure (not web-accessible)
- File contains sensitive refresh token
- Never commit to version control

## Usage Examples

### Get All Reviews and Analytics

```php
$service = new GoogleReviewsService();
$data = $service->getAllReviewsWithAnalytics();

echo "Total Reviews: " . $data['totalReviews'];
echo "Average Rating: " . $data['averageRating'];

foreach ($data['byLocation'] as $location => $stats) {
    echo $stats['displayName'] . ": " . $stats['averageRating'] . " stars";
}
```

### Get Rating Trend

```php
$service = new GoogleReviewsService();
$trend = $service->getRatingTrend('2024-01-01', '2024-01-31');

foreach ($trend as $day) {
    echo $day['date'] . ": " . $day['average'] . " (" . $day['count'] . " reviews)";
}
```

### Export to Excel

```php
$service = new GoogleReviewsService();
$data = $service->getAllReviewsWithAnalytics();
$filepath = $service->exportToSpreadsheet($data['allReviews']);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="reviews.xlsx"');
readfile($filepath);
```

## Dashboard Features

### Rating Trend Chart
- Line chart showing average rating per day
- Automatically scales to 0-5 range
- Interactive tooltips on hover
- Responsive and mobile-friendly

### Location Comparison
- Visual rating bars for each location
- Shows average rating and review count
- Sorted by review volume

### Rating Distribution
- Bar chart of 1-5 star breakdown
- Color-coded by rating level
- Helps identify rating patterns

### Reviews Table
- All reviews sorted by date (newest first)
- Inline filtering by minimum rating
- Shows reviewer name, comment, date
- Export selected range to Excel

### Filters & Actions
- **Date Range**: Filter reviews by date
- **Min Rating**: Filter to specific star rating
- **Refresh**: Fetch latest data from API
- **Export**: Download as Excel spreadsheet
- **Clear Cache**: Force data refresh

## Performance Considerations

### Caching Strategy

The service caches data to minimize API calls:

**Token Cache** (`oauth_token.json`):
- Automatic refresh when expires
- Persists across page loads

**Data Cache** (not implemented by default):
- Consider adding 1-hour cache for review data
- Reduces API quota usage
- Set cache header in response

### API Quotas

Google Business Profile API quotas per day:
- **Read requests**: 100,000 requests/day
- **Write requests**: 10,000 requests/day

Each review fetch = 1 API call. With proper caching, a location with 1000 reviews uses only 1 API call per cache refresh.

## Troubleshooting

### "No valid OAuth token found"

**Cause**: Token file doesn't exist or refresh failed

**Solutions**:
1. Check `uploads/google_reviews_cache/oauth_token.json` exists
2. Verify refresh token is valid
3. Check Google Cloud credentials are correct
4. See "Initial OAuth Setup" section above

### "Failed to parse dotenv file"

**Cause**: .env values contain special characters without quotes

**Solution**: Quote values with special characters:
```bash
# Wrong
GOOGLE_PLACE_IDS=ChIJ..., ChIJ...

# Correct
GOOGLE_PLACE_IDS="ChIJ..., ChIJ..."
```

### Reviews not loading

**Cause**: Location ID format incorrect or API error

**Solutions**:
1. Check Account ID format: `accounts/{ACCOUNT_ID}`
2. Verify locations are valid via Google My Business
3. Check API is enabled in Google Cloud Console
4. Review logs: `logs/app.log`

### Empty location list

**Cause**: Account has no locations or permissions issue

**Solutions**:
1. Verify account has at least one location in Google My Business
2. Check OAuth scopes include: `https://www.googleapis.com/auth/business.manage`
3. Verify user account has admin access to Business Profile

### Charts not rendering

**Cause**: No review data available

**Solutions**:
1. Click "Refresh Data" to force fetch
2. Check if locations have reviews
3. Verify date range in filters includes reviews

## Error Logs

All errors are logged to: `logs/app.log`

Common log messages:
```
[ERROR] Error fetching locations
[ERROR] Error fetching reviews for location
[ERROR] Error getting location comparison
[ERROR] HTTP request failed
[ERROR] Failed to refresh OAuth token
```

Check logs for detailed error messages and stack traces.

## Future Enhancements

### Planned Features
1. **Reply Management** - Reply to reviews directly from dashboard
2. **Response Analytics** - Track which reviews you've responded to
3. **Sentiment Analysis** - NLP analysis of review text
4. **Email Notifications** - Alert on new low ratings
5. **Custom Reports** - Generate PDF reports
6. **Location Insights** - Comparison with nearby competitors
7. **Bulk Actions** - Reply to multiple reviews at once
8. **Archive/Hide** - Option to hide unhelpful reviews

### Development Notes
- Business Profile API supports replying and deleting replies
- Sentiment analysis requires additional library (e.g., Azure Text Analytics)
- PDF generation requires TCPDF or similar library
- Competitor data from Google Places API

## Security Best Practices

1. **Never commit credentials to version control**
   - .env should be in .gitignore
   - Use environment variables in production

2. **Secure token storage**
   - Keep uploads/google_reviews_cache/ non-public
   - Use file permissions 0755
   - Consider encrypted storage for production

3. **API key rotation**
   - Rotate OAuth credentials regularly
   - Monitor for suspicious API usage
   - Review Google Cloud audit logs

4. **User authentication**
   - All endpoints require AuthMiddleware
   - Only authenticated users can access data
   - AJAX endpoints validate session

## Support & Resources

- [Google My Business API Docs](https://developers.google.com/my-business/reference/rest/v4/accounts.locations.reviews)
- [OAuth 2.0 Guide](https://developers.google.com/identity/protocols/oauth2)
- [App Logs](../logs/app.log)

---

**Last Updated**: 2024
**API Version**: Google My Business API v4