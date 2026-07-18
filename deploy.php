<?php
/*
 * GitHub webhook receiver.
 *
 * Configure this as the Payload URL in GitHub (Settings > Webhooks) so a
 * push to main automatically pulls the change onto this server.
 *
 * Requires webhook_secret.php (gitignored) defining $webhook_secret, which
 * must match the secret entered in the GitHub webhook settings.
 */

require __DIR__ . '/webhook_secret.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);

if (!$signature || !hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

$data = json_decode($payload, true);
if (($data['ref'] ?? '') !== 'refs/heads/main') {
    http_response_code(200);
    exit('Ignored: not a push to main');
}

$output = shell_exec('cd ' . escapeshellarg(__DIR__) . ' && git pull origin main 2>&1');
file_put_contents(__DIR__ . '/deploy.log', date('c') . "\n" . $output . "\n\n", FILE_APPEND);

http_response_code(200);
echo "OK";
?>
