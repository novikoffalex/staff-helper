const OpenAI = require('openai');

class AIService {
  constructor() {
    this.openai = null;
    this.model = process.env.OPENAI_MODEL || 'gpt-4';
    this.systemPrompt = this.getSystemPrompt();
  }

  /**
   * Инициализация сервиса
   */
  async initialize() {
    const apiKey = process.env.OPENAI_API_KEY;
    
    if (!apiKey) {
      throw new Error('OpenAI API key is required');
    }

    this.openai = new OpenAI({
      apiKey: apiKey
    });

    console.log('AI service initialized with model:', this.model);
  }

  /**
   * Системный промпт для бота
   */
  getSystemPrompt() {
    return `Ты - полезный AI-ассистент в корпоративном чате Lark. Твоя задача - помогать пользователям с планированием, организацией задач и ответами на вопросы.

Основные функции:
1. Помощь в планировании дня/недели
2. Создание списков задач
3. Напоминания о важных событиях
4. Ответы на общие вопросы
5. Помощь в организации рабочего процесса

Стиль общения:
- Дружелюбный и профессиональный
- Краткие и полезные ответы
- Используй эмодзи для лучшего восприятия
- Предлагай конкретные действия

Если пользователь просит создать задачу или напоминание, предложи структурированный формат.
Если вопрос неясен, вежливо уточни детали.`;
  }

  /**
   * Обработка сообщения пользователя
   */
  async processMessage(userMessage, context = {}) {
    if (!this.openai) {
      throw new Error('AI service not initialized');
    }

    try {
      console.log('🤖 Processing message with AI...');

      const messages = [
        {
          role: 'system',
          content: this.systemPrompt
        },
        {
          role: 'user',
          content: this.formatUserMessage(userMessage, context)
        }
      ];

      const response = await this.openai.chat.completions.create({
        model: this.model,
        messages: messages,
        max_tokens: 1000,
        temperature: 0.7,
        presence_penalty: 0.1,
        frequency_penalty: 0.1
      });

      const aiResponse = response.choices[0].message.content;
      
      console.log('✅ AI response generated');
      return this.formatResponse(aiResponse, context);

    } catch (error) {
      console.error('AI processing error:', error);
      
      if (error.code === 'insufficient_quota') {
        return 'Извините, у меня закончились лимиты API. Попробуйте позже.';
      }
      
      if (error.code === 'rate_limit_exceeded') {
        return 'Слишком много запросов. Подождите немного и попробуйте снова.';
      }
      
      return 'Извините, произошла ошибка при обработке вашего запроса. Попробуйте переформулировать вопрос.';
    }
  }

  /**
   * Форматирование сообщения пользователя с контекстом
   */
  formatUserMessage(message, context) {
    let formattedMessage = message;
    
    if (context.senderId) {
      formattedMessage = `Пользователь (ID: ${context.senderId}): ${message}`;
    }
    
    return formattedMessage;
  }

  /**
   * Форматирование ответа ИИ
   */
  formatResponse(response, context) {
    // Добавляем эмодзи для лучшего восприятия
    let formattedResponse = response;
    
    // Если это планирование или задачи
    if (response.toLowerCase().includes('задача') || 
        response.toLowerCase().includes('план') ||
        response.toLowerCase().includes('список')) {
      formattedResponse = `📋 ${formattedResponse}`;
    }
    
    // Если это напоминание
    if (response.toLowerCase().includes('напомн') || 
        response.toLowerCase().includes('время')) {
      formattedResponse = `⏰ ${formattedResponse}`;
    }
    
    // Если это помощь или совет
    if (response.toLowerCase().includes('совет') || 
        response.toLowerCase().includes('рекоменд')) {
      formattedResponse = `💡 ${formattedResponse}`;
    }
    
    return formattedResponse;
  }

  /**
   * Создание структурированного ответа с кнопками (для будущего использования)
   */
  createInteractiveResponse(text, buttons = []) {
    return {
      text: text,
      buttons: buttons
    };
  }

  /**
   * Анализ намерений пользователя
   */
  async analyzeIntent(message) {
    const intents = {
      planning: ['план', 'расписание', 'график', 'день', 'неделя'],
      tasks: ['задача', 'дело', 'сделать', 'выполнить'],
      reminder: ['напомн', 'напомнить', 'время', 'когда'],
      question: ['что', 'как', 'где', 'когда', 'почему', 'зачем'],
      help: ['помощь', 'помоги', 'как', 'что делать']
    };

    const lowerMessage = message.toLowerCase();
    
    for (const [intent, keywords] of Object.entries(intents)) {
      if (keywords.some(keyword => lowerMessage.includes(keyword))) {
        return intent;
      }
    }
    
    return 'general';
  }
}

module.exports = new AIService();
