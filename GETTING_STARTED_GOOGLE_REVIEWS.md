# Getting Started with Google Reviews Analytics

## 🎯 What You'll Get

A dashboard that shows:
- 📈 **Charts** of how your ratings change over time
- 🏪 **Location comparison** showing which store has the best ratings
- ⭐ **Review breakdown** - how many 1, 2, 3, 4, 5 star reviews you have
- 📋 **All reviews** in one place with filtering
- 📥 **Export to Excel** for reports

## ⏱️ Time Required: ~15 minutes

## 🚀 Quick Start (5 Simple Steps)

### Step 1: Create a Google Cloud Project (2 min)

1. Go to: https://console.cloud.google.com/
2. Click the project dropdown (top left)
3. Click "New Project"
4. Name it: `Laguna Integration Reviews`
5. Click "Create" and wait 1-2 minutes

✅ **Done!**

---

### Step 2: Turn On the APIs (2 min)

1. In Google Cloud Console, click the **☰ Menu** (top left)
2. Go to: **APIs & Services** → **Library**
3. Search for: `Google My Business API`
   - Click the result
   - Click **ENABLE**
4. Wait 30 seconds
5. Search for: `Google My Business API v4`
   - Click the result
   - Click **ENABLE**

✅ **Done!**

---

### Step 3: Get Your Credentials (3 min)

1. In Google Cloud Console, click **☰ Menu**
2. Go to: **APIs & Services** → **Credentials**
3. Click **+ CREATE CREDENTIALS**
4. Choose: **OAuth client ID**
5. If asked, set up OAuth consent screen:
   - Click **CREATE CONSENT SCREEN**
   - Choose **External**
   - Click **CREATE**
   - Fill in:
     - App name: `Laguna Integration`
     - Your email for support
   - Click **SAVE AND CONTINUE** (2x)
6. Back to Credentials, click **+ CREATE CREDENTIALS** → **OAuth client ID**
7. Choose: **Web application**
8. Under "Authorized redirect URIs" click **ADD URI**
9. Add: `http://localhost:8080/lag-int/oauth-callback.php`
10. Click **CREATE**
11. You'll see a popup with your **Client ID** and **Client Secret**
    - 📌 **COPY THESE** - you need them next!

✅ **Done!**

---

### Step 4: Find Your Account ID (1 min)

1. Go to: https://www.google.com/business/
2. Look at the URL in your browser
3. Find the part that looks like: `/accounts/104253967208137706761/`
4. 📌 **COPY THE NUMBERS** (that's your Account ID)

✅ **Done!**

---

### Step 5: Update Your .env File (2 min)

1. Open: `c:\xampp\htdocs\lag-int\.env`
2. Find the section with "GOOGLE"
3. Add these lines (or update them):
```bash
GOOGLE_CLIENTID=YOUR_CLIENT_ID.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET
GOOGLE_ACCOUNT_ID=YOUR_ACCOUNT_ID
```

4. Replace `YOUR_CLIENT_ID`, `YOUR_CLIENT_SECRET`, `YOUR_ACCOUNT_ID` with what you copied
5. **Example:**
```bash
GOOGLE_CLIENTID=123456789-abc123def456.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xyz789abc123
GOOGLE_ACCOUNT_ID=104253967208137706761
```

6. Save the file

✅ **Done!**

---

## 🔑 Authenticate with Google

Now you need to get your login tokens:

1. Open in your browser: `http://localhost:8080/lag-int/oauth-callback.php`
2. You'll see a "Redirecting to Google Authentication..." message
3. Click "Sign in with Google"
4. Sign in with your Google account (the one connected to Google Business Profile)
5. Click "ALLOW" to grant access
6. You'll see a "✅ Success!" message
7. Page auto-redirects to the dashboard

✅ **Done! You're now connected!**

---

## 📊 View Your Reviews Dashboard

1. Go to: `http://localhost:8080/lag-int/`
2. Log in to your account
3. Find the **"Google Reviews Analytics"** card (with ⭐ icon)
4. Click **"📊 View Analytics"**

You should now see:
- 📈 Charts with your review trends
- 🏪 Your locations compared
- ⭐ How many 1, 2, 3, 4, 5 star reviews you have
- 📋 All your reviews in a table

✅ **Success! 🎉**

---

## 🎮 What You Can Do

### 🔄 Refresh Data
- Click **"Refresh Data"** button to get latest reviews from Google
- Takes 3-5 seconds

### 🔍 Filter Reviews
- Choose a **From Date** and **To Date**
- Choose **Min Rating** (e.g., "4+ Stars")
- Click **Filter**

### 📥 Export to Excel
- Click **"Export to Excel"** button
- Download your reviews as a spreadsheet
- Open in Excel for further analysis

### 🗑️ Clear Cache
- Click **"Clear Cache"** if something seems wrong
- Will force a complete refresh from Google

---

## ❓ Common Questions

### Q: Where do my review numbers come from?
**A:** Google Business Profile API. Your reviews are pulled from Google directly.

### Q: How often do the reviews update?
**A:** Click "Refresh Data" to get the latest. Updates are not automatic - you control when to refresh.

### Q: Do I need to set this up again?
**A:** No! Your login token is saved automatically and refreshes on its own.

### Q: Can I share this with my team?
**A:** Yes! Anyone with a login account can access `/lag-int/google-reviews.php`

### Q: What if I have multiple locations?
**A:** All locations are shown automatically! The service gets reviews from all locations tied to your Google Business Profile.

### Q: Why is the page showing no reviews?
**A:** 
1. Make sure your locations have reviews in Google
2. Click "Refresh Data" to force an update
3. Check that you authenticated successfully

### Q: How many API calls does this use?
**A:** Very few! About 1-2 API calls per refresh (fetching all your locations and reviews). You have 100,000+ calls per day available.

---

## 🆘 Troubleshooting

### Problem: "No valid OAuth token found"
**Solution:** 
- Go to `http://localhost:8080/lag-int/oauth-callback.php`
- Click through the Google authentication again

### Problem: ".env parse error"
**Solution:**
- Make sure all values with commas are in quotes:
```bash
GOOGLE_PLACE_IDS="value1, value2"  # ✅ Correct
GOOGLE_PLACE_IDS=value1, value2    # ❌ Wrong
```

### Problem: "Empty location list"
**Solution:**
- Make sure you have at least one location in Google Business Profile
- Reviews might take a few minutes to sync from Google

### Problem: Charts aren't showing
**Solution:**
- Wait a few seconds for data to load
- Click "Refresh Data" button
- Check browser's developer console (F12) for errors

### Problem: "Failed to authenticate"
**Solution:**
- Check your Client ID and Client Secret in `.env` are correct
- Make sure you added the Redirect URI in Google Cloud Console
- Try clearing cache and authenticating again

---

## 📁 Important Files

| File | Purpose |
|------|---------|
| `.env` | Your Google credentials (keep secret!) |
| `public/google-reviews.php` | The dashboard (what you see) |
| `public/oauth-callback.php` | How you authenticate |
| `logs/app.log` | Error logs if something goes wrong |

---

## 🔐 Security Notes

✅ **Secure:**
- Your Google credentials are in `.env` (not in code)
- Login tokens are stored securely
- Requires authentication to view

⚠️ **Important:**
- **NEVER** share your `.env` file
- **NEVER** commit `.env` to version control
- Keep your Client Secret secret!

---

## 🎓 Next Steps

1. ✅ Complete the 5-step setup above
2. ✅ Click through Google authentication
3. ✅ View your reviews dashboard
4. ✅ Share the link with your team (they need to log in first)
5. ✅ Check reviews weekly/daily as needed

---

## 📚 Want More Details?

- **Quick Reference**: `GOOGLE_BUSINESS_PROFILE_QUICKSTART.md`
- **Full Setup Guide**: `GOOGLE_BUSINESS_PROFILE_SETUP.md`
- **Technical Details**: `documentation/GOOGLE_BUSINESS_PROFILE_API.md`
- **Complete Summary**: `GOOGLE_BUSINESS_PROFILE_SUMMARY.md`

---

## 📞 Stuck?

1. Check `logs/app.log` for error details
2. Re-read the troubleshooting section above
3. Try running oauth-callback.php again
4. Check that your .env file is saved correctly

---

**You're all set!** 🚀 Your Google Reviews Analytics dashboard is now ready to use.

Access anytime at: `http://localhost:8080/lag-int/google-reviews.php`

*(Must be logged in first)*