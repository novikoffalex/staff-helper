<?php
// Webhook с поддержкой PostgreSQL на Heroku
$botToken = getenv('TELEGRAM_BOT_TOKEN');
$openaiKey = getenv('OPENAI_API_KEY');

if (!$botToken || !$openaiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Environment variables not configured']);
    exit;
}

// Подключаемся к PostgreSQL на Heroku
try {
    $databaseUrl = getenv('DATABASE_URL');
    if ($databaseUrl) {
        $db = new PDO($databaseUrl);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $dbEnabled = true;
        error_log("Connected to PostgreSQL");
    } else {
        $dbEnabled = false;
        error_log("DATABASE_URL not found, running without database");
    }
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    $dbEnabled = false;
}

// Создаем таблицы если их нет (PostgreSQL синтаксис)
if ($dbEnabled) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS telegram_users (
            id SERIAL PRIMARY KEY,
            telegram_id BIGINT UNIQUE,
            first_name VARCHAR(255),
            last_name VARCHAR(255),
            username VARCHAR(255),
            is_bot BOOLEAN DEFAULT FALSE,
            language_code VARCHAR(10),
            is_active BOOLEAN DEFAULT TRUE,
            last_seen_at TIMESTAMP,
            user_context JSONB,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS conversation_messages (
            id SERIAL PRIMARY KEY,
            telegram_user_id INTEGER REFERENCES telegram_users(id),
            telegram_message_id BIGINT,
            message_type VARCHAR(50) DEFAULT 'text',
            message_content TEXT,
            ai_response TEXT,
            message_metadata JSONB,
            processed_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        error_log("PostgreSQL tables created/verified");
    } catch (Exception $e) {
        error_log("Table creation error: " . $e->getMessage());
        $dbEnabled = false;
    }
}

// Получаем данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

error_log("PostgreSQL webhook received: " . $input);

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
        $userName = $from['first_name'] ?? 'User';
        
        error_log("Processing message from {$userName}");
        
        // Находим или создаем пользователя (если база работает)
        $userId = null;
        if ($dbEnabled) {
            try {
                $stmt = $db->prepare("SELECT * FROM telegram_users WHERE telegram_id = ?");
                $stmt->execute([$from['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    $stmt = $db->prepare("INSERT INTO telegram_users (telegram_id, first_name, last_name, username, is_bot, language_code, last_seen_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $from['id'],
                        $from['first_name'] ?? null,
                        $from['last_name'] ?? null,
                        $from['username'] ?? null,
                        $from['is_bot'] ?? false,
                        $from['language_code'] ?? null,
                        date('Y-m-d H:i:s')
                    ]);
                    $userId = $db->lastInsertId();
                    error_log("Created new user: $userId");
                } else {
                    $userId = $user['id'];
                    $stmt = $db->prepare("UPDATE telegram_users SET last_seen_at = ? WHERE id = ?");
                    $stmt->execute([date('Y-m-d H:i:s'), $userId]);
                    error_log("Updated existing user: $userId");
                }
            } catch (Exception $e) {
                error_log("Database user error: " . $e->getMessage());
                $userId = null;
            }
        }
        
        // Отправляем "печатает..."
        sendTelegramAction($chatId, 'typing');
        
        // Обрабатываем команду /start
        if (isset($message['text']) && $message['text'] === '/start') {
            $dbStatus = $dbEnabled ? "✅ База данных активна" : "⚠️ База данных отключена";
            $welcomeMessage = "🤖 Привет, {$userName}! Я Staff Helper AI Bot v2.0.\n\n✨ Возможности:\n• {$dbStatus}\n• Умные ответы через GPT-4\n• Поддержка голосовых сообщений\n• Стабильная работа\n\nПросто напишите что-нибудь!";
            sendTelegramMessage($chatId, $welcomeMessage);
            echo json_encode(['status' => 'ok', 'message' => 'Welcome sent']);
            exit;
        }
        
        $userMessage = '';
        $messageType = 'text';
        
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
        else {
            sendTelegramMessage($chatId, "🤖 Я понимаю только текстовые и голосовые сообщения.");
            echo json_encode(['status' => 'ok', 'message' => 'Unsupported message type']);
            exit;
        }
        
        // Сохраняем сообщение и получаем историю (если база работает)
        $conversationHistory = [];
        if ($dbEnabled && $userId) {
            try {
                // Сохраняем сообщение пользователя
                $stmt = $db->prepare("INSERT INTO conversation_messages (telegram_user_id, telegram_message_id, message_type, message_content) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $message['message_id'], $messageType, $userMessage]);
                $messageId = $db->lastInsertId();
                
                // Получаем историю разговора (последние 5 сообщений)
                $stmt = $db->prepare("SELECT message_content, ai_response FROM conversation_messages WHERE telegram_user_id = ? ORDER BY created_at DESC LIMIT 5");
                $stmt->execute([$userId]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach (array_reverse($history) as $msg) {
                    if ($msg['message_content']) {
                        $conversationHistory[] = ['role' => 'user', 'content' => $msg['message_content']];
                    }
                    if ($msg['ai_response']) {
                        $conversationHistory[] = ['role' => 'assistant', 'content' => $msg['ai_response']];
                    }
                }
            } catch (Exception $e) {
                error_log("Database message error: " . $e->getMessage());
            }
        }
        
        // Получаем ответ от AI
        $aiResponse = getAIResponse($userMessage, $userName, $conversationHistory);
        
        // Сохраняем ответ AI (если база работает)
        if ($dbEnabled && isset($messageId)) {
            try {
                $stmt = $db->prepare("UPDATE conversation_messages SET ai_response = ?, processed_at = ? WHERE id = ?");
                $stmt->execute([$aiResponse, date('Y-m-d H:i:s'), $messageId]);
            } catch (Exception $e) {
                error_log("Database AI response error: " . $e->getMessage());
            }
        }
        
        // Отправляем ответ в Telegram
        sendTelegramMessage($chatId, $aiResponse);
        
        echo json_encode(['status' => 'ok', 'message' => 'AI response sent with PostgreSQL']);
    } else {
        echo json_encode(['status' => 'ok', 'message' => 'No message to process']);
    }
    
} catch (Exception $e) {
    error_log("Error processing PostgreSQL webhook: " . $e->getMessage());
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

function getAIResponse($userMessage, $userName, $conversationHistory = []) {
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
- Помни контекст разговора и не здоровайся каждый раз

Имя пользователя: {$userName}.";
    
    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    
    // Добавляем историю разговора
    foreach ($conversationHistory as $msg) {
        $messages[] = $msg;
    }
    
    // Добавляем текущее сообщение пользователя
    $messages[] = ['role' => 'user', 'content' => $userMessage];
    
    $data = [
        'model' => 'gpt-4',
        'messages' => $messages,
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
