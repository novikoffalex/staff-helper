#!/bin/bash

echo "🚀 Deploying to Railway..."

# Check if Railway CLI is installed
if ! command -v railway &> /dev/null; then
    echo "❌ Railway CLI not found. Installing..."
    npm install -g @railway/cli
fi

# Login to Railway
echo "🔐 Logging in to Railway..."
railway login

# Link to project
echo "🔗 Linking to Railway project..."
railway link

# Deploy
echo "📤 Deploying to Railway..."
railway up

echo "✅ Deployment complete!"
echo "🌐 Your bot will be available at: https://your-app.railway.app"
echo "📡 Webhook URL: https://your-app.railway.app/webhook.php"
