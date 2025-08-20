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
 *  - Switchable regex for local or E.164 phone number formats
 *  - One phone number only (no multiple recipients)
 *  - Real-time JS sanitizing and server side phone number validation
 *  - Showing how many characters is left for a message
 *  - Transliterate UTF-8 to ASCII to handle special characters that can not be sent in normal SMS
 *    (for this transliterator_transliterate PHP function is used. You can check if it is installed
 *    with command "php -m | grep intl" - if you see "intl", then function is installed)
 *  - Logs sent messages to /var/opt/sent_messages/
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

require_once __DIR__ . '/auth.php';
check_login();
?>

<?php

// Enable those two for debug purposes only:
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

session_start();

// SETTINGS
$dongle = "dongle sms dongle0 ";
$ini = "'";

// IMPORTANT: choose ONE phone regex by uncommenting:
// Allow only local Slovenian numbers: 9 digits, specific prefixes
$phoneRegex = '/^\+386(30|40|68|69|31|41|51|65|70|71|64|65)[0-9]{6}$/';
// Allow international in E.164 format:
//$phoneRegex = '/^\+[1-9][0-9]{7,14}$/';

// Prefill phone number from query string (?number=+38641234567 or ?number=%2B38641234567)
$prefillNumber = '';
if (isset($_GET['number'])) {
    $candidate = $_GET['number'];

    // If "+" was converted to a space, restore it
    if (strpos($candidate, ' ') === 0) {
        $candidate = '+' . ltrim($candidate);
    }

    // Remove accidental whitespace
    $candidate = trim($candidate);

    if (preg_match($phoneRegex, $candidate)) {
        $prefillNumber = htmlspecialchars($candidate, ENT_QUOTES, 'UTF-8');
    }
}

// Remove the delimiters:
$patternOnly = trim($phoneRegex, '/');
$jsPhoneRegex = addslashes($patternOnly);

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
.container { max-width: 600px; margin: auto; background: white; border-radius: 10px; }
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
  body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f0d5b8; /* restored your background */
  }

  .container {
    max-width: 800px;
    margin: 40px auto;
    border-radius: 8px;
    overflow: hidden; /* keeps header + content unified */
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    background: #fff; /* ensures the content area is always white */
  }

  .header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: #000;
    color: #fff;
    padding: 12px 16px;
  }

  .header h2 {
    margin: 0;
    font-size: 20px;
  }

  .actions {
    display: flex;
    gap: 10px;
  }

  .actions img.icon {
    width: 44px;
    height: 44px;
    cursor: pointer;
    transition: opacity 0.2s ease;
  }

  .actions img.icon:hover {
    opacity: 0.7;
  }

  .content {
    background: #fff;
    padding: 20px;
  }

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

  .char-counter {
    margin-top: 5px;
    font-size: 12px;
    color: #666;
  }

  /* Modal */
  .modal {
    display: none;
    position: fixed;
    z-index: 999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
  }

  .modal-content {
    background: #fff;
    margin: 15% auto;
    padding: 20px;
    width: 300px;
    border-radius: 6px;
    text-align: center;
  }
</style>

<div class="container">
  <div class="header">
    <h2>Send SMS Message</h2>
    <div class="actions">
      <a href="view.php" title="Show SMS messages">
        <img src="icons/list.png" alt="Show SMS messages" class="icon">
      </a>
      <a href="?logout=1" title="Logout">
        <img src="icons/logout.png" alt="Logout" class="icon">
      </a>
    </div>
  </div>

  <div class="content">
    <form id="smsForm" onsubmit="sendSMS(event)">
      <label>Phone Number:</label>
      <p class="note">Format: international E.164 (e.g. +38640123456)</p>
      <input type="text" id="phonenumbers" name="phonenumbers" value="<?= $prefillNumber ?>" required>

      <label>Message:</label>
      <p class="note">(Max 160 characters)</p>
      <textarea id="message" name="message" maxlength="160" required></textarea>
      <div id="charCounter" class="char-counter">160 characters remaining</div>

      <button type="submit">Send SMS</button>
      <button type="reset" onclick="updateCharCounter()">Clear</button>
    </form>
  </div>
</div>

<div class="modal" id="resultModal">
  <div class="modal-content">
    <p id="modalText"></p>
    <button onclick="closeModal()">OK</button>
  </div>
</div>
</body>
</html>
