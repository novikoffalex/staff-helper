<?php
// Простой endpoint для проверки статуса базы данных

echo "🗄️ Проверка базы данных PostgreSQL\n";
echo "=====================================\n\n";

$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl) {
    echo "❌ DATABASE_URL не установлена\n";
    exit;
}

echo "✅ DATABASE_URL найдена\n";
echo "📊 URL: " . substr($databaseUrl, 0, 50) . "...\n\n";

try {
    $db = new PDO($databaseUrl);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Подключение к базе данных успешно\n\n";
    
    // Проверяем таблицы
    $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Найденные таблицы:\n";
    foreach ($tables as $table) {
        echo "   - $table\n";
    }
    echo "\n";
    
    // Проверяем telegram_users
    if (in_array('telegram_users', $tables)) {
        $userCount = $db->query("SELECT COUNT(*) FROM telegram_users")->fetchColumn();
        echo "👥 Пользователей в базе: $userCount\n";
        
        if ($userCount > 0) {
            $users = $db->query("SELECT telegram_id, first_name, last_seen_at FROM telegram_users ORDER BY last_seen_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            echo "📊 Последние пользователи:\n";
            foreach ($users as $user) {
                echo "   - ID: {$user['telegram_id']}, Имя: {$user['first_name']}, Последний визит: {$user['last_seen_at']}\n";
            }
        }
        echo "\n";
    }
    
    // Проверяем conversation_messages
    if (in_array('conversation_messages', $tables)) {
        $messageCount = $db->query("SELECT COUNT(*) FROM conversation_messages")->fetchColumn();
        echo "💬 Сообщений в базе: $messageCount\n";
        
        if ($messageCount > 0) {
            $messages = $db->query("SELECT message_type, COUNT(*) as count FROM conversation_messages GROUP BY message_type")->fetchAll(PDO::FETCH_ASSOC);
            echo "📊 Статистика сообщений:\n";
            foreach ($messages as $msg) {
                echo "   - {$msg['message_type']}: {$msg['count']}\n";
            }
            
            echo "\n📝 Последние сообщения:\n";
            $lastMessages = $db->query("SELECT message_type, message_content, created_at FROM conversation_messages ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($lastMessages as $msg) {
                $content = substr($msg['message_content'], 0, 50) . (strlen($msg['message_content']) > 50 ? '...' : '');
                echo "   - [{$msg['message_type']}] {$content} ({$msg['created_at']})\n";
            }
        }
        echo "\n";
    }
    
    echo "✅ База данных работает корректно!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка подключения к базе данных: " . $e->getMessage() . "\n";
    echo "💡 Возможно, база данных еще не создана или не настроена\n";
}
?>
