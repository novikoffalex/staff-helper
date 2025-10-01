<?php
// Простой webhook для тестирования
error_log("=== SIMPLE WEBHOOK CALLED ===");

// Получаем данные
$input = file_get_contents('php://input');
$update = json_decode($input, true);

error_log("Input received: " . $input);

// Логируем все данные
error_log("Update data: " . print_r($update, true));

// Простой ответ
echo json_encode(['status' => 'ok', 'message' => 'Received']);

// Попробуем отправить ответ боту (если есть сообщение)
if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    
    error_log("Processing message: chat_id={$chatId}, text={$text}");
    
    // Простой ответ без AI
    $response = "🤖 Получил ваше сообщение: {$text}";
    
    // Отправляем ответ
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    if ($botToken) {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $response
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("Sent response: HTTP {$httpCode}, Result: {$result}");
    }
}
?>
