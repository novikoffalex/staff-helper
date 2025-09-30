# 🚀 Деплой через GitLab

## 📋 **Способы деплоя:**

### **Способ 1: Через Heroku Dashboard (рекомендуется)**

1. **Подключение GitLab:**
   - Перейдите на https://dashboard.heroku.com/apps/staff-helper
   - **Deploy** → **Deployment method** → **GitLab**
   - Подключите репозиторий: `a.novikov6/staff-helper`
   - Выберите ветку: `main`

2. **Автоматический деплой:**
   - Включите **"Wait for CI to pass before deploy"** (если нужно)
   - Нажмите **"Deploy Branch"**

### **Способ 2: Через GitLab CI/CD**

1. **Настройка переменных в GitLab:**
   - Перейдите в https://gitlab.com/a.novikov6/staff-helper
   - **Settings** → **CI/CD** → **Variables**
   - Добавьте переменную:
     - **Key:** `HEROKU_API_KEY`
     - **Value:** ваш API ключ Heroku

2. **Получение Heroku API Key:**
   - Перейдите на https://dashboard.heroku.com/account
   - **API Key** → **Reveal** → скопируйте ключ

3. **Запуск деплоя:**
   - Перейдите в **CI/CD** → **Pipelines**
   - Нажмите **"Run pipeline"**
   - Выберите ветку `main`
   - Нажмите **"Run pipeline"**

### **Способ 3: Ручной деплой через Git**

```bash
# Добавьте Heroku remote
git remote add heroku https://git.heroku.com/staff-helper.git

# Деплой
git push heroku main
```

## 🧪 **Тестирование после деплоя:**

### 1. **Health Check:**
```bash
curl https://staff-helper.herokuapp.com/health.php
```

### 2. **Webhook Test:**
```bash
curl -X POST https://staff-helper.herokuapp.com/webhook.php \
  -H "Content-Type: application/json" \
  -d '{"type":"url_verification","challenge":"test123"}'
```

## 🔄 **Обновление Webhook URL в Lark:**

1. Перейдите в Lark Developer Console
2. **Events & Callbacks** → **Request URL:** `https://staff-helper.herokuapp.com/webhook.php`
3. Сохраните изменения

## 📱 **Тестирование бота:**

1. В Lark найдите бота "Тест"
2. Добавьте в группу
3. Напишите: `@Тест привет`

## 🛠️ **Логи:**

Просмотр логов в Heroku Dashboard:
- **More** → **View logs**

## 🎉 **Готово!**

Ваш бот будет доступен по адресу:
**https://staff-helper.herokuapp.com**

### 📝 **Важные файлы:**
- `.gitlab-ci.yml` - конфигурация GitLab CI/CD
- `deploy-gitlab.sh` - скрипт для деплоя
- `composer.json` - зависимости PHP
- `Procfile` - команда запуска
