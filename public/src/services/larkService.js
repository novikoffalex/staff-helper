const axios = require('axios');
const crypto = require('crypto');

class LarkService {
  constructor() {
    this.appId = process.env.LARK_APP_ID;
    this.appSecret = process.env.LARK_APP_SECRET;
    this.baseUrl = 'https://open.feishu.cn/open-apis';
    this.accessToken = null;
    this.tokenExpiresAt = null;
  }

  /**
   * Инициализация сервиса
   */
  async initialize() {
    if (!this.appId || !this.appSecret) {
      throw new Error('Lark App ID and Secret are required');
    }
    
    await this.refreshAccessToken();
    console.log('Lark service initialized');
  }

  /**
   * Получение/обновление access token
   */
  async refreshAccessToken() {
    try {
      const response = await axios.post(`${this.baseUrl}/auth/v3/tenant_access_token/internal`, {
        app_id: this.appId,
        app_secret: this.appSecret
      });

      const { tenant_access_token, expire } = response.data;
      
      this.accessToken = tenant_access_token;
      this.tokenExpiresAt = Date.now() + (expire - 60) * 1000; // Обновляем за минуту до истечения
      
      console.log('✅ Lark access token refreshed');
    } catch (error) {
      console.error('Failed to get Lark access token:', error.response?.data || error.message);
      throw error;
    }
  }

  /**
   * Проверка и обновление токена при необходимости
   */
  async ensureValidToken() {
    if (!this.accessToken || Date.now() >= this.tokenExpiresAt) {
      await this.refreshAccessToken();
    }
  }

  /**
   * Отправка сообщения в чат
   */
  async sendMessage(chatId, content, replyToMessageId = null) {
    await this.ensureValidToken();

    try {
      const messageData = {
        receive_id: chatId,
        msg_type: 'text',
        content: JSON.stringify({
          text: content
        })
      };

      // Если это ответ на сообщение, добавляем reply
      if (replyToMessageId) {
        messageData.reply_to_message_id = replyToMessageId;
      }

      const response = await axios.post(
        `${this.baseUrl}/im/v1/messages`,
        messageData,
        {
          headers: {
            'Authorization': `Bearer ${this.accessToken}`,
            'Content-Type': 'application/json'
          },
          params: {
            receive_id_type: 'chat_id'
          }
        }
      );

      console.log('Message sent successfully:', response.data);
      return response.data;
    } catch (error) {
      console.error('Failed to send message:', error.response?.data || error.message);
      throw error;
    }
  }

  /**
   * Отправка карточки с кнопками
   */
  async sendCard(chatId, cardContent, replyToMessageId = null) {
    await this.ensureValidToken();

    try {
      const messageData = {
        receive_id: chatId,
        msg_type: 'interactive',
        content: JSON.stringify(cardContent)
      };

      if (replyToMessageId) {
        messageData.reply_to_message_id = replyToMessageId;
      }

      const response = await axios.post(
        `${this.baseUrl}/im/v1/messages`,
        messageData,
        {
          headers: {
            'Authorization': `Bearer ${this.accessToken}`,
            'Content-Type': 'application/json'
          },
          params: {
            receive_id_type: 'chat_id'
          }
        }
      );

      console.log('Card sent successfully:', response.data);
      return response.data;
    } catch (error) {
      console.error('Failed to send card:', error.response?.data || error.message);
      throw error;
    }
  }

  /**
   * Получение информации о чате
   */
  async getChatInfo(chatId) {
    await this.ensureValidToken();

    try {
      const response = await axios.get(
        `${this.baseUrl}/im/v1/chats/${chatId}`,
        {
          headers: {
            'Authorization': `Bearer ${this.accessToken}`
          }
        }
      );

      return response.data;
    } catch (error) {
      console.error('Failed to get chat info:', error.response?.data || error.message);
      throw error;
    }
  }

  /**
   * Получение информации о пользователе
   */
  async getUserInfo(userId) {
    await this.ensureValidToken();

    try {
      const response = await axios.get(
        `${this.baseUrl}/contact/v3/users/${userId}`,
        {
          headers: {
            'Authorization': `Bearer ${this.accessToken}`
          }
        }
      );

      return response.data;
    } catch (error) {
      console.error('Failed to get user info:', error.response?.data || error.message);
      throw error;
    }
  }
}

module.exports = new LarkService();
