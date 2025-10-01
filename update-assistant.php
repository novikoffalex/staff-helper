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
$assistantId = getenv('OPENAI_ASSISTANT_ID');

if (!$openaiKey) {
    die("❌ OPENAI_API_KEY не установлен\n");
}

if (!$assistantId) {
    die("❌ OPENAI_ASSISTANT_ID не установлен\n");
}

echo "🤖 Обновление ассистента: {$assistantId}\n\n";

// Читаем инструкции из файла
$instructions = file_get_contents('assistant-instructions.md');
if (!$instructions) {
    die("❌ Не удалось прочитать файл assistant-instructions.md\n");
}

echo "📄 Инструкции загружены (" . strlen($instructions) . " символов)\n\n";

// Обновляем ассистента
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/assistants/{$assistantId}");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'instructions' => $instructions,
    'name' => 'Staff Helper Bot - Thailand Trip Assistant',
    'model' => 'gpt-4o-mini',
    'temperature' => 0.7
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $openaiKey,
    'Content-Type: application/json',
    'OpenAI-Beta: assistants=v2'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("❌ Ошибка обновления: HTTP {$httpCode}\nОтвет: {$response}\n");
}

$result = json_decode($response, true);

echo "✅ Ассистент успешно обновлён!\n\n";
echo "📊 Детали:\n";
echo "   ID: {$result['id']}\n";
echo "   Имя: {$result['name']}\n";
echo "   Модель: {$result['model']}\n";
echo "   Инструкции: " . strlen($result['instructions']) . " символов\n\n";

echo "🎉 Готово! Теперь бот использует новые инструкции.\n";
?>
