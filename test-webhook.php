<?php
/**
 * Скрипт для тестирования webhook
 */

echo "🧪 Тестирование Lark AI Bot webhook...\n\n";

$webhookUrl = 'http://localhost:8000/webhook.php';

// Тест 1: Health Check
echo "1️⃣ Тестирование health check...\n";
$healthUrl = 'http://localhost:8000/health.php';
$response = file_get_contents($healthUrl);
$data = json_decode($response, true);

if ($data && $data['status'] === 'ok') {
    echo "✅ Health check прошел успешно\n";
} else {
    echo "❌ Health check не прошел\n";
}

// Тест 2: URL Verification
echo "\n2️⃣ Тестирование URL verification...\n";
$testData = [
    'type' => 'url_verification',
    'challenge' => 'test-challenge-123'
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($testData)
    ]
]);

$response = file_get_contents($webhookUrl, false, $context);
$data = json_decode($response, true);

if ($data && $data['challenge'] === 'test-challenge-123') {
    echo "✅ URL verification прошел успешно\n";
} else {
    echo "❌ URL verification не прошел\n";
    echo "Response: $response\n";
}

// Тест 3: Message Event
echo "\n3️⃣ Тестирование message event...\n";
$messageEvent = [
    'type' => 'event_callback',
    'event' => [
        'type' => 'im.message.receive_v1',
        'message' => [
            'message_id' => 'test-message-123',
            'chat_id' => 'test-chat-123',
            'message_type' => 'text',
            'content' => '{"text":"Привет, бот!"}',
            'mentions' => []
        ],
        'sender' => [
            'sender_id' => 'test-user-123',
            'sender_type' => 'user'
        ]
    ]
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($messageEvent)
    ]
]);

$response = file_get_contents($webhookUrl, false, $context);
$data = json_decode($response, true);

if ($data && $data['status'] === 'ok') {
    echo "✅ Message event обработан успешно\n";
} else {
    echo "❌ Message event не обработан\n";
    echo "Response: $response\n";
}

echo "\n🏁 Тестирование завершено!\n";
echo "\n📋 Если все тесты прошли успешно:\n";
echo "1. Скопируйте webhook URL: $webhookUrl\n";
echo "2. Перейдите в Lark Open Platform\n";
echo "3. В настройках webhook укажите скопированный URL\n";
echo "4. Включите 'Установить верификацию подписи'\n";
echo "5. Добавьте бота в группу Lark\n";
