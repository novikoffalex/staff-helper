#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

console.log('🚀 Настройка Lark AI Bot...\n');

// Создаем .env файл если его нет
const envPath = path.join(process.cwd(), '.env');
const envExamplePath = path.join(process.cwd(), 'env.example');

if (!fs.existsSync(envPath)) {
  if (fs.existsSync(envExamplePath)) {
    fs.copyFileSync(envExamplePath, envPath);
    console.log('✅ Создан файл .env из шаблона');
  } else {
    console.log('❌ Файл env.example не найден');
    process.exit(1);
  }
} else {
  console.log('✅ Файл .env уже существует');
}

// Генерируем верификационный токен
const verificationToken = crypto.randomBytes(32).toString('hex');
console.log(`🔑 Сгенерирован верификационный токен: ${verificationToken}`);

// Создаем папку для логов
const logsDir = path.join(process.cwd(), 'logs');
if (!fs.existsSync(logsDir)) {
  fs.mkdirSync(logsDir, { recursive: true });
  console.log('✅ Создана папка для логов');
}

// Проверяем зависимости
console.log('\n📦 Проверка зависимостей...');
try {
  const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
  const nodeModulesExists = fs.existsSync('node_modules');
  
  if (!nodeModulesExists) {
    console.log('⚠️  Зависимости не установлены. Запустите: npm install');
  } else {
    console.log('✅ Зависимости установлены');
  }
} catch (error) {
  console.log('❌ Ошибка при проверке зависимостей:', error.message);
}

console.log('\n📋 Следующие шаги:');
console.log('1. Отредактируйте файл .env и добавьте ваши ключи API');
console.log('2. Запустите: npm install (если еще не сделали)');
console.log('3. Настройте приложение в Lark Open Platform');
console.log('4. Запустите: npm run dev');
console.log('5. Используйте ngrok для тестирования webhook');

console.log('\n🔗 Полезные ссылки:');
console.log('- Lark Open Platform: https://open.feishu.cn/');
console.log('- OpenAI API: https://platform.openai.com/');
console.log('- ngrok: https://ngrok.com/');

console.log('\n✨ Настройка завершена!');
