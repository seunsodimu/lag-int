# NetSuite Saved Search API - Authentication Guide

## Overview

The NetSuite Saved Search API endpoint (`/netsuite-saved-search-api.php`) executes saved searches via RestLet with **OAuth 1.0** authentication to NetSuite and **dual authentication** for API access control.

**Endpoint:** `POST /netsuite-saved-search-api.php`

## Authentication Methods

The API supports two authentication methods for controlling access:

### 1. Session Authentication (Web Interface Users)

For users logged into the web interface, their session is automatically recognized.

**How it works:**
- User logs in via the web interface (`/login.php`)
- Session cookie is created and stored in the browser/client
- Subsequent API requests automatically include the session cookie
- API validates the session against the database

**When to use:**
- Web-based integrations
- Users accessing from the web interface
- Testing from the browser

**Example:**
```bash
# After logging in via web interface, the session cookie is automatically included
curl --location 'http://localhost:8080/netsuite-saved-search-api.php' \
  --header 'Content-Type: application/json' \
  --data '{
    "scriptID": "customscript_my_restlet",
    "searchID": "my_saved_search"
  }'
```

### 2. API Key Authentication (Programmatic Access)

For external applications, scripts, or systems that need to access the API without a web session.

**How it works:**
- API key is generated and stored in the `.env` file
- Client includes the API key in the `Authorization` header as a Bearer token
- API validates the provided key against the list of valid keys
- Request is processed if key matches

**When to use:**
- External integrations (third-party tools, middleware)
- Scheduled scripts or cron jobs
- Mobile apps or native applications
- Automated system-to-system communication

## Setup Instructions

### Step 1: Generate API Keys

Generate secure API keys for your applications. Use a strong key generator or create secure tokens:

```bash
# Linux/Mac - Generate a random API key
openssl rand -hex 32

# Or use a simpler approach
python3 -c "import secrets; print(secrets.token_urlsafe(32))"
```

### Step 2: Configure API Keys in .env

Edit your `.env` file and add the API keys:

```env
# Multiple API keys (comma-separated) for different applications
SAVED_SEARCH_API_KEYS=your-api-key-1-here,your-api-key-2-here,your-api-key-3-here

# Single API key example
SAVED_SEARCH_API_KEYS=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

**Best Practices:**
- Create separate keys for each application/environment
- Rotate keys periodically
- Keep keys confidential (don't commit to version control)
- Use environment variables in production, not hardcoded values
- Monitor key usage in application logs

### Step 3: Test Authentication

#### Test with cURL (API Key)

```bash
# Using API key
curl --location 'http://localhost:8080/netsuite-saved-search-api.php' \
  --header 'Authorization: Bearer a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6' \
  --header 'Content-Type: application/json' \
  --data '{
    "scriptID": "customscript_my_restlet",
    "searchID": "my_saved_search"
  }'
```

#### Test with Postman (API Key)

1. Open Postman
2. Create a new POST request
3. URL: `http://localhost:8080/netsuite-saved-search-api.php`
4. Go to **Headers** tab
5. Add: `Authorization: Bearer YOUR_API_KEY_HERE`
6. Add: `Content-Type: application/json`
7. Go to **Body** tab, select **raw**, choose **JSON**
8. Paste the request body:
```json
{
  "scriptID": "customscript_my_restlet",
  "searchID": "my_saved_search"
}
```
9. Click **Send**

## Response Examples

### Successful Request (201 status)

```json
{
  "success": true,
  "data": {
    "success": true,
    "data": [
      {
        "id": "123",
        "name": "Customer Name",
        "email": "customer@example.com"
      }
    ],
    "status_code": 200
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### Missing Authentication (401 status)

```json
{
  "success": false,
  "error": "Authentication required. Use session login or API key via Authorization header",
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### Invalid API Key (401 status)

```json
{
  "success": false,
  "error": "Authentication required. Use session login or API key via Authorization header",
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### Bad Request (400 status)

```json
{
  "success": false,
  "error": "Missing required parameters: scriptID and searchID",
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

## Error Codes & Troubleshooting

| HTTP Code | Meaning | Solution |
|-----------|---------|----------|
| **200** | Success | Request processed successfully |
| **400** | Bad Request | Check parameters: scriptID, searchID, and JSON format |
| **401** | Unauthorized | Verify session is active OR API key is valid and in Authorization header |
| **500** | Server Error | Check application logs in `logs/` directory |

### Common Issues

#### "Authentication required" (401)

**Causes:**
- No API key provided in Authorization header
- Invalid API key (typo or wrong key)
- Session expired
- Session validation failed

**Solutions:**
1. Verify API key is correct: `Authorization: Bearer YOUR_KEY_HERE`
2. Check that API key is configured in `.env` file
3. For session auth, ensure you're logged in
4. Check logs in `logs/app-YYYY-MM-DD.log` for detailed errors

#### "Missing required parameters" (400)

**Causes:**
- Missing `scriptID` or `searchID` in request body
- Invalid JSON format
- Empty request body

**Solutions:**
1. Include both `scriptID` and `searchID` in request body
2. Ensure request body is valid JSON
3. Check parameter values contain only alphanumeric characters and underscores

#### Development Mode - Default API Key

If `APP_DEBUG=true` and no API keys are configured, a demo key is automatically available:

```
Authorization: Bearer demo-key-for-testing-only
```

**⚠️ Warning:** This is for development only. Always configure real API keys for production.

## Security Best Practices

### 1. API Key Management
```env
# ✅ GOOD: Multiple keys for different purposes
SAVED_SEARCH_API_KEYS=key-for-app-1,key-for-app-2,key-for-scripting,key-for-webhooks

# ❌ BAD: Single key for everything
SAVED_SEARCH_API_KEYS=same-key-everywhere

# ❌ BAD: Keys in code or version control
// app.js
const apiKey = "hardcoded-key-123";
```

### 2. Key Rotation
- Rotate keys quarterly or after suspected compromise
- Generate new keys before removing old ones
- Update all applications using the old key
- Remove old key from `.env`

### 3. Monitoring & Logging
- Monitor `logs/app-*.log` for authentication failures
- Set up alerts for repeated 401 errors
- Track API key usage per application
- Review logs regularly for suspicious activity

### 4. Transport Security
- Always use HTTPS in production (not HTTP)
- Never log full API keys to stdout/files
- Use environment variables, not config files in version control
- Restrict `.env` file permissions to 600

### 5. Key Storage
```bash
# For production deployment
export SAVED_SEARCH_API_KEYS="prod-key-1,prod-key-2"

# In Docker
docker run -e SAVED_SEARCH_API_KEYS="key1,key2" ...

# In AWS Lambda
aws lambda update-function-configuration \
  --environment Variables={SAVED_SEARCH_API_KEYS=key1,key2}
```

## Integration Examples

### Python Script
```python
import requests
import json

api_key = "your-api-key-here"
endpoint = "http://localhost:8080/netsuite-saved-search-api.php"

headers = {
    "Authorization": f"Bearer {api_key}",
    "Content-Type": "application/json"
}

payload = {
    "scriptID": "customscript_customer_search",
    "searchID": "custrec_search"
}

response = requests.post(endpoint, headers=headers, json=payload)
print(response.json())
```

### Node.js / JavaScript
```javascript
const axios = require('axios');

const apiKey = 'your-api-key-here';
const endpoint = 'http://localhost:8080/netsuite-saved-search-api.php';

const config = {
  headers: {
    'Authorization': `Bearer ${apiKey}`,
    'Content-Type': 'application/json'
  }
};

const data = {
  scriptID: 'customscript_customer_search',
  searchID: 'custrec_search'
};

axios.post(endpoint, data, config)
  .then(response => console.log(response.data))
  .catch(error => console.error(error));
```

### PHP
```php
<?php
$apiKey = 'your-api-key-here';
$endpoint = 'http://localhost:8080/netsuite-saved-search-api.php';

$payload = [
    'scriptID' => 'customscript_customer_search',
    'searchID' => 'custrec_search'
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
echo json_decode($response, true);
curl_close($ch);
?>
```

## Testing the Endpoint

### Manual Testing with Documentation Interface

Visit the API documentation page to test interactively:

```
http://localhost:8080/netsuite-saved-search-api.php
```

The page provides:
- Copy-paste examples
- API documentation
- Quick start guide
- Status indicators
- Error reference

### Automated Testing

```bash
#!/bin/bash

API_KEY="your-api-key-here"
ENDPOINT="http://localhost:8080/netsuite-saved-search-api.php"

# Test 1: Valid request
echo "Test 1: Valid request with API key"
curl -X POST "$ENDPOINT" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "scriptID": "customscript_test",
    "searchID": "test_search"
  }' \
  -w "\nHTTP Status: %{http_code}\n"

# Test 2: Missing API key
echo -e "\n\nTest 2: Missing API key (should be 401)"
curl -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -d '{
    "scriptID": "customscript_test",
    "searchID": "test_search"
  }' \
  -w "\nHTTP Status: %{http_code}\n"

# Test 3: Invalid API key
echo -e "\n\nTest 3: Invalid API key (should be 401)"
curl -X POST "$ENDPOINT" \
  -H "Authorization: Bearer invalid-key-123" \
  -H "Content-Type: application/json" \
  -d '{
    "scriptID": "customscript_test",
    "searchID": "test_search"
  }' \
  -w "\nHTTP Status: %{http_code}\n"
```

## Support & Documentation

- **API Documentation**: Visit `http://localhost:8080/netsuite-saved-search-api.php` (GET request)
- **Application Logs**: Check `logs/app-YYYY-MM-DD.log` for debugging
- **NetSuite Setup**: See `documentation/setup/API_CREDENTIALS.md`
- **Troubleshooting**: See `documentation/troubleshooting/`

## Summary

The SavedSearch API provides secure access to NetSuite searches via RestLet:

✅ **Session Authentication** - For web interface users
✅ **API Key Authentication** - For external integrations  
✅ **OAuth 1.0 to NetSuite** - Automatic secure signing
✅ **Comprehensive Logging** - All requests logged
✅ **Error Handling** - Clear error messages
✅ **Security** - Multiple authentication layers

For questions or issues, check the logs or review the inline API documentation.