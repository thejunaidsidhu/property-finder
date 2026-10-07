# Property Scraper Service

Node.js Playwright-based scraping service with Decodo residential proxy support for scraping Cloudflare-protected pages like PropertyGuru.

## Features

- **Playwright with Chromium**: Modern browser automation
- **Decodo Residential Proxy**: Authenticated proxy with Singapore IP targeting
- **Cloudflare Bypass**: Handles Cloudflare challenges automatically
- **Realistic Fingerprints**: Browser fingerprinting to avoid detection
- **Resource Blocking**: Configurable blocking of images, fonts, media
- **Session Management**: Sticky sessions for consistent proxy usage
- **HTTP API**: RESTful endpoint for Laravel integration

## Installation

```bash
cd scraper-service
npm install
```

## Configuration

1. Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```

2. Update `.env` with your Decodo proxy credentials:
```env
DECODO_HOST=gate.decodo.com
DECODO_PORT=10010
DECODO_USER=your_username
DECODO_PASS=your_password
```

## Running

### Development
```bash
npm run dev
```

### Production
```bash
npm start
```

Or with PM2:
```bash
pm2 start src/server.js --name scraper-service
```

## API Endpoints

### Health Check
```
GET /health
```

### Scrape URL
```
POST /scrape
Content-Type: application/json

{
  "url": "https://www.propertyguru.com.sg/...",
  "options": {
    "waitForSelector": "#__NEXT_DATA__",
    "extractScript": "..."
  },
  "sessionId": "optional-session-id"
}
```

## Integration with Laravel

Set in Laravel `.env`:
```env
SCRAPER_SERVICE_URL=http://localhost:3000
SCRAPER_SERVICE_TIMEOUT=120
SCRAPER_USE_QUEUE=true
USE_PLAYWRIGHT_SERVICE=true
```

## Environment Variables

- `PORT`: Server port (default: 3000)
- `NODE_ENV`: Environment (development/production)
- `DECODO_HOST`: Decodo proxy host
- `DECODO_PORT`: Decodo proxy port
- `DECODO_USER`: Decodo username
- `DECODO_PASS`: Decodo password
- `PLAYWRIGHT_HEADLESS`: Run browser in headless mode (true/false)
- `PLAYWRIGHT_TIMEOUT`: Browser timeout in ms
- `PLAYWRIGHT_NAVIGATION_TIMEOUT`: Navigation timeout in ms
- `FINGERPRINT_LOCALE`: Browser locale (default: en-SG)
- `FINGERPRINT_TIMEZONE`: Browser timezone (default: Asia/Singapore)
- `BLOCK_IMAGES`: Block image resources (true/false)
- `BLOCK_STYLESHEETS`: Block CSS resources (true/false)
- `BLOCK_FONTS`: Block font resources (true/false)
- `BLOCK_MEDIA`: Block media resources (true/false)

