const path = require('path');

// Load .env from scraper-service directory (absolute path)
require('dotenv').config({ path: path.join(__dirname, '..', '.env') });

const express = require('express');
const cors = require('cors');
const scraper = require('./scraper');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json({ limit: '10mb' }));

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    service: 'property-scraper-service',
    timestamp: new Date().toISOString()
  });
});

// Main scraping endpoint
app.post('/scrape', async (req, res) => {
  const { url, options = {}, sessionId } = req.body;

  if (!url) {
    return res.status(400).json({
      success: false,
      error: 'URL is required'
    });
  }

  try {
    console.log(`Scraping request received: ${url}`);
    const result = await scraper.scrape(url, {
      ...options,
      sessionId: sessionId || `session-${Date.now()}`
    });

    if (result.success) {
      res.json(result);
    } else {
      console.error('Scraping failed:', result.error);
      res.status(500).json(result);
    }
  } catch (error) {
    console.error('Server error:', error);
    res.status(500).json({
      success: false,
      error: error.message,
      stack: process.env.NODE_ENV === 'development' ? error.stack : undefined,
      metadata: {
        timestamp: new Date().toISOString()
      }
    });
  }
});

// Error handler
app.use((err, req, res, next) => {
  console.error('Unhandled error:', err);
  res.status(500).json({
    success: false,
    error: err.message,
    metadata: {
      timestamp: new Date().toISOString()
    }
  });
});

// Start server
app.listen(PORT, () => {
  console.log(`Scraper service running on port ${PORT}`);
  console.log(`Environment: ${process.env.NODE_ENV || 'development'}`);
  console.log(`Proxy: ${process.env.DECODO_HOST}:${process.env.DECODO_PORT}`);
  console.log(`User data dir: ${process.env.PLAYWRIGHT_USER_DATA_DIR || '/var/www/playwright-data'}`);
});

// Handle process errors
process.on('unhandledRejection', (reason, promise) => {
  console.error('Unhandled Rejection at:', promise, 'reason:', reason);
  process.exit(1);
});

process.on('uncaughtException', (error) => {
  console.error('Uncaught Exception:', error);
  process.exit(1);
});
