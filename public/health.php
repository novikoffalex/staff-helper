<?php
/**
 * Health check endpoint
 */

header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'timestamp' => date('c'),
    'service' => 'lark-ai-bot-php',
    'version' => '1.0.0'
]);
