<?php
require_once 'src/TelegramService.php';

try {
    $telegram = new TelegramService();
    
    echo "🤖 Тестирование Telegram бота...\n";
    
    // Получаем информацию о боте
    $botInfo = $telegram->getMe();
    echo "✅ Бот: @{$botInfo['username']} ({$botInfo['first_name']})\n";
    
    // Проверяем webhook
    $webhookInfo = $telegram->getWebhookInfo();
    echo "📡 Webhook URL: " . ($webhookInfo['url'] ?? 'Not set') . "\n";
    
    echo "🎉 Бот настроен правильно!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>
