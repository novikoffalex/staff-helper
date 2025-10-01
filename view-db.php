<?php
// Простой endpoint для просмотра данных базы
echo "<h1>🗄️ База данных PostgreSQL</h1>";

$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl) {
    echo "<p>❌ DATABASE_URL не установлена</p>";
    exit;
}

try {
    $db = new PDO($databaseUrl);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Подключение к базе данных успешно</p>";
    
    // Проверяем таблицы
    $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>📋 Таблицы в базе:</h2><ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Проверяем telegram_users
    if (in_array('telegram_users', $tables)) {
        $userCount = $db->query("SELECT COUNT(*) FROM telegram_users")->fetchColumn();
        echo "<h2>👥 Пользователи ($userCount):</h2>";
        
        if ($userCount > 0) {
            $users = $db->query("SELECT telegram_id, first_name, last_seen_at FROM telegram_users ORDER BY last_seen_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            echo "<table border='1'><tr><th>ID</th><th>Имя</th><th>Последний визит</th></tr>";
            foreach ($users as $user) {
                echo "<tr><td>{$user['telegram_id']}</td><td>{$user['first_name']}</td><td>{$user['last_seen_at']}</td></tr>";
            }
            echo "</table>";
        }
    }
    
    // Проверяем conversation_messages
    if (in_array('conversation_messages', $tables)) {
        $messageCount = $db->query("SELECT COUNT(*) FROM conversation_messages")->fetchColumn();
        echo "<h2>💬 Сообщения ($messageCount):</h2>";
        
        if ($messageCount > 0) {
            $messages = $db->query("SELECT message_type, COUNT(*) as count FROM conversation_messages GROUP BY message_type")->fetchAll(PDO::FETCH_ASSOC);
            echo "<h3>📊 Статистика:</h3><ul>";
            foreach ($messages as $msg) {
                echo "<li>{$msg['message_type']}: {$msg['count']}</li>";
            }
            echo "</ul>";
            
            echo "<h3>📝 Последние сообщения:</h3>";
            $lastMessages = $db->query("SELECT message_type, message_content, ai_response, created_at FROM conversation_messages ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            echo "<table border='1'><tr><th>Тип</th><th>Сообщение</th><th>Ответ AI</th><th>Время</th></tr>";
            foreach ($lastMessages as $msg) {
                $content = htmlspecialchars(substr($msg['message_content'], 0, 100) . (strlen($msg['message_content']) > 100 ? '...' : ''));
                $aiResponse = htmlspecialchars(substr($msg['ai_response'], 0, 100) . (strlen($msg['ai_response']) > 100 ? '...' : ''));
                echo "<tr><td>{$msg['message_type']}</td><td>$content</td><td>$aiResponse</td><td>{$msg['created_at']}</td></tr>";
            }
            echo "</table>";
        }
    }
    
    echo "<p>✅ База данных работает корректно!</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Ошибка: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
