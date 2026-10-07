#!/bin/bash
# Production setup for Node.js symlinks (Browsershot / Puppeteer)
# Run ONCE with sudo: sudo bash setup-node-symlinks.sh

set -e

# CHANGE THIS to your server user + node version
NODE_DIR="/home/raffay/.nvm/versions/node/v24.12.0/bin"

NODE_BINARY="$NODE_DIR/node"
NPM_BINARY="$NODE_DIR/npm"

if [ ! -x "$NODE_BINARY" ] || [ ! -x "$NPM_BINARY" ]; then
    echo "❌ Node or npm not found in: $NODE_DIR"
    exit 1
fi

echo "✅ Found Node binaries:"
echo "  node -> $NODE_BINARY"
echo "  npm  -> $NPM_BINARY"

# Create system-wide symlinks
echo "🔗 Creating system-wide symlinks..."
ln -sf "$NODE_BINARY" /usr/local/bin/node
ln -sf "$NPM_BINARY" /usr/local/bin/npm

# Verify
echo ""
echo "🔍 Verifying:"
/usr/local/bin/node --version
/usr/local/bin/npm --version

# Test as www-data (CRITICAL)
echo ""
echo "👤 Testing as www-data:"
sudo -u www-data /usr/local/bin/node --version
sudo -u www-data /usr/local/bin/npm --version

echo ""
echo "✅ Node.js is now production-ready for Laravel & Browsershot"
