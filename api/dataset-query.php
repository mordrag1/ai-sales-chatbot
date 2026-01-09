<?php
declare(strict_types=1);

/**
 * Dataset Query API - Returns dataset + question in text format
 * 
 * POST /api/dataset-query.php
 * Parameters:
 *   bot_hash (required): Bot's unique hash
 *   question (required): Question text to append
 * 
 * Response: Plain text format
 * 
 * dataset:
 * 
 * *** [dataset content]
 * 
 * question: *** [question from request]
 */

error_reporting(0);
ini_set('display_errors', '0');

// Get input data first to check format parameter
$input = [];
$rawBody = file_get_contents('php://input');
if ($rawBody) {
    $jsonInput = json_decode($rawBody, true);
    if (is_array($jsonInput)) {
        $input = $jsonInput;
    }
}
$input = array_merge($_GET, $_POST, $input);

// Determine output format
$format = $input['format'] ?? 'text';

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
} else {
    header('Content-Type: text/plain; charset=utf-8');
}

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
    if ($format === 'json') {
        echo json_encode(['error' => 'Database connection failed']);
    } else {
        echo "Error: Database connection failed";
    }
    exit;
}

// Accept bot_hash (new) or client_id (legacy)
$botHash = $input['bot_hash'] ?? $input['botHash'] ?? '';
$clientId = $input['client_id'] ?? $input['clientId'] ?? '';
$question = $input['question'] ?? $input['q'] ?? '';

if ($botHash === '' && $clientId === '') {
    http_response_code(400);
    if ($format === 'json') {
        echo json_encode(['error' => 'bot_hash is required']);
    } else {
        echo "Error: bot_hash is required";
    }
    exit;
}

if ($question === '') {
    http_response_code(400);
    if ($format === 'json') {
        echo json_encode(['error' => 'question is required']);
    } else {
        echo "Error: question is required";
    }
    exit;
}

$dataset = null;
$identifier = '';

// Try to get from bots table first (new system)
if ($botHash !== '') {
    try {
        $stmt = $pdo->prepare('SELECT id, bot_hash, dataset FROM bots WHERE bot_hash = ? LIMIT 1');
        $stmt->execute([$botHash]);
        $bot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bot) {
            $dataset = $bot['dataset'];
            $identifier = $bot['bot_hash'];
        }
    } catch (PDOException $e) {
        // Bots table might not exist
    }
}

// Fallback to users table (legacy)
if ($dataset === null && $clientId !== '') {
    $stmt = $pdo->prepare('SELECT id, client_id, dataset FROM users WHERE client_id = ? LIMIT 1');
    $stmt->execute([$clientId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $dataset = $user['dataset'];
        $identifier = $user['client_id'];
    }
}

if ($dataset === null) {
    http_response_code(404);
    if ($format === 'json') {
        echo json_encode(['error' => 'Bot not found']);
    } else {
        echo "Error: Bot not found";
    }
    exit;
}

// Decode and re-encode for pretty formatting
$datasetArray = json_decode($dataset ?? '[]', true);
if (!is_array($datasetArray)) {
    $datasetArray = [];
}

// Format output
if ($format === 'json') {
    echo json_encode([
        'success' => true,
        'bot_hash' => $botHash ?: null,
        'client_id' => $clientId ?: null,
        'dataset' => $datasetArray,
        'question' => $question,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    // Plain text format - convert dataset array to readable text
    $datasetText = '';
    
    if (is_array($datasetArray)) {
        foreach ($datasetArray as $item) {
            if (is_string($item)) {
                // If item is a string, add it directly
                $datasetText .= $item . "\n\n";
            } elseif (is_array($item)) {
                // If item is an object/array, format key-value pairs
                foreach ($item as $key => $value) {
                    if (is_string($value)) {
                        $datasetText .= $key . ": " . $value . "\n";
                    } elseif (is_array($value)) {
                        $datasetText .= $key . ": " . implode(", ", $value) . "\n";
                    } else {
                        $datasetText .= $key . ": " . strval($value) . "\n";
                    }
                }
                $datasetText .= "\n";
            }
        }
    }
    
    // If dataset is empty or couldn't be parsed, show raw
    if (trim($datasetText) === '') {
        $datasetText = $dataset ?? '';
    }
    
    echo "dataset:\n\n";
    echo "*** " . trim($datasetText) . "\n\n";
    echo "question: *** " . $question;
}
