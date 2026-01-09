<?php
declare(strict_types=1);

// Prevent PHP errors from breaking JS
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    // Get hash from URL: widget.php?h=abc123 or widget/abc123
    $hash = $_GET['h'] ?? '';
    if ($hash === '' && preg_match('#/widget/([a-zA-Z0-9]{32})#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
        $hash = $m[1];
    }

    if ($hash === '' || strlen($hash) !== 32) {
        echo 'console.error("Salesbot: invalid widget hash");';
        exit;
    }

    // Load environment
    $envFile = __DIR__ . '/.env';
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

    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // First try to find bot by hash (new multi-bot system)
    $stmt = $pdo->prepare('SELECT b.*, u.id as owner_id, u.client_id, u.plan_id, u.plan_expires_at 
                           FROM bots b 
                           JOIN users u ON b.user_id = u.id 
                           WHERE b.bot_hash = ? AND b.is_active = 1 
                           LIMIT 1');
    $stmt->execute([$hash]);
    $bot = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback: try old widget_hash in users table (backwards compatibility)
    if (!$bot) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE widget_hash = ? LIMIT 1');
        $stmt->execute([$hash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Convert to bot format for backwards compatibility
            $bot = [
                'id' => null, // No bot ID for legacy
                'user_id' => $user['id'],
                'owner_id' => $user['id'],
                'bot_hash' => $hash,
                'client_id' => $user['client_id'],
                'name' => $user['name'] ?? 'Bot',
                'widget_title' => $user['widget_title'] ?? 'Support',
                'widget_operator_label' => $user['widget_operator_label'] ?? 'Operator Online',
                'widget_welcome' => $user['widget_welcome'],
                'widget_placeholder' => $user['widget_placeholder'] ?? 'Type your message...',
                'widget_typing_label' => $user['widget_typing_label'] ?? 'Operator typing...',
                'widget_sound_enabled' => $user['widget_sound_enabled'] ?? 1,
                'allowed_domains' => null,
                'dataset' => $user['dataset'] ?? null,
                'n8n_webhook_url' => null,
                'plan_id' => $user['plan_id'] ?? 'demo',
                'plan_expires_at' => $user['plan_expires_at'] ?? null,
                'is_active' => 1,
            ];
        }
    }

    if (!$bot) {
        echo 'console.error("Salesbot: widget not found");';
        exit;
    }

    // Get plan info
    $stmt = $pdo->prepare('SELECT * FROM plans WHERE id = ? LIMIT 1');
    $stmt->execute([$bot['plan_id']]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        // Default to demo plan
        $plan = [
            'id' => 'demo',
            'name' => 'Demo',
            'max_bots' => 1,
            'max_messages_per_month' => 500,
            'allowed_domains' => '["https://weba-ai.com"]',
        ];
    }

    // Check limits
    $errorCode = null;
    $errorMessage = null;
    $upgradeUrl = 'https://weba-ai.com/dashboard';

    // 1. Check domain restrictions
    $planDomains = $plan['allowed_domains'] ? json_decode($plan['allowed_domains'], true) : null;
    $botDomains = $bot['allowed_domains'] ? json_decode($bot['allowed_domains'], true) : null;
    $allowedDomains = $botDomains ?? $planDomains; // Bot domains override plan domains

    // 2. Check message limit for this month
    $yearMonth = date('Y-m');
    $maxMessages = $plan['max_messages_per_month'];

    if ($maxMessages !== null) {
        // Get usage for this user (all bots)
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(message_count), 0) as total FROM message_usage WHERE user_id = ? AND year_month = ?');
        $stmt->execute([$bot['owner_id'], $yearMonth]);
        $usage = $stmt->fetch(PDO::FETCH_ASSOC);
        $messagesUsed = (int)$usage['total'];

        if ($messagesUsed >= (int)$maxMessages) {
            $errorCode = 'MESSAGE_LIMIT_REACHED';
            $errorMessage = "Message limit reached ({$messagesUsed}/{$maxMessages} this month). Upgrade your plan to continue.";
        }
    }

    // Build config
    $clientId = $bot['client_id'];
    $botId = $bot['id'] ?? $clientId; // Use bot ID if available, else client_id
    $title = $bot['widget_title'] ?? 'Support';
    $operatorLabel = $bot['widget_operator_label'] ?? 'Operator Online';
    $welcomeMessage = $bot['widget_welcome'] ?? 'Hello! How can I help you today?';
    $placeholder = $bot['widget_placeholder'] ?? 'Type your message...';
    $typingLabel = $bot['widget_typing_label'] ?? 'Operator typing...';
    $soundEnabled = (bool)($bot['widget_sound_enabled'] ?? true);

    // N8N webhook URL - use bot's custom URL or default
    $webhookUrl = $bot['n8n_webhook_url'] ?? 'https://gicujedrotan.beget.app/webhook/a60472fc-b4e1-4e83-92c4-75c648b9dd80';

    // Build API URLs
    $cdnBaseUrl = 'https://cdn.weba-ai.com';
    $conversationApiUrl = $cdnBaseUrl . '/api/conversation.php';
    $pollApiUrl = $cdnBaseUrl . '/api/poll.php';
    $usageApiUrl = $cdnBaseUrl . '/api/usage.php';

    $jsConfig = json_encode([
        'clientId' => $clientId,
        'botId' => $botId,
        'botHash' => $hash,
        'title' => $title,
        'operatorLabel' => $operatorLabel,
        'welcomeMessage' => $welcomeMessage,
        'placeholder' => $placeholder,
        'typingLabel' => $typingLabel,
        'soundEnabled' => $soundEnabled,
        'apiUrl' => $webhookUrl,
        'conversationApiUrl' => $conversationApiUrl,
        'pollApiUrl' => $pollApiUrl,
        'usageApiUrl' => $usageApiUrl,
        'allowedDomains' => $allowedDomains,
        'planId' => $plan['id'],
        'planName' => $plan['name'],
        'error' => $errorCode ? [
            'code' => $errorCode,
            'message' => $errorMessage,
            'upgradeUrl' => $upgradeUrl,
        ] : null,
    ], JSON_UNESCAPED_UNICODE);

echo <<<JS
(function () {
    const config = $jsConfig;
    config.sendLabel = 'Send';
    config.sendingLabel = 'Sending...';
    config.typingDelay = 900;
    config.soundSrc = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAIlYAAESsAAACABAAZGF0YQAAAAA=';
    config.errorMessage = 'Something went wrong. Please try again soon.';
    config.developerUrl = 'https://weba-ai.com/';
    config.upgradeUrl = 'https://weba-ai.com/dashboard';

    // Check domain restrictions
    const checkDomain = () => {
        if (!config.allowedDomains || config.allowedDomains.length === 0) {
            return { allowed: true };
        }
        const currentOrigin = window.location.origin;
        const currentUrl = window.location.href;
        
        for (const domain of config.allowedDomains) {
            // Check if domain matches origin or URL starts with domain
            if (currentOrigin === domain || 
                currentUrl.startsWith(domain) || 
                currentOrigin.replace(/^https?:\\/\\//, '') === domain.replace(/^https?:\\/\\//, '')) {
                return { allowed: true };
            }
        }
        return { 
            allowed: false, 
            message: 'This widget is not authorized for domain: ' + currentOrigin + '. Allowed: ' + config.allowedDomains.join(', '),
            code: 'DOMAIN_NOT_ALLOWED'
        };
    };

    // Cookie helpers
    const cookies = {
        set: (name, value, days = 365) => {
            try {
                const expires = new Date(Date.now() + days * 864e5).toUTCString();
                document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + expires + ';path=/;SameSite=Lax';
            } catch {}
        },
        get: (name) => {
            try {
                const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                return match ? decodeURIComponent(match[2]) : null;
            } catch {
                return null;
            }
        }
    };

    // Storage helpers (localStorage + cookies fallback)
    const storage = (() => {
        try {
            return window.localStorage;
        } catch {
            return null;
        }
    })();

    const USER_ID_KEY = 'salesbot-user-' + config.clientId;
    const COOKIE_USER_ID_KEY = 'sb_uid_' + config.clientId;

    // Get or create userId from localStorage or cookies
    const getUserId = () => {
        let userId = null;
        // Try localStorage first
        if (storage) {
            try {
                userId = storage.getItem(USER_ID_KEY);
            } catch {}
        }
        // Try cookies if not in localStorage
        if (!userId) {
            userId = cookies.get(COOKIE_USER_ID_KEY);
        }
        // Generate new if not found
        if (!userId) {
            userId = 'v-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
        }
        // Save to both storages
        saveUserId(userId);
        return userId;
    };

    const saveUserId = (userId) => {
        if (storage) {
            try {
                storage.setItem(USER_ID_KEY, userId);
            } catch {}
        }
        cookies.set(COOKIE_USER_ID_KEY, userId, 365);
    };

    config.userId = getUserId();

    function initWidget() {
    const MOBILE_BREAKPOINT = 768;
    let hasOpened = false;
    const STORAGE_KEY = 'salesbot-chat-' + config.clientId;
    const HISTORY_LIMIT = 80;
    let historyState = {
        messages: [],
        hasUnread: false,
    };
    let hasUnread = false;
    let conversationLoaded = false;
    let widgetBlocked = false;
    let blockReason = null;
    
    const dingAudio = config.soundEnabled ? new Audio(config.soundSrc) : null;
    if (dingAudio) {
        dingAudio.preload = 'auto';
    }

    // Check for errors (domain, limits)
    const domainCheck = checkDomain();
    if (!domainCheck.allowed) {
        widgetBlocked = true;
        blockReason = {
            code: domainCheck.code,
            message: domainCheck.message,
            upgradeUrl: config.upgradeUrl
        };
    } else if (config.error) {
        widgetBlocked = true;
        blockReason = config.error;
    }

    const style = document.createElement('style');
    style.textContent = \`
        .salesbot-widget {
            position: fixed;
            right: 24px;
            bottom: 120px;
            z-index: 2147483647;
            width: min(360px, 90vw);
            max-width: 420px;
            min-width: 260px;
            min-height: 320px;
            max-height: calc(80vh - 60px);
            background: #0f172a;
            color: #fff;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.45);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            font-family: "Inter", system-ui, sans-serif;
            transition: transform 0.3s ease, opacity 0.3s ease, box-shadow 0.3s ease;
            transform: translateY(25px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            resize: both;
            will-change: transform, opacity;
        }
        .salesbot-widget.visible {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            animation: salesbot-appear 0.35s ease;
        }
        .salesbot-widget *,
        .salesbot-widget *::before,
        .salesbot-widget *::after {
            box-sizing: border-box;
        }
        .salesbot-header {
            padding: 16px;
            font-weight: 600;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .salesbot-title-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .salesbot-header-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #34d399;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .salesbot-header-status.visible {
            opacity: 1;
        }
        .salesbot-close {
            background-color: transparent;
            color: #9ca3af;
            border: none;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
        }
        .salesbot-messages {
            flex: 1;
            padding: 16px 16px 8px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.45) rgba(15, 23, 42, 0.8);
        }
        .salesbot-messages::-webkit-scrollbar {
            width: 8px;
        }
        .salesbot-messages::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.8);
        }
        .salesbot-messages::-webkit-scrollbar-thumb {
            border-radius: 99px;
            background: rgba(255, 255, 255, 0.35);
        }
        .salesbot-message {
            padding: 10px 14px;
            border-radius: 12px;
            line-height: 1.4;
            word-break: break-word;
            white-space: pre-wrap;
        }
        .salesbot-message.user {
            background: #f3f4f6;
            color: #111;
            align-self: flex-end;
            max-width: 85%;
        }
        .salesbot-message.assistant {
            background: #1f2937;
            color: #fff;
            align-self: flex-start;
            max-width: 90%;
        }
        .salesbot-form {
            display: flex;
            align-items: center;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: #0f172a;
            padding: 12px 16px;
            gap: 12px;
            flex-shrink: 0;
        }
        .salesbot-input {
            flex: 1;
            padding: 12px 14px;
            border: none;
            outline: none;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 14px;
            border-radius: 10px;
        }
        .salesbot-submit {
            border: none;
            background: #10b981;
            color: #fff;
            padding: 0 18px;
            min-width: 100px;
            height: 44px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 10px;
            transition: background 0.2s ease;
        }
        .salesbot-submit:disabled {
            background: #047857;
            cursor: wait;
        }
        .salesbot-toggle {
            position: fixed;
            right: 24px;
            bottom: 40px;
            z-index: 2147483647;
            padding: 0 22px;
            height: 60px;
            min-width: 180px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(180deg, #22d3ee, #2563eb);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.03em;
            cursor: pointer;
            box-shadow: 0 12px 40px rgba(37, 99, 235, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            animation: salesbot-pulse 3s ease infinite;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .salesbot-toggle.animate-unread {
            animation: salesbot-notify 1.6s ease infinite;
        }
        .salesbot-toggle.hidden {
            opacity: 0;
            pointer-events: none;
            transform: scale(0.95);
        }
        .salesbot-toggle .salesbot-toggle-label {
            display: block;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.08em;
        }
        .salesbot-toggle .salesbot-toggle-status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #34d399;
            box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.4);
            opacity: 1;
        }
        .salesbot-toggle .salesbot-unread-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #facc15;
            box-shadow: 0 0 0 4px rgba(250, 204, 21, 0.5);
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .salesbot-toggle.has-unread .salesbot-unread-dot {
            opacity: 1;
        }
        @keyframes salesbot-pulse {
            0%, 100% {
                transform: translateY(0) scale(1);
                box-shadow: 0 10px 35px rgba(37, 99, 235, 0.35);
            }
            50% {
                transform: translateY(-2px) scale(1.01);
                box-shadow: 0 18px 50px rgba(37, 99, 235, 0.45);
            }
        }
        @keyframes salesbot-notify {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-4px);
            }
        }
        @keyframes salesbot-appear {
            from {
                opacity: 0;
                transform: translateY(32px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .salesbot-typing {
            padding: 10px 14px;
            border-radius: 12px;
            max-width: 70%;
            background: rgba(255, 255, 255, 0.08);
            color: #f3f4f6;
            font-style: italic;
            align-self: flex-start;
        }
        .salesbot-widget.mobile-expanded {
            bottom: 20px;
            right: 8px;
            width: calc(100vw - 32px);
            max-width: calc(100vw - 32px);
            min-height: 60vh;
            max-height: 80vh;
            border-radius: 16px;
            padding-bottom: 8px;
        }
        .salesbot-widget.mobile-expanded .salesbot-messages {
            padding-bottom: 120px;
        }
        .salesbot-widget.mobile-expanded .salesbot-form {
            padding: 12px 16px;
        }
        @media (max-width: 768px) {
            .salesbot-toggle {
                right: 16px;
                bottom: 14px;
                box-shadow: 0 8px 25px rgba(37, 99, 235, 0.45);
            }
        }
        /* Error banner styles */
        .salesbot-error-banner {
            position: fixed;
            right: 24px;
            bottom: 110px;
            z-index: 2147483646;
            background: #dc2626;
            color: #fff;
            padding: 12px 16px;
            border-radius: 12px;
            font-family: "Inter", system-ui, sans-serif;
            font-size: 13px;
            max-width: 320px;
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
            display: none;
        }
        .salesbot-error-banner.visible {
            display: block;
            animation: salesbot-appear 0.3s ease;
        }
        .salesbot-error-banner a {
            color: #fef08a;
            font-weight: 600;
            text-decoration: underline;
        }
        .salesbot-error-banner a:hover {
            color: #fef9c3;
        }
        /* Developer link styles */
        .salesbot-footer {
            padding: 8px 16px;
            text-align: center;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            flex-shrink: 0;
        }
        .salesbot-footer a {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .salesbot-footer a:hover {
            color: rgba(255, 255, 255, 0.8);
        }
        @media (max-width: 768px) {
            .salesbot-error-banner {
                right: 16px;
                bottom: 85px;
                max-width: calc(100vw - 32px);
            }
        }
    \`;
    document.head.appendChild(style);

    const widget = document.createElement('div');
    widget.className = 'salesbot-widget';

    const header = document.createElement('div');
    header.className = 'salesbot-header';
    header.innerHTML = \`
        <div class="salesbot-title-wrap">
            <span class="salesbot-header-status"></span>
            <span class="salesbot-title-text">\${config.title}</span>
        </div>
    \`;
    const headerTitleText = header.querySelector('.salesbot-title-text');
    const headerStatusDot = header.querySelector('.salesbot-header-status');

    const closeButton = document.createElement('button');
    closeButton.className = 'salesbot-close';
    closeButton.textContent = '✕';
    closeButton.addEventListener('click', () => {
        closeWidget();
    });
    header.appendChild(closeButton);

    const setHeaderState = (isOnline) => {
        if (headerTitleText) {
            headerTitleText.textContent = isOnline ? config.operatorLabel : config.title;
        }
        headerStatusDot?.classList.toggle('visible', isOnline);
    };

    const messagesEl = document.createElement('div');
    messagesEl.className = 'salesbot-messages';

    const form = document.createElement('form');
    form.className = 'salesbot-form';

    const input = document.createElement('input');
    input.className = 'salesbot-input';
    input.type = 'text';
    input.placeholder = config.placeholder;
    input.autocomplete = 'off';

    const submit = document.createElement('button');
    submit.className = 'salesbot-submit';
    submit.type = 'submit';
    submit.textContent = config.sendLabel;

    form.appendChild(input);
    form.appendChild(submit);

    // Developer footer
    const footer = document.createElement('div');
    footer.className = 'salesbot-footer';
    footer.innerHTML = 'Powered by <a href="' + config.developerUrl + '" target="_blank" rel="noopener">WebA AI</a>';

    const typingIndicator = document.createElement('div');
    typingIndicator.className = 'salesbot-typing';
    typingIndicator.textContent = config.typingLabel;
    let typingActive = false;

    // Error banner for blocked widget
    const errorBanner = document.createElement('div');
    errorBanner.className = 'salesbot-error-banner';

    if (widgetBlocked && blockReason) {
        errorBanner.innerHTML = blockReason.message + ' <a href="' + blockReason.upgradeUrl + '" target="_blank">Upgrade Plan</a>';
        input.disabled = true;
        submit.disabled = true;
        input.placeholder = 'Chat disabled';
    }

    const saveHistory = (message = null, skipApiSave = false) => {
        saveLocalHistory();
        // Save to API if new message
        if (message && !skipApiSave) {
            saveMessageToApi(message);
        }
    };

    const showTypingIndicator = () => {
        if (typingActive) {
            return;
        }
        typingActive = true;
        messagesEl.appendChild(typingIndicator);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const hideTypingIndicator = () => {
        if (!typingActive) {
            return;
        }
        typingActive = false;
        typingIndicator.remove();
    };

    const setUnread = (value) => {
        hasUnread = value;
        toggle?.classList?.toggle('has-unread', value && !widget.classList.contains('visible'));
        if (value && !widget.classList.contains('visible')) {
            toggle?.classList?.add('animate-unread');
        } else {
            toggle?.classList?.remove('animate-unread');
        }
        saveHistory();
    };

    const appendMessage = (role, text, { persist = false, isHistory = false, playSound = true, skipApiSave = false } = {}) => {
        const bubble = document.createElement('div');
        bubble.className = 'salesbot-message ' + role;
        bubble.textContent = text;
        messagesEl.appendChild(bubble);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        if (persist && !isHistory) {
            const messageObj = {
                role,
                text,
                id: Date.now() + '-' + Math.random().toString(36).slice(2, 6),
                timestamp: Date.now(),
            };
            historyState.messages.push(messageObj);
            saveHistory(messageObj, skipApiSave);
        }

        if (role === 'assistant' && playSound && config.soundEnabled) {
            dingAudio?.play().catch(() => {});
            const shouldMarkUnread = !widget.classList.contains('visible');
            if (shouldMarkUnread) {
                setUnread(true);
            }
        }
    };

    // Save message to API and track usage
    const saveMessageToApi = (message) => {
        if (!config.conversationApiUrl) return;
        try {
            fetch(config.conversationApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    botId: config.botId,
                    botHash: config.botHash,
                    clientId: config.clientId,
                    userId: config.userId,
                    message: message,
                    pageUrl: window.location.href,
                    referrer: document.referrer || null,
                }),
            }).catch(() => {});
        } catch {}
    };

    // Load history from API first, fallback to localStorage
    const loadHistory = async () => {
        // Try loading from API
        if (config.conversationApiUrl) {
            try {
                const url = config.conversationApiUrl + '?client_id=' + encodeURIComponent(config.clientId) + '&user_id=' + encodeURIComponent(config.userId);
                const response = await fetch(url);
                if (response.ok) {
                    const data = await response.json();
                    if (data.exists && data.dialog && data.dialog.length > 0) {
                        conversationLoaded = true;
                        historyState.messages = data.dialog;
                        data.dialog.forEach((entry) => {
                            appendMessage(entry.role, entry.text, { isHistory: true, playSound: false, skipApiSave: true });
                        });
                        // Save to localStorage as backup
                        saveLocalHistory();
                        return;
                    }
                }
            } catch {}
        }
        
        // Fallback to localStorage
        if (!storage) return;
        try {
            const stored = JSON.parse(storage.getItem(STORAGE_KEY) || 'null');
            if (stored?.messages?.length) {
                historyState.messages = stored.messages;
                historyState.hasUnread = !!stored.hasUnread;
                historyState.messages.forEach((entry) => {
                    appendMessage(entry.role, entry.text, { isHistory: true, playSound: false, skipApiSave: true });
                });
                hasUnread = historyState.hasUnread;
            }
        } catch {}
    };

    const saveLocalHistory = () => {
        if (!storage) return;
        const trimmed = historyState.messages.slice(-HISTORY_LIMIT);
        historyState.messages = trimmed;
        historyState.hasUnread = hasUnread;
        try {
            storage.setItem(STORAGE_KEY, JSON.stringify(historyState));
        } catch {}
    };

    widget.appendChild(header);
    widget.appendChild(messagesEl);
    widget.appendChild(form);
    widget.appendChild(footer);

    loadHistory();
    setHeaderState(false);
    document.body.appendChild(widget);
    document.body.appendChild(errorBanner);

    const toggle = document.createElement('button');
    toggle.className = 'salesbot-toggle';
    toggle.innerHTML = '<span class="salesbot-toggle-status" aria-hidden="true"></span><span class="salesbot-toggle-label">Manager Online</span><span class="salesbot-unread-dot" aria-hidden="true"></span>';
    const isMobileViewport = () => window.innerWidth <= MOBILE_BREAKPOINT;

    const applyResponsiveState = () => {
        if (!widget.classList.contains('visible')) {
            widget.classList.remove('mobile-expanded');
            return;
        }
        widget.classList.toggle('mobile-expanded', isMobileViewport());
    };

    const updateToggleState = () => {
        toggle.classList.toggle('hidden', widget.classList.contains('visible'));
        toggle.classList.toggle('has-unread', hasUnread && !widget.classList.contains('visible'));
    };

    const openWidget = () => {
        if (!hasOpened) {
            if (!widgetBlocked) {
                appendMessage('assistant', config.welcomeMessage);
            } else {
                appendMessage('assistant', '⚠️ ' + blockReason.message);
            }
            hasOpened = true;
        }
        widget.classList.add('visible');
        setHeaderState(!widgetBlocked);
        setUnread(false);
        applyResponsiveState();
        updateToggleState();
        
        // Show error banner if blocked
        if (widgetBlocked) {
            errorBanner.classList.add('visible');
        }
        
        if (!widgetBlocked) {
            input.focus();
        }
    };

    const closeWidget = () => {
        widget.classList.remove('visible');
        setHeaderState(false);
        applyResponsiveState();
        updateToggleState();
        errorBanner.classList.remove('visible');
    };

    toggle.addEventListener('click', () => {
        if (widget.classList.contains('visible')) {
            closeWidget();
            return;
        }
        openWidget();
    });
    document.body.appendChild(toggle);

    setUnread(historyState.hasUnread);

    const handleResize = () => {
        applyResponsiveState();
        updateToggleState();
    };
    window.addEventListener('resize', handleResize);
    handleResize();

    const setLoading = (isLoading) => {
        submit.disabled = isLoading || widgetBlocked;
        submit.textContent = isLoading ? config.sendingLabel : config.sendLabel;
    };

    const deliverAssistantPayload = (payloadMessages) => {
        hideTypingIndicator();
        (payloadMessages || []).forEach((message) => {
            appendMessage(message.role || 'assistant', message.text || '', { persist: true });
        });
    };

    const handleFailedResponse = () => {
        hideTypingIndicator();
        appendMessage('assistant', config.errorMessage, { persist: true });
    };

    const sendMessage = (text) => {
        if (!text.trim() || widgetBlocked) {
            return;
        }
        appendMessage('user', text, { persist: true, playSound: false });
        input.value = '';
        input.blur();
        setLoading(true);
        showTypingIndicator();

        fetch(config.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                botId: config.botId,
                botHash: config.botHash,
                clientId: config.clientId,
                userId: config.userId,
                message: text,
            }),
        })
            .then((response) => response.json())
            .then((payload) => {
                // Check if error returned (e.g., limit reached)
                if (payload.error) {
                    hideTypingIndicator();
                    widgetBlocked = true;
                    blockReason = payload.error;
                    input.disabled = true;
                    submit.disabled = true;
                    errorBanner.innerHTML = payload.error.message + ' <a href="' + config.upgradeUrl + '" target="_blank">Upgrade Plan</a>';
                    errorBanner.classList.add('visible');
                    appendMessage('assistant', '⚠️ ' + payload.error.message, { persist: false });
                    return;
                }
                
                // If n8n returns messages synchronously, show them
                if (payload.messages && payload.messages.length > 0) {
                    setTimeout(() => {
                        deliverAssistantPayload(payload.messages);
                    }, config.typingDelay);
                } else {
                    // Async mode: n8n will push later, keep typing indicator
                    // Typing indicator will be hidden when push arrives
                }
            })
            .catch(() => {
                // Don't show error immediately - n8n might push later
                // Set a timeout to hide typing if no push arrives
                setTimeout(() => {
                    if (typingActive) {
                        hideTypingIndicator();
                    }
                }, 30000); // 30 second timeout
            })
            .finally(() => {
                setLoading(false);
            });
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input.value);
    });

    // Polling for push messages from n8n
    const POLL_INTERVAL = 2000; // 2 seconds
    let pollTimer = null;
    let isPolling = false;

    const pollForMessages = async () => {
        if (isPolling || !config.pollApiUrl || widgetBlocked) return;
        isPolling = true;
        
        try {
            const url = config.pollApiUrl + '?client_id=' + encodeURIComponent(config.clientId) + '&user_id=' + encodeURIComponent(config.userId);
            const response = await fetch(url);
            if (response.ok) {
                const data = await response.json();
                if (data.messages && data.messages.length > 0) {
                    hideTypingIndicator();
                    data.messages.forEach((msg) => {
                        appendMessage(msg.role || 'assistant', msg.text, { persist: true, skipApiSave: true });
                    });
                }
            }
        } catch {}
        
        isPolling = false;
    };

    const startPolling = () => {
        if (pollTimer || widgetBlocked) return;
        pollForMessages();
        pollTimer = setInterval(pollForMessages, POLL_INTERVAL);
    };

    const stopPolling = () => {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    // Start polling when widget is initialized (only if not blocked)
    if (!widgetBlocked) {
        startPolling();
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', stopPolling);

    } // end initWidget

    // Wait for body to be ready before initializing
    if (document.body) {
        initWidget();
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidget);
    } else {
        // Fallback: poll for body
        const interval = setInterval(() => {
            if (document.body) {
                clearInterval(interval);
                initWidget();
            }
        }, 10);
    }

})();
JS;

} catch (Throwable $e) {
    echo 'console.error("Salesbot: initialization failed - ' . addslashes($e->getMessage()) . '");';
    exit;
}
