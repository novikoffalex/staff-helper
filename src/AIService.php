<?php
/**
 * Сервис для работы с OpenAI API
 */

class AIService
{
    private $apiKey;
    private $model;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = OPENAI_API_KEY;
        $this->model = OPENAI_MODEL;
        $this->baseUrl = 'https://api.openai.com/v1';
    }

    /**
     * Инициализация сервиса
     */
    public function initialize()
    {
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key is required');
        }

        error_log('AI service initialized with model: ' . $this->model);
    }

    /**
     * Системный промпт для бота
     */
    private function getSystemPrompt()
    {
        return "Ты - полезный AI-ассистент в корпоративном чате Lark. Твоя задача - помогать пользователям с планированием, организацией задач и ответами на вопросы.

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
Если вопрос неясен, вежливо уточни детали.";
    }

    /**
     * Обработка сообщения пользователя
     */
    public function processMessage($userMessage, $context = [])
    {
        if (empty($this->apiKey)) {
            throw new Exception('AI service not initialized');
        }

        try {
            error_log('Processing message with AI...');

            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->getSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $this->formatUserMessage($userMessage, $context)
                ]
            ];

            $response = $this->makeRequest('/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.7,
                'presence_penalty' => 0.1,
                'frequency_penalty' => 0.1
            ]);

            $aiResponse = $response['choices'][0]['message']['content'];
            
            error_log('AI response generated');
            return $this->formatResponse($aiResponse, $context);

        } catch (Exception $e) {
            error_log('AI processing error: ' . $e->getMessage());
            
            if (strpos($e->getMessage(), 'insufficient_quota') !== false) {
                return 'Извините, у меня закончились лимиты API. Попробуйте позже.';
            }
            
            if (strpos($e->getMessage(), 'rate_limit_exceeded') !== false) {
                return 'Слишком много запросов. Подождите немного и попробуйте снова.';
            }
            
            return 'Извините, произошла ошибка при обработке вашего запроса. Попробуйте переформулировать вопрос.';
        }
    }

    /**
     * Форматирование сообщения пользователя с контекстом
     */
    private function formatUserMessage($message, $context)
    {
        $formattedMessage = $message;
        
        if (!empty($context['senderId'])) {
            $formattedMessage = "Пользователь (ID: {$context['senderId']}): $message";
        }
        
        return $formattedMessage;
    }

    /**
     * Форматирование ответа ИИ
     */
    private function formatResponse($response, $context)
    {
        // Добавляем эмодзи для лучшего восприятия
        $formattedResponse = $response;
        
        // Если это планирование или задачи
        if (stripos($response, 'задача') !== false || 
            stripos($response, 'план') !== false ||
            stripos($response, 'список') !== false) {
            $formattedResponse = "📋 $formattedResponse";
        }
        
        // Если это напоминание
        if (stripos($response, 'напомн') !== false || 
            stripos($response, 'время') !== false) {
            $formattedResponse = "⏰ $formattedResponse";
        }
        
        // Если это помощь или совет
        if (stripos($response, 'совет') !== false || 
            stripos($response, 'рекоменд') !== false) {
            $formattedResponse = "💡 $formattedResponse";
        }
        
        return $formattedResponse;
    }

    /**
     * Анализ намерений пользователя
     */
    public function analyzeIntent($message)
    {
        $intents = [
            'planning' => ['план', 'расписание', 'график', 'день', 'неделя'],
            'tasks' => ['задача', 'дело', 'сделать', 'выполнить'],
            'reminder' => ['напомн', 'напомнить', 'время', 'когда'],
            'question' => ['что', 'как', 'где', 'когда', 'почему', 'зачем'],
            'help' => ['помощь', 'помоги', 'как', 'что делать']
        ];

        $lowerMessage = strtolower($message);
        
        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($lowerMessage, $keyword) !== false) {
                    return $intent;
                }
            }
        }
        
        return 'general';
    }

    /**
     * Выполнение HTTP запроса к OpenAI API
     */
    private function makeRequest($endpoint, $data)
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'User-Agent: Lark-AI-Bot/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: $error");
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP error $httpCode: $response";
            throw new Exception($errorMessage);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: $response");
        }

        return $decoded;
    }
}
