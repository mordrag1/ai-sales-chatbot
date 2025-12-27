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
        welcomeMessage: scriptTag?.dataset.salesbotWelcome || 'Привет! Я готов помочь вам с продажами.',
    };

    const style = document.createElement('style');
    style.textContent = `
        .salesbot-widget {
            position: fixed;
            right: 24px;
            bottom: 120px;
            width: 320px;
            max-height: 420px;
            background: #111827;
            color: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 35px rgba(15, 23, 42, 0.4);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            font-family: "Inter", system-ui, sans-serif;
            transition: transform 0.3s ease, opacity 0.3s ease;
            transform: translateY(20px);
            opacity: 0;
        }
        .salesbot-widget.visible {
            transform: translateY(0);
            opacity: 1;
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
            padding: 12px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .salesbot-message {
            padding: 10px 14px;
            border-radius: 12px;
            line-height: 1.4;
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
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .salesbot-input {
            flex: 1;
            padding: 12px 14px;
            border: none;
            outline: none;
            background: #111827;
            color: #fff;
            font-size: 14px;
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
            width: 54px;
            height: 54px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(180deg, #22d3ee, #2563eb);
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            box-shadow: 0 8px 30px rgba(37, 99, 235, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
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
        widget.classList.remove('visible');
    });
    header.appendChild(closeButton);

    const messagesEl = document.createElement('div');
    messagesEl.className = 'salesbot-messages';

    const form = document.createElement('form');
    form.className = 'salesbot-form';

    const input = document.createElement('input');
    input.className = 'salesbot-input';
    input.type = 'text';
    input.placeholder = 'Напиши сообщение';
    input.autocomplete = 'off';

    const submit = document.createElement('button');
    submit.className = 'salesbot-submit';
    submit.type = 'submit';
    submit.textContent = 'Отправить';

    form.appendChild(input);
    form.appendChild(submit);

    widget.appendChild(header);
    widget.appendChild(messagesEl);
    widget.appendChild(form);
    document.body.appendChild(widget);

    const toggle = document.createElement('button');
    toggle.className = 'salesbot-toggle';
    toggle.textContent = 'AI';
    toggle.addEventListener('click', () => {
        widget.classList.toggle('visible');
        if (widget.classList.contains('visible')) {
            input.focus();
        }
    });
    document.body.appendChild(toggle);

    const appendMessage = (role, text) => {
        const bubble = document.createElement('div');
        bubble.className = `salesbot-message ${role}`;
        bubble.textContent = text;
        messagesEl.appendChild(bubble);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const setLoading = (isLoading) => {
        submit.disabled = isLoading;
        submit.textContent = isLoading ? '...Отправка' : 'Отправить';
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
                appendMessage('assistant', 'Что-то пошло не так. Повторите запрос чуть позже.');
            })
            .finally(() => {
                setLoading(false);
            });
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input.value);
    });

    const initWidget = () => {
        appendMessage('assistant', config.welcomeMessage);
        widget.classList.add('visible');
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidget);
    } else {
        initWidget();
    }
})();
JS;

