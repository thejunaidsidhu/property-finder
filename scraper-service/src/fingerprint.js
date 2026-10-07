/**
 * Generate realistic browser fingerprints
 */
class FingerprintGenerator {
  constructor() {
    this.locale = process.env.FINGERPRINT_LOCALE || 'en-SG';
    this.timezone = process.env.FINGERPRINT_TIMEZONE || 'Asia/Singapore';
  }

  /**
   * Get realistic User-Agent for Singapore
   */
  getUserAgent() {
    const userAgents = [
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
      'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
      'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ];
    return userAgents[Math.floor(Math.random() * userAgents.length)];
  }

  /**
   * Get viewport size
   */
  getViewport() {
    const viewports = [
      { width: 1920, height: 1080 },
      { width: 1366, height: 768 },
      { width: 1536, height: 864 }
    ];
    return viewports[Math.floor(Math.random() * viewports.length)];
  }

  /**
   * Get browser context options with fingerprint
   */
  getContextOptions() {
    const viewport = this.getViewport();
    
    return {
      viewport: viewport,
      userAgent: this.getUserAgent(),
      locale: this.locale,
      timezoneId: this.timezone,
      permissions: ['geolocation'],
      geolocation: { latitude: 1.3521, longitude: 103.8198 }, // Singapore coordinates
      colorScheme: 'light',
      extraHTTPHeaders: {
        'Accept-Language': `${this.locale},en;q=0.9`,
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Encoding': 'gzip, deflate, br',
        'Referer': 'https://www.google.com/',
        'Sec-Fetch-Dest': 'document',
        'Sec-Fetch-Mode': 'navigate',
        'Sec-Fetch-Site': 'cross-site',
        'Upgrade-Insecure-Requests': '1'
      }
    };
  }
}

module.exports = new FingerprintGenerator();

