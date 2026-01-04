<?php
declare(strict_types=1);

/**
 * Dataset API - CRUD operations for user dataset field
 * 
 * GET    /api/dataset.php?client_id=1           - Get full dataset
 * POST   /api/dataset.php                       - Add item(s) to dataset
 * PUT    /api/dataset.php                       - Update item in dataset by index
 * DELETE /api/dataset.php                       - Delete item from dataset by index
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
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

// Get input data
$input = [];
$rawBody = file_get_contents('php://input');
if ($rawBody) {
    $jsonInput = json_decode($rawBody, true);
    if (is_array($jsonInput)) {
        $input = $jsonInput;
    }
}
// Merge with POST/GET
$input = array_merge($_GET, $_POST, $input);

$clientId = $input['client_id'] ?? $input['clientId'] ?? '';

if ($clientId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'client_id is required']);
    exit;
}

// Verify API key (optional security)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $input['api_key'] ?? '';

// Get current user and dataset
$stmt = $pdo->prepare('SELECT id, dataset, api_key FROM users WHERE client_id = ? LIMIT 1');
$stmt->execute([$clientId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Optional: Validate API key if provided
if ($apiKey !== '' && $apiKey !== $user['api_key']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

$dataset = json_decode($user['dataset'] ?? '[]', true);
if (!is_array($dataset)) {
    $dataset = [];
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Return full dataset
        echo json_encode([
            'success' => true,
            'client_id' => $clientId,
            'dataset' => $dataset,
            'count' => count($dataset),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    case 'POST':
        // Add item(s) to dataset
        $items = $input['items'] ?? $input['data'] ?? null;
        $item = $input['item'] ?? null;

        if ($items !== null && is_array($items)) {
            // Add multiple items
            foreach ($items as $newItem) {
                $dataset[] = $newItem;
            }
        } elseif ($item !== null) {
            // Add single item
            $dataset[] = $item;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'item or items array is required']);
            exit;
        }

        $updateStmt = $pdo->prepare('UPDATE users SET dataset = ? WHERE id = ?');
        $updateStmt->execute([json_encode($dataset, JSON_UNESCAPED_UNICODE), $user['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Item(s) added',
            'count' => count($dataset),
        ]);
        break;

    case 'PUT':
        // Update item by index
        $index = $input['index'] ?? null;
        $item = $input['item'] ?? $input['data'] ?? null;

        if ($index === null || !is_numeric($index)) {
            http_response_code(400);
            echo json_encode(['error' => 'index is required']);
            exit;
        }

        $index = (int)$index;

        if ($index < 0 || $index >= count($dataset)) {
            http_response_code(400);
            echo json_encode(['error' => 'index out of range', 'max_index' => count($dataset) - 1]);
            exit;
        }

        if ($item === null) {
            http_response_code(400);
            echo json_encode(['error' => 'item is required']);
            exit;
        }

        $dataset[$index] = $item;

        $updateStmt = $pdo->prepare('UPDATE users SET dataset = ? WHERE id = ?');
        $updateStmt->execute([json_encode($dataset, JSON_UNESCAPED_UNICODE), $user['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Item updated',
            'index' => $index,
        ]);
        break;

    case 'DELETE':
        // Delete item by index
        $index = $input['index'] ?? null;

        if ($index === null || !is_numeric($index)) {
            http_response_code(400);
            echo json_encode(['error' => 'index is required']);
            exit;
        }

        $index = (int)$index;

        if ($index < 0 || $index >= count($dataset)) {
            http_response_code(400);
            echo json_encode(['error' => 'index out of range', 'max_index' => count($dataset) - 1]);
            exit;
        }

        array_splice($dataset, $index, 1);

        $updateStmt = $pdo->prepare('UPDATE users SET dataset = ? WHERE id = ?');
        $updateStmt->execute([json_encode($dataset, JSON_UNESCAPED_UNICODE), $user['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Item deleted',
            'count' => count($dataset),
        ]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

