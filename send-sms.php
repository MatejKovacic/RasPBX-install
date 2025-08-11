<?php
/*********************************************************************
 *  Chan_Dongle SMS Sender v0.1- AJAX Version
 *  for The Raspberry Asterisk
 *
 *  Author: Matej Kovacic, 2025
 *  The script is based on Chan_Dongle SMS Script by Troy Nahrwold
 *  from Eternal Works company (https://www.eternalworks.com)
 *
 *  Updated features:
 *  =================
 *  - Mobile friendly
 *  - Switchable regex for local (Slovenian) or E.164 phone number formats
 *  - One phone number only (no multiple recipients)
 *  - Real-time JS sanitizing and server side phone number validation
 *  - Showing how many characters is left for a message (160 max)
 *  - Transliterate UTF-8 to ASCII to handle special characters that can not be sent in normal SMS
 *    (for this transliterator_transliterate PHP function is used. You can check if it is installed
 *    with command "php -m | grep intl" - if you see "intl", then function is installed)
 *  - Logs sent messages to /var/opt/sent_messages/
 *
 *    IMPORTANT: you need to create this directory first! Do it by typing commands;
 *    - sudo mkdir -p /var/opt/sent_messages
 *    - sudo chown asterisk:asterisk /var/opt/sent_messages
 *    - sudo chmod 755 /var/opt/sent_messages/
 *
 *  - Password stored in hashed form, using bcrypt (password_hash)
 *  - Asterisk output captured to get queue ID
 * 
 *  You can view the status of the queued SMS message by issuing a command:
 *  cat /var/log/asterisk/full | grep <queue_ID>
 * 
 *  Example for error (SMS not sent):
 *  cat /var/log/asterisk/full | grep 0xb4005c60
 * 
 * [2025-08-11 21:18:00] VERBOSE[1604] at_response.c: [dongle0] Error sending SMS message 0xb4005c60
 * [2025-08-11 09:18:00] ERROR[1604] at_response.c: [dongle0] Error sending SMS message 0xb4005c60
 * 
 * Example for success (SMS sent successfully):
 * cat /var/log/asterisk/full | grep 0xb3e61a00
 * [2025-08-11 21:22:07] VERBOSE[1604] at_response.c: [dongle0] Successfully sent SMS message 0xb3e61a00
 * [2025-08-11 21:22:07] NOTICE[1604] at_response.c: [dongle0] Successfully sent SMS message 0xb3e61a00
 * 
 *********************************************************************
 * Please see the settings below! You may want to:
 *  - set your own secure password
 *  - select regex for phone number validation
 *********************************************************************/

// Enable those two for debug purposes only:
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

session_start();

// SETTINGS
$dongle = "dongle sms dongle0 ";
$ini = "'";
$stored_hash = '$2y$10$7w9jcauybTEKk9X.VtPlVOYVKQb8.EED.uL2zJq3WJhL/esCKvYzq'; // Default password is: "ChangeYourPassword"
// IMPORTANT: you can change default (or forgotten) password by typing this command to terminal:
// php -r "echo password_hash('ChangeYourPassword', PASSWORD_DEFAULT) . PHP_EOL;"

// IMPORTANT: choose ONE phone regex by uncommenting:
// Allow only local Slovenian numbers: 9 digits, specific prefixes
$phoneRegex = '/^\+386(30|40|68|69|31|41|51|65|70|71|64|65)[0-9]{6}$/';
// Allow international in E.164 format:
// $phoneRegex = '/^\+[1-9][0-9]{7,14}$/';

// Remove the delimiters:
$patternOnly = trim($phoneRegex, '/');
$jsPhoneRegex = addslashes($patternOnly);

// LOGIN HANDLING
if (!isset($_SESSION['loggedIn'])) {
    $_SESSION['loggedIn'] = false;
}

if (isset($_POST['password']) && !isset($_POST['ajax'])) {
    if (password_verify($_POST['password'], $stored_hash)) {
        $_SESSION['loggedIn'] = true;
    } else {
        $loginError = "Incorrect password. Please try again.";
    }
}

if (!$_SESSION['loggedIn'] && !isset($_POST['ajax'])):
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login to SMS Sender</title>
<style>
body { font-family: sans-serif; background-color: #f0d5b8; margin: 0; padding: 20px; }
.container { max-width: 600px; margin: auto; background: white; padding: 25px; border-radius: 10px; }
input, button { width: 100%; padding: 15px; margin: 10px 0; border-radius: 8px; font-size: 1.1em; box-sizing: border-box; max-width: 100%; }
button { background: #2b2a28; color: white; border: none; }
.modal { display: <?= isset($loginError) ? 'block' : 'none' ?>; position: fixed; inset: 0; background: rgba(0,0,0,0.5); }
.modal-content { background: white; padding: 20px; border-radius: 8px; margin: 100px auto; max-width: 300px; text-align: center; }
</style>
</head>
<body>
<div class="container">
  <h2>Login to SMS Sender</h2>
  <form method="post">
    <label>Password:</label>
    <input type="password" name="password" required>
    <button type="submit">Login</button>
  </form>
</div>
<div class="modal">
  <div class="modal-content">
    <p><?= isset($loginError) ? htmlspecialchars($loginError) : '' ?></p>
    <button onclick="this.closest('.modal').style.display='none'">OK</button>
  </div>
</div>
</body>
</html>
<?php
exit();
endif;

// AJAX SMS SEND HANDLER
if (isset($_POST['ajax']) && $_POST['ajax'] === 'sendSMS') {
    header('Content-Type: application/json');
    $phone = trim($_POST['phonenumbers']);
    $message = substr(trim($_POST['message']), 0, 160);

    // Transliterate UTF-8 to ASCII to handle special characters like č, š, ž
    $message = transliterator_transliterate('Any-Latin; Latin-ASCII;', $message);

    // Replace newlines with spaces to keep SMS on one line:
    $message = str_replace(["\r\n", "\r", "\n"], ' ', $message); // remove newlines

    if (!preg_match($phoneRegex, $phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid phone number format.']);
        exit;
    }

    if ($message === '') {
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty.']);
        exit;
    }

    //$cmd = '/usr/sbin/asterisk -rx' . $ini . $dongle . escapeshellarg($phone) . " " . escapeshellarg($message) . $ini;
    //exec($cmd . ' 2>&1', $cmdOutput, $returnVar);
    // MACKA
    $fullCommand = $dongle . $phone . ' ' . $message;
    $cmd = '/usr/sbin/asterisk -rx ' . escapeshellarg($fullCommand);
    exec($cmd . ' 2>&1', $cmdOutput, $returnVar);

    $asteriskReply = implode("\n", $cmdOutput);
    if (preg_match('/id\s+(\S+)/', $asteriskReply, $m)) {
        $queueId = $m[1];
        $shortReply = "SMS message queue ID: $queueId";
    } else {
        $shortReply = "Asterisk response: $asteriskReply";
    }

    // Log file
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $date = date('Ymd_His');
    $rand = substr(md5(uniqid('', true)), 0, 3);
    $logFile = "/var/opt/sent_messages/SMS_{$phone}_{$date}_{$rand}.txt";
    $logData = "Date/Time: " . date('Y-m-d H:i:s') . "\n"
             . "Sender IP: $ip\n"
             . "Phone Number: $phone\n"
             . "Message: $message\n"
             . "Asterisk Reply: $asteriskReply\n";
    file_put_contents($logFile, $logData);

    echo json_encode(['status' => 'success', 'number' => $phone, 'text' => $message, 'reply' => $shortReply]);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Send SMS</title>
<style>
body { font-family: sans-serif; background-color: #f0d5b8; margin: 0; padding: 20px; }
.container { max-width: 600px; margin: auto; background: white; padding: 25px; border-radius: 10px; }
textarea, input, button { width: 100%; box-sizing: border-box; }
input[type=text] { padding: 12px; font-size: 1.1em; border: 2px solid #ddd; border-radius: 8px; }
textarea { padding: 12px; font-size: 1.1em; border: 2px solid #ddd; border-radius: 8px; resize: none; height: 100px; }
button { padding: 15px; background: #2b2a28; color: white; border: none; border-radius: 8px; font-size: 1.1em; margin-top: 10px; }
.note { font-size: 0.9em; color: #666; margin-bottom: 10px; }
.char-counter { text-align: right; font-size: 0.9em; }
.char-counter.red { color: #b30000; }
.modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); }
.modal-content { background: white; padding: 20px; border-radius: 8px; margin: 100px auto; max-width: 400px; text-align: center; }
.modal-content small { font-size: 0.85em; color: #555; }
</style>
<script>
const phoneRegex = new RegExp('<?= $jsPhoneRegex ?>');

function updateCharCounter() {
    const msg = document.getElementById('message');
    const counter = document.getElementById('charCounter');
    let remaining = 160 - msg.value.length;
    counter.textContent = remaining + " characters remaining";
    counter.className = "char-counter" + (remaining < 20 ? " red" : "");
}

function sanitizePhoneInput(e) {
    let allowed = e.target.value.replace(/[^0-9+]/g, '');
    if (allowed !== e.target.value) {
        e.target.value = allowed;
    }
}

function showModal(text) {
    document.getElementById('modalText').innerHTML = text;
    document.getElementById('resultModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('resultModal').style.display = 'none';
}

function sendSMS(e) {
    e.preventDefault();
    const phone = document.getElementById('phonenumbers').value.trim();
    const msg = document.getElementById('message').value.trim();

    if (!phoneRegex.test(phone)) {
        showModal('Invalid phone number format.');
        return;
    }
    if (msg.length === 0) {
        showModal('Message cannot be empty.');
        return;
    }

    const formData = new FormData();
    formData.append('ajax', 'sendSMS');
    formData.append('phonenumbers', phone);
    formData.append('message', msg);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showModal(
                "<strong>Message sent successfully!</strong><br>" +
                "Number: " + data.number + "<br>" +
                "Message: " + data.text + "<br><small>" + data.reply + "</small>"
            );
            document.getElementById('smsForm').reset();
            updateCharCounter();
        } else {
            showModal(data.message);
        }
    })
    .catch(err => showModal('Error: ' + err));
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('phonenumbers').addEventListener('input', sanitizePhoneInput);
    document.getElementById('message').addEventListener('input', updateCharCounter);
    updateCharCounter();
});
</script>
</head>
<body>

<style>
  #phonenumbers {
    margin-bottom: 20px;
    width: 100%;
    box-sizing: border-box;
  }

  #message {
    height: 100px;
    width: 100%;
    box-sizing: border-box;
  }

  .note {
    margin-bottom: 16px;
  }
</style>

<div class="container">
  <h2>Send SMS Message</h2>
  <form id="smsForm" onsubmit="sendSMS(event)">
    <label>Phone Number:</label>
    <p class="note">Format: international E.164 (e.g. +38640123456)</p>
    <input type="text" id="phonenumbers" name="phonenumbers" required>

    <label>Message:</label>
    <p class="note">(Max 160 characters)</p>
    <textarea id="message" name="message" maxlength="160" required></textarea>
    <div id="charCounter" class="char-counter">160 characters remaining</div>

    <button type="submit">Send SMS</button>
    <button type="reset" onclick="updateCharCounter()">Clear</button>
  </form>
</div>

<div class="modal" id="resultModal">
  <div class="modal-content">
    <p id="modalText"></p>
    <button onclick="closeModal()">OK</button>
  </div>
</div>
</body>
</html>
