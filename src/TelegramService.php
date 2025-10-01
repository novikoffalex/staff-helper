<?php

class TelegramService
{
    private $botToken;
    private $apiUrl;
    
    public function __construct()
    {
        $this->botToken = getenv('TELEGRAM_BOT_TOKEN');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
        
        if (!$this->botToken) {
            throw new Exception('TELEGRAM_BOT_TOKEN not set');
        }
    }
    
    /**
     * Отправляет сообщение в чат
     */
    public function sendMessage($chatId, $text, $parseMode = 'HTML')
    {
        $url = $this->apiUrl . '/sendMessage';
        
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode
        ];
        
        $response = $this->makeRequest($url, $data);
        
        if (!$response['ok']) {
            throw new Exception('Failed to send message: ' . $response['description']);
        }
        
        return $response['result'];
    }
    
    /**
     * Устанавливает webhook
     */
    public function setWebhook($webhookUrl)
    {
        $url = $this->apiUrl . '/setWebhook';
        
        $data = [
            'url' => $webhookUrl
        ];
        
        $response = $this->makeRequest($url, $data);
        
        if (!$response['ok']) {
            throw new Exception('Failed to set webhook: ' . $response['description']);
        }
        
        return $response;
    }
    
    /**
     * Получает информацию о боте
     */
    public function getMe()
    {
        $url = $this->apiUrl . '/getMe';
        $response = $this->makeRequest($url);
        
        if (!$response['ok']) {
            throw new Exception('Failed to get bot info: ' . $response['description']);
        }
        
        return $response['result'];
    }
    
    /**
     * Выполняет HTTP запрос
     */
    private function makeRequest($url, $data = null)
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('HTTP error: ' . $httpCode);
        }
        
        return json_decode($response, true);
    }
}
?>
