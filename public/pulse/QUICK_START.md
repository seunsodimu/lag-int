# Pulse Display - Quick Start Guide

## What's New?
The Pulse display now supports **explicit location configuration** instead of automatic geolocation detection. You can specify exactly which city's time and weather should be displayed.

## Get Started in 3 Steps

### Step 1: Access the Location Configuration
Open the file: `public/pulse/json/location.json`

### Step 2: Change the Location (Optional)
The default location is **Los Angeles**. To change it:

**Example 1: Change to New York**
```json
{
  "city": "New York",
  "state": "NY",
  "country": "USA",
  "latitude": 40.7128,
  "longitude": -74.0060,
  "timezone": "America/New_York"
}
```

**Example 2: Use a Preset**
- See `LOCATION_PRESETS.md` for 50+ pre-configured locations
- Copy and paste any preset directly into `location.json`

### Step 3: Refresh the Display
1. Save the file
2. Open/refresh browser: `http://localhost:8080/pulse/`
3. Time and weather automatically update to the new location

## What Changed?

| Aspect | Before | After |
|--------|--------|-------|
| Location Detection | Browser geolocation | JSON configuration file |
| Time Zone | Browser's timezone | Location's timezone |
| Weather | Browser's location | Configured location coordinates |
| Configuration | Automatic | Explicit & Controllable |

## Configuration Parameters Explained

```json
{
  "city": "Name of the city",
  "state": "State/Province abbreviation (optional)",
  "country": "Country name",
  "latitude": 40.1234,           // Geographic latitude
  "longitude": -74.5678,         // Geographic longitude
  "timezone": "America/New_York" // IANA timezone identifier
}
```

⚠️ **Important**: `latitude`, `longitude`, and `timezone` are used by the system to fetch weather and display correct time.

## Find Coordinates & Timezone

### Quick Lookup
- **Coordinates**: Right-click on Google Maps → see coordinates at bottom
- **Timezone**: Check Wikipedia's "List of tz database time zones"

### Useful Links
- **TimeAndDate.com**: https://www.timeanddate.com/time/zones/
- **IANA Timezones**: https://en.wikipedia.org/wiki/List_of_tz_database_time_zones
- **Coordinates**: https://latitude.to/

## Common Configuration Examples

### USA Offices
```json
{ "city": "New York", "state": "NY", "country": "USA", "latitude": 40.7128, "longitude": -74.0060, "timezone": "America/New_York" }
{ "city": "Chicago", "state": "IL", "country": "USA", "latitude": 41.8781, "longitude": -87.6298, "timezone": "America/Chicago" }
{ "city": "San Francisco", "state": "CA", "country": "USA", "latitude": 37.7749, "longitude": -122.4194, "timezone": "America/Los_Angeles" }
```

### International Offices
```json
{ "city": "London", "state": "England", "country": "UK", "latitude": 51.5074, "longitude": -0.1278, "timezone": "Europe/London" }
{ "city": "Tokyo", "country": "Japan", "latitude": 35.6762, "longitude": 139.6503, "timezone": "Asia/Tokyo" }
{ "city": "Singapore", "country": "Singapore", "latitude": 1.3521, "longitude": 103.8198, "timezone": "Asia/Singapore" }
```

## Troubleshooting

### Time not updating?
- ✅ Verify timezone is in IANA format (e.g., `America/New_York`)
- ✅ Check JSON syntax is valid
- ✅ Hard refresh browser (Ctrl+Shift+R)

### Weather not showing?
- ✅ Verify latitude and longitude are valid
- ✅ Check internet connection
- ✅ Clear browser cache
- 🌐 Falls back to "Weather unavailable" if API fails

### Changes not appearing?
- ✅ Save the file
- ✅ Hard refresh (Ctrl+Shift+R) - regular refresh may use cache
- ✅ Clear browser cache (Ctrl+Shift+Delete)

## Browser Display

The header displays:
- **Left**: Laguna Pulse logo
- **Center**: Current time for configured location
- **Right**: Weather emoji + temperature, current time

## Files Included

| File | Purpose |
|------|---------|
| `location.json` | Your location configuration |
| `QUICK_START.md` | This guide (fast setup) |
| `LOCATION_CONFIG.md` | Detailed configuration reference |
| `LOCATION_PRESETS.md` | 50+ pre-configured locations |
| `LOCATION_UPDATE_SUMMARY.md` | Technical changes overview |

## Next Steps

- **Quick Setup**: Follow steps above ✓
- **Need Different Location?**: See `LOCATION_PRESETS.md`
- **Detailed Reference**: See `LOCATION_CONFIG.md`
- **Technical Details**: See `LOCATION_UPDATE_SUMMARY.md`

## Support

For detailed information about:
- **Configuration Options**: See `LOCATION_CONFIG.md`
- **Available Presets**: See `LOCATION_PRESETS.md`
- **How it Works**: See `LOCATION_UPDATE_SUMMARY.md`

---

**Have your location configured and refreshed? You're done!** 🎉

The display will now show the correct time and weather for your specified location every time you visit.