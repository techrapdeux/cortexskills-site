<?php
// Cortex Skills — Free Guide Delivery Endpoint
// Receives email from the homepage form, sends 3 free guides via AgentMail API.

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate-limit: simple file-based cooldown per IP (1 request per 60s)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$lockDir = sys_get_temp_dir() . '/cortex-rate-limit';
if (!is_dir($lockDir)) @mkdir($lockDir, 0700, true);
$lockFile = $lockDir . '/' . md5($ip) . '.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 60) {
    http_response_code(429);
    echo json_encode(['error' => 'Please wait before requesting again.']);
    exit;
}
touch($lockFile);

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
if (!$email) {
    // fallback: form-encoded
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
}

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email required.']);
    exit;
}

// AgentMail credentials
$apiKey  = 'am_us_708bf5e36086cc894a0ef08823bfb566224387d3b6a23f42c2cd9394d5cb8ecb';
$inboxId = 'lexus@agentmail.to';

$guideBase = 'https://cortexskills.com/guides';

$htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#0B1120;color:#F1F5F9;padding:24px}
.card{background:#111827;border:1px solid rgba(124,58,237,.28);border-radius:16px;padding:24px;max-width:600px;margin:0 auto}
h1{font-size:24px;margin:0 0 8px} .accent{color:#22D3EE} a.btn{display:inline-block;margin:8px 0;padding:12px 18px;background:#F59E0B;color:#1f1302;text-decoration:none;border-radius:999px;font-weight:700}
.guide{margin:16px 0;padding:12px;border:1px solid rgba(124,58,237,.2);border-radius:12px}
.guide h3{margin:0 0 4px;font-size:16px} .guide p{margin:0;color:#94A3B8;font-size:14px}
.footer{margin-top:24px;font-size:12px;color:#94A3B8;text-align:center}
</style></head>
<body>
<div class="card">
  <p class="accent" style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;font-weight:700">CORTEX SKILLS</p>
  <h1>Your 3 Free Guides Are Here</h1>
  <p style="color:#94A3B8">Thanks for signing up. Here are your guides — no fluff, just practical AI skills you can use today.</p>

  <div class="guide">
    <h3>Guide 1: The 5-Minute AI Agent Setup</h3>
    <p>Go from zero to a working AI agent fast.</p>
    <a class="btn" href="{$guideBase}/guide-1-agent-setup.html">Read Guide 1</a>
  </div>

  <div class="guide">
    <h3>Guide 2: The Master Prompt Formula</h3>
    <p>The RCETO framework for prompts that actually work.</p>
    <a class="btn" href="{$guideBase}/guide-2-master-prompt.html">Read Guide 2</a>
  </div>

  <div class="guide">
    <h3>Guide 3: The Daily AI Workflow Cheatsheet</h3>
    <p>Save 2+ hours a day with a tested AI routine.</p>
    <a class="btn" href="{$guideBase}/guide-3-daily-workflow.html">Read Guide 3</a>
  </div>

  <div class="footer">
    <p>Cortex Skills — practical AI systems for real work.</p>
    <p><a href="https://cortexskills.com" style="color:#22D3EE">cortexskills.com</a></p>
  </div>
</div>
</body>
</html>
HTML;

$textBody = "Your 3 Free Guides from Cortex Skills\n\n"
    . "Guide 1: The 5-Minute AI Agent Setup\n{$guideBase}/guide-1-agent-setup.html\n\n"
    . "Guide 2: The Master Prompt Formula\n{$guideBase}/guide-2-master-prompt.html\n\n"
    . "Guide 3: The Daily AI Workflow Cheatsheet\n{$guideBase}/guide-3-daily-workflow.html\n\n"
    . "— Cortex Skills | cortexskills.com";

$payload = json_encode([
    'to'      => $email,
    'subject' => 'Your 3 Free AI Guides from Cortex Skills',
    'text'    => $textBody,
    'html'    => $htmlBody,
]);

$ch = curl_init("https://api.agentmail.to/v0/inboxes/{$inboxId}/messages/send");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Email delivery failed.']);
    exit;
}

if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(502);
    echo json_encode(['error' => 'Email delivery failed.', 'status' => $httpCode]);
}
