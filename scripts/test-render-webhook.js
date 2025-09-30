#!/usr/bin/env node

const axios = require('axios');

async function testRenderWebhook() {
  console.log('🧪 Тестирование webhook на Render...\n');

  // Запрашиваем URL у пользователя
  const readline = require('readline');
  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
  });

  const webhookUrl = await new Promise((resolve) => {
    rl.question('Введите URL вашего приложения на Render (например: https://lark-ai-bot.onrender.com): ', resolve);
  });

  rl.close();

  if (!webhookUrl) {
    console.log('❌ URL не указан');
    return;
  }

  const fullWebhookUrl = webhookUrl.endsWith('/webhook') ? webhookUrl : `${webhookUrl}/webhook`;

  console.log(`🔗 Тестирование: ${fullWebhookUrl}\n`);

  // Тест 1: Health Check
  console.log('1️⃣ Тестирование health check...');
  try {
    const healthUrl = webhookUrl.replace('/webhook', '/health');
    const response = await axios.get(healthUrl, { timeout: 10000 });
    
    if (response.data.status === 'ok') {
      console.log('✅ Health check прошел успешно');
    } else {
      console.log('❌ Health check не прошел');
    }
  } catch (error) {
    console.log('❌ Ошибка health check:', error.message);
  }

  // Тест 2: URL Verification
  console.log('\n2️⃣ Тестирование URL verification...');
  try {
    const response = await axios.post(fullWebhookUrl, {
      type: 'url_verification',
      challenge: 'test-challenge-123'
    }, { timeout: 10000 });
    
    if (response.data.challenge === 'test-challenge-123') {
      console.log('✅ URL verification прошел успешно');
    } else {
      console.log('❌ URL verification не прошел');
    }
  } catch (error) {
    console.log('❌ Ошибка URL verification:', error.message);
  }

  // Тест 3: Message Event
  console.log('\n3️⃣ Тестирование message event...');
  try {
    const messageEvent = {
      type: 'event_callback',
      event: {
        type: 'im.message.receive_v1',
        message: {
          message_id: 'test-message-123',
          chat_id: 'test-chat-123',
          message_type: 'text',
          content: '{"text":"Привет, бот!"}',
          mentions: []
        },
        sender: {
          sender_id: 'test-user-123',
          sender_type: 'user'
        }
      }
    };

    const response = await axios.post(fullWebhookUrl, messageEvent, { timeout: 10000 });
    
    if (response.data.status === 'ok') {
      console.log('✅ Message event обработан успешно');
    } else {
      console.log('❌ Message event не обработан');
    }
  } catch (error) {
    console.log('❌ Ошибка message event:', error.message);
  }

  console.log('\n🏁 Тестирование завершено!');
  console.log('\n📋 Если все тесты прошли успешно:');
  console.log('1. Скопируйте webhook URL выше');
  console.log('2. Перейдите в Lark Open Platform');
  console.log('3. В настройках webhook укажите скопированный URL');
  console.log('4. Включите "Установить верификацию подписи"');
  console.log('5. Добавьте бота в группу Lark');
}

if (require.main === module) {
  testRenderWebhook().catch(console.error);
}

module.exports = testRenderWebhook;
