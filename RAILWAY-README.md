# 🚀 Railway Deployment Guide

## 📋 **Быстрый деплой на Railway**

### 1. **Установка Railway CLI:**
```bash
npm install -g @railway/cli
```

### 2. **Логин:**
```bash
railway login
```

### 3. **Создание проекта:**
```bash
railway init
```

### 4. **Деплой:**
```bash
./deploy-railway.sh
```

## 🔧 **Настройка Environment Variables**

В Railway Dashboard добавьте:
```
LARK_APP_ID=your_lark_app_id
LARK_APP_SECRET=your_lark_app_secret
LARK_WEBHOOK_URL=https://your-app.railway.app/webhook.php
OPENAI_API_KEY=your_openai_api_key
OPENAI_MODEL=gpt-4
WEBHOOK_VERIFICATION_TOKEN=your_verification_token
NODE_ENV=production
```

## 🧪 **Тестирование**

1. **Health Check:**
   ```bash
   curl https://your-app.railway.app/health.php
   ```

2. **Webhook Test:**
   ```bash
   curl -X POST https://your-app.railway.app/webhook.php \
     -H "Content-Type: application/json" \
     -d '{"type":"url_verification","challenge":"test123"}'
   ```

## 🔄 **Обновление Webhook URL в Lark**

После деплоя обновите webhook URL в Lark Developer Console:
- **Events & Callbacks** → **Request URL:** `https://your-app.railway.app/webhook.php`

## 📱 **Добавление бота в чат**

1. В Lark найдите бота по имени "Тест"
2. Добавьте в группу
3. Напишите: `@Тест привет`

## 🛠️ **Логи**

Просмотр логов в Railway:
```bash
railway logs
```

## 💰 **Стоимость**

- **Бесплатно** для небольших проектов
- **$5/месяц** для коммерческих проектов
- **Автоматическое масштабирование**

## 🔐 **SSL**

Railway автоматически предоставляет SSL сертификаты.
