<?php
// Простой рабочий webhook без базы данных
$botToken = getenv('TELEGRAM_BOT_TOKEN');
$openaiKey = getenv('OPENAI_API_KEY');

if (!$botToken || !$openaiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Environment variables not configured']);
    exit;
}

// Получаем данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

error_log("Simple webhook received: " . $input);

if (!$update) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

try {
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $userName = $message['from']['first_name'] ?? 'User';
        
        error_log("Processing message from {$userName}");
        
        // Отправляем "печатает..."
        sendTelegramAction($chatId, 'typing');
        
        $userMessage = '';
        
        // Обрабатываем команду /start
        if (isset($message['text']) && $message['text'] === '/start') {
            $welcomeMessage = "🤖 Привет, {$userName}! Я Staff Helper AI Bot v2.0.\n\n✨ Возможности:\n• Понимаю голосовые сообщения 🎤\n• Отвечаю через GPT-4\n• Работаю стабильно\n• Поддержка русского языка\n\nПишите или говорите - я пойму!";
            sendTelegramMessage($chatId, $welcomeMessage);
            echo json_encode(['status' => 'ok', 'message' => 'Welcome sent']);
            exit;
        }
        
        // Обрабатываем текстовые сообщения
        if (isset($message['text'])) {
            $userMessage = $message['text'];
            
            // Игнорируем команды
            if (strpos($userMessage, '/') === 0) {
                echo json_encode(['status' => 'ok', 'message' => 'Command ignored']);
                exit;
            }
        }
        // Обрабатываем голосовые сообщения
        elseif (isset($message['voice'])) {
            $voice = $message['voice'];
            $fileId = $voice['file_id'];
            
            // Распознаем голосовое сообщение
            $userMessage = transcribeVoiceMessage($fileId);
            
            if (!$userMessage) {
                sendTelegramMessage($chatId, "🤖 Извините, не смог распознать голосовое сообщение. Попробуйте еще раз или напишите текстом.");
                echo json_encode(['status' => 'ok', 'message' => 'Voice transcription failed']);
                exit;
            }
            
            // Не отправляем тестовое сообщение - сразу переходим к AI ответу
        }
        else {
            sendTelegramMessage($chatId, "🤖 Я понимаю только текстовые и голосовые сообщения.");
            echo json_encode(['status' => 'ok', 'message' => 'Unsupported message type']);
            exit;
        }
        
        // Получаем ответ от AI
        $aiResponse = getAIResponse($userMessage, $userName);
        
        // Отправляем ответ в Telegram
        sendTelegramMessage($chatId, $aiResponse);
        
        echo json_encode(['status' => 'ok', 'message' => 'AI response sent']);
    } else {
        echo json_encode(['status' => 'ok', 'message' => 'No message to process']);
    }
    
} catch (Exception $e) {
    error_log("Error processing simple webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

function sendTelegramMessage($chatId, $text) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

function sendTelegramAction($chatId, $action) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    $url = "https://api.telegram.org/bot{$botToken}/sendChatAction";
    
    $data = ['chat_id' => $chatId, 'action' => $action];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

function getAIResponse($userMessage, $userName) {
    $openaiKey = getenv('OPENAI_API_KEY');
    
    $systemPrompt = "Ты Staff Helper AI Bot - умный ассистент для помощи сотрудникам. 

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

Имя пользователя: {$userName}.";
    
    $data = [
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'max_tokens' => 500,
        'temperature' => 0.7
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("OpenAI API error: HTTP {$httpCode}, Response: {$response}");
        return "🤖 Извините, у меня проблемы с AI сервисом. Попробуйте позже!";
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    }
    
    error_log("Unexpected OpenAI response: " . $response);
    return "🤖 Извините, не могу обработать ваш запрос.";
}

/**
 * Распознает голосовое сообщение через OpenAI Whisper
 */
function transcribeVoiceMessage($fileId) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    $openaiKey = getenv('OPENAI_API_KEY');
    
    if (!$openaiKey) {
        error_log("OpenAI API key not set");
        return false;
    }
    
    try {
        // Получаем информацию о файле
        $fileUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
        $fileResponse = file_get_contents($fileUrl);
        $fileData = json_decode($fileResponse, true);
        
        if (!$fileData['ok']) {
            error_log("Failed to get file info: " . $fileResponse);
            return false;
        }
        
        $filePath = $fileData['result']['file_path'];
        $voiceUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
        
        // Скачиваем голосовой файл
        $voiceData = file_get_contents($voiceUrl);
        if (!$voiceData) {
            error_log("Failed to download voice file");
            return false;
        }
        
        // Сохраняем временно
        $tempFile = tempnam(sys_get_temp_dir(), 'voice_') . '.ogg';
        file_put_contents($tempFile, $voiceData);
        
        // Отправляем в OpenAI Whisper
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions');
        curl_setopt($ch, CURLOPT_POST, true);
        
        $postFields = [
            'file' => new CURLFile($tempFile, 'audio/ogg', 'voice.ogg'),
            'model' => 'whisper-1',
            'language' => 'ru'
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $openaiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Удаляем временный файл
        unlink($tempFile);
        
        if ($httpCode !== 200) {
            error_log("Whisper API error: HTTP {$httpCode}, Response: {$response}");
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['text'])) {
            error_log("Voice transcribed: " . $result['text']);
            return trim($result['text']);
        } else {
            error_log("No transcription in Whisper response: " . $response);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Voice transcription error: " . $e->getMessage());
        return false;
    }
}
?>
