<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');

echo <<<'JS'
(function () {
    const DEFAULT_API_URL = 'https://cdn.weba-ai.com/api/chat.php';
    const scriptTag = document.currentScript || (function () {
        const scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();

    const config = {
        botId: scriptTag?.dataset.salesbotId || 'demo',
        userId: scriptTag?.dataset.salesbotUserId || 'guest',
        apiUrl: scriptTag?.dataset.apiUrl || DEFAULT_API_URL,
        title: scriptTag?.dataset.salesbotTitle || 'Salesbot',
        welcomeMessage: scriptTag?.dataset.salesbotWelcome || 'Hello! I am ready to assist with your questions.',
        placeholder: scriptTag?.dataset.salesbotPlaceholder || 'Type your message...',
        sendLabel: scriptTag?.dataset.salesbotSendLabel || 'Send',
        sendingLabel: scriptTag?.dataset.salesbotSendingLabel || 'Sending...',
        errorMessage: scriptTag?.dataset.salesbotError || 'Something went wrong. Please try again soon.',
    };
    const MOBILE_BREAKPOINT = 768;
    let hasOpened = false;

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
        }
        .salesbot-widget.visible {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
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
            padding: 0 16px;
            height: 54px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(180deg, #22d3ee, #2563eb);
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.02em;
            cursor: pointer;
            box-shadow: 0 10px 35px rgba(37, 99, 235, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            animation: salesbot-pulse 3s ease infinite;
        }
        .salesbot-toggle .salesbot-toggle-label {
            display: block;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.08em;
        }
        .salesbot-toggle .salesbot-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #34d399;
            box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.4);
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
        .salesbot-widget.mobile-fullscreen {
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            width: 100vw;
            max-width: 100vw;
            min-width: 100vw;
            max-height: 100vh;
            min-height: 100vh;
            border-radius: 0;
            padding: 0;
            resize: none;
            border: none;
            box-shadow: none;
        }
        .salesbot-widget.mobile-fullscreen .salesbot-messages {
            padding-bottom: 140px;
        }
        .salesbot-widget.mobile-fullscreen .salesbot-form {
            padding: 18px;
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
    header.innerHTML = `<span>${config.title}</span>`;

    const closeButton = document.createElement('button');
    closeButton.className = 'salesbot-close';
    closeButton.textContent = '✕';
    closeButton.addEventListener('click', () => {
        closeWidget();
    });
    header.appendChild(closeButton);

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

    widget.appendChild(header);
    widget.appendChild(messagesEl);
    widget.appendChild(form);
    document.body.appendChild(widget);

    const toggle = document.createElement('button');
    toggle.className = 'salesbot-toggle';
    toggle.innerHTML = '<span class="salesbot-toggle-label">Manager Online</span><span class="salesbot-status-dot" aria-hidden="true"></span>';
    const isMobileViewport = () => window.innerWidth <= MOBILE_BREAKPOINT;

    const applyResponsiveState = () => {
        if (!widget.classList.contains('visible')) {
            widget.classList.remove('mobile-fullscreen');
            return;
        }
        widget.classList.toggle('mobile-fullscreen', isMobileViewport());
    };

    const openWidget = () => {
        if (!hasOpened) {
            appendMessage('assistant', config.welcomeMessage);
            hasOpened = true;
        }
        widget.classList.add('visible');
        applyResponsiveState();
        input.focus();
    };

    const closeWidget = () => {
        widget.classList.remove('visible');
        applyResponsiveState();
    };

    toggle.addEventListener('click', () => {
        if (widget.classList.contains('visible')) {
            closeWidget();
            return;
        }
        openWidget();
    });
    document.body.appendChild(toggle);

    window.addEventListener('resize', applyResponsiveState);
    applyResponsiveState();

    const appendMessage = (role, text) => {
        const bubble = document.createElement('div');
        bubble.className = `salesbot-message ${role}`;
        bubble.textContent = text;
        messagesEl.appendChild(bubble);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const setLoading = (isLoading) => {
        submit.disabled = isLoading;
        submit.textContent = isLoading ? config.sendingLabel : config.sendLabel;
    };

    const sendMessage = (text) => {
        if (!text.trim()) {
            return;
        }
        appendMessage('user', text);
        input.value = '';
        setLoading(true);

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
                (payload.messages || []).forEach((message) => {
                    appendMessage(message.role || 'assistant', message.text || '');
                });
            })
            .catch(() => {
                appendMessage('assistant', config.errorMessage);
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

