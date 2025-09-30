<?php
/**
 * Конфигурация приложения
 */

// Загружаем переменные окружения из .env файла
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Конфигурация Lark
define('LARK_APP_ID', $_ENV['LARK_APP_ID'] ?? '');
define('LARK_APP_SECRET', $_ENV['LARK_APP_SECRET'] ?? '');
define('LARK_WEBHOOK_URL', $_ENV['LARK_WEBHOOK_URL'] ?? '');
define('LARK_BASE_URL', 'https://open.feishu.cn/open-apis');

// Конфигурация OpenAI
define('OPENAI_API_KEY', $_ENV['OPENAI_API_KEY'] ?? '');
define('OPENAI_MODEL', $_ENV['OPENAI_MODEL'] ?? 'gpt-4');

// Конфигурация сервера
define('WEBHOOK_VERIFICATION_TOKEN', $_ENV['WEBHOOK_VERIFICATION_TOKEN'] ?? '');
define('NODE_ENV', $_ENV['NODE_ENV'] ?? 'development');

// Проверяем обязательные переменные
$required_vars = ['LARK_APP_ID', 'LARK_APP_SECRET', 'OPENAI_API_KEY'];
foreach ($required_vars as $var) {
    if (empty(constant($var))) {
        error_log("Missing required environment variable: $var");
    }
}
