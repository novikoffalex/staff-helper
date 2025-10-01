<?php
// Загружаем конфигурацию
require_once 'config/config.php';

// Получаем данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Логируем полученные данные
error_log("Telegram webhook received: " . $input);

if (!$update) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Проверяем переменные окружения
$botToken = getenv('TELEGRAM_BOT_TOKEN');
if (!$botToken) {
    error_log("TELEGRAM_BOT_TOKEN not set");
    http_response_code(500);
    echo json_encode(['error' => 'Bot token not configured']);
    exit;
}

try {
    // Обрабатываем сообщение
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $userName = $message['from']['first_name'] ?? 'User';
        
        error_log("Processing message from {$userName}: {$text}");
        
        // Обрабатываем команду /start
        if ($text === '/start') {
            $welcomeMessage = "🤖 Привет! Я Staff Helper AI Bot.\n\nЯ могу помочь вам с различными вопросами. Просто напишите мне что-нибудь!";
            sendTelegramMessage($chatId, $welcomeMessage);
            echo json_encode(['status' => 'ok', 'message' => 'Welcome sent']);
            exit;
        }
        
        // Игнорируем другие команды
        if (strpos($text, '/') === 0) {
            echo json_encode(['status' => 'ok', 'message' => 'Command ignored']);
            exit;
        }
        
        // Отправляем "печатает..."
        sendTelegramAction($chatId, 'typing');
        
        // Простые ответы без AI
        $response = getSimpleResponse($text, $userName);
        
        // Отправляем ответ в Telegram
        sendTelegramMessage($chatId, $response);
        
        echo json_encode(['status' => 'ok', 'message' => 'Response sent']);
    } else {
        echo json_encode(['status' => 'ok', 'message' => 'No message to process']);
    }
    
} catch (Exception $e) {
    error_log("Error processing Telegram webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

/**
 * Простые ответы без AI
 */
function getSimpleResponse($text, $userName) {
    $text = strtolower(trim($text));
    
    // Приветствие
    if (in_array($text, ['привет', 'hello', 'hi', 'хай'])) {
        return "👋 Привет, {$userName}! Как дела? Чем могу помочь?";
    }
    
    // Как дела
    if (in_array($text, ['как дела', 'как ты', 'how are you'])) {
        return "😊 У меня все отлично! Готов помочь с любыми вопросами!";
    }
    
    // Помощь
    if (in_array($text, ['помощь', 'help', 'что ты умеешь'])) {
        return "🤖 Я могу помочь вам с:\n\n• Планированием задач\n• Ответами на вопросы\n• Организацией работы\n• Решением проблем\n\nПросто спросите!";
    }
    
    // Время
    if (in_array($text, ['время', 'time', 'сколько времени'])) {
        return "🕐 Сейчас: " . date('H:i, d.m.Y');
    }
    
    // Погода (заглушка)
    if (in_array($text, ['погода', 'weather'])) {
        return "🌤️ К сожалению, я пока не могу проверить погоду. Но могу помочь с другими задачами!";
    }
    
    // Программирование
    if (strpos($text, 'код') !== false || strpos($text, 'программирование') !== false) {
        return "💻 Я могу помочь с программированием! Задайте конкретный вопрос о коде или технологиях.";
    }
    
    // По умолчанию
    return "🤔 Интересный вопрос! К сожалению, я пока работаю в простом режиме. Попробуйте спросить:\n\n• Привет\n• Как дела\n• Помощь\n• Время";
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Sent message: HTTP {$httpCode}, Result: {$result}");
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}
?>
