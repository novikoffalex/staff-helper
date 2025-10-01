#!/bin/bash

echo "🔍 Мониторинг базы данных (обновляется каждые 2 секунды)"
echo "Нажмите Ctrl+C для остановки"
echo ""

while true; do
    clear
    echo "📊 База данных Telegram бота - $(date '+%H:%M:%S')"
    echo "================================"
    echo ""
    
    cd telegram-bot-laravel
    
    USERS=$(sqlite3 database/database.sqlite "SELECT COUNT(*) FROM telegram_users;")
    MESSAGES=$(sqlite3 database/database.sqlite "SELECT COUNT(*) FROM conversation_messages;")
    
    echo "📈 Статистика:"
    echo "  Пользователей: $USERS"
    echo "  Сообщений: $MESSAGES"
    echo ""
    
    if [ "$USERS" -gt 0 ]; then
        echo "👥 Пользователи:"
        sqlite3 database/database.sqlite "SELECT id, telegram_id, first_name, last_seen_at FROM telegram_users;" -header -column
        echo ""
    fi
    
    if [ "$MESSAGES" -gt 0 ]; then
        echo "💬 Последние сообщения:"
        sqlite3 database/database.sqlite "SELECT id, message_type, substr(message_content, 1, 40) as content, substr(ai_response, 1, 40) as response FROM conversation_messages ORDER BY created_at DESC LIMIT 5;" -header -column
        echo ""
    fi
    
    cd ..
    
    sleep 2
done
