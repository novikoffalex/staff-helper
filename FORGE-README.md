# 🚀 Laravel Forge Deployment Guide

## 📋 **Настройка в Forge**

### 1. **Создание сайта**
- **Site Name:** `stuffhelper`
- **Domain:** `your-domain.com` (или поддомен)
- **PHP Version:** `8.4`
- **Webroot:** `public`
- **Directory:** `/home/forge/stuffhelper`

### 2. **Environment Variables**
В настройках сайта добавьте:
```
LARK_APP_ID=your_lark_app_id
LARK_APP_SECRET=your_lark_app_secret
LARK_WEBHOOK_URL=https://your-domain.com/webhook.php
OPENAI_API_KEY=your_openai_api_key
OPENAI_MODEL=gpt-4
WEBHOOK_VERIFICATION_TOKEN=your_verification_token
NODE_ENV=production
```

### 3. **Git Repository**
- **Repository:** `https://gitlab.com/a.novikov6/staff-helper`
- **Branch:** `main`
- **Deploy Script:** (оставьте пустым)

### 4. **Nginx Configuration**
Добавьте в настройки Nginx:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## 🔧 **Деплой**

### Автоматический деплой:
```bash
./deploy-forge.sh
```

### Ручной деплой:
```bash
git add .
git commit -m "Deploy to Forge"
git push forge main
```

## 🧪 **Тестирование**

1. **Health Check:**
   ```bash
   curl https://your-domain.com/health.php
   ```

2. **Webhook Test:**
   ```bash
   curl -X POST https://your-domain.com/webhook.php \
     -H "Content-Type: application/json" \
     -d '{"type":"url_verification","challenge":"test123"}'
   ```

## 🔄 **Обновление Webhook URL в Lark**

После деплоя обновите webhook URL в Lark Developer Console:
- **Events & Callbacks** → **Request URL:** `https://your-domain.com/webhook.php`

## 📱 **Добавление бота в чат**

1. В Lark найдите бота по имени "Тест"
2. Добавьте в группу
3. Напишите: `@Тест привет`

## 🛠️ **Логи**

Просмотр логов в Forge:
- **Logs** → **Laravel Logs**
- **Logs** → **Nginx Error Logs**

## 🔐 **SSL**

Forge автоматически настроит SSL сертификат через Let's Encrypt.
