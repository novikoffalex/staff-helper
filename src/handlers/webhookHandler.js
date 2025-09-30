const larkService = require('../services/larkService');
const aiService = require('../services/aiService');
const { verifySignature } = require('../utils/security');

/**
 * Обработчик webhook событий от Lark
 */
async function handleWebhook(req, res) {
  try {
    const { headers, body } = req;
    
    // Проверяем подпись (если настроена)
    if (process.env.WEBHOOK_VERIFICATION_TOKEN) {
      const isValid = verifySignature(headers, body);
      if (!isValid) {
        console.warn('Invalid webhook signature');
        return res.status(401).json({ error: 'Unauthorized' });
      }
    }

    // Обрабатываем разные типы событий
    switch (body.type) {
      case 'url_verification':
        return handleUrlVerification(body, res);
      
      case 'event_callback':
        return await handleEventCallback(body, res);
      
      default:
        console.log('Unknown event type:', body.type);
        return res.status(200).json({ status: 'ok' });
    }
  } catch (error) {
    console.error('Webhook handler error:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
}

/**
 * Обработка URL verification от Lark
 */
function handleUrlVerification(body, res) {
  console.log('URL verification request:', body);
  
  const { challenge } = body;
  if (!challenge) {
    return res.status(400).json({ error: 'Missing challenge' });
  }
  
  console.log('✅ URL verification successful');
  res.json({ challenge });
}

/**
 * Обработка событий от Lark
 */
async function handleEventCallback(body, res) {
  const { event } = body;
  
  if (!event) {
    return res.status(400).json({ error: 'Missing event data' });
  }

  console.log('Received event:', event.type);

  switch (event.type) {
    case 'im.message.receive_v1':
      await handleMessageReceive(event);
      break;
    
    case 'im.message.message_read_v1':
      console.log('Message read:', event.message_id);
      break;
    
    default:
      console.log('Unhandled event type:', event.type);
  }

  res.json({ status: 'ok' });
}

/**
 * Обработка входящих сообщений
 */
async function handleMessageReceive(event) {
  try {
    const { message, sender } = event;
    
    // Проверяем, что это текстовое сообщение
    if (message.message_type !== 'text') {
      console.log('Non-text message received, ignoring');
      return;
    }

    // Извлекаем текст сообщения
    const messageText = message.content;
    const messageId = message.message_id;
    const chatId = message.chat_id;
    
    console.log(`📨 New message from ${sender.sender_id}: ${messageText}`);

    // Проверяем, что сообщение адресовано боту
    if (!isMessageForBot(messageText, message)) {
      console.log('Message not for bot, ignoring');
      return;
    }

    // Обрабатываем сообщение через ИИ
    const aiResponse = await aiService.processMessage(messageText, {
      senderId: sender.sender_id,
      chatId: chatId,
      messageId: messageId
    });

    if (aiResponse) {
      // Отправляем ответ через Lark API
      await larkService.sendMessage(chatId, aiResponse, messageId);
      console.log('✅ Response sent successfully');
    }

  } catch (error) {
    console.error('Error processing message:', error);
    
    // Отправляем сообщение об ошибке пользователю
    try {
      await larkService.sendMessage(
        event.message.chat_id, 
        'Извините, произошла ошибка при обработке вашего сообщения. Попробуйте позже.',
        event.message.message_id
      );
    } catch (sendError) {
      console.error('Failed to send error message:', sendError);
    }
  }
}

/**
 * Проверяем, адресовано ли сообщение боту
 */
function isMessageForBot(messageText, message) {
  // Проверяем упоминание бота (@bot)
  if (message.mentions && message.mentions.length > 0) {
    return true;
  }
  
  // Проверяем приватный чат
  if (message.chat_type === 'p2p') {
    return true;
  }
  
  // Проверяем ключевые слова
  const botKeywords = ['бот', 'помощь', 'помоги', 'assistant', 'help'];
  const lowerText = messageText.toLowerCase();
  
  return botKeywords.some(keyword => lowerText.includes(keyword));
}

module.exports = handleWebhook;
