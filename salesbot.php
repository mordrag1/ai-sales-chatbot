<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');

echo <<<'JS'
(function () {
    const DEFAULT_API_URL = 'https://gicujedrotan.beget.app/webhook/a60472fc-b4e1-4e83-92c4-75c648b9dd80';
    const scriptTag = document.currentScript || (function () {
        const scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();

    const dataset = scriptTag ? scriptTag.dataset || {} : {};
    const rawSoundEnabled = dataset.salesbotSoundEnabled;
    const config = {
        botId: dataset.salesbotId || 'demo',
        userId: dataset.salesbotUserId || 'guest',
        apiUrl: dataset.apiUrl || DEFAULT_API_URL,
        title: dataset.salesbotTitle || 'Salesbot',
        operatorLabel: dataset.salesbotOperatorLabel || 'Operator Online',
        welcomeMessage: dataset.salesbotWelcome || 'Hello! I am ready to assist with your questions.',
        placeholder: dataset.salesbotPlaceholder || 'Type your message...',
        sendLabel: dataset.salesbotSendLabel || 'Send',
        sendingLabel: dataset.salesbotSendingLabel || 'Sending...',
        typingLabel: dataset.salesbotTypingLabel || 'Operator typing...',
        typingDelay: Number(dataset.salesbotTypingDelay || '900'),
        soundSrc: dataset.salesbotSound || 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAIlYAAESsAAACABAAZGF0YQAAAAA=',
        soundEnabled: rawSoundEnabled === undefined ? true : String(rawSoundEnabled).toLowerCase() !== 'false',
        errorMessage: dataset.salesbotError || 'Something went wrong. Please try again soon.',
    };
    const MOBILE_BREAKPOINT = 768;
    let hasOpened = false;
    const STORAGE_KEY = `salesbot-chat-${config.botId}`;
    const HISTORY_LIMIT = 80;
    const storage = (() => {
        try {
            return window.localStorage;
        } catch {
            return null;
        }
    })();
    let historyState = {
        messages: [],
        hasUnread: false,
    };
    let hasUnread = false;
    const dingAudio = config.soundEnabled ? new Audio(config.soundSrc) : null;
    if (dingAudio) {
        dingAudio.preload = 'auto';
    }

    const style = document.createElement('style');
    style.textContent = `
        .salesbot-widget {
            position: fixed;
            right: 24px;
            bottom: 120px;
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
    `;
    document.head.appendChild(style);

    const widget = document.createElement('div');
    widget.className = 'salesbot-widget';

    const header = document.createElement('div');
    header.className = 'salesbot-header';
    header.innerHTML = `
        <div class="salesbot-title-wrap">
            <span class="salesbot-header-status"></span>
            <span class="salesbot-title-text">${config.title}</span>
        </div>
    `;
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

    const typingIndicator = document.createElement('div');
    typingIndicator.className = 'salesbot-typing';
    typingIndicator.textContent = config.typingLabel;
    let typingActive = false;

    const saveHistory = () => {
        if (!storage) {
            return;
        }
        const trimmed = historyState.messages.slice(-HISTORY_LIMIT);
        historyState.messages = trimmed;
        historyState.hasUnread = hasUnread;
        try {
            storage.setItem(STORAGE_KEY, JSON.stringify(historyState));
        } catch {
            // ignore storage errors
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

    const appendMessage = (role, text, { persist = false, isHistory = false, playSound = true } = {}) => {
        const bubble = document.createElement('div');
        bubble.className = `salesbot-message ${role}`;
        bubble.textContent = text;
        messagesEl.appendChild(bubble);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        if (persist && !isHistory) {
            historyState.messages.push({
                role,
                text,
                id: `${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
                timestamp: Date.now(),
            });
            saveHistory();
        }

        if (role === 'assistant' && playSound && config.soundEnabled) {
            dingAudio?.play().catch(() => {});
            const shouldMarkUnread = !widget.classList.contains('visible');
            if (shouldMarkUnread) {
                setUnread(true);
            }
        }
    };

    const loadHistory = () => {
        if (!storage) {
            return;
        }
        try {
            const stored = JSON.parse(storage.getItem(STORAGE_KEY) || 'null');
            if (stored?.messages?.length) {
                historyState.messages = stored.messages;
                historyState.hasUnread = !!stored.hasUnread;
                historyState.messages.forEach((entry) => {
                    appendMessage(entry.role, entry.text, { isHistory: true, playSound: false });
                });
                hasUnread = historyState.hasUnread;
            }
        } catch {
            // ignore corrupt storage
        }
    };

    widget.appendChild(header);
    widget.appendChild(messagesEl);
    widget.appendChild(form);

    loadHistory();
    setHeaderState(false);
    document.body.appendChild(widget);

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
            appendMessage('assistant', config.welcomeMessage);
            hasOpened = true;
        }
        widget.classList.add('visible');
        setHeaderState(true);
        setUnread(false);
        applyResponsiveState();
        updateToggleState();
        input.focus();
    };

    const closeWidget = () => {
        widget.classList.remove('visible');
        setHeaderState(false);
        applyResponsiveState();
        updateToggleState();
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
        submit.disabled = isLoading;
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
        if (!text.trim()) {
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
                userId: config.userId,
                message: text,
            }),
        })
            .then((response) => response.json())
            .then((payload) => {
                setTimeout(() => {
                    deliverAssistantPayload(payload.messages);
                }, config.typingDelay);
            })
            .catch(() => {
                setTimeout(() => {
                    handleFailedResponse();
                }, config.typingDelay);
            })
            .finally(() => {
                setLoading(false);
            });
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input.value);
    });

})();
JS;

