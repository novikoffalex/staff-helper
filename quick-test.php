<?php
/**
 * Быстрый тест всех компонентов
 */

echo "🚀 Быстрый тест Lark AI Bot...\n\n";

// 1. Проверяем конфигурацию
echo "1️⃣ Проверка конфигурации...\n";
require_once 'config/config.php';

$required_vars = ['LARK_APP_ID', 'LARK_APP_SECRET', 'OPENAI_API_KEY'];
$all_configured = true;

foreach ($required_vars as $var) {
    $value = constant($var);
    if (empty($value) || $value === 'your_' . strtolower($var) . '_here') {
        echo "❌ $var не настроен\n";
        $all_configured = false;
    } else {
        echo "✅ $var настроен\n";
    }
}

if (!$all_configured) {
    echo "\n⚠️  Настройте переменные в .env файле\n";
    exit(1);
}

// 2. Проверяем локальный сервер
echo "\n2️⃣ Проверка локального сервера...\n";
$health = file_get_contents('http://localhost:8000/health.php');
$healthData = json_decode($health, true);

if ($healthData && $healthData['status'] === 'ok') {
    echo "✅ Локальный сервер работает\n";
} else {
    echo "❌ Локальный сервер не отвечает. Запустите: php start-server.php\n";
    exit(1);
}

// 3. Проверяем ngrok
echo "\n3️⃣ Проверка ngrok...\n";
$ngrokInfo = file_get_contents('http://localhost:4040/api/tunnels');
$ngrokData = json_decode($ngrokInfo, true);

if ($ngrokData && !empty($ngrokData['tunnels'])) {
    $publicUrl = $ngrokData['tunnels'][0]['public_url'];
    echo "✅ ngrok работает: $publicUrl\n";
    
    // Тестируем публичный URL
    $webhookUrl = $publicUrl . '/webhook.php';
    $testData = json_encode(['type' => 'url_verification', 'challenge' => 'test123']);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $testData
        ]
    ]);
    
    $response = file_get_contents($webhookUrl, false, $context);
    $responseData = json_decode($response, true);
    
    if ($responseData && $responseData['challenge'] === 'test123') {
        echo "✅ Публичный webhook работает\n";
    } else {
        echo "❌ Публичный webhook не работает\n";
    }
} else {
    echo "❌ ngrok не запущен. Запустите: ngrok http 8000\n";
    exit(1);
}

echo "\n🎉 Все тесты пройдены!\n";
echo "\n📋 Следующие шаги:\n";
echo "1. Скопируйте webhook URL: $webhookUrl\n";
echo "2. Перейдите в Lark Open Platform\n";
echo "3. В настройках webhook укажите скопированный URL\n";
echo "4. Включите 'Установить верификацию подписи'\n";
echo "5. Добавьте бота в группу Lark\n";
echo "\n💡 Для тестирования сообщений напишите боту в Lark:\n";
echo "   - 'привет' или 'помощь'\n";
echo "   - 'помоги спланировать день'\n";
echo "   - 'создай список задач'\n";
