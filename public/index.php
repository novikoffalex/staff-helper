<?php
/**
 * Lark AI Bot - PHP версия
 * Главный файл для обработки webhook от Lark
 */

require_once 'config/config.php';
require_once 'src/LarkService.php';
require_once 'src/AIService.php';
require_once 'src/WebhookHandler.php';

// Устанавливаем заголовки
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обрабатываем OPTIONS запросы
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $webhookHandler = new WebhookHandler();
    $webhookHandler->handle();
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
