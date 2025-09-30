<?php
/**
 * Сервис для работы с Lark API
 */

class LarkService
{
    private $appId;
    private $appSecret;
    private $baseUrl;
    private $accessToken;
    private $tokenExpiresAt;

    public function __construct()
    {
        $this->appId = LARK_APP_ID;
        $this->appSecret = LARK_APP_SECRET;
        $this->baseUrl = LARK_BASE_URL;
        $this->accessToken = null;
        $this->tokenExpiresAt = null;
    }

    /**
     * Инициализация сервиса
     */
    public function initialize()
    {
        if (empty($this->appId) || empty($this->appSecret)) {
            throw new Exception('Lark App ID and Secret are required');
        }
        
        $this->refreshAccessToken();
        error_log('Lark service initialized');
    }

    /**
     * Получение/обновление access token
     */
    private function refreshAccessToken()
    {
        try {
            $response = $this->makeRequest('POST', '/auth/v3/tenant_access_token/internal', [
                'app_id' => $this->appId,
                'app_secret' => $this->appSecret
            ]);

            $this->accessToken = $response['tenant_access_token'] ?? '';
            $this->tokenExpiresAt = time() + (($response['expire'] ?? 7200) - 60); // Обновляем за минуту до истечения
            
            error_log('Lark access token refreshed');
        } catch (Exception $e) {
            error_log('Failed to get Lark access token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Проверка и обновление токена при необходимости
     */
    private function ensureValidToken()
    {
        if (!$this->accessToken || time() >= $this->tokenExpiresAt) {
            $this->refreshAccessToken();
        }
    }

    /**
     * Отправка сообщения в чат
     */
    public function sendMessage($chatId, $content, $replyToMessageId = null)
    {
        $this->ensureValidToken();

        try {
            $messageData = [
                'receive_id' => $chatId,
                'msg_type' => 'text',
                'content' => json_encode([
                    'text' => $content
                ])
            ];

            // Если это ответ на сообщение, добавляем reply
            if ($replyToMessageId) {
                $messageData['reply_to_message_id'] = $replyToMessageId;
            }

            $response = $this->makeRequest('POST', '/im/v1/messages', $messageData, [
                'receive_id_type' => 'chat_id'
            ], [
                'Authorization: Bearer ' . $this->accessToken
            ]);

            error_log('Message sent successfully: ' . json_encode($response));
            return $response;
        } catch (Exception $e) {
            error_log('Failed to send message: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Отправка карточки с кнопками
     */
    public function sendCard($chatId, $cardContent, $replyToMessageId = null)
    {
        $this->ensureValidToken();

        try {
            $messageData = [
                'receive_id' => $chatId,
                'msg_type' => 'interactive',
                'content' => json_encode($cardContent)
            ];

            if ($replyToMessageId) {
                $messageData['reply_to_message_id'] = $replyToMessageId;
            }

            $response = $this->makeRequest('POST', '/im/v1/messages', $messageData, [
                'receive_id_type' => 'chat_id'
            ], [
                'Authorization: Bearer ' . $this->accessToken
            ]);

            error_log('Card sent successfully: ' . json_encode($response));
            return $response;
        } catch (Exception $e) {
            error_log('Failed to send card: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получение информации о чате
     */
    public function getChatInfo($chatId)
    {
        $this->ensureValidToken();

        try {
            $response = $this->makeRequest('GET', "/im/v1/chats/$chatId", [], [], [
                'Authorization: Bearer ' . $this->accessToken
            ]);

            return $response;
        } catch (Exception $e) {
            error_log('Failed to get chat info: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Выполнение HTTP запроса
     */
    private function makeRequest($method, $endpoint, $data = [], $params = [], $headers = [])
    {
        $url = $this->baseUrl . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $defaultHeaders = [
            'Content-Type: application/json',
            'User-Agent: Lark-AI-Bot/1.0'
        ];

        $headers = array_merge($defaultHeaders, $headers);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: $error");
        }

        if ($httpCode >= 400) {
            throw new Exception("HTTP error $httpCode: $response");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: $response");
        }

        return $decoded;
    }
}
