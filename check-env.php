<?php
echo "<h1>🔧 Переменные окружения</h1>";

echo "<h2>DATABASE_URL:</h2>";
$dbUrl = getenv('DATABASE_URL');
if ($dbUrl) {
    echo "<p>✅ DATABASE_URL установлена</p>";
    echo "<p>URL: " . substr($dbUrl, 0, 50) . "...</p>";
} else {
    echo "<p>❌ DATABASE_URL не установлена</p>";
}

echo "<h2>Все переменные окружения:</h2>";
echo "<table border='1'>";
echo "<tr><th>Переменная</th><th>Значение</th></tr>";

$envVars = [
    'DATABASE_URL',
    'TELEGRAM_BOT_TOKEN', 
    'OPENAI_API_KEY',
    'PORT',
    'NODE_ENV'
];

foreach ($envVars as $var) {
    $value = getenv($var);
    if ($value) {
        $displayValue = (strlen($value) > 50) ? substr($value, 0, 50) . '...' : $value;
        echo "<tr><td>$var</td><td>$displayValue</td></tr>";
    } else {
        echo "<tr><td>$var</td><td>❌ Не установлена</td></tr>";
    }
}

echo "</table>";
?>
