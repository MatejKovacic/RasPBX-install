<?php
require_once __DIR__ . '/auth.php';
check_login();

session_start();

$queueId = $_GET['id'] ?? '';
if (!$queueId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing queue ID']);
    exit;
}

$logFile = "/var/log/asterisk/full";

// Read last 500 lines only for speed
$cmd = "tail -n 500 " . escapeshellarg($logFile) . " | grep " . escapeshellarg($queueId);
exec($cmd, $output, $rv);
$joined = implode("\n", $output);

if (strpos($joined, 'Successfully sent SMS message') !== false) {
    echo json_encode(['status' => 'success']);
} elseif (strpos($joined, 'Error sending SMS message') !== false) {
    echo json_encode(['status' => 'failed']);
} else {
    echo json_encode(['status' => 'pending']);
}
exit;
?>
