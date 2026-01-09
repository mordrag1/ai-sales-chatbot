<?php
declare(strict_types=1);

/**
 * Plans API
 * 
 * GET /api/plans.php           - List all available plans
 * GET /api/plans.php?id=X      - Get specific plan details
 * GET /api/plans.php?current=1 - Get current user's plan and usage
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

// Check if user wants current plan info
$getCurrent = isset($_GET['current']) && $_GET['current'] === '1';
$planId = $_GET['id'] ?? null;

if ($getCurrent) {
    // Need authentication
    $authToken = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if ($authToken === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required', 'code' => 'AUTH_REQUIRED']);
        exit;
    }
    
    $stmt = $pdo->prepare('SELECT id, plan_id, plan_expires_at FROM users WHERE auth_token = ? AND token_expires_at > NOW() LIMIT 1');
    $stmt->execute([$authToken]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token', 'code' => 'INVALID_TOKEN']);
        exit;
    }
    
    $userId = (int)$user['id'];
    $userPlanId = $user['plan_id'];
    
    // Get plan details
    $stmt = $pdo->prepare('SELECT * FROM plans WHERE id = ? LIMIT 1');
    $stmt->execute([$userPlanId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        $plan = [
            'id' => 'demo',
            'name' => 'Demo',
            'max_bots' => 1,
            'max_messages_per_month' => 500,
            'allowed_domains' => '["https://weba-ai.com"]',
            'price_monthly' => '0.00',
        ];
    }
    
    // Get current usage
    $yearMonth = date('Y-m');
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(message_count), 0) as total FROM message_usage WHERE user_id = ? AND year_month = ?');
    $stmt->execute([$userId, $yearMonth]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);
    $messagesUsed = (int)$usage['total'];
    
    // Get bot count
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM bots WHERE user_id = ?');
    $stmt->execute([$userId]);
    $botCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    $maxMessages = $plan['max_messages_per_month'];
    $maxBots = $plan['max_bots'];
    
    echo json_encode([
        'success' => true,
        'plan' => formatPlan($plan),
        'usage' => [
            'messages_used' => $messagesUsed,
            'messages_limit' => $maxMessages,
            'messages_remaining' => $maxMessages === null ? null : max(0, $maxMessages - $messagesUsed),
            'messages_percent' => $maxMessages === null ? 0 : round(($messagesUsed / $maxMessages) * 100, 1),
            'bots_count' => $botCount,
            'bots_limit' => $maxBots,
            'bots_remaining' => $maxBots === null ? null : max(0, $maxBots - $botCount),
        ],
        'expires_at' => $user['plan_expires_at'],
        'limits_reached' => [
            'messages' => $maxMessages !== null && $messagesUsed >= $maxMessages,
            'bots' => $maxBots !== null && $botCount >= $maxBots,
        ],
    ]);
    exit;
}

if ($planId !== null) {
    // Get specific plan
    $stmt = $pdo->prepare('SELECT * FROM plans WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        http_response_code(404);
        echo json_encode(['error' => 'Plan not found', 'code' => 'PLAN_NOT_FOUND']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'plan' => formatPlan($plan),
    ]);
    exit;
}

// List all plans
$stmt = $pdo->query('SELECT * FROM plans WHERE is_active = 1 ORDER BY price_monthly ASC');
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = array_map('formatPlan', $plans);

echo json_encode([
    'success' => true,
    'plans' => $result,
]);

function formatPlan(array $plan): array {
    return [
        'id' => $plan['id'],
        'name' => $plan['name'],
        'max_bots' => $plan['max_bots'] === null ? null : (int)$plan['max_bots'],
        'max_messages_per_month' => $plan['max_messages_per_month'] === null ? null : (int)$plan['max_messages_per_month'],
        'allowed_domains' => $plan['allowed_domains'] ? json_decode($plan['allowed_domains'], true) : null,
        'price_monthly' => (float)$plan['price_monthly'],
        'features' => $plan['features'] ? json_decode($plan['features'], true) : null,
    ];
}

