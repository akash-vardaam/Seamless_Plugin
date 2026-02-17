#!/bin/bash

# Seamless React Events - WordPress Plugin Setup Script
# This script helps copy the React app build to the WordPress plugin

echo "======================================="
echo "Seamless React Events - Setup Script"
echo "======================================="
echo ""

# Check if running from correct directory
if [ ! -f "seamless.php" ]; then
    echo "❌ Error: seamless.php not found!"
    echo "Please run this script from the plugin directory."
    exit 1
fi

echo "✓ Plugin directory detected"
echo ""

# Create react-app directory if it doesn't exist
if [ ! -d "react-app" ]; then
    echo "📁 Creating react-app directory..."
    mkdir -p react-app
    echo "✓ Directory created"
fi

# Check if dist folder exists in parent directory
if [ ! -d "../dist" ]; then
    echo ""
    echo "❌ Error: React app dist folder not found at ../dist"
    echo ""
    echo "Please build the React app first:"
    echo "  1. cd .."
    echo "  2. npm install"
    echo "  3. npm run build"
    echo ""
    exit 1
fi

echo "📦 Found React app build at ../dist"
echo ""

# Copy dist folder
echo "📋 Copying dist folder..."
rm -rf react-app/dist 2>/dev/null
cp -r ../dist react-app/

if [ $? -eq 0 ]; then
    echo "✓ Files copied successfully!"
else
    echo "❌ Error copying files!"
    exit 1
fi

echo ""
echo "✅ Setup complete!"
echo ""
echo "Next steps:"
echo "1. Copy the 'seamless' folder to your WordPress plugins directory:"
echo "   wp-content/plugins/seamless/"
echo ""
echo "2. Activate the plugin in WordPress Admin → Plugins"
echo ""
echo "3. Configure API endpoint:"
echo "   WordPress Admin → Settings → React Events"
echo ""
echo "4. Add shortcode to a page:"
echo "   [seamless_react_events]"
echo ""
echo "5. Ensure CORS is enabled on your API server"
echo ""
echo "======================================="
