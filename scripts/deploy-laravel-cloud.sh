#!/bin/bash

echo "🚀 Развертывание Lark AI Bot на Laravel Cloud..."

# Проверяем, что мы в правильной директории
if [ ! -f "package.json" ]; then
    echo "❌ Ошибка: package.json не найден. Запустите скрипт из корневой директории проекта."
    exit 1
fi

# Проверяем наличие Laravel CLI
if ! command -v laravel &> /dev/null; then
    echo "❌ Laravel CLI не установлен. Установите его:"
    echo "composer global require laravel/installer"
    exit 1
fi

# Проверяем авторизацию в Laravel Cloud
echo "🔐 Проверка авторизации в Laravel Cloud..."
if ! laravel cloud:auth:status &> /dev/null; then
    echo "❌ Не авторизованы в Laravel Cloud. Выполните:"
    echo "laravel cloud:auth:login"
    exit 1
fi

# Создаем приложение в Laravel Cloud (если не существует)
echo "📱 Создание приложения в Laravel Cloud..."
APP_NAME="lark-ai-bot-$(date +%s)"

if ! laravel cloud:apps:create "$APP_NAME" --region=us-east-1; then
    echo "⚠️  Приложение уже существует или ошибка создания. Продолжаем..."
fi

# Настраиваем переменные окружения
echo "⚙️  Настройка переменных окружения..."

# Запрашиваем у пользователя необходимые переменные
read -p "Введите Lark App ID: " LARK_APP_ID
read -p "Введите Lark App Secret: " LARK_APP_SECRET
read -p "Введите OpenAI API Key: " OPENAI_API_KEY
read -s -p "Введите верификационный токен (или нажмите Enter для автогенерации): " WEBHOOK_TOKEN

if [ -z "$WEBHOOK_TOKEN" ]; then
    WEBHOOK_TOKEN=$(openssl rand -hex 32)
    echo "🔑 Сгенерирован токен: $WEBHOOK_TOKEN"
fi

# Устанавливаем переменные окружения
laravel cloud:env:set LARK_APP_ID="$LARK_APP_ID" --app="$APP_NAME"
laravel cloud:env:set LARK_APP_SECRET="$LARK_APP_SECRET" --app="$APP_NAME"
laravel cloud:env:set OPENAI_API_KEY="$OPENAI_API_KEY" --app="$APP_NAME"
laravel cloud:env:set WEBHOOK_VERIFICATION_TOKEN="$WEBHOOK_TOKEN" --app="$APP_NAME"
laravel cloud:env:set NODE_ENV="production" --app="$APP_NAME"
laravel cloud:env:set PORT="8000" --app="$APP_NAME"

# Устанавливаем webhook URL
WEBHOOK_URL="https://$APP_NAME.laravelcloud.com/webhook"
laravel cloud:env:set LARK_WEBHOOK_URL="$WEBHOOK_URL" --app="$APP_NAME"

echo "✅ Переменные окружения настроены"
echo "🔗 Webhook URL: $WEBHOOK_URL"

# Развертываем приложение
echo "🚀 Развертывание приложения..."
if laravel cloud:deploy --app="$APP_NAME"; then
    echo "✅ Приложение успешно развернуто!"
    echo ""
    echo "📋 Следующие шаги:"
    echo "1. Скопируйте webhook URL: $WEBHOOK_URL"
    echo "2. Перейдите в Lark Open Platform"
    echo "3. В настройках webhook укажите: $WEBHOOK_URL"
    echo "4. Включите 'Установить верификацию подписи'"
    echo "5. Добавьте бота в вашу группу Lark"
    echo ""
    echo "🧪 Тестирование:"
    echo "curl -X POST $WEBHOOK_URL -H 'Content-Type: application/json' -d '{\"type\":\"url_verification\",\"challenge\":\"test123\"}'"
else
    echo "❌ Ошибка при развертывании"
    exit 1
fi
