<?php
/**
 * Обработчик webhook событий от Lark
 */

class WebhookHandler
{
    private $larkService;
    private $aiService;

    public function __construct()
    {
        $this->larkService = new LarkService();
        $this->aiService = new AIService();
    }

    /**
     * Основной метод обработки webhook
     */
    public function handle()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            $this->sendError('Invalid JSON data');
            return;
        }

        // Логируем входящий запрос
        error_log("Webhook received: " . json_encode($data));

        switch ($data['type'] ?? '') {
            case 'url_verification':
                $this->handleUrlVerification($data);
                break;
            
            case 'event_callback':
                $this->handleEventCallback($data);
                break;
            
            default:
                $this->sendResponse(['status' => 'ok']);
        }
    }

    /**
     * Обработка URL verification от Lark
     */
    private function handleUrlVerification($data)
    {
        $challenge = $data['challenge'] ?? '';
        
        if (empty($challenge)) {
            $this->sendError('Missing challenge');
            return;
        }

        error_log("URL verification successful");
        $this->sendResponse(['challenge' => $challenge]);
    }

    /**
     * Обработка событий от Lark
     */
    private function handleEventCallback($data)
    {
        $event = $data['event'] ?? [];
        
        if (empty($event)) {
            $this->sendError('Missing event data');
            return;
        }

        error_log("Event received: " . $event['type']);

        switch ($event['type']) {
            case 'im.message.receive_v1':
                $this->handleMessageReceive($event);
                break;
            
            case 'im.message.message_read_v1':
                error_log("Message read: " . ($event['message_id'] ?? 'unknown'));
                break;
            
            default:
                error_log("Unhandled event type: " . $event['type']);
        }

        $this->sendResponse(['status' => 'ok']);
    }

    /**
     * Обработка входящих сообщений
     */
    private function handleMessageReceive($event)
    {
        try {
            $message = $event['message'] ?? [];
            $sender = $event['sender'] ?? [];
            
            // Проверяем, что это текстовое сообщение
            if (($message['message_type'] ?? '') !== 'text') {
                error_log("Non-text message received, ignoring");
                return;
            }

            // Извлекаем текст сообщения
            $messageText = $this->extractMessageText($message);
            $messageId = $message['message_id'] ?? '';
            $chatId = $message['chat_id'] ?? '';
            
            error_log("New message from {$sender['sender_id']}: $messageText");

            // Проверяем, что сообщение адресовано боту
            if (!$this->isMessageForBot($messageText, $message)) {
                error_log("Message not for bot, ignoring");
                return;
            }

            // Обрабатываем сообщение через ИИ
            $aiResponse = $this->aiService->processMessage($messageText, [
                'senderId' => $sender['sender_id'] ?? '',
                'chatId' => $chatId,
                'messageId' => $messageId
            ]);

            if ($aiResponse) {
                // Отправляем ответ через Lark API
                $this->larkService->sendMessage($chatId, $aiResponse, $messageId);
                error_log("Response sent successfully");
            }

        } catch (Exception $e) {
            error_log("Error processing message: " . $e->getMessage());
            
            // Отправляем сообщение об ошибке пользователю
            try {
                $this->larkService->sendMessage(
                    $event['message']['chat_id'] ?? '',
                    'Извините, произошла ошибка при обработке вашего сообщения. Попробуйте позже.',
                    $event['message']['message_id'] ?? ''
                );
            } catch (Exception $sendError) {
                error_log("Failed to send error message: " . $sendError->getMessage());
            }
        }
    }

    /**
     * Извлекает текст из сообщения Lark
     */
    private function extractMessageText($message)
    {
        $content = $message['content'] ?? '';
        $decoded = json_decode($content, true);
        return $decoded['text'] ?? $content;
    }

    /**
     * Проверяет, адресовано ли сообщение боту
     */
    private function isMessageForBot($messageText, $message)
    {
        // Проверяем упоминание бота (@bot)
        if (isset($message['mentions']) && !empty($message['mentions'])) {
            return true;
        }
        
        // Проверяем приватный чат
        if (($message['chat_type'] ?? '') === 'p2p') {
            return true;
        }
        
        // Проверяем ключевые слова
        $botKeywords = ['бот', 'помощь', 'помоги', 'assistant', 'help'];
        $lowerText = strtolower($messageText);
        
        foreach ($botKeywords as $keyword) {
            if (strpos($lowerText, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Отправляет успешный ответ
     */
    private function sendResponse($data)
    {
        http_response_code(200);
        echo json_encode($data);
    }

    /**
     * Отправляет ошибку
     */
    private function sendError($message)
    {
        http_response_code(400);
        echo json_encode(['error' => $message]);
    }
}
