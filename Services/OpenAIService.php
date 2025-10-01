<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('openai.api_key');
        $this->model = config('openai.model', 'gpt-4');
    }

    public function generateResponse(string $userMessage, array $conversationHistory = [], array $userContext = []): string
    {
        $systemPrompt = $this->buildSystemPrompt($userContext);
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // Добавляем историю разговора
        foreach ($conversationHistory as $message) {
            $messages[] = [
                'role' => $message['role'],
                'content' => $message['content']
            ];
        }

        // Добавляем текущее сообщение пользователя
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json'
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7
        ]);

        if (!$response->successful()) {
            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return "🤖 Извините, у меня проблемы с AI сервисом. Попробуйте позже!";
        }

        $result = $response->json();
        
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }

        Log::error('Unexpected OpenAI response', ['response' => $result]);
        return "🤖 Извините, не могу обработать ваш запрос.";
    }

    public function transcribeAudio(string $audioContent): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}"
        ])->attach('file', $audioContent, 'voice.ogg')
          ->post('https://api.openai.com/v1/audio/transcriptions', [
            'model' => 'whisper-1',
            'language' => 'ru'
        ]);

        if (!$response->successful()) {
            Log::error('OpenAI Whisper API error', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return '';
        }

        $result = $response->json();
        return $result['text'] ?? '';
    }

    private function buildSystemPrompt(array $userContext = []): string
    {
        $userName = $userContext['first_name'] ?? 'User';
        $lastSeen = $userContext['last_seen_at'] ?? null;
        
        $prompt = "Ты Staff Helper AI Bot - умный ассистент для помощи сотрудникам. 

Твои задачи:
- Помогать с рабочими вопросами и задачами
- Отвечать на вопросы о процессах компании
- Предоставлять полезную информацию
- Быть дружелюбным и профессиональным

Правила:
- Отвечай на русском языке
- Будь кратким, но информативным
- Если не знаешь ответ, честно скажи об этом
- Предлагай альтернативные решения
- Используй эмодзи для дружелюбности
- Помни контекст разговора и не здоровайся каждый раз

Имя пользователя: {$userName}.";

        if ($lastSeen) {
            $lastSeenFormatted = date('d.m.Y H:i', strtotime($lastSeen));
            $prompt .= "\n\nПользователь был в последний раз: {$lastSeenFormatted}";
        }

        return $prompt;
    }
}
