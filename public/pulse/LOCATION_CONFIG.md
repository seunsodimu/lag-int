# Pulse Display Location Configuration

## Overview
The Laguna Pulse display now supports explicit location configuration. Instead of automatically detecting the browser's location, you can specify a location in the JSON configuration, and the time and weather will be displayed for that location.

## Configuration File
Location settings are stored in `json/location.json`.

### Configuration Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `city` | string | Yes | City name |
| `state` | string | No | State or province abbreviation |
| `country` | string | No | Country name |
| `latitude` | number | Yes | Geographic latitude coordinate |
| `longitude` | number | Yes | Geographic longitude coordinate |
| `timezone` | string | Yes | IANA timezone identifier (e.g., `America/Los_Angeles`) |

### Example Configuration

```json
{
  "city": "Los Angeles",
  "state": "CA",
  "country": "USA",
  "latitude": 34.0522,
  "longitude": -118.2437,
  "timezone": "America/Los_Angeles"
}
```

## Features

### 1. **Time Display**
- Displays the current time for the configured location
- Updates every second
- Uses the timezone specified in the configuration
- 24-hour format with AM/PM indicator

### 2. **Weather Display**
- Fetches real-time weather data from Open-Meteo API (free, no API key required)
- Shows current temperature in Celsius
- Displays weather condition with emoji indicators:
  - `☀️` Clear skies
  - `⛅` Mainly clear to overcast
  - `🌫️` Fog
  - `🌦️` Drizzle
  - `🌧️` Rain
  - `❄️` Snow
  - `⛈️` Thunderstorm
  - `🌡️` Unknown conditions

## Updating the Location

### To Change the Display Location:

1. Open `public/pulse/json/location.json`
2. Update the location parameters:

**Example: Changing to New York**

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

**Example: Changing to London**

```json
{
  "city": "London",
  "state": "England",
  "country": "UK",
  "latitude": 51.5074,
  "longitude": -0.1278,
  "timezone": "Europe/London"
}
```

**Example: Changing to Tokyo**

```json
{
  "city": "Tokyo",
  "country": "Japan",
  "latitude": 35.6762,
  "longitude": 139.6503,
  "timezone": "Asia/Tokyo"
}
```

3. Save the file
4. Refresh the browser - the display will automatically use the new location

## Finding Coordinates and Timezone

### Coordinate Lookup:
- **Google Maps**: Right-click on location, coordinates appear in the popup
- **OpenStreetMap**: Right-click on location, view coordinates
- **Latitude.to**: Online search tool for coordinates

### Timezone Lookup:
- **IANA Timezone Database**: https://en.wikipedia.org/wiki/List_of_tz_database_time_zones
- **TimeAndDate.com**: https://www.timeanddate.com/time/zones/

### Common Timezones:
- US Eastern: `America/New_York`
- US Central: `America/Chicago`
- US Mountain: `America/Denver`
- US Pacific: `America/Los_Angeles`
- UK: `Europe/London`
- Europe Central: `Europe/Paris`
- India: `Asia/Kolkata`
- China: `Asia/Shanghai`
- Japan: `Asia/Tokyo`
- Australia Eastern: `Australia/Sydney`

## Troubleshooting

### Weather Not Displaying
- Check internet connectivity
- Verify coordinates are accurate
- Open browser console (F12) for error messages
- Fallback displays "🌐 Weather unavailable"

### Time Not Updating
- Check that timezone is valid (IANA format)
- Verify configuration file is loading properly
- Check browser console for errors

### Configuration Not Loading
- Verify `json/location.json` exists
- Check file permissions are readable
- Ensure JSON syntax is valid (use JSON validator)
- Clear browser cache and hard refresh (Ctrl+Shift+R)

## Browser Support
- Modern browsers with JSON fetch support
- Requires geolocation API disabled (not needed with explicit location)
- Best viewed on latest Chrome, Firefox, Safari, or Edge

## API Information
- **Weather API**: Open-Meteo (https://open-meteo.com/)
- **Rate Limit**: No official limit for non-commercial use
- **Updates**: Weather data refreshes on page load
- **Accuracy**: Temperature and weather code from nearest weather station