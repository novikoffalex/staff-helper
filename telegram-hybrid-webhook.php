<?php
// Загружаем переменные окружения из .env файла
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

// Пытаемся подключиться к PostgreSQL
$dbEnabled = false;
$db = null;

$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl) {
    try {
        $db = new PDO($databaseUrl);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Создаем таблицы если их нет
        $db->exec("CREATE TABLE IF NOT EXISTS telegram_users (
            id SERIAL PRIMARY KEY,
            telegram_id BIGINT UNIQUE,
            first_name TEXT,
            last_name TEXT,
            username TEXT,
            is_bot BOOLEAN DEFAULT FALSE,
            language_code TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            last_seen_at TIMESTAMP,
            user_context JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS conversation_messages (
            id SERIAL PRIMARY KEY,
            telegram_user_id INTEGER REFERENCES telegram_users(id) ON DELETE CASCADE,
            telegram_message_id BIGINT,
            message_type VARCHAR(20) DEFAULT 'text',
            message_content TEXT,
            ai_response TEXT,
            message_metadata JSON,
            processed_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $dbEnabled = true;
        error_log("PostgreSQL database connected and tables created");
    } catch (Exception $e) {
        error_log("PostgreSQL connection failed: " . $e->getMessage());
        $dbEnabled = false;
    }
}

// Получаем данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

error_log("Hybrid webhook received: " . $input);

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
            $welcomeMessage = "🤖 Привет, {$userName}! Я Staff Helper AI Bot v2.0.\n\n✨ Возможности:\n• {$dbStatus}\n• Понимаю голосовые сообщения 🎤\n• Умные ответы через GPT-4\n• Стабильная работа\n\nПросто напишите что-нибудь!";
            sendTelegramMessage($chatId, $welcomeMessage);
            echo json_encode(['status' => 'ok', 'message' => 'Welcome sent']);
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
        
        // Получаем историю сообщений (если база работает)
        $conversationHistory = [];
        if ($dbEnabled && $userId) {
            try {
                $stmt = $db->prepare("SELECT message_content, ai_response FROM conversation_messages WHERE telegram_user_id = ? ORDER BY created_at DESC LIMIT 5");
                $stmt->execute([$userId]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Формируем историю в обратном порядке
                foreach (array_reverse($messages) as $msg) {
                    if ($msg['message_content']) {
                        $conversationHistory[] = ['role' => 'user', 'content' => $msg['message_content']];
                    }
                    if ($msg['ai_response']) {
                        $conversationHistory[] = ['role' => 'assistant', 'content' => $msg['ai_response']];
                    }
                }
            } catch (Exception $e) {
                error_log("Database history error: " . $e->getMessage());
            }
        }
        
        // Получаем ответ от AI
        $aiResponse = getAIResponse($userMessage, $userName, $conversationHistory);
        
        if ($aiResponse) {
            sendTelegramMessage($chatId, $aiResponse);
            
            // Сохраняем в базу данных (если база работает)
            if ($dbEnabled && $userId) {
                try {
                    $stmt = $db->prepare("INSERT INTO conversation_messages (telegram_user_id, telegram_message_id, message_type, message_content, ai_response, processed_at) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $userId,
                        $message['message_id'] ?? null,
                        $messageType,
                        $userMessage,
                        $aiResponse,
                        date('Y-m-d H:i:s')
                    ]);
                    error_log("Saved conversation to database");
                } catch (Exception $e) {
                    error_log("Database save error: " . $e->getMessage());
                }
            }
            
            $dbStatus = $dbEnabled ? "with PostgreSQL" : "without database";
            echo json_encode(['status' => 'ok', 'message' => "AI response sent {$dbStatus}"]);
        } else {
            sendTelegramMessage($chatId, "🤖 Извините, произошла ошибка при обработке вашего сообщения.");
            echo json_encode(['status' => 'ok', 'message' => 'AI error']);
        }
    }
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
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

function getAIResponse($userMessage, $userName, $conversationHistory = []) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        error_log("OpenAI API key not set");
        return "🤖 Извините, AI сервис недоступен.";
    }
    
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
    
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];
    
    // Добавляем историю разговора
    $messages = array_merge($messages, $conversationHistory);
    
    // Добавляем текущее сообщение
    $messages[] = ['role' => 'user', 'content' => $userMessage];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4',
        'messages' => $messages,
        'max_tokens' => 500,
        'temperature' => 0.7
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openaiKey,
        'Content-Type: application/json'
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
