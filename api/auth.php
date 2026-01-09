<?php
declare(strict_types=1);

/**
 * Authentication API
 * 
 * POST /api/auth.php?action=register   - Register new user
 * POST /api/auth.php?action=login      - Login user
 * POST /api/auth.php?action=verify     - Verify auth token
 * POST /api/auth.php?action=logout     - Logout (invalidate token)
 * POST /api/auth.php?action=refresh    - Refresh auth token
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

/**
 * Generate secure random token
 */
function generateToken(int $length = 64): string {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate unique client_id
 */
function generateClientId(PDO $pdo): string {
    do {
        $clientId = (string)random_int(100000, 999999);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE client_id = ?');
        $stmt->execute([$clientId]);
    } while ($stmt->fetch());
    return $clientId;
}

/**
 * Generate widget hash
 */
function generateWidgetHash(): string {
    return bin2hex(random_bytes(16));
}

/**
 * Generate API key
 */
function generateApiKey(): string {
    return bin2hex(random_bytes(16));
}

switch ($action) {
    case 'register':
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $name = trim($input['name'] ?? '');

        if ($email === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password are required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            exit;
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 6 characters']);
            exit;
        }

        // Check if email already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            exit;
        }

        // Create user
        $clientId = generateClientId($pdo);
        $widgetHash = generateWidgetHash();
        $apiKey = generateApiKey();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $authToken = generateToken();
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $pdo->prepare('
            INSERT INTO users (client_id, widget_hash, name, email, password_hash, api_key, auth_token, token_expires_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, "active")
        ');
        $stmt->execute([$clientId, $widgetHash, $name ?: 'User', $email, $passwordHash, $apiKey, $authToken, $tokenExpiry]);

        $userId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => (int)$userId,
                'client_id' => $clientId,
                'email' => $email,
                'name' => $name ?: 'User',
                'widget_hash' => $widgetHash,
                'api_key' => $apiKey,
            ],
            'auth_token' => $authToken,
            'expires_at' => $tokenExpiry,
        ]);
        break;

    case 'login':
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password are required']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND status = "active"');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'] ?? '')) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
            exit;
        }

        // Generate new token
        $authToken = generateToken();
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $pdo->prepare('UPDATE users SET auth_token = ?, token_expires_at = ? WHERE id = ?');
        $stmt->execute([$authToken, $tokenExpiry, $user['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => (int)$user['id'],
                'client_id' => $user['client_id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'widget_hash' => $user['widget_hash'],
                'api_key' => $user['api_key'],
                'plan_id' => $user['plan_id'],
            ],
            'auth_token' => $authToken,
            'expires_at' => $tokenExpiry,
        ]);
        break;

    case 'verify':
        $token = $input['token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';

        if ($token === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Token is required']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE auth_token = ? AND token_expires_at > NOW() AND status = "active"');
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token', 'valid' => false]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'valid' => true,
            'user' => [
                'id' => (int)$user['id'],
                'client_id' => $user['client_id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'widget_hash' => $user['widget_hash'],
                'api_key' => $user['api_key'],
                'plan_id' => $user['plan_id'],
            ],
        ]);
        break;

    case 'refresh':
        $token = $input['token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';

        if ($token === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Token is required']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE auth_token = ? AND status = "active"');
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }

        // Generate new token
        $newToken = generateToken();
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $pdo->prepare('UPDATE users SET auth_token = ?, token_expires_at = ? WHERE id = ?');
        $stmt->execute([$newToken, $tokenExpiry, $user['id']]);

        echo json_encode([
            'success' => true,
            'auth_token' => $newToken,
            'expires_at' => $tokenExpiry,
        ]);
        break;

    case 'logout':
        $token = $input['token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';

        if ($token !== '') {
            $stmt = $pdo->prepare('UPDATE users SET auth_token = NULL, token_expires_at = NULL WHERE auth_token = ?');
            $stmt->execute([$token]);
        }

        echo json_encode(['success' => true, 'message' => 'Logged out']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Use: register, login, verify, refresh, logout']);
}


