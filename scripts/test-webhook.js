#!/usr/bin/env node

const axios = require('axios');

const WEBHOOK_URL = process.env.WEBHOOK_URL || 'http://localhost:3000/webhook';

async function testWebhook() {
  console.log('🧪 Тестирование webhook...\n');

  // Тест 1: URL Verification
  console.log('1️⃣ Тестирование URL verification...');
  try {
    const response = await axios.post(WEBHOOK_URL, {
      type: 'url_verification',
      challenge: 'test-challenge-123'
    });
    
    if (response.data.challenge === 'test-challenge-123') {
      console.log('✅ URL verification прошел успешно');
    } else {
      console.log('❌ URL verification не прошел');
    }
  } catch (error) {
    console.log('❌ Ошибка URL verification:', error.message);
  }

  // Тест 2: Health Check
  console.log('\n2️⃣ Тестирование health check...');
  try {
    const healthUrl = WEBHOOK_URL.replace('/webhook', '/health');
    const response = await axios.get(healthUrl);
    
    if (response.data.status === 'ok') {
      console.log('✅ Health check прошел успешно');
    } else {
      console.log('❌ Health check не прошел');
    }
  } catch (error) {
    console.log('❌ Ошибка health check:', error.message);
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

    const response = await axios.post(WEBHOOK_URL, messageEvent);
    
    if (response.data.status === 'ok') {
      console.log('✅ Message event обработан успешно');
    } else {
      console.log('❌ Message event не обработан');
    }
  } catch (error) {
    console.log('❌ Ошибка message event:', error.message);
  }

  console.log('\n🏁 Тестирование завершено!');
}

// Запуск тестов
if (require.main === module) {
  testWebhook().catch(console.error);
}

module.exports = testWebhook;
