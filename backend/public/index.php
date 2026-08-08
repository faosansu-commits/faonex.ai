<?php

declare(strict_types=1);

// ไม่กำหนด SameSite/HttpOnly ชัดเจน ทำให้แต่ละเบราว์เซอร์ใช้ค่า default ของตัวเอง
// (Chrome/Firefox/Safari ตีความต่างกัน) ส่งผลให้ login ใช้งานได้ไม่เหมือนกันข้ามเบราว์เซอร์
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false, // ระบบนี้รันผ่าน HTTP ธรรมดา ถ้าเปลี่ยนไปใช้ HTTPS ควรตั้งเป็น true
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/ConversationStore.php';
require __DIR__ . '/../src/OllamaClient.php';
require __DIR__ . '/../src/UsageStore.php';
require __DIR__ . '/../src/ContentModerator.php';
require __DIR__ . '/../src/ModerationStore.php';
require __DIR__ . '/../src/SystemMonitor.php';
require __DIR__ . '/../src/ApiKeyStore.php';
require __DIR__ . '/../src/UserImportExport.php';
require __DIR__ . '/../src/TextChunker.php';
require __DIR__ . '/../src/PdfTextExtractor.php';
require __DIR__ . '/../src/KnowledgeStore.php';
require __DIR__ . '/../src/RagService.php';
require __DIR__ . '/../src/KnowledgeTemplateBuilder.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if ($path === '') {
    $path = '/';
}

// /v1/* is the public API for other servers — API-key authenticated, CORS-open.
// Everything else stays session-cookie-only with no CORS headers.
if (str_starts_with($path, '/v1/')) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

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
    } elseif ($path === '/usage/me' && $method === 'GET') {
        handleUsageMe();
    } elseif ($path === '/models' && $method === 'GET') {
        handleListModels();
    } elseif ($path === '/models/pull' && $method === 'POST') {
        handlePullModel();
    } elseif ($path === '/admin/users' && $method === 'GET') {
        handleAdminListUsers();
    } elseif ($path === '/admin/users' && $method === 'POST') {
        handleAdminCreateUser();
    } elseif ($path === '/admin/users/export' && $method === 'GET') {
        handleAdminExportUsersXlsx();
    } elseif ($path === '/admin/users/import' && $method === 'POST') {
        handleAdminImportUsers();
    } elseif ($method === 'GET' && preg_match('#^/admin/users/(\d+)/export$#', $path, $m) === 1) {
        handleAdminExportUserPdf((int) $m[1]);
    } elseif ($method === 'PUT' && preg_match('#^/admin/users/(\d+)$#', $path, $m) === 1) {
        handleAdminUpdateUser((int) $m[1]);
    } elseif ($method === 'DELETE' && preg_match('#^/admin/users/(\d+)$#', $path, $m) === 1) {
        handleAdminDeleteUser((int) $m[1]);
    } elseif ($path === '/admin/flags' && $method === 'GET') {
        handleAdminFlags();
    } elseif ($path === '/admin/stats' && $method === 'GET') {
        handleAdminStats();
    } elseif ($path === '/admin/system' && $method === 'GET') {
        handleAdminSystem();
    } elseif ($path === '/admin/database' && $method === 'GET') {
        handleAdminDatabase();
    } elseif ($path === '/admin/api-keys' && $method === 'GET') {
        handleAdminListApiKeys();
    } elseif ($path === '/admin/api-keys' && $method === 'POST') {
        handleAdminCreateApiKey();
    } elseif ($method === 'DELETE' && preg_match('#^/admin/api-keys/(\d+)$#', $path, $m) === 1) {
        handleAdminRevokeApiKey((int) $m[1]);
    } elseif ($path === '/admin/knowledge/topics' && $method === 'GET') {
        handleAdminListKnowledgeTopics();
    } elseif ($path === '/admin/knowledge/topics' && $method === 'POST') {
        handleAdminCreateKnowledgeTopic();
    } elseif ($method === 'PUT' && preg_match('#^/admin/knowledge/topics/(\d+)$#', $path, $m) === 1) {
        handleAdminUpdateKnowledgeTopic((int) $m[1]);
    } elseif ($method === 'DELETE' && preg_match('#^/admin/knowledge/topics/(\d+)$#', $path, $m) === 1) {
        handleAdminDeleteKnowledgeTopic((int) $m[1]);
    } elseif ($method === 'GET' && preg_match('#^/admin/knowledge/topics/(\d+)/documents$#', $path, $m) === 1) {
        handleAdminListKnowledgeDocuments((int) $m[1]);
    } elseif ($method === 'POST' && preg_match('#^/admin/knowledge/topics/(\d+)/documents$#', $path, $m) === 1) {
        handleAdminUploadKnowledgeDocument((int) $m[1]);
    } elseif ($method === 'POST' && preg_match('#^/admin/knowledge/topics/(\d+)/text$#', $path, $m) === 1) {
        handleAdminAddKnowledgeText((int) $m[1]);
    } elseif ($method === 'DELETE' && preg_match('#^/admin/knowledge/documents/(\d+)$#', $path, $m) === 1) {
        handleAdminDeleteKnowledgeDocument((int) $m[1]);
    } elseif ($path === '/admin/knowledge/template' && $method === 'GET') {
        handleAdminKnowledgeTemplate();
    } elseif ($path === '/v1/chat' && $method === 'POST') {
        handlePublicChat();
    } elseif ($path === '/v1/models' && $method === 'GET') {
        handlePublicModels();
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

function sanitizeImages($raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach (array_slice($raw, 0, 1) as $img) {
        if (!is_string($img) || $img === '') {
            continue;
        }
        if (preg_match('#^data:image/[a-zA-Z0-9.+-]+;base64,(.+)$#', $img, $m)) {
            $img = $m[1];
        }
        // ~8MB decoded ceiling (base64 is ~4/3 the size of the original bytes).
        if (strlen($img) > 11000000) {
            continue;
        }
        $out[] = $img;
    }

    return $out;
}

/** A picture always routes to the vision model — none of the text-only chat/code models can see it. */
function resolveModelAndPrompt(bool $hasImage, string $mode, ?string $modelId): array
{
    if ($hasImage) {
        return [Config::visionModelTag(), Config::visionSystemPrompt()];
    }
    if ($mode === 'code') {
        return [Config::codeModel(), Config::codeSystemPrompt()];
    }
    return [Config::resolveChatModelTag($modelId), Config::systemPrompt()];
}

function requireApiKey(): int
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        http_response_code(401);
        echo json_encode(['error' => 'ต้องระบุ Authorization: Bearer <api_key>'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $keyId = ApiKeyStore::verify(trim($m[1]));
    if ($keyId === null) {
        http_response_code(401);
        echo json_encode(['error' => 'API key ไม่ถูกต้องหรือถูกยกเลิกแล้ว'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $keyId;
}

function handlePublicChat(): void
{
    $apiKeyId = requireApiKey();
    $data = readJsonBody();

    $message = trim((string) ($data['message'] ?? ''));
    $images = sanitizeImages($data['images'] ?? []);
    $hasImage = !empty($images);

    if ($message === '' && $hasImage) {
        $message = 'อธิบายสิ่งที่เห็นในภาพนี้';
    }
    if ($message === '') {
        http_response_code(400);
        echo json_encode(['error' => 'กรุณาระบุ message'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $mode = ($data['mode'] ?? 'chat') === 'code' ? 'code' : 'chat';
    $modelId = isset($data['model']) ? (string) $data['model'] : null;

    $requestLimit = Config::defaultDailyRequestLimit();
    $tokenLimit = Config::defaultDailyTokenLimit();
    $usage = UsageStore::todayUsageForApiKey($apiKeyId);

    if ($requestLimit !== null && $usage['requests'] >= $requestLimit) {
        http_response_code(429);
        echo json_encode(['error' => "API key นี้ใช้งานครบโควตารายวันแล้ว ({$requestLimit} ครั้ง/วัน)"], JSON_UNESCAPED_UNICODE);
        return;
    }
    if ($tokenLimit !== null && $usage['tokens'] >= $tokenLimit) {
        http_response_code(429);
        echo json_encode(['error' => "API key นี้ใช้ token ครบโควตารายวันแล้ว ({$tokenLimit} token/วัน)"], JSON_UNESCAPED_UNICODE);
        return;
    }

    [$model, $systemPrompt] = resolveModelAndPrompt($hasImage, $mode, $modelId);

    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    $history = is_array($data['history'] ?? null) ? $data['history'] : [];
    foreach (array_slice($history, -20) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $role = $item['role'] ?? '';
        $content = $item['content'] ?? '';
        if (!in_array($role, ['user', 'assistant'], true) || !is_string($content) || $content === '') {
            continue;
        }
        $messages[] = ['role' => $role, 'content' => $content];
    }

    $userTurn = ['role' => 'user', 'content' => $message];
    if ($hasImage) {
        $userTurn['images'] = $images;
    }
    $messages[] = $userTurn;

    try {
        $result = OllamaClient::chat($messages, $model);
    } catch (Throwable $e) {
        http_response_code(502);
        echo json_encode([
            'error' => 'ไม่สามารถเชื่อมต่อกับ AI ได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง',
            'detail' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    UsageStore::logUsage(0, null, $mode, $result['promptTokens'], $result['completionTokens'], $apiKeyId);

    echo json_encode([
        'reply' => $result['content'],
        'promptTokens' => $result['promptTokens'],
        'completionTokens' => $result['completionTokens'],
    ], JSON_UNESCAPED_UNICODE);
}

function handlePublicModels(): void
{
    requireApiKey();
    $installed = OllamaClient::listInstalledTags();

    $models = [];
    foreach (Config::chatModelCatalog() as $entry) {
        $models[] = [
            'id' => $entry['id'],
            'label' => $entry['label'],
            'installed' => OllamaClient::isInstalled($entry['tag'], $installed),
        ];
    }

    echo json_encode(['models' => $models], JSON_UNESCAPED_UNICODE);
}

function handleChat(): void
{
    $user = Auth::requireAuth();
    $data = readJsonBody();

    $message = trim((string) ($data['message'] ?? ''));
    $images = sanitizeImages($data['images'] ?? []);
    $hasImage = !empty($images);

    $attachmentName = trim((string) ($data['attachmentName'] ?? ''));
    $attachmentText = (string) ($data['attachmentText'] ?? '');
    if (mb_strlen($attachmentText) > 20000) {
        $attachmentText = mb_substr($attachmentText, 0, 20000) . "\n...(เนื้อหาถูกตัดเนื่องจากยาวเกินไป)";
    }

    if ($message === '' && $hasImage) {
        $message = 'อธิบายสิ่งที่เห็นในภาพนี้';
    }
    if ($message === '' && $attachmentText !== '') {
        $message = 'สรุปเนื้อหาจากไฟล์ที่แนบมา';
    }
    if ($message === '') {
        http_response_code(400);
        echo json_encode(['error' => 'กรุณาระบุข้อความ (message)'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $mode = ($data['mode'] ?? 'chat') === 'code' ? 'code' : 'chat';
    $modelId = isset($data['model']) ? (string) $data['model'] : null;

    // Enforce daily quota before doing any work, so an over-quota user never
    // triggers an Ollama call (this is the main energy-saving lever).
    $requestLimit = $user['dailyRequestLimit'] ?? Config::defaultDailyRequestLimit();
    $tokenLimit = $user['dailyTokenLimit'] ?? Config::defaultDailyTokenLimit();
    $usage = UsageStore::todayUsage($user['id']);

    if ($requestLimit !== null && $usage['requests'] >= $requestLimit) {
        http_response_code(429);
        echo json_encode(['error' => "คุณใช้งานครบโควตารายวันแล้ว ({$requestLimit} ครั้ง/วัน) กรุณาลองใหม่พรุ่งนี้"], JSON_UNESCAPED_UNICODE);
        return;
    }
    if ($tokenLimit !== null && $usage['tokens'] >= $tokenLimit) {
        http_response_code(429);
        echo json_encode(['error' => "คุณใช้ token ครบโควตารายวันแล้ว ({$tokenLimit} token/วัน) กรุณาลองใหม่พรุ่งนี้"], JSON_UNESCAPED_UNICODE);
        return;
    }

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

    // Heuristic keyword flagging for admin review — logs only, never blocks.
    $matchedKeywords = ContentModerator::scan($message);
    if (!empty($matchedKeywords)) {
        ModerationStore::flag($user['id'], $user['username'], $conversationId, $message, $matchedKeywords);
    }

    $storedContent = $message;
    if ($attachmentName !== '') {
        $storedContent .= "\n\n[แนบไฟล์: {$attachmentName}]";
    }
    if ($hasImage) {
        $storedContent .= "\n\n[แนบรูปภาพ]";
    }

    // Admin-defined topic rules take priority over the general system prompt —
    // only for plain text chat turns (not images/code mode). See RagService.
    $ragTopic = (!$hasImage && $mode === 'chat') ? RagService::matchTopic($message) : null;

    $forcedReply = null;
    $model = null;
    $systemPrompt = null;

    if ($ragTopic !== null) {
        try {
            $ragAnswer = RagService::buildAnswer($ragTopic, $message);
        } catch (Throwable $e) {
            http_response_code(502);
            echo json_encode([
                'error' => 'ไม่สามารถเชื่อมต่อกับ AI ได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง',
                'detail' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // No matching content found in the topic's documents — answer with
        // the admin-configured fallback directly, without spending an AI call.
        if ($ragAnswer['forcedReply'] !== null) {
            $forcedReply = $ragAnswer['forcedReply'];
        } else {
            $model = Config::resolveChatModelTag($modelId);
            $systemPrompt = $ragAnswer['systemPrompt'];
        }
    } else {
        [$model, $systemPrompt] = resolveModelAndPrompt($hasImage, $mode, $modelId);
    }

    $messages = [];
    if ($forcedReply === null) {
        $promptForModel = $message;
        if ($attachmentText !== '') {
            $label = $attachmentName !== '' ? " ({$attachmentName})" : '';
            $promptForModel = "เนื้อหาจากไฟล์ที่แนบมา{$label}:\n\n{$attachmentText}\n\n---\n\nคำถาม: {$message}";
        }

        $history = ConversationStore::messages($conversationId);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $item) {
            $messages[] = ['role' => $item['role'], 'content' => $item['content']];
        }

        $userTurn = ['role' => 'user', 'content' => $promptForModel];
        if ($hasImage) {
            $userTurn['images'] = $images;
        }
        $messages[] = $userTurn;
    }

    ConversationStore::addMessage($conversationId, 'user', $storedContent);

    // From here on the response is a streamed NDJSON body (one JSON object
    // per line) so the browser can render the reply as it's generated
    // instead of waiting for the whole thing. The HTTP status is already
    // committed as 200, so failures from this point are reported as an
    // {"error": ...} event rather than an HTTP error status.
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('X-Accel-Buffering: no');
    header('Cache-Control: no-cache');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_implicit_flush(true);

    echo json_encode(['conversationId' => $conversationId], JSON_UNESCAPED_UNICODE) . "\n";
    flush();

    if ($forcedReply !== null) {
        echo json_encode(['delta' => $forcedReply], JSON_UNESCAPED_UNICODE) . "\n";
        flush();

        ConversationStore::addMessage($conversationId, 'assistant', $forcedReply);
        UsageStore::logUsage($user['id'], $conversationId, 'topic', 0, 0);

        echo json_encode(['done' => true, 'promptTokens' => 0, 'completionTokens' => 0], JSON_UNESCAPED_UNICODE) . "\n";
        flush();
        return;
    }

    try {
        $result = OllamaClient::chatStream($messages, $model, function (string $delta): void {
            echo json_encode(['delta' => $delta], JSON_UNESCAPED_UNICODE) . "\n";
            flush();
        });
    } catch (Throwable $e) {
        echo json_encode([
            'error' => 'ไม่สามารถเชื่อมต่อกับ AI ได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง: ' . $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE) . "\n";
        flush();
        return;
    }

    ConversationStore::addMessage($conversationId, 'assistant', $result['content']);
    UsageStore::logUsage($user['id'], $conversationId, $mode, $result['promptTokens'], $result['completionTokens']);

    echo json_encode([
        'done' => true,
        'promptTokens' => $result['promptTokens'],
        'completionTokens' => $result['completionTokens'],
    ], JSON_UNESCAPED_UNICODE) . "\n";
    flush();
}

function handleListModels(): void
{
    Auth::requireAuth();
    $installed = OllamaClient::listInstalledTags();

    $models = [];
    foreach (Config::chatModelCatalog() as $entry) {
        $models[] = [
            'id' => $entry['id'],
            'label' => $entry['label'],
            'vendor' => $entry['vendor'],
            'sizeGb' => $entry['sizeGb'],
            'kind' => 'chat',
            'installed' => OllamaClient::isInstalled($entry['tag'], $installed),
        ];
    }
    $models[] = [
        'id' => 'vision',
        'label' => 'LLaVA-Phi3 (ดูรูปภาพ)',
        'vendor' => 'Microsoft/LLaVA',
        'sizeGb' => 2.9,
        'kind' => 'vision',
        'installed' => OllamaClient::isInstalled(Config::visionModelTag(), $installed),
    ];

    echo json_encode(['models' => $models], JSON_UNESCAPED_UNICODE);
}

function handlePullModel(): void
{
    Auth::requireAuth();
    $data = readJsonBody();
    $modelId = (string) ($data['modelId'] ?? '');
    $tag = Config::resolvePullableTag($modelId);

    if ($tag === null) {
        http_response_code(400);
        echo json_encode(['error' => 'ไม่รู้จักโมเดลนี้'], JSON_UNESCAPED_UNICODE);
        return;
    }

    set_time_limit(0);
    $ok = OllamaClient::pull($tag);

    if (!$ok) {
        http_response_code(502);
        echo json_encode(['error' => 'ดาวน์โหลดโมเดลไม่สำเร็จ กรุณาลองใหม่'], JSON_UNESCAPED_UNICODE);
        return;
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
}

function handleUsageMe(): void
{
    $user = Auth::requireAuth();
    $usage = UsageStore::todayUsage($user['id']);

    echo json_encode([
        'requests' => $usage['requests'],
        'requestLimit' => $user['dailyRequestLimit'] ?? Config::defaultDailyRequestLimit(),
        'tokens' => $usage['tokens'],
        'tokenLimit' => $user['dailyTokenLimit'] ?? Config::defaultDailyTokenLimit(),
    ], JSON_UNESCAPED_UNICODE);
}

function fetchAdminUserRows(string $q): array
{
    $pdo = Database::connection();
    if ($q !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, username, display_name, role, is_active, daily_request_limit, daily_token_limit, created_at
             FROM users WHERE username LIKE ? OR display_name LIKE ? ORDER BY id ASC'
        );
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $pdo->query(
        'SELECT id, username, display_name, role, is_active, daily_request_limit, daily_token_limit, created_at
         FROM users ORDER BY id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
}

function mapAdminUserRow(array $row): array
{
    $usage = UsageStore::todayUsage((int) $row['id']);
    return [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'displayName' => $row['display_name'],
        'role' => $row['role'],
        'isActive' => (bool) $row['is_active'],
        'dailyRequestLimit' => $row['daily_request_limit'] !== null ? (int) $row['daily_request_limit'] : null,
        'dailyTokenLimit' => $row['daily_token_limit'] !== null ? (int) $row['daily_token_limit'] : null,
        'createdAt' => $row['created_at'],
        'todayRequests' => $usage['requests'],
        'todayTokens' => $usage['tokens'],
    ];
}

function handleAdminListUsers(): void
{
    Auth::requireAdmin();
    $q = trim((string) ($_GET['q'] ?? ''));
    $rows = fetchAdminUserRows($q);
    $users = array_map('mapAdminUserRow', $rows);

    echo json_encode(['users' => $users], JSON_UNESCAPED_UNICODE);
}

function handleAdminExportUsersXlsx(): void
{
    Auth::requireAdmin();
    $users = array_map('mapAdminUserRow', fetchAdminUserRows(''));
    $spreadsheet = UserImportExport::buildUsersSpreadsheet($users);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="faonex-users.xlsx"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}

function handleAdminImportUsers(): void
{
    Auth::requireAdmin();
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'กรุณาแนบไฟล์ Excel (.xlsx) ในฟิลด์ file'], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $result = UserImportExport::importFromUploadedFile($_FILES['file']['tmp_name']);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['error' => 'อ่านไฟล์ไม่สำเร็จ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function handleAdminExportUserPdf(int $id): void
{
    Auth::requireAdmin();
    $rows = fetchAdminUserRows('');
    $row = null;
    foreach ($rows as $r) {
        if ((int) $r['id'] === $id) {
            $row = $r;
            break;
        }
    }
    if ($row === null) {
        http_response_code(404);
        echo json_encode(['error' => 'ไม่พบผู้ใช้งานนี้'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $user = mapAdminUserRow($row);
    $usageToday = UsageStore::todayUsage($id);
    $recentUsage = UsageStore::dailyUsageForUser($id, 30);
    $pdfBytes = UserImportExport::buildUserPdf($user, $usageToday, $recentUsage);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="faonex-user-' . $id . '.pdf"');
    echo $pdfBytes;
    exit;
}

function handleAdminCreateUser(): void
{
    Auth::requireAdmin();
    $data = readJsonBody();
    try {
        $user = Auth::adminCreateUser(
            (string) ($data['username'] ?? ''),
            (string) ($data['password'] ?? ''),
            (string) ($data['displayName'] ?? ''),
            (string) ($data['role'] ?? 'user'),
            isset($data['dailyRequestLimit']) && $data['dailyRequestLimit'] !== '' && $data['dailyRequestLimit'] !== null
                ? (int) $data['dailyRequestLimit'] : null,
            isset($data['dailyTokenLimit']) && $data['dailyTokenLimit'] !== '' && $data['dailyTokenLimit'] !== null
                ? (int) $data['dailyTokenLimit'] : null
        );
        echo json_encode(['user' => $user], JSON_UNESCAPED_UNICODE);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function handleAdminUpdateUser(int $id): void
{
    Auth::requireAdmin();
    $data = readJsonBody();
    try {
        Auth::adminUpdateUser($id, $data);
        echo json_encode(['ok' => true]);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function handleAdminDeleteUser(int $id): void
{
    $admin = Auth::requireAdmin();
    try {
        Auth::adminDeleteUser($id, $admin['id']);
        echo json_encode(['ok' => true]);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function handleAdminFlags(): void
{
    Auth::requireAdmin();
    echo json_encode(['flags' => ModerationStore::list()], JSON_UNESCAPED_UNICODE);
}

function handleAdminStats(): void
{
    Auth::requireAdmin();
    $period = in_array($_GET['period'] ?? 'day', ['day', 'month', 'year'], true) ? $_GET['period'] : 'day';

    $stats = UsageStore::overallStats();
    $stats['allTime'] = UsageStore::allTimeTotals();
    $stats['seriesPeriod'] = $period;
    $stats['series'] = UsageStore::periodSeries($period);
    $stats['modeBreakdown'] = UsageStore::modeBreakdown();

    echo json_encode($stats, JSON_UNESCAPED_UNICODE);
}

function handleAdminSystem(): void
{
    Auth::requireAdmin();
    echo json_encode(SystemMonitor::snapshot(), JSON_UNESCAPED_UNICODE);
}

function handleAdminDatabase(): void
{
    Auth::requireAdmin();
    echo json_encode(Database::tableSummary(), JSON_UNESCAPED_UNICODE);
}

function handleAdminListApiKeys(): void
{
    Auth::requireAdmin();
    echo json_encode(['keys' => ApiKeyStore::list()], JSON_UNESCAPED_UNICODE);
}

function handleAdminCreateApiKey(): void
{
    $admin = Auth::requireAdmin();
    $data = readJsonBody();
    $label = trim((string) ($data['label'] ?? ''));

    if ($label === '') {
        http_response_code(400);
        echo json_encode(['error' => 'กรุณาระบุชื่อ (label) ของ API key'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $key = ApiKeyStore::generate($label, $admin['id']);
    echo json_encode(['key' => $key], JSON_UNESCAPED_UNICODE);
}

function handleAdminRevokeApiKey(int $id): void
{
    Auth::requireAdmin();
    ApiKeyStore::revoke($id);
    echo json_encode(['ok' => true]);
}

function handleAdminListKnowledgeTopics(): void
{
    Auth::requireAdmin();
    echo json_encode(['topics' => KnowledgeStore::listTopics()], JSON_UNESCAPED_UNICODE);
}

function handleAdminCreateKnowledgeTopic(): void
{
    $admin = Auth::requireAdmin();
    $data = readJsonBody();
    try {
        $topic = KnowledgeStore::createTopic(
            (string) ($data['name'] ?? ''),
            (string) ($data['keywords'] ?? ''),
            (string) ($data['fallbackMessage'] ?? ''),
            $admin['id']
        );
        echo json_encode(['topic' => $topic], JSON_UNESCAPED_UNICODE);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function handleAdminUpdateKnowledgeTopic(int $id): void
{
    Auth::requireAdmin();
    $data = readJsonBody();
    try {
        $topic = KnowledgeStore::updateTopic($id, $data);
        echo json_encode(['topic' => $topic], JSON_UNESCAPED_UNICODE);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function handleAdminDeleteKnowledgeTopic(int $id): void
{
    Auth::requireAdmin();
    KnowledgeStore::deleteTopic($id);
    echo json_encode(['ok' => true]);
}

function handleAdminListKnowledgeDocuments(int $topicId): void
{
    Auth::requireAdmin();
    echo json_encode(['documents' => KnowledgeStore::listDocuments($topicId)], JSON_UNESCAPED_UNICODE);
}

/** Shared by both the PDF-upload and manual-text ingestion paths. */
function embedTextChunks(string $text): array
{
    $pieces = TextChunker::chunk($text, 800);
    if (empty($pieces)) {
        throw new RuntimeException('ไม่สามารถแบ่งเนื้อหานี้ได้');
    }

    $chunks = [];
    foreach ($pieces as $piece) {
        $chunks[] = ['content' => $piece, 'embedding' => RagService::embedWithAutoPull($piece)];
    }

    return $chunks;
}

function handleAdminUploadKnowledgeDocument(int $topicId): void
{
    $admin = Auth::requireAdmin();

    if (KnowledgeStore::getTopic($topicId) === null) {
        http_response_code(404);
        echo json_encode(['error' => 'ไม่พบหัวข้อนี้'], JSON_UNESCAPED_UNICODE);
        return;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'กรุณาแนบไฟล์ PDF ในฟิลด์ file'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $originalName = (string) $_FILES['file']['name'];
    if (strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)) !== 'pdf') {
        http_response_code(400);
        echo json_encode(['error' => 'รองรับเฉพาะไฟล์ PDF เท่านั้น'], JSON_UNESCAPED_UNICODE);
        return;
    }

    set_time_limit(0);

    try {
        $text = PdfTextExtractor::extractText($_FILES['file']['tmp_name']);
        if (trim($text) === '') {
            throw new RuntimeException('ไม่พบข้อความในไฟล์ PDF นี้ (อาจเป็นไฟล์สแกนภาพที่ไม่มีข้อความ)');
        }

        $chunks = embedTextChunks($text);
        $document = KnowledgeStore::addDocument($topicId, $originalName, $chunks, $admin['id']);
        echo json_encode(['document' => $document], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['error' => 'ประมวลผลไฟล์ไม่สำเร็จ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function handleAdminAddKnowledgeText(int $topicId): void
{
    $admin = Auth::requireAdmin();

    if (KnowledgeStore::getTopic($topicId) === null) {
        http_response_code(404);
        echo json_encode(['error' => 'ไม่พบหัวข้อนี้'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $data = readJsonBody();
    $content = trim((string) ($data['content'] ?? ''));
    if ($content === '') {
        http_response_code(400);
        echo json_encode(['error' => 'กรุณากรอกคำตอบ'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        $title = 'คำตอบที่กรอกเอง ' . date('Y-m-d H:i');
    }

    set_time_limit(0);

    try {
        $chunks = embedTextChunks($content);
        $document = KnowledgeStore::addDocument($topicId, $title, $chunks, $admin['id']);
        echo json_encode(['document' => $document], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['error' => 'บันทึกไม่สำเร็จ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function handleAdminDeleteKnowledgeDocument(int $id): void
{
    Auth::requireAdmin();
    KnowledgeStore::deleteDocument($id);
    echo json_encode(['ok' => true]);
}

function handleAdminKnowledgeTemplate(): void
{
    Auth::requireAdmin();

    $phpWord = KnowledgeTemplateBuilder::build();

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="faonex-ai-knowledge-template.docx"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save('php://output');
    exit;
}
