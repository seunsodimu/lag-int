# Google Reviews Aggregator

A comprehensive feature to retrieve, display, filter, and export Google reviews from multiple business locations.

## Overview

The Google Reviews Aggregator allows you to:
- Retrieve reviews from multiple Google Places in real-time
- Display reviews in an interactive DataTable with sorting and filtering
- Export reviews to Excel spreadsheets
- Cache reviews for improved performance (24-hour cache)
- Manage locations through environment variables

## Architecture

### Components

#### 1. **GoogleReviewsService** (`src/Services/GoogleReviewsService.php`)
Handles all business logic for Google Places API integration:

- **`getAllReviews($useCache = true)`** - Retrieves reviews from all configured locations
- **`getPlaceReviews($placeId, $useCache = true)`** - Fetches reviews for a specific place
- **`fetchPlaceDetails($placeId)`** - Calls Google Places API
- **`transformReview($review, $locationName)`** - Converts API response to required format
- **`exportToSpreadsheet($reviews)`** - Generates Excel file with formatted data
- **`clearCache()`** - Removes cached reviews

**Key Features:**
- 24-hour caching to reduce API calls
- Quarter/Year calculation for review dates
- Proper error handling and logging
- Uses PHPOffice/PhpSpreadsheet (already in vendor)

#### 2. **GoogleReviewsController** (`src/Controllers/GoogleReviewsController.php`)
Handles HTTP requests and responses:

- **AJAX Endpoint: `/google-reviews.php?action=get-reviews`** - Returns reviews as JSON
- **AJAX Endpoint: `/google-reviews.php?action=export`** - Streams Excel file
- **AJAX Endpoint: `/google-reviews.php?action=clear-cache`** - Clears all cached data

#### 3. **User Interface** (`public/google-reviews.php`)
Interactive web page featuring:

- **DataTables Integration** - Client-side sorting, filtering, and pagination
- **Bootstrap 5 Styling** - Modern, responsive design
- **Export to Excel** - One-click download as spreadsheet
- **Statistics Card** - Shows total review count
- **Loading States** - Spinner during API calls
- **Alert System** - Success/error notifications
- **Responsive Design** - Works on mobile and desktop

## Setup Instructions

### 1. Environment Configuration

Add to `.env`:
```env
GOOGLE_API_KEY=your_api_key_here
GOOGLE_PLACE_IDS=ChIJO2axl7aFToYR9oHtR1_nTb0, ChIJ66CsrYLe3IARJM2AVLO_wY4
```

**Getting API Key:**
1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project
3. Enable the "Places API"
4. Create an API key with "Places API" restrictions
5. Add to `.env`

**Getting Place IDs:**
1. Go to [Google Places API](https://developers.google.com/maps/documentation/places/web-service/overview)
2. Use the "Place Details" request to get your business Place ID
3. Or use [Google Maps](https://maps.google.com), search for your business, and find the Place ID in the URL

### 2. File Locations

```
src/Services/GoogleReviewsService.php    <- API calls & data processing
src/Controllers/GoogleReviewsController.php <- HTTP request handling
public/google-reviews.php                <- User interface
```

### 3. Cache Directory

Ensure `uploads/google_reviews_cache/` is writable:
```powershell
# Windows
icacls "C:\xampp\htdocs\lag-int\uploads\google_reviews_cache" /grant:r "Everyone":(F)
```

## Usage

### Accessing the Interface

**Two ways to access:**

1. **Via Dashboard** (Recommended):
   - Navigate to: `http://localhost:8080/lag-int/`
   - Look for the **"Google Reviews"** card
   - Click **"View Reviews"** button

2. **Direct URL**:
   - Navigate to: `http://localhost:8080/lag-int/google-reviews.php`

The page requires authentication - you'll be redirected to login if needed.

### Features

#### Refresh Reviews
- Click **"Refresh"** button to load latest reviews
- Uses cached data if available (updated every 24 hours)
- Shows loading spinner during fetch

#### Export to Excel
- Click **"Export to Excel"** button
- Downloads file: `google_reviews_YYYY-MM-DD_HH-MM-SS.xlsx`
- Formatted with:
  - Bold headers with blue background
  - Auto-sized columns
  - Frozen header row for easy scrolling

#### Clear Cache
- Click **"Clear Cache"** button
- Requires confirmation
- Forces fresh API fetch on next load
- Useful for immediate updates

#### Filtering & Sorting
- **Sort** by any column (click header)
- **Filter** using search box (filters all columns)
- **Pagination** - Select rows per page (default: 25)

#### Columns

| Column | Description | Format |
|--------|-------------|--------|
| Location Name | Google Business location | Text |
| Date Posted | When the review was posted | Mon, Feb 03, 2020 05:06 PM |
| Rating | Star rating | 5.0, 4.5, etc. |
| Quarter With Year | Financial quarter | Q1 2020 |
| Quarter | Quarter only | Q1, Q2, Q3, Q4 |
| Year | Year only | 2020 |
| Author | Reviewer's name | Text |
| Review | Review content (first 100 chars) | Text |

## API Reference

### Get Reviews
```
GET /lag-int/google-reviews.php?action=get-reviews&cache=true
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "locationName": "Laguna Tools - Grand Prairie",
      "datePosted": "Mon, Feb 03, 2020 05:06 PM",
      "rating": "5.0",
      "quarterWithYear": "Q1 2020",
      "quarter": "Q1",
      "year": "2020",
      "authorName": "John Doe",
      "reviewText": "Great service!",
      "relativeTimeDescription": "2 years ago"
    }
  ],
  "count": 15
}
```

### Export Reviews
```
GET /lag-int/google-reviews.php?action=export
```

Returns XLSX file with formatted data.

### Clear Cache
```
GET /lag-int/google-reviews.php?action=clear-cache
```

**Response:**
```json
{
  "success": true,
  "message": "Cache cleared successfully"
}
```

**Note:** All endpoints require authentication. Unauthenticated requests will receive a 401 error.

## Configuration

### Review Columns
To add/remove columns, modify `renderTable()` in `google-reviews.php`:

```javascript
let tableHTML = `
    <table class="table table-hover" id="reviewsTable">
        <thead>
            <tr>
                <th>Location Name</th>
                <th>Date Posted</th>
                <!-- Add more columns here -->
            </tr>
        </thead>
        ...
```

### Cache Duration
To change cache duration (default: 24 hours = 86400 seconds), edit `GoogleReviewsService.php`:

```php
// Cache for 24 hours
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    // Change 86400 to desired seconds
}
```

### Rows Per Page
To change default pagination, edit `google-reviews.php`:

```javascript
reviewsTable = $('#reviewsTable').DataTable({
    pageLength: 25,  // Change this value
    // ...
});
```

## Troubleshooting

### "Google API Key not configured"
- Check `.env` file has `GOOGLE_API_KEY`
- Verify key is valid in Google Cloud Console
- Check for trailing whitespace

### "No Google Place IDs configured"
- Check `.env` file has `GOOGLE_PLACE_IDS`
- Verify Place IDs are comma-separated
- Ensure no extra spaces or formatting issues

### Reviews not showing
1. Check browser console for errors (F12)
2. View application logs in `logs/`
3. Try "Clear Cache" to force fresh API fetch
4. Verify Google API quota hasn't been exceeded

### Export fails
- Ensure `uploads/google_reviews_cache/` directory exists and is writable
- Check server has PHPOffice/PhpSpreadsheet library (included)
- Review error logs for details

### Slow loading
- Normal: First load fetches from Google API (5-10 seconds)
- Next loads use cache (instant)
- Click "Clear Cache" if need immediate fresh data

## Logging

All operations are logged to `logs/app-*.log`:

```
[2024-01-15 10:30:45] 3dcart-netsuite.INFO: Fetching place details {"place_id":"ChIJO2axl7aFToYR9oHtR1_nTb0"}
[2024-01-15 10:30:48] 3dcart-netsuite.INFO: Retrieved Google reviews {"count":25,"use_cache":false}
[2024-01-15 10:31:02] 3dcart-netsuite.INFO: Exported reviews to spreadsheet {"count":25}
```

## Performance Notes

- **First Load:** 5-10 seconds (API call)
- **Cached Loads:** <100ms
- **Export:** 1-2 seconds
- **Cache Size:** ~50KB per location
- **API Quota:** Each request counts toward Google Places API quota

## Security

- API key is stored in `.env` (not in code)
- Sensitive data is hidden from logs
- HTML escaping prevents XSS attacks
- No sensitive data in URLs
- Cache files are in non-public directory

## Future Enhancements

Possible additions:
- Review sentiment analysis
- Comparison charts across locations
- Review response management
- Automated review alerts
- Custom date range filtering
- Rating distribution charts
- Average rating trends

## Support

For issues or questions:
1. Check `logs/` directory for error messages
2. Review this documentation
3. Check Google Places API documentation
4. Verify environment configuration

## Dependencies

- Bootstrap 5.1.3 (CSS Framework)
- DataTables 1.11.5 (Table functionality)
- jQuery 3.6.0 (JavaScript library)
- Moment.js 2.29.1 (Date parsing)
- Font Awesome 6.0.0 (Icons)
- PHPOffice/PhpSpreadsheet (Excel export)

All JavaScript dependencies are loaded from CDN for easier maintenance.