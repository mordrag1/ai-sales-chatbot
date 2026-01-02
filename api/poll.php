<?php
declare(strict_types=1);

/**
 * Poll endpoint for widget to fetch pending messages.
 * 
 * GET /api/poll.php?client_id=X&user_id=Y
 * 
 * Returns pending messages and marks them as delivered.
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
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

$clientId = $_GET['client_id'] ?? '';
$userId = $_GET['user_id'] ?? '';

if ($clientId === '' || $userId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'client_id and user_id are required']);
    exit;
}

// Fetch pending messages
$stmt = $pdo->prepare('
    SELECT id, message_role, message_text, created_at 
    FROM pending_messages 
    WHERE client_id = ? AND user_id = ? AND delivered = FALSE 
    ORDER BY id ASC
    LIMIT 50
');
$stmt->execute([$clientId, $userId]);
$pendingMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$messages = [];
$ids = [];

foreach ($pendingMessages as $row) {
    $messages[] = [
        'id' => (int)$row['id'],
        'role' => $row['message_role'],
        'text' => $row['message_text'],
        'timestamp' => strtotime($row['created_at']) * 1000,
    ];
    $ids[] = (int)$row['id'];
}

// Mark as delivered
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $updateStmt = $pdo->prepare("UPDATE pending_messages SET delivered = TRUE WHERE id IN ($placeholders)");
    $updateStmt->execute($ids);
}

echo json_encode([
    'messages' => $messages,
    'count' => count($messages),
]);

