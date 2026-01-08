<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Dataset - Demo</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            margin: 0;
            padding: 40px 20px;
            color: #e0e0e0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            color: #00d9ff;
            text-align: center;
            margin-bottom: 30px;
        }
        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .status {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .status.success {
            background: rgba(0, 200, 83, 0.2);
            border: 1px solid #00c853;
            color: #69f0ae;
        }
        .status.error {
            background: rgba(255, 82, 82, 0.2);
            border: 1px solid #ff5252;
            color: #ff8a80;
        }
        .status.info {
            background: rgba(0, 176, 255, 0.2);
            border: 1px solid #00b0ff;
            color: #80d8ff;
        }
        pre {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
            color: #c9d1d9;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #00d9ff, #00b0ff);
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn.secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        h2 {
            color: #00d9ff;
            margin-top: 0;
        }
        .response {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Dataset Setup Demo</h1>

        <?php
        $apiBaseUrl = 'https://cdn.weba-ai.com/api';
        $clientId = '1';
        
        $datasetContent = 'You are an AI sales assistant for the product "WebA AI Chatbot".

Your role: Provide clear, accurate, and consistent answers about the WebA AI Chatbot product. Answer questions only using the information below. Do not invent features, pricing, integrations, guarantees, or technical details that are not explicitly stated. When a user asks for details outside this knowledge, suggest requesting a demo.

Tone and style:

Professional and confident
Clear and concise
Sales-oriented but not aggressive
Focused on benefits and use cases
Product overview: WebA AI Chatbot is a next-generation AI sales assistant trained individually on a client\'s real business data, not templates. It learns products, services, FAQs, and sales scenarios to communicate with website visitors, capture leads, and help increase website conversion by engaging users instantly. The chatbot works 24/7 and never misses incoming leads due to slow responses.

Core features:

Individually trained on real business data
No templates, only client-provided information
Works 24/7 without interruptions
Instant responses to website visitors
Automatically captures leads (name, email, phone number, message)
Human-like dialogue and realistic communication logic
Accepts training data in any format (documents, PDFs, websites, FAQs, scripts, CRM exports, plain text)
One-line installation on any website
Fast launch without complex setup
How it works:

Training on your data: The client provides information about their business, products, services, and preferred conversation scenarios.
Dialogue testing: Conversations are tested and refined on a private demo page to ensure accuracy and quality.
Website installation: The AI sales chatbot is embedded directly into the client\'s website using a single line of code.
Lead generation: The chatbot starts converting visitors into leads and customers automatically from existing traffic.
Use cases:

E-commerce websites: Answers product questions, recommends items, handles objections, and guides users to checkout.
Service businesses: Qualifies leads, explains services and pricing, and helps book consultations.
B2B companies: Pre-qualifies inbound traffic, collects business details, and routes leads to sales teams.
Landing pages and ads traffic: Instantly engages paid traffic from Google Ads and social media without delays.
SaaS products: Explains features, pricing plans, onboarding steps, and helps increase trial sign-ups.
Advantages over standard chatbots:

No templates, only your data
Instant AI responses with no waiting
No missed leads
Continuous conversations 24/7
Reduces workload on sales managers by handling first contact
Quick and simple setup
Lead capture and sales assistance: WebA AI Chatbot automatically collects contact information during conversations and helps guide users toward actions such as submitting a request or booking a consultation. It acts as a digital sales manager, handling initial communication before passing qualified leads to human managers if needed.

Common questions and answers:

What is WebA AI Chatbot? WebA AI Chatbot is an AI-powered sales assistant trained on your real business data to communicate with visitors, answer questions, capture leads, and improve conversions.

How is WebA AI Chatbot different from regular chatbots? Unlike template-based chatbots, WebA AI Chatbot is trained individually on your business data and understands your specific products, services, and sales scenarios.

What data can be used for training? Documents, PDFs, websites, FAQs, scripts, CRM exports, and plain text.

Does it work 24/7? Yes. The chatbot works continuously and responds instantly.

Can it collect leads? Yes. It collects names, emails, phone numbers, and messages during conversations.

Is installation complicated? No. Installation requires only a single line of code added to the website.

Does it replace human sales managers? No. It handles the first contact and qualification of leads, reducing workload and allowing managers to focus on closing deals.

Call to action logic: If a user shows interest, asks about customization, pricing, integrations, or advanced functionality, encourage them to request a free demo. Explain that the demo allows them to see how WebA AI Chatbot works specifically for their business.

End goal: Help users understand how WebA AI Chatbot can turn their website into a 24/7 sales machine and guide them toward requesting a demo when appropriate.';

        $action = $_GET['action'] ?? '';
        $result = null;
        $error = null;

        if ($action === 'update') {
            // Update dataset via API
            $ch = curl_init($apiBaseUrl . '/dataset.php');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'client_id' => $clientId,
                    'item' => $datasetContent
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
            } else {
                $error = "HTTP $httpCode: $response";
            }
        } elseif ($action === 'view') {
            // View current dataset
            $ch = curl_init($apiBaseUrl . '/dataset.php?client_id=' . urlencode($clientId));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
            } else {
                $error = "HTTP $httpCode: $response";
            }
        } elseif ($action === 'query') {
            // Test query
            $question = $_GET['q'] ?? 'What is WebA AI Chatbot?';
            $ch = curl_init($apiBaseUrl . '/dataset-query.php');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'client_id' => $clientId,
                    'question' => $question
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = ['text' => $response];
            } else {
                $error = "HTTP $httpCode: $response";
            }
        }
        ?>

        <div class="card">
            <h2>Actions</h2>
            <a href="?action=update" class="btn">📥 Add Dataset for Client 1</a>
            <a href="?action=view" class="btn secondary">👁 View Current Dataset</a>
            <a href="?action=query&q=What is WebA AI Chatbot?" class="btn secondary">🔍 Test Query</a>
        </div>

        <?php if ($action): ?>
        <div class="card">
            <h2>Result: <?= htmlspecialchars($action) ?></h2>
            
            <?php if ($error): ?>
                <div class="status error">❌ Error: <?= htmlspecialchars($error) ?></div>
            <?php elseif ($result): ?>
                <div class="status success">✅ Success!</div>
                
                <?php if ($action === 'query'): ?>
                    <div class="response">
                        <h3>Text Response:</h3>
                        <pre><?= htmlspecialchars($result['text']) ?></pre>
                    </div>
                <?php else: ?>
                    <div class="response">
                        <h3>API Response:</h3>
                        <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <h2>Dataset Content to Add</h2>
            <div class="status info">ℹ️ Click "Add Dataset" to add the following content to Client 1's dataset</div>
            <pre><?= htmlspecialchars($datasetContent) ?></pre>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>API Endpoints</h2>
            <ul>
                <li><code>GET /api/dataset.php?client_id=1</code> - Get dataset</li>
                <li><code>POST /api/dataset.php</code> - Add item to dataset</li>
                <li><code>POST /api/dataset-query.php</code> - Get dataset + question as text</li>
            </ul>
        </div>
    </div>
</body>
</html>


