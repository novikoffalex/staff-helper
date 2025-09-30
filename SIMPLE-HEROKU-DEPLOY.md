# 🚀 Простой деплой на Heroku (без CI/CD)

## 📋 **Самый простой способ:**

### **1. Прямой деплой через Git:**
```bash
# Добавить Heroku remote
git remote add heroku https://git.heroku.com/staff-helper.git

# Деплой
git push heroku main
```

### **2. Через Heroku Dashboard:**
1. Перейдите на https://dashboard.heroku.com/apps/staff-helper
2. **Deploy** → **Manual deploy**
3. Выберите ветку `main`
4. Нажмите **"Deploy Branch"**

### **3. Через Heroku CLI (если установлен):**
```bash
# Логин
heroku login

# Деплой
git push heroku main
```

## ✅ **Переменные окружения уже настроены:**
- LARK_APP_ID
- LARK_APP_SECRET  
- OPENAI_API_KEY
- LARK_WEBHOOK_URL
- OPENAI_MODEL
- WEBHOOK_VERIFICATION_TOKEN
- NODE_ENV

## 🧪 **После деплоя:**
```bash
# Проверка
curl https://staff-helper.herokuapp.com/health.php

# Webhook test
curl -X POST https://staff-helper.herokuapp.com/webhook.php \
  -H "Content-Type: application/json" \
  -d '{"type":"url_verification","challenge":"test123"}'
```

## 🔄 **Обновление webhook в Lark:**
- **Events & Callbacks** → **Request URL:** `https://staff-helper.herokuapp.com/webhook.php`

## 🎉 **Готово!**
Heroku автоматически определит PHP и развернет приложение.
