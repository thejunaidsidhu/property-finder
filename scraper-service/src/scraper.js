const { chromium } = require('playwright');
const proxyManager = require('./proxy-manager');
const fingerprint = require('./fingerprint');

class PropertyGuruScraper {
  constructor() {
    this.timeout = parseInt(process.env.PLAYWRIGHT_TIMEOUT) || 60000;
    this.navigationTimeout = parseInt(process.env.PLAYWRIGHT_NAVIGATION_TIMEOUT) || 30000;
    this.headless = process.env.PLAYWRIGHT_HEADLESS !== 'false';
  }

  /**
   * Configure resource blocking
   */
  setupResourceBlocking(page) {
    const blockImages = process.env.BLOCK_IMAGES === 'true';
    const blockStylesheets = process.env.BLOCK_STYLESHEETS === 'true';
    const blockFonts = process.env.BLOCK_FONTS === 'true';
    const blockMedia = process.env.BLOCK_MEDIA === 'true';

    page.route('**/*', (route) => {
      const resourceType = route.request().resourceType();
      
      if (blockImages && resourceType === 'image') {
        route.abort();
      } else if (blockStylesheets && resourceType === 'stylesheet') {
        route.abort();
      } else if (blockFonts && resourceType === 'font') {
        route.abort();
      } else if (blockMedia && ['media', 'websocket'].includes(resourceType)) {
        route.abort();
      } else {
        route.continue();
      }
    });
  }

  /**
   * Wait for Cloudflare challenge (if present)
   */
  async waitForCloudflare(page) {
    try {
      console.log('Checking for Cloudflare challenge...');
      
      // Wait a bit for page to start loading
      await page.waitForTimeout(2000);
      
      // Check for Cloudflare indicators
      const cloudflareSelectors = [
        '.cf-browser-verification',
        'text=Checking your browser',
        'text=Just a moment',
        '#challenge-form',
        '.cf-im-under-attack'
      ];
      
      let cfDetected = false;
      for (const selector of cloudflareSelectors) {
        try {
          await page.waitForSelector(selector, { timeout: 5000 });
          cfDetected = true;
          console.log(`Cloudflare challenge detected: ${selector}`);
          break;
        } catch (e) {
          // Continue checking other selectors
        }
      }
      
      if (cfDetected) {
        console.log('Waiting for Cloudflare challenge to complete (max 90 seconds)...');
        // Wait for the selector to appear, then wait for it to disappear
        await page.waitForSelector('#__NEXT_DATA__', { state: 'attached', timeout: 90000 });
        console.log('Cloudflare challenge completed');
      } else {
        console.log('No Cloudflare challenge detected');
      }
    } catch (error) {
      console.log('Cloudflare wait error (may have passed):', error.message);
      // Don't fail here - continue and let the main selector wait handle it
    }
  }

  /**
   * Add human-like delays
   */
  async humanDelay(min = 500, max = 2000) {
    const delay = Math.floor(Math.random() * (max - min + 1)) + min;
    await new Promise(resolve => setTimeout(resolve, delay));
  }

  /**
   * Scrape PropertyGuru page
   */
  async scrape(url, options = {}) {
    const {
      waitForSelector = '#__NEXT_DATA__',
      extractScript = null,
      sessionId = null
    } = options;

    let context = null;
    let page = null;

    try {
      const proxy = proxyManager.getProxy(sessionId);
      const contextOptions = fingerprint.getContextOptions();

      console.log(`Launching browser with proxy: ${proxyManager.getProxyUrl().replace(/:[^:@]+@/, ':****@')}`);

      // Set user data dir for www-data (writable location)
      const userDataDir = process.env.PLAYWRIGHT_USER_DATA_DIR || '/var/www/playwright-data';

      // Use launchPersistentContext instead of launch when using userDataDir
      context = await chromium.launchPersistentContext(userDataDir, {
        headless: this.headless,
        proxy: proxy,
        timeout: this.timeout,
        ...contextOptions,  // Merge fingerprint options
        args: [
          '--no-sandbox',
          '--disable-setuid-sandbox',
          '--disable-dev-shm-usage',
          '--disable-gpu'
          // Remove --user-data-dir from args since it's the first parameter
        ]
      });

      page = await context.newPage();

      // Set timeouts
      page.setDefaultTimeout(this.timeout);
      page.setDefaultNavigationTimeout(this.navigationTimeout);

      // Setup resource blocking
      this.setupResourceBlocking(page);

      // Navigate with human-like behavior
      await this.humanDelay(300, 800);
      await page.goto(url, {
        waitUntil: 'commit',  // Changed from 'domcontentloaded' to 'commit' for faster navigation
        timeout: this.navigationTimeout,  // Add explicit timeout
        referer: 'https://www.google.com/'
      });

      // Wait for Cloudflare if present
      await this.waitForCloudflare(page);

      // Wait for specific selector if provided (use 'attached' state for script tags like #__NEXT_DATA__)
      if (waitForSelector) {
        let selectorFound = false;
        try {
          await page.waitForSelector(waitForSelector, { state: 'attached', timeout: 120000 }); // Increased to 120 seconds (2 minutes)
          selectorFound = true;
          console.log(`✓ Selector ${waitForSelector} found`);
        } catch (error) {
          console.warn(`✗ Selector ${waitForSelector} timeout after 120s`);
          
          // Debug: Get page info to understand what's happening
          try {
            const pageTitle = await page.title();
            const pageUrl = page.url();
            const pageHtml = await page.content();
            const hasCloudflare = pageHtml.includes('cf-browser-verification') || 
                                  pageHtml.includes('Checking your browser') ||
                                  pageHtml.includes('Just a moment') ||
                                  pageHtml.includes('cf-challenge');
            
            console.log(`Page Title: ${pageTitle}`);
            console.log(`Page URL: ${pageUrl}`);
            console.log(`Has Cloudflare: ${hasCloudflare}`);
            console.log(`HTML Length: ${pageHtml.length}`);
            console.log(`HTML Preview (first 500 chars): ${pageHtml.substring(0, 500)}`);
            
            // If Cloudflare challenge detected, throw specific error
            if (hasCloudflare) {
              throw new Error(`Cloudflare challenge detected. Page stuck in verification. URL: ${pageUrl}`);
            }
          } catch (debugError) {
            console.error('Error getting page debug info:', debugError.message);
          }
          
          // If selector not found, throw error to fail the scrape
          if (!selectorFound) {
            throw error; // Re-throw to fail the scrape
          }
        }
      }

      // Additional wait for network to be idle (optional, non-blocking)
      await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {
        console.log('Network idle timeout, continuing...');
      });

      // Extract data
      let html = await page.content();
      let jsonData = null;

      if (extractScript) {
        jsonData = await page.evaluate((script) => {
          try {
            return eval(script);
          } catch (e) {
            return null;
          }
        }, extractScript);
      } else {
        // Default: extract __NEXT_DATA__
        jsonData = await page.evaluate(() => {
          const script = document.querySelector('#__NEXT_DATA__');
          if (script) {
            return JSON.parse(script.textContent);
          }
          return null;
        });
      }

      // Get proxy IP (if possible)
      const proxyInfo = await page.evaluate(() => {
        // This will be set by the proxy, may not always be available
        return null;
      }).catch(() => null);

      return {
        success: true,
        html: html,
        json: jsonData,
        metadata: {
          url: url,
          proxyUsed: `${proxyManager.host}:${proxyManager.port}`,
          sessionId: sessionId,
          timestamp: new Date().toISOString()
        }
      };

    } catch (error) {
      console.error('Scraping error:', error.message);
      console.error('Error stack:', error.stack);
      return {
        success: false,
        error: error.message,
        stack: process.env.NODE_ENV === 'development' ? error.stack : undefined,
        metadata: {
          url: url,
          proxyUsed: `${proxyManager.host}:${proxyManager.port}`,
          sessionId: sessionId,
          timestamp: new Date().toISOString()
        }
      };
    } finally {
      if (page) {
        try {
          await page.close();
        } catch (e) {
          console.error('Error closing page:', e.message);
        }
      }
      if (context) {
        try {
          await context.close();
        } catch (e) {
          console.error('Error closing context:', e.message);
        }
      }
    }
  }
}

module.exports = new PropertyGuruScraper();

