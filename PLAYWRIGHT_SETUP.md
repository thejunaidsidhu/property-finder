# Playwright Scraper Service - Setup Guide

## Overview

This implementation adds a Node.js Playwright-based scraping service with Decodo residential proxy support. The service is designed to reliably scrape Cloudflare-protected pages like PropertyGuru.

## Architecture

- **Node.js Service**: Standalone Express server running Playwright
- **Laravel Integration**: Service class and queue job for seamless integration
- **Feature Flag**: Can switch between old (Browsershot) and new (Playwright) methods
- **Backward Compatible**: Existing code is preserved and commented, not removed

## Installation Steps

### 1. Install Node.js Service Dependencies

```bash
cd scraper-service
npm install
```

This will install:
- `playwright` (includes Chromium)
- `express` (HTTP server)
- `dotenv` (environment variables)
- `cors` (CORS support)

### 2. Configure Environment Variables

#### Node.js Service (scraper-service/.env)

Copy the example file:
```bash
cd scraper-service
cp .env.example .env
```

The `.env` file should contain:
```env
PORT=3000
NODE_ENV=production
DECODO_HOST=gate.decodo.com
DECODO_PORT=10010
DECODO_USER=sp9w8ahl5r
DECODO_PASS=l79WNJ_t3mWudu8xrk
PLAYWRIGHT_HEADLESS=true
PLAYWRIGHT_TIMEOUT=60000
PLAYWRIGHT_NAVIGATION_TIMEOUT=30000
FINGERPRINT_LOCALE=en-SG
FINGERPRINT_TIMEZONE=Asia/Singapore
BLOCK_IMAGES=false
BLOCK_STYLESHEETS=false
BLOCK_FONTS=true
BLOCK_MEDIA=true
```

#### Laravel (.env)

Add these variables to your Laravel `.env` file:
```env
# Playwright Scraper Service
SCRAPER_SERVICE_URL=http://localhost:3000
SCRAPER_SERVICE_TIMEOUT=120
SCRAPER_USE_QUEUE=true
USE_PLAYWRIGHT_SERVICE=false
```

**Note**: Set `USE_PLAYWRIGHT_SERVICE=true` to enable the new service. Keep it `false` to use the old Browsershot method.

### 3. Start the Node.js Service

#### Development Mode
```bash
cd scraper-service
npm run dev
```

#### Production Mode
```bash
cd scraper-service
npm start
```

#### With PM2 (Recommended for Production)
```bash
pm2 start scraper-service/src/server.js --name scraper-service
pm2 save
pm2 startup  # Follow instructions to enable on boot
```

### 4. Configure Laravel Queue (Optional)

If using queue jobs (`SCRAPER_USE_QUEUE=true`), start the queue worker:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

Or with supervisor/systemd for production.

### 5. Test the Service

#### Test Node.js Service Health
```bash
curl http://localhost:3000/health
```

#### Test Scraping Endpoint
```bash
curl -X POST http://localhost:3000/scrape \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://www.propertyguru.com.sg/property-for-sale",
    "options": {
      "waitForSelector": "#__NEXT_DATA__"
    }
  }'
```

## Switching Between Methods

### Use New Playwright Service
Set in `.env`:
```env
USE_PLAYWRIGHT_SERVICE=true
SCRAPER_USE_QUEUE=true  # Optional: use queue jobs
```

### Use Old Browsershot Method (Default)
Set in `.env`:
```env
USE_PLAYWRIGHT_SERVICE=false
```

The old code in `PropertyFinderController.php` is preserved and will be used when this flag is `false`.

## File Structure

```
property-finder/
├── scraper-service/              # NEW: Node.js service
│   ├── src/
│   │   ├── server.js            # Express HTTP server
│   │   ├── scraper.js           # Playwright scraping logic
│   │   ├── proxy-manager.js     # Decodo proxy handling
│   │   └── fingerprint.js       # Browser fingerprinting
│   ├── package.json
│   ├── .env.example
│   └── README.md
│
├── app/
│   ├── Services/
│   │   └── PlaywrightScraperService.php  # NEW: Laravel service wrapper
│   ├── Jobs/
│   │   └── ScrapePropertyGuruJob.php     # NEW: Queue job
│   └── Http/Controllers/
│       └── PropertyFinderController.php  # UPDATED: Feature flag added
│
└── config/
    └── scraper.php               # NEW: Configuration file
```

## Key Features

### 1. Decodo Proxy Integration
- Authenticated proxy with username/password
- Single endpoint configuration (port 10010)
- Session management for sticky sessions

### 2. Cloudflare Bypass
- Automatic detection of Cloudflare challenges
- Waits for challenge completion
- Realistic browser fingerprints

### 3. Browser Fingerprinting
- Realistic User-Agents
- Singapore locale and timezone
- Proper viewport sizes
- Realistic HTTP headers

### 4. Resource Blocking
- Configurable blocking of images, fonts, media
- Reduces bandwidth and improves speed
- Can be configured via environment variables

### 5. Error Handling
- Automatic retries
- Graceful timeout handling
- Comprehensive logging

## Troubleshooting

### Service Not Starting
- Check Node.js version: `node --version` (should be 16+)
- Check if port 3000 is available: `lsof -i :3000`
- Check `.env` file exists in `scraper-service/` directory

### Proxy Connection Issues
- Verify Decodo credentials in `.env`
- Test proxy manually: `curl --proxy http://user:pass@gate.decodo.com:10010 https://api.ipify.org`
- Check Decodo dashboard for account status

### Scraping Failures
- Check Laravel logs: `storage/logs/laravel.log`
- Check Node.js service logs (console output or PM2 logs)
- Verify PropertyGuru URL is accessible
- Check if Cloudflare challenge is being detected

### Queue Jobs Not Processing
- Ensure queue worker is running: `php artisan queue:work`
- Check queue connection in `.env`: `QUEUE_CONNECTION=database` or `redis`
- Check `failed_jobs` table for errors

## Migration Notes

- **Old code is preserved**: The original Browsershot code in `PropertyFinderController.php` is still there, just behind a feature flag
- **No breaking changes**: Default behavior uses old method until flag is enabled
- **Gradual migration**: Test with `USE_PLAYWRIGHT_SERVICE=true` on staging first
- **Rollback**: Simply set `USE_PLAYWRIGHT_SERVICE=false` to revert

## Performance Considerations

- **Resource blocking**: Enable `BLOCK_IMAGES=true` and `BLOCK_FONTS=true` for faster scraping
- **Headless mode**: Keep `PLAYWRIGHT_HEADLESS=true` for production
- **Timeout settings**: Adjust `PLAYWRIGHT_TIMEOUT` based on network conditions
- **Queue processing**: Use queue jobs for better scalability

## Security Notes

- **Never commit `.env` files**: Already in `.gitignore`
- **Proxy credentials**: Keep Decodo credentials secure
- **Service URL**: In production, use internal network or add authentication

## Next Steps

1. Test the service with a single client request
2. Monitor logs for any issues
3. Gradually enable for all clients
4. Monitor proxy usage in Decodo dashboard
5. Optimize resource blocking settings based on needs

