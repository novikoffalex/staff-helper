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

$botToken = getenv('TELEGRAM_BOT_TOKEN');
if (!$botToken) {
    die("TELEGRAM_BOT_TOKEN not set\n");
}

echo "🤖 Запуск AI Telegram бота...\n";

$lastUpdateId = 0;

while (true) {
    $url = "https://api.telegram.org/bot{$botToken}/getUpdates?offset=" . ($lastUpdateId + 1);
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if (!$data['ok']) {
        echo "❌ Ошибка API: " . $data['description'] . "\n";
        sleep(5);
        continue;
    }
    
    foreach ($data['result'] as $update) {
        $lastUpdateId = $update['update_id'];
        
        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';
            $userName = $message['from']['first_name'] ?? 'User';
            
            echo "📨 Получено сообщение от {$userName}: {$text}\n";
            
            // Отправляем "печатает..."
            sendTelegramAction($chatId, 'typing');
            
            // Получаем ответ от AI
            $aiResponse = getAIResponse($text, $userName);
            
            // Отправляем ответ
            sendTelegramMessage($chatId, $aiResponse);
            
            echo "📤 Отправлен AI ответ\n";
        }
    }
    
    sleep(1);
}

/**
 * Отправляет сообщение в Telegram
 */
function sendTelegramMessage($chatId, $text) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
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
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

/**
 * Отправляет действие в Telegram
 */
function sendTelegramAction($chatId, $action) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
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
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

/**
 * Получает ответ от AI
 */
function getAIResponse($userMessage, $userName) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        return "🤖 Извините, AI сервис не настроен.";
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
        echo "❌ OpenAI API ошибка: HTTP {$httpCode}\n";
        return "🤖 Извините, у меня проблемы с AI сервисом.";
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    } else {
        echo "❌ Неожиданный ответ от OpenAI\n";
        return "🤖 Извините, не могу обработать ваш запрос.";
    }
}
?>
