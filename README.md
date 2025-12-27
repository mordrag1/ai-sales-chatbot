# Salesbot CDN widget

A lightweight PHP stack that demonstrates the CDN-delivered chat widget and its backend.

1. `salesbot.php` is the single script that CDN clients include; it injects the widget, reads `data-*` attributes, and forwards messages to `api/chat.php`.
2. Clients specify `data-salesbot-id` to choose which configuration from `data/clients.php` applies; each botId can point to a different n8n webhook.
3. `api/chat.php` currently returns demo text but already exposes the mapped `n8nWebhookUrl`. In production it will proxy requests to n8n and relay the structured response.
4. `demo/index.html` shows how to embed the widget with overrides for title, placeholder, and API endpoint.

## Running locally

1. Install PHP or use any server that can serve PHP files.
2. Start the server from the repo root, e.g. `php -S 0.0.0.0:8000`.
3. Open `http://localhost:8000/demo/index.html` and click the manager button to launch the chat.

## Client integration

```html
<script src="https://cdn.weba-ai.com/salesbot.php"
        data-salesbot-id="client-alpha"
        data-salesbot-user-id="user-123"
        data-salesbot-title="Manager Online"
        data-salesbot-placeholder="How can I help?"
        data-salesbot-send-label="Send">
</script>
```

Every `botId` is resolved against `data/clients.php`, so you can maintain per-client metadata (label, n8n webhook URL, demo text).

## Integrating with n8n

`api/chat.php` already returns the resolved `n8nWebhookUrl` so you can route the request to the right workflow. In production:

1. POST the payload `{ message, userId, botId }` plus any metadata to `n8nWebhookUrl`.
2. Transform the workflow output into the `messages` array that the widget expects.
3. Add caching, logging, throttling, or advanced routing as requirements evolve.

## Operator state & persistence

- The widget header switches to “Operator Online” with its own green indicator while a conversation is active, and the floating toggle hides during that state. When the chat is hidden and a new AI message arrives, the toggle reappears with a green badge, animation, and sound until the visitor reopens the panel.
- Every assistant reply renders after a short typing delay with a “Operator typing…” bubble so you can demonstrate the typing indicator, and the logic stores every message per `botId` in `localStorage` so the history survives page reloads while maintaining unread tracking.
- The toggle always shows the green online dot, a separate yellow badge animates when replies arrive while the chat is hidden, and the panel smoothly fades in/out with a styled custom scrollbar so the floating interface never exposes default browser chrome.
- Play a chime when the operator reply lands via the `data-salesbot-sound-enabled` flag (defaults to true) so you can silence the widget on noisy pages.

## CDN & deployment notes

Host `salesbot.php` and `api/chat.php` (or their rewrites) under `cdn.weba-ai.com`. The API endpoint must allow CORS if served from a different subdomain than the host page or be served from the same origin for simplicity during the demo.

## Cache busting

`demo/index.html` injects both `demo.css` and `salesbot.php` with a random `cb` query parameter so each refresh pulls the latest files while you develop. Replicate the same pattern on the CDN if those assets sit behind aggressive caches.

## What's shipped in this iteration

- demo page with a floating manager button and chat window (`demo/index.html`);
- CDN widget (`salesbot.php`) that renders without dependencies and supports desktop/mobile layouts;
- API proxy (`api/chat.php`) that reads `botId`, returns demo messages, and exposes the target `n8nWebhookUrl`;
- client map (`data/clients.php`) with placeholders for multiple bots.

