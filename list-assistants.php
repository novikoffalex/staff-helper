<?php
// Загружаем переменные окружения
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

$openaiKey = getenv('OPENAI_API_KEY');

if (!$openaiKey) {
    die("❌ OPENAI_API_KEY не установлен\n");
}

echo "🤖 Список всех ассистентов в вашем аккаунте:\n\n";

// Получаем список ассистентов
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/assistants?limit=20');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $openaiKey,
    'OpenAI-Beta: assistants=v2'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("❌ Ошибка: HTTP {$httpCode}\nОтвет: {$response}\n");
}

$result = json_decode($response, true);
$assistants = $result['data'] ?? [];

if (empty($assistants)) {
    echo "❌ Ассистенты не найдены\n";
    exit;
}

echo "Найдено ассистентов: " . count($assistants) . "\n\n";

foreach ($assistants as $idx => $assistant) {
    echo "📋 Ассистент #" . ($idx + 1) . ":\n";
    echo "   ID: {$assistant['id']}\n";
    echo "   Имя: {$assistant['name']}\n";
    echo "   Модель: {$assistant['model']}\n";
    echo "   Создан: " . date('Y-m-d H:i:s', $assistant['created_at']) . "\n";
    
    if (!empty($assistant['instructions'])) {
        $instrLength = strlen($assistant['instructions']);
        $preview = substr($assistant['instructions'], 0, 100);
        echo "   Инструкции: {$instrLength} символов\n";
        echo "   Превью: {$preview}...\n";
    }
    
    echo "\n   🔗 Прямая ссылка:\n";
    echo "   https://platform.openai.com/playground/assistants?assistant={$assistant['id']}\n\n";
    echo "---\n\n";
}
?>
