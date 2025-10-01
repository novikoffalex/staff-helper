<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;
    private string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    public function sendMessage(int $chatId, string $text, string $parseMode = 'HTML'): array
    {
        $response = Http::post("{$this->apiUrl}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode
        ]);

        Log::info('Telegram sendMessage response', [
            'chat_id' => $chatId,
            'response' => $response->json()
        ]);

        return $response->json();
    }

    public function sendChatAction(int $chatId, string $action = 'typing'): array
    {
        $response = Http::post("{$this->apiUrl}/sendChatAction", [
            'chat_id' => $chatId,
            'action' => $action
        ]);

        return $response->json();
    }

    public function getFile(string $fileId): array
    {
        $response = Http::get("{$this->apiUrl}/getFile", [
            'file_id' => $fileId
        ]);

        return $response->json();
    }

    public function downloadFile(string $filePath): string
    {
        $url = "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
        $response = Http::get($url);
        
        return $response->body();
    }

    public function setWebhook(string $url): array
    {
        $response = Http::post("{$this->apiUrl}/setWebhook", [
            'url' => $url
        ]);

        return $response->json();
    }

    public function getWebhookInfo(): array
    {
        $response = Http::get("{$this->apiUrl}/getWebhookInfo");
        return $response->json();
    }
}
