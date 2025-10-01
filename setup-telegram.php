<?php
require_once 'config/config.php';
require_once 'src/TelegramService.php';

echo "🤖 Настройка Telegram бота...\n\n";

// Проверяем переменные окружения
$botToken = getenv('TELEGRAM_BOT_TOKEN');
$webhookUrl = getenv('TELEGRAM_WEBHOOK_URL');

if (!$botToken) {
    echo "❌ TELEGRAM_BOT_TOKEN не установлен!\n";
    echo "Установите токен бота в переменных окружения.\n";
    exit(1);
}

if (!$webhookUrl) {
    echo "❌ TELEGRAM_WEBHOOK_URL не установлен!\n";
    echo "Установите URL webhook'а в переменных окружения.\n";
    exit(1);
}

try {
    $telegram = new TelegramService();
    
    echo "📡 Получение информации о боте...\n";
    $botInfo = $telegram->getMe();
    
    echo "✅ Бот: @{$botInfo['username']} ({$botInfo['first_name']})\n";
    echo "🆔 ID: {$botInfo['id']}\n\n";
    
    echo "🔗 Установка webhook...\n";
    $result = $telegram->setWebhook($webhookUrl);
    
    if ($result['ok']) {
        echo "✅ Webhook установлен: {$webhookUrl}\n";
        echo "🎉 Бот готов к работе!\n";
        echo "\n📱 Напишите боту: @{$botInfo['username']}\n";
    } else {
        echo "❌ Ошибка установки webhook: {$result['description']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>
