<?php
declare(strict_types=1);

/**
 * API для смены тарифа пользователя
 * 
 * POST /api/plan.php
 * {
 *   "email": "user@example.com",
 *   "plan_id": "pro"
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Допустимые планы
$validPlans = ['demo', 'start', 'pro', 'max'];

// API ключ для авторизации (установить в переменной окружения)
$apiSecret = getenv('PLAN_API_SECRET') ?: '';

// Проверка авторизации
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($apiSecret !== '' && $authHeader !== "Bearer {$apiSecret}") {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Получение данных
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');
$planId = trim($input['plan_id'] ?? '');

// Валидация
if ($email === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

if (!in_array($planId, $validPlans, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid plan_id. Valid: ' . implode(', ', $validPlans)
    ]);
    exit;
}

// Подключение к БД
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_NAME') ?: 'salesbot'
        ),
        getenv('DB_USER') ?: 'root',
        getenv('DB_PASS') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Обновление плана
    $stmt = $pdo->prepare('UPDATE users SET plan_id = ? WHERE email = ?');
    $stmt->execute([$planId, $email]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'email' => $email,
        'plan_id' => $planId
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

