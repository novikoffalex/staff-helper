#!/bin/bash

echo "🚀 Deploying to Laravel Forge..."

# Check if git is initialized
if [ ! -d ".git" ]; then
    echo "❌ Git not initialized. Please run: git init"
    exit 1
fi

# Add all files
git add .

# Commit changes
git commit -m "Deploy to Forge: $(date)"

# Push to remote (replace with your Forge git URL)
echo "📤 Pushing to Forge..."
git push forge main

echo "✅ Deployment complete!"
echo "🌐 Your bot will be available at: https://your-domain.com"
echo "📡 Webhook URL: https://your-domain.com/webhook.php"
