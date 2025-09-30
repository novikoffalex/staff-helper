# 🚀 Heroku Deployment Guide

## 📋 **Быстрый деплой на Heroku**

### 1. **Установка Heroku CLI:**
```bash
# Уже установлен у вас
heroku --version
```

### 2. **Логин:**
```bash
heroku login
```

### 3. **Создание приложения:**
```bash
heroku create stuffhelper-lark-bot
```

### 4. **Настройка Environment Variables:**
```bash
heroku config:set LARK_APP_ID="your_lark_app_id"
heroku config:set LARK_APP_SECRET="your_lark_app_secret"
heroku config:set LARK_WEBHOOK_URL="https://stuffhelper-lark-bot.herokuapp.com/webhook.php"
heroku config:set OPENAI_API_KEY="your_openai_api_key"
heroku config:set OPENAI_MODEL="gpt-4"
heroku config:set WEBHOOK_VERIFICATION_TOKEN="your_verification_token"
heroku config:set NODE_ENV="production"
```

### 5. **Деплой:**
```bash
./deploy-heroku.sh
```

## 🧪 **Тестирование**

1. **Health Check:**
   ```bash
   curl https://stuffhelper-lark-bot.herokuapp.com/health.php
   ```

2. **Webhook Test:**
   ```bash
   curl -X POST https://stuffhelper-lark-bot.herokuapp.com/webhook.php \
     -H "Content-Type: application/json" \
     -d '{"type":"url_verification","challenge":"test123"}'
   ```

## 🔄 **Обновление Webhook URL в Lark**

После деплоя обновите webhook URL в Lark Developer Console:
- **Events & Callbacks** → **Request URL:** `https://stuffhelper-lark-bot.herokuapp.com/webhook.php`

## 📱 **Добавление бота в чат**

1. В Lark найдите бота по имени "Тест"
2. Добавьте в группу
3. Напишите: `@Тест привет`

## 🛠️ **Логи**

Просмотр логов в Heroku:
```bash
heroku logs --tail
```

## 💰 **Стоимость**

- **Бесплатно** для небольших проектов (с ограничениями)
- **$7/месяц** для коммерческих проектов
- **Автоматическое масштабирование**

## 🔐 **SSL**

Heroku автоматически предоставляет SSL сертификаты.

## 📝 **Важные файлы**

- `composer.json` - зависимости PHP
- `Procfile` - команда запуска
- `deploy-heroku.sh` - скрипт деплоя
