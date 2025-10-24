# Google Business Profile Reviews - Quick Start

**Time to setup: ~15 minutes**

## ✅ Pre-Setup Checklist

- [ ] You have a Google Business Profile account
- [ ] Your Business Profile has at least one location
- [ ] You have access to Google Cloud Console
- [ ] You can edit `.env` file

## 🎯 Quick Setup (5 Steps)

### 1️⃣ Create Google Cloud Project
```
Google Cloud Console
→ Create new project "Laguna Integration Reviews"
→ Wait 1-2 minutes for creation
```

### 2️⃣ Enable APIs
```
Google Cloud Console
→ APIs & Services → Library
→ Enable: "Google My Business API"
→ Enable: "Google My Business API v4"
```

### 3️⃣ Create OAuth Credentials
```
Google Cloud Console
→ APIs & Services → Credentials
→ Create OAuth 2.0 Client ID (Web Application)
→ Add Redirect URI: http://localhost:8080/lag-int/oauth-callback.php
→ Copy Client ID and Client Secret
```

### 4️⃣ Get Account ID
```
Google My Business
→ Look at URL in browser
→ Copy Account ID from URL path
Format: /accounts/{ACCOUNT_ID}/locations
```

### 5️⃣ Update .env File
```bash
# Edit .env in project root
GOOGLE_CLIENTID=YOUR_CLIENT_ID.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET
GOOGLE_ACCOUNT_ID=YOUR_ACCOUNT_ID
```

## 🔑 Get Initial Tokens

1. Navigate to: `http://localhost:8080/lag-int/oauth-callback.php`
2. Click "Sign in with Google"
3. Approve permissions
4. See success message
5. Tokens automatically stored

## 📊 Access Dashboard

1. Go to: `http://localhost:8080/lag-int/`
2. Log in to your account
3. Click "Google Reviews" card
4. See your reviews analytics!

## 📈 What You'll See

- **Rating Trends** - Line chart of average ratings over time
- **Location Comparison** - Bar chart comparing locations
- **Review Distribution** - Breakdown of 1-5 star ratings
- **Recent Reviews** - Table of all reviews with filtering
- **Export** - Download data as Excel spreadsheet

## 🆘 Issues?

| Problem | Fix |
|---------|-----|
| "No OAuth token" | Run oauth-callback.php again |
| "Empty location list" | Add location in Google My Business |
| "No reviews" | Locations must have reviews from customers |
| ".env parse error" | Quote comma-separated values: `"val1, val2"` |

## 📚 Learn More

- **Full Setup Guide**: `GOOGLE_BUSINESS_PROFILE_SETUP.md`
- **Technical Docs**: `documentation/GOOGLE_BUSINESS_PROFILE_API.md`
- **Error Logs**: `logs/app.log`

## 🔒 Security Notes

1. **Never commit `.env` to version control**
2. **Tokens stored in**: `uploads/google_reviews_cache/` (non-public)
3. **OAuth refreshes automatically** - You don't need to re-authenticate
4. **Access restricted** to authenticated users only

## 🚀 Next Steps

After setup:
1. Share reviews dashboard link with team
2. Check reviews daily/weekly
3. Export reports as needed
4. Monitor rating trends

## 💡 Tips

- **API Quota**: ~100,000 requests/day (plenty for most uses)
- **Refresh Data**: Click "Refresh" button to fetch latest reviews
- **Export**: Use Excel export for analysis in spreadsheets
- **Cache**: Automatically cleared after 24 hours

---

**Questions?** Check logs: `logs/app.log`

**Full Documentation**: See `GOOGLE_BUSINESS_PROFILE_SETUP.md`