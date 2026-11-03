<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$token = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($payload['token'] ?? $_GET['token'] ?? ''));

if (strlen($token) < 16 || strlen($token) > 64) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid chat conversation.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['messages' => load_chat_messages($token)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
$name = trim((string) ($payload['name'] ?? 'Guest'));
$email = trim((string) ($payload['email'] ?? ''));
if ($message === '' || strlen($message) > 2000) {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a message up to 2000 characters.']);
    exit;
}

if (!send_chat_message($token, 'customer', $message, $name, $email)) {
    http_response_code(500);
    echo json_encode(['error' => 'Your message could not be sent.']);
    exit;
}

echo json_encode(['success' => true, 'messages' => load_chat_messages($token)]);
