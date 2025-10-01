<?php
require_once 'config/config.php';
require_once 'src/TelegramService.php';
require_once 'src/AIService.php';

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

try {
    $telegram = new TelegramService();
    $ai = new AIService();
    
    // Обрабатываем сообщение
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $userName = $message['from']['first_name'] ?? 'User';
        
        error_log("Processing message from {$userName}: {$text}");
        
        // Игнорируем команды бота
        if (strpos($text, '/') === 0) {
            echo json_encode(['status' => 'ok', 'message' => 'Command ignored']);
            exit;
        }
        
        // Получаем ответ от AI
        $aiResponse = $ai->generateResponse($text, $userName);
        
        // Отправляем ответ в Telegram
        $telegram->sendMessage($chatId, $aiResponse);
        
        echo json_encode(['status' => 'ok', 'message' => 'Response sent']);
    } else {
        echo json_encode(['status' => 'ok', 'message' => 'No message to process']);
    }
    
} catch (Exception $e) {
    error_log("Error processing Telegram webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
