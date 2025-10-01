<?php
// Laravel-подобный webhook с базой данных для Heroku
// Загружаем переменные окружения
$botToken = getenv('TELEGRAM_BOT_TOKEN');
$openaiKey = getenv('OPENAI_API_KEY');

if (!$botToken || !$openaiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Environment variables not configured']);
    exit;
}

// Подключаемся к SQLite базе данных
$dbPath = getenv('DATABASE_URL') ? parse_url(getenv('DATABASE_URL'))['path'] ?? '/tmp/database.sqlite' : '/app/database/database.sqlite';
$db = new PDO("sqlite:$dbPath");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Создаем таблицы если их нет
$db->exec("CREATE TABLE IF NOT EXISTS telegram_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    telegram_id INTEGER UNIQUE,
    first_name TEXT,
    last_name TEXT,
    username TEXT,
    is_bot BOOLEAN DEFAULT 0,
    language_code TEXT,
    is_active BOOLEAN DEFAULT 1,
    last_seen_at DATETIME,
    user_context TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS conversation_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    telegram_user_id INTEGER,
    telegram_message_id INTEGER,
    message_type TEXT DEFAULT 'text',
    message_content TEXT,
    ai_response TEXT,
    message_metadata TEXT,
    processed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (telegram_user_id) REFERENCES telegram_users (id)
)");

// Получаем данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

error_log("Laravel-like webhook received: " . $input);

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
        
        error_log("Processing message from {$from['first_name']}");
        
        // Находим или создаем пользователя
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
        
        // Отправляем "печатает..."
        sendTelegramAction($chatId, 'typing');
        
        // Обрабатываем команду /start
        if (isset($message['text']) && $message['text'] === '/start') {
            $welcomeMessage = "🤖 Привет, {$from['first_name']}! Я Staff Helper AI Bot v2.0.\n\n✨ Новые возможности:\n• Запоминаю наши разговоры\n• Не буду здороваться каждый раз\n• Понимаю контекст\n• Храню всё в базе данных\n\nПросто напишите или скажите что-нибудь!";
            sendTelegramMessage($chatId, $welcomeMessage);
            echo json_encode(['status' => 'ok', 'message' => 'Welcome sent']);
            exit;
        }
        
        $messageContent = '';
        $messageType = 'text';
        
        // Обрабатываем текстовые сообщения
        if (isset($message['text'])) {
            $messageContent = $message['text'];
            
            // Игнорируем команды
            if (strpos($messageContent, '/') === 0) {
                echo json_encode(['status' => 'ok', 'message' => 'Command ignored']);
                exit;
            }
        }
        // Обрабатываем голосовые сообщения
        elseif (isset($message['voice'])) {
            $messageContent = "🎤 [Голосовое сообщение]";
            $messageType = 'voice';
        }
        else {
            sendTelegramMessage($chatId, "🤖 Я понимаю только текстовые и голосовые сообщения.");
            echo json_encode(['status' => 'ok', 'message' => 'Unsupported message type']);
            exit;
        }
        
        // Сохраняем сообщение пользователя
        $stmt = $db->prepare("INSERT INTO conversation_messages (telegram_user_id, telegram_message_id, message_type, message_content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $message['message_id'], $messageType, $messageContent]);
        $messageId = $db->lastInsertId();
        
        // Получаем историю разговора (последние 5 сообщений)
        $stmt = $db->prepare("SELECT message_content, ai_response FROM conversation_messages WHERE telegram_user_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$userId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $conversationHistory = [];
        foreach (array_reverse($history) as $msg) {
            if ($msg['message_content']) {
                $conversationHistory[] = ['role' => 'user', 'content' => $msg['message_content']];
            }
            if ($msg['ai_response']) {
                $conversationHistory[] = ['role' => 'assistant', 'content' => $msg['ai_response']];
            }
        }
        
        // Генерируем ответ через AI
        $aiResponse = generateAIResponse($messageContent, $conversationHistory, $from['first_name'] ?? 'User');
        
        // Сохраняем ответ AI
        $stmt = $db->prepare("UPDATE conversation_messages SET ai_response = ?, processed_at = ? WHERE id = ?");
        $stmt->execute([$aiResponse, date('Y-m-d H:i:s'), $messageId]);
        
        // Отправляем ответ в Telegram
        sendTelegramMessage($chatId, $aiResponse);
        
        error_log("Sent AI response to user $userId");
        echo json_encode(['status' => 'ok', 'message' => 'AI response sent']);
    } else {
        echo json_encode(['status' => 'ok', 'message' => 'No message to process']);
    }
    
} catch (Exception $e) {
    error_log("Error processing Laravel-like webhook: " . $e->getMessage());
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

function generateAIResponse($userMessage, $conversationHistory, $userName) {
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
?>
