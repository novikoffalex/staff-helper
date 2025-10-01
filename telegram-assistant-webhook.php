<?php
// Загружаем переменные окружения
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Простое хранилище для thread_id пользователей (в памяти)
// В продакшене можно использовать Redis или файл
$threadsFile = '/tmp/telegram_threads.json';

function getUserThread($userId) {
    global $threadsFile;
    if (file_exists($threadsFile)) {
        $threads = json_decode(file_get_contents($threadsFile), true);
        return $threads[$userId] ?? null;
    }
    return null;
}

function saveUserThread($userId, $threadId) {
    global $threadsFile;
    $threads = [];
    if (file_exists($threadsFile)) {
        $threads = json_decode(file_get_contents($threadsFile), true);
    }
    $threads[$userId] = $threadId;
    file_put_contents($threadsFile, json_encode($threads));
}

// Получаем данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

error_log("Assistant webhook received: " . $input);

if (!$update) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

try {
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $from = $message['from'];
        $userId = $from['id'];
        $userName = $from['first_name'] ?? 'User';
        
        error_log("Processing message from {$userName} (ID: {$userId})");
        
        // Отправляем "печатает..."
        sendTelegramAction($chatId, 'typing');
        
        // Обрабатываем команду /start
        if (isset($message['text']) && $message['text'] === '/start') {
            $welcomeMessage = "🤖 Привет, {$userName}! Я Staff Helper AI Bot v3.0.\n\n✨ Возможности:\n• 🧠 OpenAI Assistants API\n• 🎤 Понимаю голосовые сообщения\n• 💬 Помню всю историю разговора\n• ⚡ Быстрые и умные ответы\n\nПросто напишите или отправьте голосовое сообщение!";
            sendTelegramMessage($chatId, $welcomeMessage);
            echo json_encode(['status' => 'ok', 'message' => 'Welcome sent']);
            exit;
        }
        
        // Обрабатываем команду /reset - сброс контекста
        if (isset($message['text']) && $message['text'] === '/reset') {
            saveUserThread($userId, null);
            sendTelegramMessage($chatId, "🔄 Контекст разговора сброшен. Начнём сначала!");
            echo json_encode(['status' => 'ok', 'message' => 'Thread reset']);
            exit;
        }
        
        $userMessage = '';
        $messageType = 'text';
        
        // Обрабатываем голосовые сообщения
        if (isset($message['voice'])) {
            $voice = $message['voice'];
            $fileId = $voice['file_id'];
            
            error_log("Processing voice message: " . $fileId);
            
            // Распознаем голосовое сообщение
            $userMessage = transcribeVoiceMessage($fileId);
            
            if (!$userMessage) {
                sendTelegramMessage($chatId, "🤖 Извините, не смог распознать голосовое сообщение. Попробуйте еще раз или напишите текстом.");
                echo json_encode(['status' => 'ok', 'message' => 'Voice transcription failed']);
                exit;
            }
            
            $messageType = 'voice';
            error_log("Voice transcribed: " . $userMessage);
        }
        // Обрабатываем текстовые сообщения
        elseif (isset($message['text'])) {
            $userMessage = $message['text'];
            
            // Игнорируем команды
            if (strpos($userMessage, '/') === 0) {
                echo json_encode(['status' => 'ok', 'message' => 'Command ignored']);
                exit;
            }
        }
        else {
            sendTelegramMessage($chatId, "🤖 Я понимаю только текстовые и голосовые сообщения.");
            echo json_encode(['status' => 'ok', 'message' => 'Unsupported message type']);
            exit;
        }
        
        // Получаем или создаем thread для пользователя
        $threadId = getUserThread($userId);
        
        if (!$threadId) {
            $threadId = createThread();
            if ($threadId) {
                saveUserThread($userId, $threadId);
                error_log("Created new thread: {$threadId} for user {$userId}");
            } else {
                sendTelegramMessage($chatId, "🤖 Извините, произошла ошибка при создании разговора.");
                echo json_encode(['status' => 'error', 'message' => 'Thread creation failed']);
                exit;
            }
        }
        
        // Получаем ответ от Assistant
        $aiResponse = getAssistantResponse($threadId, $userMessage, $userName);
        
        if ($aiResponse) {
            sendTelegramMessage($chatId, $aiResponse);
            echo json_encode(['status' => 'ok', 'message' => 'Assistant response sent', 'thread_id' => $threadId]);
        } else {
            sendTelegramMessage($chatId, "🤖 Извините, произошла ошибка при обработке вашего сообщения.");
            echo json_encode(['status' => 'ok', 'message' => 'Assistant error']);
        }
    }
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Создает новый thread в OpenAI
 */
function createThread() {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        error_log("OpenAI API key not set");
        return null;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/threads');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openaiKey,
        'Content-Type: application/json',
        'OpenAI-Beta: assistants=v2'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("OpenAI Thread creation error: HTTP {$httpCode}, Response: {$response}");
        return null;
    }
    
    $result = json_decode($response, true);
    return $result['id'] ?? null;
}

/**
 * Получает ответ от OpenAI Assistant
 */
function getAssistantResponse($threadId, $userMessage, $userName) {
    $openaiKey = getenv('OPENAI_API_KEY');
    $assistantId = getenv('OPENAI_ASSISTANT_ID');
    
    if (!$openaiKey) {
        error_log("OpenAI API key not set");
        return "🤖 Извините, AI сервис недоступен.";
    }
    
    // Если нет assistant_id, создаем нового ассистента
    if (!$assistantId) {
        $assistantId = createAssistant();
        if (!$assistantId) {
            return "🤖 Извините, не удалось создать ассистента.";
        }
    }
    
    // Добавляем сообщение в thread
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/threads/{$threadId}/messages");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'role' => 'user',
        'content' => $userMessage
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openaiKey,
        'Content-Type: application/json',
        'OpenAI-Beta: assistants=v2'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("OpenAI Message error: HTTP {$httpCode}, Response: {$response}");
        return "🤖 Извините, не удалось отправить сообщение.";
    }
    
    // Запускаем run
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/threads/{$threadId}/runs");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'assistant_id' => $assistantId
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openaiKey,
        'Content-Type: application/json',
        'OpenAI-Beta: assistants=v2'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("OpenAI Run error: HTTP {$httpCode}, Response: {$response}");
        return "🤖 Извините, не удалось запустить обработку.";
    }
    
    $runResult = json_decode($response, true);
    $runId = $runResult['id'] ?? null;
    
    if (!$runId) {
        return "🤖 Извините, не удалось получить ID обработки.";
    }
    
    // Ждем завершения run
    $maxAttempts = 30;
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        sleep(1);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/threads/{$threadId}/runs/{$runId}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $openaiKey,
            'OpenAI-Beta: assistants=v2'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $runStatus = json_decode($response, true);
            $status = $runStatus['status'] ?? '';
            
            error_log("Run status: {$status}");
            
            if ($status === 'completed') {
                break;
            } elseif ($status === 'failed' || $status === 'cancelled' || $status === 'expired') {
                error_log("Run failed with status: {$status}");
                return "🤖 Извините, обработка не удалась.";
            }
        }
        
        $attempt++;
    }
    
    if ($attempt >= $maxAttempts) {
        return "🤖 Извините, обработка заняла слишком много времени.";
    }
    
    // Получаем сообщения из thread
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/threads/{$threadId}/messages?limit=1");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openaiKey,
        'OpenAI-Beta: assistants=v2'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("OpenAI Messages retrieval error: HTTP {$httpCode}, Response: {$response}");
        return "🤖 Извините, не удалось получить ответ.";
    }
    
    $messagesResult = json_decode($response, true);
    $messages = $messagesResult['data'] ?? [];
    
    if (empty($messages)) {
        return "🤖 Извините, не получил ответ от ассистента.";
    }
    
    // Получаем текст последнего сообщения от ассистента
    $lastMessage = $messages[0];
    $content = $lastMessage['content'] ?? [];
    
    if (empty($content)) {
        return "🤖 Извините, ответ пустой.";
    }
    
    // Извлекаем текст
    $textContent = '';
    foreach ($content as $item) {
        if ($item['type'] === 'text') {
            $textContent .= $item['text']['value'] ?? '';
        }
    }
    
    return $textContent ?: "🤖 Извините, не удалось извлечь текст ответа.";
}

/**
 * Создает нового OpenAI Assistant
 */
function createAssistant() {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        error_log("OpenAI API key not set");
        return null;
    }
    
    $instructions = "Ты Staff Helper AI Bot - умный ассистент для помощи сотрудникам.

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
- Используй эмодзи для дружелюбности";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/assistants');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'name' => 'Staff Helper Bot',
        'instructions' => $instructions
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openaiKey,
        'Content-Type: application/json',
        'OpenAI-Beta: assistants=v2'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("OpenAI Assistant creation error: HTTP {$httpCode}, Response: {$response}");
        return null;
    }
    
    $result = json_decode($response, true);
    $assistantId = $result['id'] ?? null;
    
    if ($assistantId) {
        error_log("Created new assistant: {$assistantId}");
        // Сохраняем ID ассистента для будущего использования
        echo "\n\n⚠️ ВАЖНО! Добавьте в .env и Heroku config:\nOPENAI_ASSISTANT_ID={$assistantId}\n\n";
    }
    
    return $assistantId;
}

function sendTelegramMessage($chatId, $text) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    if (!$botToken) {
        error_log("TELEGRAM_BOT_TOKEN not set");
        return false;
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Telegram API error: HTTP {$httpCode}, Response: {$response}");
        return false;
    }
    
    return true;
}

function sendTelegramAction($chatId, $action) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    if (!$botToken) {
        return false;
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/sendChatAction";
    
    $data = [
        'chat_id' => $chatId,
        'action' => $action
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    curl_exec($ch);
    curl_close($ch);
    
    return true;
}

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
