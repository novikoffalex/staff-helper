#!/bin/bash

echo "🚀 Deploying to Heroku..."

# Check if Heroku CLI is installed
if ! command -v heroku &> /dev/null; then
    echo "❌ Heroku CLI not found. Please install it first."
    exit 1
fi

# Login to Heroku
echo "🔐 Logging in to Heroku..."
heroku login

# Create app (if not exists)
echo "📱 Creating Heroku app..."
heroku create stuffhelper-lark-bot

# Set environment variables
echo "🔧 Setting environment variables..."
heroku config:set LARK_APP_ID="your_lark_app_id"
heroku config:set LARK_APP_SECRET="your_lark_app_secret"
heroku config:set LARK_WEBHOOK_URL="https://stuffhelper-lark-bot.herokuapp.com/webhook.php"
heroku config:set OPENAI_API_KEY="your_openai_api_key"
heroku config:set OPENAI_MODEL="gpt-4"
heroku config:set WEBHOOK_VERIFICATION_TOKEN="your_verification_token_here"
heroku config:set NODE_ENV="production"

# Deploy
echo "📤 Deploying to Heroku..."
git add .
git commit -m "Deploy to Heroku"
git push heroku main

echo "✅ Deployment complete!"
echo "🌐 Your bot will be available at: https://stuffhelper-lark-bot.herokuapp.com"
echo "📡 Webhook URL: https://stuffhelper-lark-bot.herokuapp.com/webhook.php"
