<?php
declare(strict_types=1);

/**
 * Usage Tracking API
 * 
 * GET  /api/usage.php?bot_hash=X           - Get usage stats for a bot
 * POST /api/usage.php                      - Increment message count (called by n8n or widget)
 * GET  /api/usage.php?check=1&bot_hash=X   - Check if limit reached
 * 
 * Internal API for tracking message usage per bot per month
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Auth-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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

$method = $_SERVER['REQUEST_METHOD'];
$yearMonth = date('Y-m');

// Helper to get bot and user info
function getBotInfo(PDO $pdo, string $botHash): ?array {
    $stmt = $pdo->prepare('
        SELECT b.*, u.id as owner_id, u.plan_id, p.max_messages_per_month 
        FROM bots b 
        JOIN users u ON b.user_id = u.id 
        LEFT JOIN plans p ON u.plan_id = p.id 
        WHERE b.bot_hash = ? 
        LIMIT 1
    ');
    $stmt->execute([$botHash]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// GET - Get usage stats
if ($method === 'GET') {
    $botHash = $_GET['bot_hash'] ?? '';
    $check = isset($_GET['check']) && $_GET['check'] === '1';
    
    if ($botHash === '') {
        http_response_code(400);
        echo json_encode(['error' => 'bot_hash is required']);
        exit;
    }
    
    $bot = getBotInfo($pdo, $botHash);
    if (!$bot) {
        http_response_code(404);
        echo json_encode(['error' => 'Bot not found', 'code' => 'BOT_NOT_FOUND']);
        exit;
    }
    
    // Get usage for this user (all bots)
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(message_count), 0) as total FROM message_usage WHERE user_id = ? AND year_month = ?');
    $stmt->execute([$bot['owner_id'], $yearMonth]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);
    $messagesUsed = (int)$usage['total'];
    
    // Get usage for this specific bot
    $stmt = $pdo->prepare('SELECT message_count FROM message_usage WHERE bot_id = ? AND year_month = ? LIMIT 1');
    $stmt->execute([$bot['id'], $yearMonth]);
    $botUsage = $stmt->fetch(PDO::FETCH_ASSOC);
    $botMessages = (int)($botUsage['message_count'] ?? 0);
    
    $maxMessages = $bot['max_messages_per_month'];
    $limitReached = $maxMessages !== null && $messagesUsed >= (int)$maxMessages;
    
    if ($check) {
        // Quick check mode
        echo json_encode([
            'allowed' => !$limitReached,
            'limit_reached' => $limitReached,
            'messages_used' => $messagesUsed,
            'messages_limit' => $maxMessages,
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'bot_id' => (int)$bot['id'],
        'user_id' => (int)$bot['owner_id'],
        'year_month' => $yearMonth,
        'usage' => [
            'bot_messages' => $botMessages,
            'total_messages' => $messagesUsed,
            'limit' => $maxMessages,
            'remaining' => $maxMessages === null ? null : max(0, (int)$maxMessages - $messagesUsed),
            'limit_reached' => $limitReached,
            'percent_used' => $maxMessages === null ? 0 : round(($messagesUsed / (int)$maxMessages) * 100, 1),
        ],
    ]);
    exit;
}

// POST - Increment message count
if ($method === 'POST') {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '{}', true) ?? [];
    $payload = array_merge($_POST, $payload);
    
    $botHash = $payload['bot_hash'] ?? $payload['botHash'] ?? '';
    $count = (int)($payload['count'] ?? 1);
    
    if ($botHash === '') {
        http_response_code(400);
        echo json_encode(['error' => 'bot_hash is required']);
        exit;
    }
    
    $bot = getBotInfo($pdo, $botHash);
    if (!$bot) {
        http_response_code(404);
        echo json_encode(['error' => 'Bot not found', 'code' => 'BOT_NOT_FOUND']);
        exit;
    }
    
    // Check current usage
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(message_count), 0) as total FROM message_usage WHERE user_id = ? AND year_month = ?');
    $stmt->execute([$bot['owner_id'], $yearMonth]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);
    $messagesUsed = (int)$usage['total'];
    
    $maxMessages = $bot['max_messages_per_month'];
    
    // Check limit before incrementing
    if ($maxMessages !== null && $messagesUsed >= (int)$maxMessages) {
        http_response_code(403);
        echo json_encode([
            'error' => [
                'code' => 'MESSAGE_LIMIT_REACHED',
                'message' => "Message limit reached ({$messagesUsed}/{$maxMessages} this month). Upgrade your plan to continue.",
                'upgradeUrl' => 'https://weba-ai.com/dashboard',
            ],
            'allowed' => false,
            'messages_used' => $messagesUsed,
            'messages_limit' => $maxMessages,
        ]);
        exit;
    }
    
    // Upsert usage record
    $stmt = $pdo->prepare('
        INSERT INTO message_usage (user_id, bot_id, year_month, message_count, last_message_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            message_count = message_count + VALUES(message_count),
            last_message_at = NOW()
    ');
    $stmt->execute([$bot['owner_id'], $bot['id'], $yearMonth, $count]);
    
    $newTotal = $messagesUsed + $count;
    
    echo json_encode([
        'success' => true,
        'allowed' => true,
        'messages_used' => $newTotal,
        'messages_limit' => $maxMessages,
        'remaining' => $maxMessages === null ? null : max(0, (int)$maxMessages - $newTotal),
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

