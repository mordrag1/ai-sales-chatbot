<?php
declare(strict_types=1);

/**
 * Bot Management API
 * 
 * GET    /api/bots.php              - List all user's bots
 * GET    /api/bots.php?id=X         - Get specific bot
 * POST   /api/bots.php              - Create new bot
 * PUT    /api/bots.php?id=X         - Update bot
 * DELETE /api/bots.php?id=X         - Delete bot
 * 
 * All endpoints require X-Auth-Token header
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Auth-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

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

// Authenticate user via token
$authToken = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
if ($authToken === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required', 'code' => 'AUTH_REQUIRED']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, client_id, plan_id, email, name FROM users WHERE auth_token = ? AND token_expires_at > NOW() LIMIT 1');
$stmt->execute([$authToken]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token', 'code' => 'INVALID_TOKEN']);
    exit;
}

$userId = (int)$user['id'];
$planId = $user['plan_id'];

// Get plan limits
try {
    $stmt = $pdo->prepare('SELECT * FROM plans WHERE id = ? LIMIT 1');
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Plans table might not exist yet
    $plan = null;
}

if (!$plan) {
    // Fallback to demo plan
    $plan = [
        'id' => 'demo',
        'name' => 'Demo',
        'max_bots' => 1,
        'max_messages_per_month' => 500,
        'allowed_domains' => '["https://weba-ai.com"]',
    ];
}

$maxBots = $plan['max_bots'] === null ? PHP_INT_MAX : (int)$plan['max_bots'];

$method = $_SERVER['REQUEST_METHOD'];
$botId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// GET - List bots or get specific bot
if ($method === 'GET') {
    try {
        if ($botId !== null) {
            // Get specific bot
            $stmt = $pdo->prepare('SELECT * FROM bots WHERE id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$botId, $userId]);
            $bot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bot) {
                http_response_code(404);
                echo json_encode(['error' => 'Bot not found', 'code' => 'BOT_NOT_FOUND']);
                exit;
            }
            
            // Get current month usage
            $yearMonth = date('Y-m');
            try {
                $stmt = $pdo->prepare('SELECT message_count FROM message_usage WHERE bot_id = ? AND `year_month` = ? LIMIT 1');
                $stmt->execute([$botId, $yearMonth]);
                $usage = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $usage = null;
            }
            
            echo json_encode([
                'success' => true,
                'bot' => formatBot($bot),
                'usage' => [
                    'messages_this_month' => (int)($usage['message_count'] ?? 0),
                    'limit' => $plan['max_messages_per_month'],
                ],
            ]);
        } else {
            // List all bots
            $stmt = $pdo->prepare('SELECT * FROM bots WHERE user_id = ? ORDER BY created_at DESC');
            $stmt->execute([$userId]);
            $bots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get usage for current month
            $yearMonth = date('Y-m');
            $usageMap = [];
            try {
                $stmt = $pdo->prepare('SELECT bot_id, message_count FROM message_usage WHERE user_id = ? AND `year_month` = ?');
                $stmt->execute([$userId, $yearMonth]);
                $usageRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($usageRows as $row) {
                    $usageMap[(int)$row['bot_id']] = (int)$row['message_count'];
                }
            } catch (PDOException $e) {
                // message_usage table might not exist
            }
            
            $result = [];
            foreach ($bots as $bot) {
                $formatted = formatBot($bot);
                $formatted['messages_this_month'] = $usageMap[(int)$bot['id']] ?? 0;
                $result[] = $formatted;
            }
        
        echo json_encode([
            'success' => true,
            'bots' => $result,
            'count' => count($result),
            'plan' => [
                'id' => $plan['id'],
                'name' => $plan['name'] ?? 'Demo',
                'max_bots' => $plan['max_bots'],
                'max_messages_per_month' => $plan['max_messages_per_month'],
            ],
            'can_create_more' => count($result) < $maxBots,
        ]);
        }
    } catch (PDOException $e) {
        // Bots table might not exist - return empty list
        echo json_encode([
            'success' => true,
            'bots' => [],
            'count' => 0,
            'plan' => [
                'id' => $plan['id'],
                'name' => $plan['name'] ?? 'Demo',
                'max_bots' => $plan['max_bots'],
                'max_messages_per_month' => $plan['max_messages_per_month'],
            ],
            'can_create_more' => true,
            'notice' => 'Bots table not initialized. Run SQL migration.',
        ]);
    }
    exit;
}

// POST - Create new bot
if ($method === 'POST') {
    // Check bot limit
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM bots WHERE user_id = ?');
    $stmt->execute([$userId]);
    $count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    if ($count >= $maxBots) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Bot limit reached',
            'code' => 'BOT_LIMIT_REACHED',
            'current' => $count,
            'limit' => $maxBots,
            'upgrade_url' => 'https://weba-ai.com/dashboard',
        ]);
        exit;
    }
    
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '{}', true) ?? [];
    
    $botHash = bin2hex(random_bytes(16));
    $name = trim($payload['name'] ?? 'My Bot');
    $widgetTitle = trim($payload['widget_title'] ?? 'Support');
    $widgetOperatorLabel = trim($payload['widget_operator_label'] ?? 'Operator Online');
    $widgetWelcome = $payload['widget_welcome'] ?? null;
    $widgetPlaceholder = trim($payload['widget_placeholder'] ?? 'Type your message...');
    $widgetTypingLabel = trim($payload['widget_typing_label'] ?? 'Operator typing...');
    $widgetSoundEnabled = isset($payload['widget_sound_enabled']) ? (int)(bool)$payload['widget_sound_enabled'] : 1;
    $allowedDomains = isset($payload['allowed_domains']) ? json_encode($payload['allowed_domains']) : null;
    $n8nWebhookUrl = $payload['n8n_webhook_url'] ?? null;
    
    $stmt = $pdo->prepare('
        INSERT INTO bots (user_id, bot_hash, name, widget_title, widget_operator_label, widget_welcome, 
                         widget_placeholder, widget_typing_label, widget_sound_enabled, allowed_domains, n8n_webhook_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $userId, $botHash, $name, $widgetTitle, $widgetOperatorLabel, $widgetWelcome,
        $widgetPlaceholder, $widgetTypingLabel, $widgetSoundEnabled, $allowedDomains, $n8nWebhookUrl
    ]);
    
    $newBotId = (int)$pdo->lastInsertId();
    
    $stmt = $pdo->prepare('SELECT * FROM bots WHERE id = ? LIMIT 1');
    $stmt->execute([$newBotId]);
    $newBot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'bot' => formatBot($newBot),
        'embed_code' => '<script src="https://cdn.weba-ai.com/widget.php?h=' . $botHash . '"></script>',
    ]);
    exit;
}

// PUT - Update bot
if ($method === 'PUT') {
    if ($botId === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Bot ID required', 'code' => 'BOT_ID_REQUIRED']);
        exit;
    }
    
    // Check ownership
    $stmt = $pdo->prepare('SELECT * FROM bots WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$botId, $userId]);
    $bot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bot) {
        http_response_code(404);
        echo json_encode(['error' => 'Bot not found', 'code' => 'BOT_NOT_FOUND']);
        exit;
    }
    
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '{}', true) ?? [];
    
    $updates = [];
    $params = [];
    
    $allowedFields = [
        'name', 'widget_title', 'widget_operator_label', 'widget_welcome',
        'widget_placeholder', 'widget_typing_label', 'widget_sound_enabled',
        'allowed_domains', 'n8n_webhook_url', 'dataset', 'is_active'
    ];
    
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $payload)) {
            if ($field === 'allowed_domains' || $field === 'dataset') {
                $updates[] = "`$field` = ?";
                $params[] = is_array($payload[$field]) ? json_encode($payload[$field]) : $payload[$field];
            } elseif ($field === 'widget_sound_enabled' || $field === 'is_active') {
                $updates[] = "`$field` = ?";
                $params[] = (int)(bool)$payload[$field];
            } else {
                $updates[] = "`$field` = ?";
                $params[] = $payload[$field];
            }
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update', 'code' => 'NO_FIELDS']);
        exit;
    }
    
    $params[] = $botId;
    $sql = 'UPDATE bots SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $stmt = $pdo->prepare('SELECT * FROM bots WHERE id = ? LIMIT 1');
    $stmt->execute([$botId]);
    $updatedBot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'bot' => formatBot($updatedBot),
    ]);
    exit;
}

// DELETE - Delete bot
if ($method === 'DELETE') {
    if ($botId === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Bot ID required', 'code' => 'BOT_ID_REQUIRED']);
        exit;
    }
    
    // Check ownership
    $stmt = $pdo->prepare('SELECT id FROM bots WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$botId, $userId]);
    $bot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bot) {
        http_response_code(404);
        echo json_encode(['error' => 'Bot not found', 'code' => 'BOT_NOT_FOUND']);
        exit;
    }
    
    $stmt = $pdo->prepare('DELETE FROM bots WHERE id = ?');
    $stmt->execute([$botId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Bot deleted successfully',
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

// Helper function to format bot for API response
function formatBot(array $bot): array {
    return [
        'id' => (int)$bot['id'],
        'bot_hash' => $bot['bot_hash'],
        'name' => $bot['name'],
        'widget_title' => $bot['widget_title'],
        'widget_operator_label' => $bot['widget_operator_label'],
        'widget_welcome' => $bot['widget_welcome'],
        'widget_placeholder' => $bot['widget_placeholder'],
        'widget_typing_label' => $bot['widget_typing_label'],
        'widget_sound_enabled' => (bool)$bot['widget_sound_enabled'],
        'allowed_domains' => $bot['allowed_domains'] ? json_decode($bot['allowed_domains'], true) : null,
        'dataset' => $bot['dataset'] ? json_decode($bot['dataset'], true) : null,
        'n8n_webhook_url' => $bot['n8n_webhook_url'],
        'is_active' => (bool)$bot['is_active'],
        'embed_code' => '<script src="https://cdn.weba-ai.com/widget.php?h=' . $bot['bot_hash'] . '"></script>',
        'created_at' => $bot['created_at'],
        'updated_at' => $bot['updated_at'],
    ];
}

