const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '..', '.env') });

class DecodoProxyManager {
  constructor() {
    this.host = process.env.DECODO_HOST;
    this.port = process.env.DECODO_PORT;
    this.username = process.env.DECODO_USER;
    this.password = process.env.DECODO_PASS;
    
    if (!this.host || !this.port || !this.username || !this.password) {
      console.error('Missing proxy configuration in .env');
    }
  }

  /**
   * Get proxy configuration for Playwright
   * @param {string} sessionId - Optional session ID for sticky sessions
   * @returns {Object} Proxy configuration
   */
  getProxy(sessionId = null) {
    return {
      server: `http://${this.host}:${this.port}`,
      username: this.username,
      password: this.password
    };
  }

  /**
   * Get proxy URL string (for logging/debugging)
   */
  getProxyUrl() {
    return `http://${this.username}:${this.password}@${this.host}:${this.port}`;
  }
}

module.exports = new DecodoProxyManager();

