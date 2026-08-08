<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/ConversationStore.php';
require __DIR__ . '/../src/OllamaClient.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if ($path === '') {
    $path = '/';
}

try {
    if ($path === '/health' && $method === 'GET') {
        handleHealth();
    } elseif ($path === '/config' && $method === 'GET') {
        handleConfig();
    } elseif ($path === '/auth/register' && $method === 'POST') {
        handleRegister();
    } elseif ($path === '/auth/login' && $method === 'POST') {
        handleLogin();
    } elseif ($path === '/auth/logout' && $method === 'POST') {
        handleLogout();
    } elseif ($path === '/auth/me' && $method === 'GET') {
        handleMe();
    } elseif ($path === '/conversations' && $method === 'GET') {
        handleListConversations();
    } elseif ($path === '/conversations' && $method === 'POST') {
        handleCreateConversation();
    } elseif ($method === 'GET' && preg_match('#^/conversations/(\d+)/messages$#', $path, $m) === 1) {
        handleConversationMessages((int) $m[1]);
    } elseif ($method === 'DELETE' && preg_match('#^/conversations/(\d+)$#', $path, $m) === 1) {
        handleDeleteConversation((int) $m[1]);
    } elseif ($path === '/chat' && $method === 'POST') {
        handleChat();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'ไม่พบเส้นทางที่ร้องขอ'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'เกิดข้อผิดพลาดภายในระบบ', 'detail' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function handleHealth(): void
{
    $ok = OllamaClient::ping();
    http_response_code($ok ? 200 : 503);
    echo json_encode(['status' => $ok ? 'ok' : 'unavailable'], JSON_UNESCAPED_UNICODE);
}

function handleConfig(): void
{
    echo json_encode(['orgName' => Config::orgName()], JSON_UNESCAPED_UNICODE);
}

function handleRegister(): void
{
    $data = readJsonBody();
    try {
        $user = Auth::register(
            (string) ($data['username'] ?? ''),
            (string) ($data['password'] ?? ''),
            (string) ($data['displayName'] ?? '')
        );
        echo json_encode(['user' => $user], JSON_UNESCAPED_UNICODE);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function handleLogin(): void
{
    $data = readJsonBody();
    try {
        $user = Auth::login((string) ($data['username'] ?? ''), (string) ($data['password'] ?? ''));
        echo json_encode(['user' => $user], JSON_UNESCAPED_UNICODE);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function handleLogout(): void
{
    Auth::logout();
    echo json_encode(['ok' => true]);
}

function handleMe(): void
{
    $user = Auth::currentUser();
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'ยังไม่ได้เข้าสู่ระบบ'], JSON_UNESCAPED_UNICODE);
        return;
    }
    echo json_encode(['user' => $user], JSON_UNESCAPED_UNICODE);
}

function handleListConversations(): void
{
    $user = Auth::requireAuth();
    echo json_encode(['conversations' => ConversationStore::list($user['id'])], JSON_UNESCAPED_UNICODE);
}

function handleCreateConversation(): void
{
    $user = Auth::requireAuth();
    $id = ConversationStore::create($user['id'], 'บทสนทนาใหม่');
    echo json_encode(['id' => $id], JSON_UNESCAPED_UNICODE);
}

function handleConversationMessages(int $id): void
{
    $user = Auth::requireAuth();
    $conversation = ConversationStore::find($id, $user['id']);
    if ($conversation === null) {
        http_response_code(404);
        echo json_encode(['error' => 'ไม่พบบทสนทนา'], JSON_UNESCAPED_UNICODE);
        return;
    }
    echo json_encode(['messages' => ConversationStore::messages($id)], JSON_UNESCAPED_UNICODE);
}

function handleDeleteConversation(int $id): void
{
    $user = Auth::requireAuth();
    $ok = ConversationStore::delete($id, $user['id']);
    if (!$ok) {
        http_response_code(404);
        echo json_encode(['error' => 'ไม่พบบทสนทนา'], JSON_UNESCAPED_UNICODE);
        return;
    }
    echo json_encode(['ok' => true]);
}

function handleChat(): void
{
    $user = Auth::requireAuth();
    $data = readJsonBody();

    $message = trim((string) ($data['message'] ?? ''));
    if ($message === '') {
        http_response_code(400);
        echo json_encode(['error' => 'กรุณาระบุข้อความ (message)'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $mode = ($data['mode'] ?? 'chat') === 'code' ? 'code' : 'chat';
    $conversationId = isset($data['conversationId']) && $data['conversationId'] !== null
        ? (int) $data['conversationId']
        : null;

    if ($conversationId !== null) {
        $conversation = ConversationStore::find($conversationId, $user['id']);
        if ($conversation === null) {
            http_response_code(404);
            echo json_encode(['error' => 'ไม่พบบทสนทนา'], JSON_UNESCAPED_UNICODE);
            return;
        }
    } else {
        $conversationId = ConversationStore::create($user['id'], ConversationStore::makeTitle($message));
    }

    $history = ConversationStore::messages($conversationId);

    $systemPrompt = $mode === 'code' ? Config::codeSystemPrompt() : Config::systemPrompt();
    $model = $mode === 'code' ? Config::codeModel() : Config::ollamaModel();

    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($history as $item) {
        $messages[] = ['role' => $item['role'], 'content' => $item['content']];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    ConversationStore::addMessage($conversationId, 'user', $message);

    try {
        $reply = OllamaClient::chat($messages, $model);
    } catch (Throwable $e) {
        http_response_code(502);
        echo json_encode([
            'error' => 'ไม่สามารถเชื่อมต่อกับ AI ได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง',
            'detail' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    ConversationStore::addMessage($conversationId, 'assistant', $reply);

    echo json_encode(['reply' => $reply, 'conversationId' => $conversationId], JSON_UNESCAPED_UNICODE);
}
