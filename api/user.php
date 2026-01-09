<?php
declare(strict_types=1);

/**
 * User Profile API
 * 
 * GET  /api/user.php              - Get current user profile
 * PUT  /api/user.php              - Update user profile
 * GET  /api/user.php?action=widget - Get widget settings
 * PUT  /api/user.php?action=widget - Update widget settings
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');

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

// Get auth token
$token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
if ($token === '') {
    // Try to get from input
    $input = [];
    $rawBody = file_get_contents('php://input');
    if ($rawBody) {
        $jsonInput = json_decode($rawBody, true);
        if (is_array($jsonInput)) {
            $input = $jsonInput;
        }
    }
    $input = array_merge($_GET, $_POST, $input);
    $token = $input['auth_token'] ?? $input['token'] ?? '';
}

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
$input = array_merge($_GET, $_POST, $input);

$action = $input['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'widget') {
    // Widget settings
    if ($method === 'GET') {
        echo json_encode([
            'success' => true,
            'widget' => [
                'hash' => $user['widget_hash'],
                'title' => $user['widget_title'],
                'operator_label' => $user['widget_operator_label'],
                'welcome' => $user['widget_welcome'],
                'placeholder' => $user['widget_placeholder'],
                'typing_label' => $user['widget_typing_label'],
                'sound_enabled' => (bool)$user['widget_sound_enabled'],
            ],
            'embed_code' => '<script src="https://cdn.weba-ai.com/widget.php?h=' . $user['widget_hash'] . '"></script>',
        ]);
    } elseif ($method === 'PUT') {
        $updates = [];
        $params = [];

        $allowedFields = [
            'title' => 'widget_title',
            'operator_label' => 'widget_operator_label',
            'welcome' => 'widget_welcome',
            'placeholder' => 'widget_placeholder',
            'typing_label' => 'widget_typing_label',
            'sound_enabled' => 'widget_sound_enabled',
        ];

        foreach ($allowedFields as $inputKey => $dbField) {
            if (isset($input[$inputKey])) {
                $value = $input[$inputKey];
                if ($dbField === 'widget_sound_enabled') {
                    $value = $value ? 1 : 0;
                }
                $updates[] = "$dbField = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }

        $params[] = $user['id'];
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Widget settings updated']);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} else {
    // User profile
    if ($method === 'GET') {
        $dataset = json_decode($user['dataset'] ?? '[]', true);
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => (int)$user['id'],
                'client_id' => $user['client_id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'plan_id' => $user['plan_id'],
                'api_key' => $user['api_key'],
                'widget_hash' => $user['widget_hash'],
                'dataset_count' => is_array($dataset) ? count($dataset) : 0,
                'created_at' => $user['created_at'],
            ],
        ]);
    } elseif ($method === 'PUT') {
        $updates = [];
        $params = [];

        if (isset($input['name']) && trim($input['name']) !== '') {
            $updates[] = 'name = ?';
            $params[] = trim($input['name']);
        }

        if (isset($input['password']) && $input['password'] !== '') {
            if (strlen($input['password']) < 6) {
                http_response_code(400);
                echo json_encode(['error' => 'Password must be at least 6 characters']);
                exit;
            }
            $updates[] = 'password_hash = ?';
            $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }

        $params[] = $user['id'];
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Profile updated']);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}


