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

echo "🤖 Запуск polling для Telegram бота...\n";

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
            
            // Простой ответ
            $response = "🤖 Получил: {$text}";
            
            // Отправляем ответ
            $sendUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $postData = json_encode([
                'chat_id' => $chatId,
                'text' => $response
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $sendUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $result = curl_exec($ch);
            curl_close($ch);
            
            echo "📤 Отправлен ответ: {$response}\n";
        }
    }
    
    sleep(1);
}
?>
