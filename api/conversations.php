<?php
declare(strict_types=1);

/**
 * Conversations List API - Get all conversations for a bot
 * 
 * GET /api/conversations.php?bot_hash=X              - List all conversations for a bot
 * GET /api/conversations.php?bot_hash=X&user_id=Y    - Get specific conversation
 * 
 * Optional parameters:
 *   limit: Number of conversations to return (default 50, max 100)
 *   offset: Pagination offset (default 0)
 *   sort: Sort order - "newest" or "oldest" (default "newest")
 * 
 * Requires X-Auth-Token header (bot owner authentication)
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Auth-Token');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Load environment
$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '=') !== false && $line[0] !== '#') {
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $env[trim($parts[0])] = trim($parts[1]);
            }
        }
    }
}

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_NAME'] ?? 'aicdn';
$dbUser = $env['DB_USER'] ?? 'aicdn';
$dbPass = $env['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Authenticate user via token
$authToken = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
if ($authToken === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required', 'code' => 'AUTH_REQUIRED']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, client_id FROM users WHERE auth_token = ? AND token_expires_at > NOW() LIMIT 1');
$stmt->execute([$authToken]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token', 'code' => 'INVALID_TOKEN']);
    exit;
}

$userId = (int)$user['id'];
$userClientId = $user['client_id'];

// Get parameters
$botHash = $_GET['bot_hash'] ?? $_GET['botHash'] ?? '';
$visitorId = $_GET['user_id'] ?? $_GET['userId'] ?? '';
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$sort = ($_GET['sort'] ?? 'newest') === 'oldest' ? 'ASC' : 'DESC';

if ($botHash === '') {
    http_response_code(400);
    echo json_encode(['error' => 'bot_hash is required']);
    exit;
}

// Verify bot belongs to user
$botClientId = null;
try {
    $stmt = $pdo->prepare('SELECT b.id, u.client_id FROM bots b JOIN users u ON b.user_id = u.id WHERE b.bot_hash = ? AND b.user_id = ? LIMIT 1');
    $stmt->execute([$botHash, $userId]);
    $bot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bot) {
        $botClientId = $bot['client_id'];
    }
} catch (PDOException $e) {
    // Bots table might not exist
}

// Fallback: check if bot_hash matches user's widget_hash (legacy)
if ($botClientId === null) {
    $stmt = $pdo->prepare('SELECT client_id FROM users WHERE widget_hash = ? AND id = ? LIMIT 1');
    $stmt->execute([$botHash, $userId]);
    $legacyUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($legacyUser) {
        $botClientId = $legacyUser['client_id'];
    }
}

if ($botClientId === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Bot not found or access denied', 'code' => 'BOT_NOT_FOUND']);
    exit;
}

// Get specific conversation or list all
if ($visitorId !== '') {
    // Get specific conversation
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE client_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$botClientId, $visitorId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        http_response_code(404);
        echo json_encode(['error' => 'Conversation not found', 'code' => 'CONVERSATION_NOT_FOUND']);
        exit;
    }
    
    $dialog = json_decode($conversation['dialog'] ?? '[]', true) ?: [];
    
    echo json_encode([
        'success' => true,
        'conversation' => [
            'id' => (int)$conversation['id'],
            'bot_hash' => $botHash,
            'client_id' => $conversation['client_id'],
            'user_id' => $conversation['user_id'],
            'dialog' => $dialog,
            'message_count' => (int)$conversation['message_count'],
            'user_agent' => $conversation['user_agent'],
            'ip_address' => $conversation['ip_address'],
            'referrer' => $conversation['referrer'],
            'page_url' => $conversation['page_url'],
            'last_message_at' => $conversation['last_message_at'],
            'created_at' => $conversation['created_at'],
        ],
    ]);
    exit;
}

// List all conversations for this bot
$stmt = $pdo->prepare("
    SELECT * FROM conversations 
    WHERE client_id = ? 
    ORDER BY last_message_at $sort 
    LIMIT ? OFFSET ?
");
$stmt->execute([$botClientId, $limit, $offset]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$countStmt = $pdo->prepare('SELECT COUNT(*) as total FROM conversations WHERE client_id = ?');
$countStmt->execute([$botClientId]);
$total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

$result = [];
foreach ($conversations as $conv) {
    $dialog = json_decode($conv['dialog'] ?? '[]', true) ?: [];
    $lastMessage = !empty($dialog) ? end($dialog) : null;
    
    $result[] = [
        'id' => (int)$conv['id'],
        'user_id' => $conv['user_id'],
        'message_count' => (int)$conv['message_count'],
        'last_message' => $lastMessage ? [
            'role' => $lastMessage['role'] ?? 'unknown',
            'text' => mb_substr($lastMessage['text'] ?? '', 0, 100) . (mb_strlen($lastMessage['text'] ?? '') > 100 ? '...' : ''),
            'timestamp' => $lastMessage['timestamp'] ?? null,
        ] : null,
        'page_url' => $conv['page_url'],
        'user_agent' => $conv['user_agent'],
        'last_message_at' => $conv['last_message_at'],
        'created_at' => $conv['created_at'],
    ];
}

echo json_encode([
    'success' => true,
    'bot_hash' => $botHash,
    'conversations' => $result,
    'pagination' => [
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => ($offset + $limit) < $total,
    ],
]);

