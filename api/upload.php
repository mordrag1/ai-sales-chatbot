<?php
declare(strict_types=1);

/**
 * File Upload API
 * 
 * POST /api/upload.php - Upload text file and add content to dataset
 * 
 * Accepts:
 * - file: TXT file upload (multipart/form-data)
 * - text: Plain text content (JSON or form data)
 * 
 * Both methods add the content to user's dataset
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
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

// Get auth token
$token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? $_POST['auth_token'] ?? $_GET['auth_token'] ?? '';

if ($token === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Verify token
$stmt = $pdo->prepare('SELECT * FROM users WHERE auth_token = ? AND token_expires_at > NOW() AND status = "active"');
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

// Get input
$input = [];
$rawBody = file_get_contents('php://input');
if ($rawBody) {
    $jsonInput = json_decode($rawBody, true);
    if (is_array($jsonInput)) {
        $input = $jsonInput;
    }
}
$input = array_merge($_POST, $input);

$content = '';
$title = $input['title'] ?? '';
$type = 'text';

// Check for file upload
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    
    // Validate file type
    $allowedTypes = ['text/plain', 'text/csv', 'text/markdown', 'application/json'];
    $allowedExtensions = ['txt', 'csv', 'md', 'json'];
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mimeType = mime_content_type($file['tmp_name']) ?: 'text/plain';
    
    if (!in_array($extension, $allowedExtensions) && !in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type. Allowed: txt, csv, md, json']);
        exit;
    }
    
    // Check file size (max 5MB)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => 'File too large. Maximum size: 5MB']);
        exit;
    }
    
    // Read file content
    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read file']);
        exit;
    }
    
    // Ensure UTF-8 encoding
    $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    
    if ($title === '') {
        $title = pathinfo($file['name'], PATHINFO_FILENAME);
    }
    $type = 'file';
    
} elseif (isset($input['text']) && trim($input['text']) !== '') {
    // Plain text upload
    $content = trim($input['text']);
    $type = 'text';
    
} elseif (isset($input['content']) && trim($input['content']) !== '') {
    // Alternative content field
    $content = trim($input['content']);
    $type = 'text';
    
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No file or text content provided']);
    exit;
}

// Get current dataset
$dataset = json_decode($user['dataset'] ?? '[]', true);
if (!is_array($dataset)) {
    $dataset = [];
}

// Create new item
$newItem = [
    'id' => uniqid('item_'),
    'type' => $type,
    'title' => $title ?: 'Untitled',
    'content' => $content,
    'char_count' => mb_strlen($content),
    'created_at' => date('Y-m-d H:i:s'),
];

// Add to dataset
$dataset[] = $newItem;

// Save to database
$stmt = $pdo->prepare('UPDATE users SET dataset = ? WHERE id = ?');
$stmt->execute([json_encode($dataset, JSON_UNESCAPED_UNICODE), $user['id']]);

echo json_encode([
    'success' => true,
    'message' => 'Content added to dataset',
    'item' => $newItem,
    'dataset_count' => count($dataset),
]);


