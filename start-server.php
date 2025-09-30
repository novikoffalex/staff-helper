<?php
/**
 * Скрипт для запуска локального PHP сервера
 */

echo "🚀 Запуск Lark AI Bot на PHP...\n\n";

// Проверяем PHP версию
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    echo "❌ Требуется PHP 7.4 или выше. Текущая версия: " . PHP_VERSION . "\n";
    exit(1);
}

echo "✅ PHP версия: " . PHP_VERSION . "\n";

// Проверяем наличие cURL
if (!extension_loaded('curl')) {
    echo "❌ Требуется расширение cURL\n";
    exit(1);
}

echo "✅ cURL доступен\n";

// Проверяем наличие JSON
if (!extension_loaded('json')) {
    echo "❌ Требуется расширение JSON\n";
    exit(1);
}

echo "✅ JSON доступен\n";

// Проверяем .env файл
if (!file_exists('.env')) {
    echo "⚠️  Файл .env не найден. Создайте его на основе env.php\n";
    echo "   cp env.php .env\n";
    echo "   Затем отредактируйте .env с вашими API ключами\n\n";
}

echo "\n🌐 Запуск сервера на http://localhost:8000\n";
echo "📡 Webhook URL: http://localhost:8000/webhook.php\n";
echo "❤️  Health check: http://localhost:8000/health.php\n";
echo "\n💡 Для тестирования используйте ngrok:\n";
echo "   ngrok http 8000\n";
echo "\n🛑 Для остановки нажмите Ctrl+C\n\n";

// Запускаем встроенный PHP сервер
$command = "php -S localhost:8000 -t .";
passthru($command);
