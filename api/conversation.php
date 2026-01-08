<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
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

// GET: Load conversation
if ($method === 'GET') {
    $clientId = $_GET['client_id'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    
    if ($clientId === '' || $userId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'client_id and user_id are required']);
        exit;
    }
    
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE client_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$clientId, $userId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        echo json_encode([
            'exists' => false,
            'dialog' => [],
            'message_count' => 0,
        ]);
        exit;
    }
    
    $dialog = json_decode($conversation['dialog'] ?? '[]', true) ?: [];
    
    echo json_encode([
        'exists' => true,
        'id' => (int)$conversation['id'],
        'bot_id' => $conversation['bot_id'],
        'client_id' => $conversation['client_id'],
        'user_id' => $conversation['user_id'],
        'dialog' => $dialog,
        'message_count' => (int)$conversation['message_count'],
        'last_message_at' => $conversation['last_message_at'],
        'created_at' => $conversation['created_at'],
    ]);
    exit;
}

// POST: Save message to conversation
if ($method === 'POST') {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '{}', true) ?? [];
    
    $botId = $payload['botId'] ?? '';
    $clientId = $payload['clientId'] ?? '';
    $userId = $payload['userId'] ?? '';
    $message = $payload['message'] ?? null;
    $pageUrl = $payload['pageUrl'] ?? null;
    $referrer = $payload['referrer'] ?? null;
    
    if ($clientId === '' || $userId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'clientId and userId are required']);
        exit;
    }
    
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    
    // Check if conversation exists
    $stmt = $pdo->prepare('SELECT id, dialog, message_count FROM conversations WHERE client_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$clientId, $userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing conversation
        $dialog = json_decode($existing['dialog'] ?? '[]', true) ?: [];
        
        if ($message !== null) {
            $dialog[] = $message;
            $messageCount = (int)$existing['message_count'] + 1;
            
            $stmt = $pdo->prepare('
                UPDATE conversations 
                SET dialog = ?, message_count = ?, last_message_at = NOW(), page_url = COALESCE(?, page_url)
                WHERE id = ?
            ');
            $stmt->execute([
                json_encode($dialog, JSON_UNESCAPED_UNICODE),
                $messageCount,
                $pageUrl,
                $existing['id'],
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'action' => 'updated',
            'id' => (int)$existing['id'],
            'message_count' => $messageCount ?? (int)$existing['message_count'],
        ]);
    } else {
        // Create new conversation
        $dialog = $message !== null ? [$message] : [];
        $messageCount = $message !== null ? 1 : 0;
        
        $stmt = $pdo->prepare('
            INSERT INTO conversations 
            (bot_id, client_id, user_id, dialog, user_agent, ip_address, referrer, page_url, message_count, last_message_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $botId,
            $clientId,
            $userId,
            json_encode($dialog, JSON_UNESCAPED_UNICODE),
            $userAgent,
            $ipAddress,
            $referrer,
            $pageUrl,
            $messageCount,
        ]);
        
        $newId = (int)$pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'action' => 'created',
            'id' => $newId,
            'message_count' => $messageCount,
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);




