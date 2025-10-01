<?php
// Простой webhook для демонстрации концепции с базой данных
require_once 'vendor/autoload.php';

// Инициализируем Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TelegramUser;
use App\Models\ConversationMessage;
use App\Services\TelegramService;
use App\Services\OpenAIService;

// Получаем данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Логируем полученные данные
error_log("Laravel Telegram webhook received: " . $input);

if (!$update) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Проверяем переменные окружения
$botToken = config('telegram.bot_token');
if (!$botToken) {
    error_log("TELEGRAM_BOT_TOKEN not set");
    http_response_code(500);
    echo json_encode(['error' => 'Bot token not configured']);
    exit;
}

try {
    $telegramService = new TelegramService();
    $openAIService = new OpenAIService();
    
    // Обрабатываем сообщение
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $from = $message['from'];
        
        error_log("Processing message from {$from['first_name']}");
        
        // Находим или создаем пользователя
        $user = TelegramUser::where('telegram_id', $from['id'])->first();
        
        if (!$user) {
            $user = TelegramUser::create([
                'telegram_id' => $from['id'],
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'username' => $from['username'] ?? null,
                'is_bot' => $from['is_bot'] ?? false,
                'language_code' => $from['language_code'] ?? null,
                'last_seen_at' => now(),
            ]);
            error_log("Created new user: {$user->id}");
        } else {
            $user->update(['last_seen_at' => now()]);
            error_log("Updated existing user: {$user->id}");
        }
        
        // Отправляем "печатает..."
        $telegramService->sendChatAction($chatId, 'typing');
        
        // Обрабатываем команду /start
        if (isset($message['text']) && $message['text'] === '/start') {
            $welcomeMessage = "🤖 Привет, {$user->first_name}! Я Staff Helper AI Bot v2.0.\n\n✨ Новые возможности:\n• Запоминаю наши разговоры\n• Не буду здороваться каждый раз\n• Понимаю контекст\n\nПросто напишите или скажите что-нибудь!";
            $telegramService->sendMessage($chatId, $welcomeMessage);
            echo json_encode(['status' => 'ok', 'message' => 'Welcome sent']);
            exit;
        }
        
        $messageContent = '';
        $messageType = 'text';
        
        // Обрабатываем текстовые сообщения
        if (isset($message['text'])) {
            $messageContent = $message['text'];
            
            // Игнорируем команды
            if (strpos($messageContent, '/') === 0) {
                echo json_encode(['status' => 'ok', 'message' => 'Command ignored']);
                exit;
            }
        }
        // Обрабатываем голосовые сообщения
        elseif (isset($message['voice'])) {
            $voice = $message['voice'];
            $fileId = $voice['file_id'];
            
            // Простое сообщение для демонстрации
            $messageContent = "🎤 [Голосовое сообщение]";
            $messageType = 'voice';
        }
        else {
            $telegramService->sendMessage($chatId, "🤖 Я понимаю только текстовые и голосовые сообщения.");
            echo json_encode(['status' => 'ok', 'message' => 'Unsupported message type']);
            exit;
        }
        
        // Сохраняем сообщение пользователя
        $conversationMessage = ConversationMessage::create([
            'telegram_user_id' => $user->id,
            'telegram_message_id' => $message['message_id'],
            'message_type' => $messageType,
            'message_content' => $messageContent,
        ]);
        
        // Получаем историю разговора (последние 5 сообщений)
        $history = $user->getLatestMessages(5);
        $conversationHistory = [];
        
        foreach ($history as $msg) {
            if ($msg->message_content) {
                $conversationHistory[] = [
                    'role' => 'user',
                    'content' => $msg->message_content
                ];
            }
            
            if ($msg->ai_response) {
                $conversationHistory[] = [
                    'role' => 'assistant',
                    'content' => $msg->ai_response
                ];
            }
        }
        
        // Генерируем ответ через AI
        $aiResponse = $openAIService->generateResponse(
            $messageContent,
            $conversationHistory,
            $user->user_context ?? []
        );
        
        // Сохраняем ответ AI
        $conversationMessage->update([
            'ai_response' => $aiResponse,
            'processed_at' => now()
        ]);
        
        // Отправляем ответ в Telegram
        $telegramService->sendMessage($chatId, $aiResponse);
        
        error_log("Sent AI response to user {$user->id}");
        echo json_encode(['status' => 'ok', 'message' => 'AI response sent']);
    } else {
        echo json_encode(['status' => 'ok', 'message' => 'No message to process']);
    }
    
} catch (Exception $e) {
    error_log("Error processing Laravel Telegram webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
