<?php

require_once __DIR__ . '/auth.php';
check_login();

session_start();

// ================== Configuration ==================
$MESSAGE_DIR   = '/var/opt/raspbx/sent_messages';
$CONTACTS_FILE = '/var/opt/my_contacts.txt';

// ================== Load contacts ==================
$contacts = [];
if (file_exists($CONTACTS_FILE)) {
    foreach (file($CONTACTS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, "\t") !== false) {
            list($name, $phone) = explode("\t", $line, 2);
            $phone = preg_replace('/[^0-9+]/', '', $phone);
            $contacts[$phone] = $name;
        }
    }
}

// ================== Parse SMS messages ==================
$messages = [];
if (is_dir($MESSAGE_DIR)) {
    foreach (scandir($MESSAGE_DIR) as $file) {
        if (preg_match('/^SMS_(\+?\d+)_([0-9]{8})_([0-9]{6})_/', $file, $m)) {
            $filepath = $MESSAGE_DIR.'/'.$file;
            $content  = file_get_contents($filepath);
            $lines    = explode("\n", $content);
            $dt = ''; $ip = ''; $queue_id = ''; $msg_text = '';
            $phone = preg_replace('/[^0-9+]/','',$m[1]);

            foreach ($lines as $line) {
                if (!$dt && preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $line, $d)) $dt = $d[0];
                if (!$ip && preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $line, $i)) $ip = $i[0];
                if (!$queue_id && preg_match('/id\s+(0x[0-9a-fA-F]+)/', $line, $q)) $queue_id = $q[1];
            }
            foreach ($lines as $line) {
                if (!preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $line) &&
                    !preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $line) &&
                    stripos($line, 'queued for send') === false) {
                    if (strlen($line) > strlen($msg_text)) $msg_text = trim($line);
                }
            }
            $name = isset($contacts[$phone]) ? $contacts[$phone] : '';
            $messages[] = [
                'dt'       => $dt,
                'phone'    => $phone,
                'name'     => $name,
                'ip'       => $ip,
                'queue_id' => $queue_id,
                'message'  => $msg_text,
                'file'     => $file
            ];
        }
    }
    usort($messages, function($a, $b) {
        return strcmp($b['dt'], $a['dt']);
    });
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SMS viewer</title>
<style>
body { font-family: sans-serif; background:#f0d5b8; margin:0; }
header { background:#2b2a28; color:white; padding:15px; position:sticky; top:0; display:flex; justify-content:space-between; align-items:center; }
h1 { margin:0; font-size:20px; }
.actions { display:flex; gap:15px; align-items:center; }
.actions a { color:white; text-decoration:none; font-size:16px; }
.filters { margin-top:10px; display:flex; gap:10px; }
.filters input { flex:2; padding:8px; border-radius:6px; font-size:14px; border:1px solid #ccc; }
.filters select { flex:1; padding:8px; border-radius:6px; font-size:14px; border:1px solid #ccc; }
table { width:100%; border-collapse:collapse; font-size:15px; margin-top:10px; }
thead th { background:#e0c0a0; padding:8px; text-align:left; position:sticky; top:80px; }
tbody td { padding:10px; border-bottom:1px solid #ddd; vertical-align:top; }
tbody tr:nth-child(even) { background:#f9e6d2; }
.muted { color:#666; font-size:12px; }
.wrap { word-break:break-word; white-space:pre-wrap; font-size:15px; }
.pill { background:#2b2a28; color:white; padding:2px 6px; border-radius:12px; font-size:13px; cursor:help; }
@media(max-width:768px){thead th:nth-child(4),tbody td:nth-child(4), thead th:nth-child(5),tbody td:nth-child(5){display:none;}}
</style>
</head>
<body>
<header>
  <h1>SMS viewer</h1>
  <div class="actions">
    <a href="index.php" title="Send new SMS">
      <img src="icons/send.png" alt="Send SMS" class="icon">
    </a>
    <a href="?logout=1" title="Logout">
      <img src="icons/logout.png" alt="Logout" class="icon">
    </a>
  </div>
</header>

<style>
header { display:flex; justify-content: space-between; align-items: center; padding: 10px 20px; background-color: #2b2a28; color: white; }
header h1 { margin:0; font-size:1.5em; }
.actions { display:flex; align-items:center; }
.icon { width: 36px; height: 36px; margin-left: 15px; transition: transform 0.2s; }
.icon:hover { transform: scale(1.1); }
@media (max-width:600px){ .icon { width:44px; height:44px; } }
</style>

<div class="filters">
  <input type="search" id="q" placeholder="Filter by any field...">
  <select id="when">
    <option value="">Any time</option>
    <option value="1">Last 24 hours</option>
    <option value="7">Last 7 days</option>
    <option value="30">Last 30 days</option>
  </select>
</div>
<main>
<table id="tbl">
<thead>
<tr><th>Date/Time</th><th>Phone / Name</th><th>Message</th><th>Sender IP</th><th>Queue ID</th></tr>
</thead>
<tbody>
<?php foreach($messages as $m): ?>
<tr data-dt="<?= htmlspecialchars($m['dt']) ?>">
<td><?= htmlspecialchars($m['dt']) ?></td>
<td><div><strong><?= htmlspecialchars($m['phone']) ?></strong></div><div class="muted"><?= htmlspecialchars($m['name']) ?></div></td>
<td class="wrap"><?= htmlspecialchars($m['message']) ?></td>
<td><?= htmlspecialchars($m['ip']) ?></td>
<td><span class="pill" title="<?= htmlspecialchars($m['file']) ?>"><?= htmlspecialchars($m['queue_id']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</main>
<script>
(function(){
  var q=document.getElementById('q'), when=document.getElementById('when');
  var rows=[].slice.call(document.querySelectorAll('#tbl tbody tr'));
  function filter(){
    var needle=q.value.toLowerCase();
    var days=parseInt(when.value)||0;
    var now=new Date();
    rows.forEach(function(r){
      var text=r.textContent.toLowerCase();
      var dt=new Date(r.getAttribute('data-dt'));
      var ok=true;
      if(needle && text.indexOf(needle)===-1) ok=false;
      if(days && !isNaN(dt.getTime())){
        var diff=(now-dt)/86400000;
        if(diff>days) ok=false;
      }
      r.style.display=ok?'':'none';
    });
  }
  q.addEventListener('input',filter);
  when.addEventListener('change',filter);
})();
</script>
</body>
</html>

