#!/bin/bash

echo "🚀 Deploying to Heroku via GitLab..."

# Check if we're in a GitLab CI environment
if [ -n "$CI" ]; then
    echo "✅ Running in GitLab CI"
    
    # Install Heroku CLI
    curl https://cli-assets.heroku.com/install.sh | sh
    
    # Login to Heroku
    echo "$HEROKU_API_KEY" | heroku auth:token
    
    # Deploy
    git push https://heroku:$HEROKU_API_KEY@git.heroku.com/staff-helper.git HEAD:main
else
    echo "❌ Not running in GitLab CI"
    echo "Please run this script in GitLab CI/CD pipeline"
    exit 1
fi

echo "✅ Deployment complete!"
echo "🌐 Your bot will be available at: https://staff-helper.herokuapp.com"
