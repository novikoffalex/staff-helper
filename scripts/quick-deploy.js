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

async function quickDeploy() {
  console.log('🚀 Быстрое развертывание Lark AI Bot на Laravel Cloud\n');

  try {
    // Проверяем Laravel CLI
    console.log('🔍 Проверка Laravel CLI...');
    try {
      execSync('laravel --version', { stdio: 'pipe' });
      console.log('✅ Laravel CLI найден');
    } catch (error) {
      console.log('❌ Laravel CLI не установлен. Установите:');
      console.log('composer global require laravel/installer');
      process.exit(1);
    }

    // Проверяем авторизацию
    console.log('🔐 Проверка авторизации...');
    try {
      execSync('laravel cloud:auth:status', { stdio: 'pipe' });
      console.log('✅ Авторизованы в Laravel Cloud');
    } catch (error) {
      console.log('❌ Не авторизованы. Выполните: laravel cloud:auth:login');
      process.exit(1);
    }

    // Собираем информацию
    const appName = await question('Введите имя приложения (или нажмите Enter для автогенерации): ') || `lark-ai-bot-${Date.now()}`;
    const larkAppId = await question('Lark App ID: ');
    const larkAppSecret = await question('Lark App Secret: ');
    const openaiKey = await question('OpenAI API Key: ');
    
    const webhookToken = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
    const webhookUrl = `https://${appName}.laravelcloud.com/webhook`;

    console.log('\n⚙️  Настройка приложения...');

    // Создаем приложение
    try {
      execSync(`laravel cloud:apps:create "${appName}" --region=us-east-1`, { stdio: 'pipe' });
      console.log('✅ Приложение создано');
    } catch (error) {
      console.log('⚠️  Приложение уже существует или ошибка создания');
    }

    // Устанавливаем переменные окружения
    const envVars = [
      `LARK_APP_ID=${larkAppId}`,
      `LARK_APP_SECRET=${larkAppSecret}`,
      `OPENAI_API_KEY=${openaiKey}`,
      `WEBHOOK_VERIFICATION_TOKEN=${webhookToken}`,
      `LARK_WEBHOOK_URL=${webhookUrl}`,
      'NODE_ENV=production',
      'PORT=8000'
    ];

    for (const envVar of envVars) {
      try {
        execSync(`laravel cloud:env:set ${envVar} --app="${appName}"`, { stdio: 'pipe' });
      } catch (error) {
        console.log(`⚠️  Ошибка установки переменной: ${envVar}`);
      }
    }

    console.log('✅ Переменные окружения настроены');

    // Развертываем
    console.log('🚀 Развертывание...');
    try {
      execSync(`laravel cloud:deploy --app="${appName}"`, { stdio: 'inherit' });
      console.log('✅ Приложение развернуто!');
    } catch (error) {
      console.log('❌ Ошибка развертывания');
      process.exit(1);
    }

    console.log('\n🎉 Развертывание завершено!');
    console.log(`📱 Приложение: ${appName}`);
    console.log(`🔗 Webhook URL: ${webhookUrl}`);
    console.log(`🔑 Токен верификации: ${webhookToken}`);
    
    console.log('\n📋 Следующие шаги:');
    console.log('1. Скопируйте webhook URL выше');
    console.log('2. Перейдите в Lark Open Platform');
    console.log('3. В настройках webhook укажите скопированный URL');
    console.log('4. Включите "Установить верификацию подписи"');
    console.log('5. Добавьте бота в группу Lark');

  } catch (error) {
    console.error('❌ Ошибка:', error.message);
  } finally {
    rl.close();
  }
}

if (require.main === module) {
  quickDeploy();
}

module.exports = quickDeploy;
