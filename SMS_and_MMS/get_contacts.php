<?php
require_once __DIR__ . '/auth.php';
check_login();

header('Content-Type: application/json');

// File with contacts
$file = "/var/opt/raspbx/my_contacts.txt";

$contacts = [];
if (is_readable($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode("\t", $line);
        if (count($parts) >= 2) {
            $name = trim($parts[0]);
            $number = trim($parts[1]);
            if ($name && $number) {
                $contacts[] = ['name' => $name, 'number' => $number];
            }
        }
    }
}

echo json_encode($contacts);
