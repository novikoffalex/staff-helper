#!/usr/bin/env node

const { execSync } = require('child_process');
const readline = require('readline');

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

function question(prompt) {
  return new Promise((resolve) => {
    rl.question(prompt, resolve);
  });
}

async function deployToRender() {
  console.log('🚀 Развертывание Lark AI Bot на Render\n');

  try {
    // Проверяем Git
    console.log('🔍 Проверка Git...');
    try {
      execSync('git --version', { stdio: 'pipe' });
      console.log('✅ Git найден');
    } catch (error) {
      console.log('❌ Git не установлен. Установите Git и повторите попытку');
      process.exit(1);
    }

    // Проверяем, что мы в Git репозитории
    try {
      execSync('git status', { stdio: 'pipe' });
      console.log('✅ Git репозиторий найден');
    } catch (error) {
      console.log('🔄 Инициализация Git репозитория...');
      execSync('git init', { stdio: 'pipe' });
      execSync('git add .', { stdio: 'pipe' });
      execSync('git commit -m "Initial commit"', { stdio: 'pipe' });
      console.log('✅ Git репозиторий инициализирован');
    }

    // Собираем информацию
    console.log('\n📝 Настройка переменных окружения:');
    const larkAppId = await question('Lark App ID: ');
    const larkAppSecret = await question('Lark App Secret: ');
    const openaiKey = await question('OpenAI API Key: ');
    
    const webhookToken = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
    
    console.log('\n🔗 После развертывания ваш webhook URL будет:');
    console.log('https://your-app-name.onrender.com/webhook');
    console.log('(замените your-app-name на имя вашего приложения)');

    // Создаем .env файл для локальной разработки
    const envContent = `# Render Production Environment
NODE_ENV=production
PORT=10000

# Lark Bot Configuration
LARK_APP_ID=${larkAppId}
LARK_APP_SECRET=${larkAppSecret}
LARK_WEBHOOK_URL=https://your-app-name.onrender.com/webhook

# OpenAI Configuration
OPENAI_API_KEY=${openaiKey}
OPENAI_MODEL=gpt-4

# Security
WEBHOOK_VERIFICATION_TOKEN=${webhookToken}
`;

    require('fs').writeFileSync('.env', envContent);
    console.log('✅ Создан .env файл');

    // Коммитим изменения
    execSync('git add .', { stdio: 'pipe' });
    execSync('git commit -m "Add Render configuration"', { stdio: 'pipe' });
    console.log('✅ Изменения закоммичены');

    console.log('\n🎉 Готово к развертыванию на Render!');
    console.log('\n📋 Следующие шаги:');
    console.log('1. Перейдите на https://render.com');
    console.log('2. Войдите в аккаунт (или создайте новый)');
    console.log('3. Нажмите "New +" → "Web Service"');
    console.log('4. Подключите ваш GitHub репозиторий');
    console.log('5. Настройте переменные окружения:');
    console.log(`   - LARK_APP_ID: ${larkAppId}`);
    console.log(`   - LARK_APP_SECRET: ${larkAppSecret}`);
    console.log(`   - OPENAI_API_KEY: ${openaiKey}`);
    console.log(`   - WEBHOOK_VERIFICATION_TOKEN: ${webhookToken}`);
    console.log('   - LARK_WEBHOOK_URL: https://your-app-name.onrender.com/webhook');
    console.log('6. Нажмите "Create Web Service"');
    console.log('7. Дождитесь завершения развертывания');
    console.log('8. Скопируйте URL приложения и обновите LARK_WEBHOOK_URL');
    console.log('9. Настройте webhook в Lark Open Platform');

    console.log('\n🔗 Полезные ссылки:');
    console.log('- Render Dashboard: https://dashboard.render.com');
    console.log('- Lark Open Platform: https://open.larksuite.com');

  } catch (error) {
    console.error('❌ Ошибка:', error.message);
  } finally {
    rl.close();
  }
}

if (require.main === module) {
  deployToRender();
}

module.exports = deployToRender;
