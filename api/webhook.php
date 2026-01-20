<?php
declare(strict_types=1);

/**
 * Polar.sh Webhook Handler
 * Processes payment and subscription events to update user plans.
 */

header('Content-Type: application/json; charset=utf-8');

// Webhook secret from Polar.sh (set in environment or config)
$webhookSecret = getenv('POLAR_WEBHOOK_SECRET') ?: '';

// Get request data
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_WEBHOOK_TIMESTAMP'] ?? '';
$webhookId = $_SERVER['HTTP_WEBHOOK_ID'] ?? '';

/**
 * Verify webhook signature
 */
function verifySignature(string $payload, string $signature, string $timestamp, string $webhookId, string $secret): bool
{
    if (empty($secret)) {
        // Skip verification if secret not configured (development mode)
        return true;
    }
    
    if (empty($signature) || empty($timestamp) || empty($webhookId)) {
        return false;
    }
    
    $signedContent = "{$webhookId}.{$timestamp}.{$payload}";
    $expectedSignature = base64_encode(hash_hmac('sha256', $signedContent, $secret, true));
    
    // Polar sends multiple signatures separated by space, check each
    $signatures = explode(' ', $signature);
    foreach ($signatures as $sig) {
        // Remove version prefix if present (v1,xxx)
        $parts = explode(',', $sig);
        $sigValue = end($parts);
        if (hash_equals($expectedSignature, $sigValue)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Send JSON response
 */
function respond(bool $success, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log webhook event (optional - implement if needed)
 */
function logWebhook(string $eventType, string $eventId, array $data, string $status, ?string $error = null): void
{
    // TODO: Implement database logging if needed
    error_log(sprintf(
        '[Webhook] %s | %s | %s | %s',
        $eventType,
        $eventId,
        $status,
        $error ?? 'OK'
    ));
}

/**
 * Get PDO connection
 */
function getDb(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=aicdn;charset=utf8mb4',
            'aicdn',
            'xM0iF4mW5l',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    
    return $pdo;
}

/**
 * Find user by external_id (client_id) or email
 */
function findUser(array $customer): ?array
{
    $db = getDb();
    
    // Try external_id first (mapped to client_id)
    $externalId = $customer['external_id'] ?? null;
    if ($externalId) {
        $stmt = $db->prepare('SELECT * FROM users WHERE client_id = ? LIMIT 1');
        $stmt->execute([$externalId]);
        $user = $stmt->fetch();
        if ($user) {
            return $user;
        }
    }
    
    // Fallback to email
    $email = $customer['email'] ?? null;
    if ($email) {
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            return $user;
        }
    }
    
    return null;
}

/**
 * Map Polar product to plan_id
 */
function mapProductToPlan(array $product): ?string
{
    $plans = require __DIR__ . '/../data/plans.php';
    
    $productId = $product['id'] ?? '';
    $productName = $product['name'] ?? '';
    
    // Check by product ID
    if (isset($plans['products'][$productId])) {
        return $plans['products'][$productId];
    }
    
    // Check by product name
    if (isset($plans['names'][$productName])) {
        return $plans['names'][$productName];
    }
    
    // Try partial name match
    $productNameLower = strtolower($productName);
    foreach (['start', 'pro', 'max'] as $plan) {
        if (str_contains($productNameLower, $plan)) {
            return $plan;
        }
    }
    
    return null;
}

/**
 * Update user plan
 */
function updateUserPlan(int $userId, string $planId): bool
{
    $db = getDb();
    $stmt = $db->prepare('UPDATE users SET plan_id = ? WHERE id = ?');
    return $stmt->execute([$planId, $userId]);
}

// ============================================================================
// Main Webhook Processing
// ============================================================================

// Verify signature
if (!verifySignature($payload, $signature, $timestamp, $webhookId, $webhookSecret)) {
    logWebhook('unknown', '', [], 'error', 'Invalid signature');
    respond(false, 'Invalid signature', 401);
}

// Parse payload
$data = json_decode($payload, true);
if (!$data) {
    logWebhook('unknown', '', [], 'error', 'Invalid JSON');
    respond(false, 'Invalid JSON payload', 400);
}

$eventType = $data['type'] ?? '';
$eventId = $data['data']['id'] ?? '';
$eventData = $data['data'] ?? [];

// Log incoming webhook
error_log("[Webhook] Received: {$eventType}");

try {
    switch ($eventType) {
        // ----------------------------------------------------------------
        // Order paid - one-time payment or subscription first payment
        // ----------------------------------------------------------------
        case 'order.paid':
            $customer = $eventData['customer'] ?? [];
            $product = $eventData['product'] ?? [];
            
            $user = findUser($customer);
            if (!$user) {
                logWebhook($eventType, $eventId, $eventData, 'error', 'User not found');
                respond(false, 'User not found', 404);
            }
            
            $planId = mapProductToPlan($product);
            if (!$planId) {
                logWebhook($eventType, $eventId, $eventData, 'error', 'Unknown product');
                respond(false, 'Unknown product', 400);
            }
            
            updateUserPlan($user['id'], $planId);
            logWebhook($eventType, $eventId, $eventData, 'success');
            respond(true, "Plan updated to {$planId}");
            break;

        // ----------------------------------------------------------------
        // Subscription created
        // ----------------------------------------------------------------
        case 'subscription.created':
            $customer = $eventData['customer'] ?? [];
            $product = $eventData['product'] ?? [];
            $status = $eventData['status'] ?? '';
            
            $user = findUser($customer);
            if (!$user) {
                logWebhook($eventType, $eventId, $eventData, 'error', 'User not found');
                respond(false, 'User not found', 404);
            }
            
            // Only activate if subscription is active
            if ($status === 'active') {
                $planId = mapProductToPlan($product);
                if ($planId) {
                    updateUserPlan($user['id'], $planId);
                }
            }
            
            logWebhook($eventType, $eventId, $eventData, 'success');
            respond(true, 'Subscription processed');
            break;

        // ----------------------------------------------------------------
        // Subscription updated (plan change, status change)
        // ----------------------------------------------------------------
        case 'subscription.updated':
            $customer = $eventData['customer'] ?? [];
            $product = $eventData['product'] ?? [];
            $status = $eventData['status'] ?? '';
            $cancelAtPeriodEnd = $eventData['cancel_at_period_end'] ?? false;
            
            $user = findUser($customer);
            if (!$user) {
                logWebhook($eventType, $eventId, $eventData, 'error', 'User not found');
                respond(false, 'User not found', 404);
            }
            
            if ($status === 'active' && !$cancelAtPeriodEnd) {
                // Active subscription - update plan
                $planId = mapProductToPlan($product);
                if ($planId) {
                    updateUserPlan($user['id'], $planId);
                }
            } elseif ($status === 'canceled' || $status === 'expired') {
                // Subscription ended - revert to demo
                updateUserPlan($user['id'], 'demo');
            }
            // If cancel_at_period_end=true, keep current plan until period ends
            
            logWebhook($eventType, $eventId, $eventData, 'success');
            respond(true, 'Subscription updated');
            break;

        // ----------------------------------------------------------------
        // Subscription canceled
        // ----------------------------------------------------------------
        case 'subscription.canceled':
            $customer = $eventData['customer'] ?? [];
            
            $user = findUser($customer);
            if (!$user) {
                logWebhook($eventType, $eventId, $eventData, 'error', 'User not found');
                respond(false, 'User not found', 404);
            }
            
            // Revert to demo plan
            updateUserPlan($user['id'], 'demo');
            
            logWebhook($eventType, $eventId, $eventData, 'success');
            respond(true, 'Subscription canceled, plan reverted to demo');
            break;

        // ----------------------------------------------------------------
        // Subscription revoked (immediate cancellation)
        // ----------------------------------------------------------------
        case 'subscription.revoked':
            $customer = $eventData['customer'] ?? [];
            
            $user = findUser($customer);
            if ($user) {
                updateUserPlan($user['id'], 'demo');
            }
            
            logWebhook($eventType, $eventId, $eventData, 'success');
            respond(true, 'Subscription revoked');
            break;

        // ----------------------------------------------------------------
        // Unknown event - acknowledge but don't process
        // ----------------------------------------------------------------
        default:
            logWebhook($eventType, $eventId, $eventData, 'skipped');
            respond(true, "Event {$eventType} acknowledged");
    }
} catch (PDOException $e) {
    error_log("[Webhook] Database error: " . $e->getMessage());
    logWebhook($eventType, $eventId, $eventData, 'error', 'Database error');
    respond(false, 'Database error', 500);
} catch (Throwable $e) {
    error_log("[Webhook] Error: " . $e->getMessage());
    logWebhook($eventType, $eventId, $eventData, 'error', $e->getMessage());
    respond(false, 'Internal error', 500);
}


