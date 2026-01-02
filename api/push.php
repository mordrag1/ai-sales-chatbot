<?php
declare(strict_types=1);

/**
 * Push endpoint for n8n to send messages to specific users.
 * 
 * Accepts both JSON body and form POST data:
 * 
 * POST /api/push.php
 * Form data or JSON:
 *   clientId (or client_id): "1"
 *   userId (or user_id): "v-abc123"
 *   text: "Hello!"
 *   role: "assistant" (optional, defaults to "assistant")
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Accept both JSON body and form POST data
$payload = [];

// First try to get from $_POST (form data)
if (!empty($_POST)) {
    $payload = $_POST;
}

// Also try JSON body
$rawBody = file_get_contents('php://input');
if ($rawBody) {
    $jsonPayload = json_decode($rawBody, true);
    if (is_array($jsonPayload)) {
        // Merge with POST, JSON takes precedence
        $payload = array_merge($payload, $jsonPayload);
    }
}

// Also check $_GET for query parameters
if (!empty($_GET)) {
    foreach ($_GET as $key => $value) {
        if (!isset($payload[$key])) {
            $payload[$key] = $value;
        }
    }
}

$clientId = $payload['clientId'] ?? $payload['client_id'] ?? '';
$userId = $payload['userId'] ?? $payload['user_id'] ?? '';

if ($clientId === '' || $userId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'clientId and userId are required']);
    exit;
}

// Support both formats: array of messages or single message
$messages = [];
if (isset($payload['messages']) && is_array($payload['messages'])) {
    $messages = $payload['messages'];
} elseif (isset($payload['text'])) {
    $messages[] = [
        'role' => $payload['role'] ?? 'assistant',
        'text' => $payload['text'],
    ];
}

if (empty($messages)) {
    http_response_code(400);
    echo json_encode(['error' => 'No messages provided']);
    exit;
}

$insertedIds = [];
$stmt = $pdo->prepare('
    INSERT INTO pending_messages (client_id, user_id, message_role, message_text, metadata)
    VALUES (?, ?, ?, ?, ?)
');

foreach ($messages as $msg) {
    $role = $msg['role'] ?? 'assistant';
    $text = $msg['text'] ?? '';
    $metadata = isset($msg['metadata']) ? json_encode($msg['metadata']) : null;
    
    if ($text === '') {
        continue;
    }
    
    $stmt->execute([$clientId, $userId, $role, $text, $metadata]);
    $insertedIds[] = (int)$pdo->lastInsertId();
}

// Also save to conversation history
$convStmt = $pdo->prepare('SELECT id, dialog, message_count FROM conversations WHERE client_id = ? AND user_id = ? LIMIT 1');
$convStmt->execute([$clientId, $userId]);
$conversation = $convStmt->fetch(PDO::FETCH_ASSOC);

if ($conversation) {
    $dialog = json_decode($conversation['dialog'] ?? '[]', true) ?: [];
    foreach ($messages as $msg) {
        if (($msg['text'] ?? '') !== '') {
            $dialog[] = [
                'role' => $msg['role'] ?? 'assistant',
                'text' => $msg['text'],
                'id' => time() . '-' . substr(md5(random_bytes(8)), 0, 6),
                'timestamp' => time() * 1000,
            ];
        }
    }
    $messageCount = (int)$conversation['message_count'] + count($messages);
    
    $updateStmt = $pdo->prepare('
        UPDATE conversations SET dialog = ?, message_count = ?, last_message_at = NOW() WHERE id = ?
    ');
    $updateStmt->execute([json_encode($dialog, JSON_UNESCAPED_UNICODE), $messageCount, $conversation['id']]);
}

echo json_encode([
    'success' => true,
    'inserted' => count($insertedIds),
    'ids' => $insertedIds,
]);

