<?php
session_start();
require_once __DIR__ . '/auth.php';
check_login();

// ================== Configuration ==================
$SENT_DIR      = '/var/opt/raspbx/sent_messages';
$RECEIVED_DIR  = '/var/opt/raspbx/received_messages';
$CONTACTS_FILE = '/var/opt/raspbx/my_contacts.txt';

// ================== Load contacts ==================
$contacts = [];
if (file_exists($CONTACTS_FILE)) {
    foreach (file($CONTACTS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, "\t") !== false) {
            list($name, $phone) = explode("\t", $line, 2);
            $phone = preg_replace('/[^0-9+]/', '', $phone);
            if (strpos($phone, '00') === 0) {
                $phone = '+' . substr($phone, 2);
            } elseif ($phone !== '' && $phone[0] !== '+') {
                $phone = '+' . $phone;
            }
            $contacts[$phone] = $name;
        }
    }
}

// ================== Helper: normalize phone ==================
function normalize_phone($num) {
    $num = preg_replace('/[^0-9+]/', '', $num);
    if (strpos($num, '00') === 0) {
        $num = '+' . substr($num, 2);
    } elseif ($num !== '' && $num[0] !== '+') {
        $num = '+' . $num;
    }
    return $num;
}

// ================== Parse sent SMS messages ==================
$messages = [];
if (is_dir($SENT_DIR)) {
    foreach (scandir($SENT_DIR) as $file) {
        if (preg_match('/^SMS_(\+?\d+)_([0-9]{8})_([0-9]{6})_/', $file, $m)) {
            $filepath = $SENT_DIR.'/'.$file;
            $content  = file_get_contents($filepath);
            $lines    = explode("\n", $content);
            $dt = ''; $ip = ''; $queue_id = ''; $msg_text = '';
            $phone = normalize_phone($m[1]);

            foreach ($lines as $line) {
                if (!$dt && preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $line, $d)) $dt = $d[0];
                if (!$ip && preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $line, $i)) $ip = $i[0];
                if (!$queue_id && preg_match('/id\s+(0x[0-9a-fA-F]+)/', $line, $q)) $queue_id = $q[1];
            }
            $msg_text = '';
            foreach ($lines as $line) {
                if (stripos($line, 'Message:') === 0) {
                    $msg_text = trim(substr($line, 8));
                    break; // only one Message: line per file
                }
            }
            $name = $contacts[$phone] ?? '';
            $messages[] = [
                'dt'       => $dt,
                'phone'    => $phone,
                'name'     => $name,
                'ip'       => $ip,
                'queue_id' => $queue_id,
                'message'  => $msg_text,
                'file'     => $file,
                'type'     => 'sent SMS'
            ];
        }
    }
}

// ------------------ Parse received messages (SMS/MMS) ------------------
if (is_dir($RECEIVED_DIR)) {
    foreach (scandir($RECEIVED_DIR) as $file) {
        if (preg_match('/^MSG_.*\.meta$/', $file)) {
            $filepath = $RECEIVED_DIR.'/'.$file;
            $lines = file($filepath, FILE_IGNORE_NEW_LINES); // keep all lines
            $info = ['type'=>'','from'=>'','date'=>'','decoded'=>'','mms_url'=>''];
            
            foreach ($lines as $line) {
                $line = trim($line); // remove whitespace at start/end
                if ($line === '') continue; // skip empty lines
                
                if (preg_match('/^Type:\s*(.+)$/i',$line,$m)) $info['type']=trim($m[1]);
                if (preg_match('/^From:\s*(.+)$/i',$line,$m)) $info['from']=normalize_phone($m[1]);
                if (preg_match('/^Date:\s*(.+)$/i',$line,$m)) $info['date']=trim($m[1]);
                if (preg_match('/^DecodedFile:\s*(.+)$/i',$line,$m)) $info['decoded']=trim($m[1]);
                if (preg_match('/^MMS_URL:\s*(.+)$/i',$line,$m)) $info['mms_url']=trim($m[1]);
            }

            $msg_text = '';
            if ($info['decoded'] && file_exists($info['decoded'])) {
                $msg_text = trim(file_get_contents($info['decoded']));
            }
            if ($info['type']==='MMS' && $info['mms_url']) {
                if ($msg_text) $msg_text .= "\n";
                $msg_text .= "[MMS content: ".$info['mms_url']."]";
            }

            $name = $contacts[$info['from']] ?? '';
            $messages[] = [
                'dt'       => $info['date'],
                'phone'    => $info['from'],
                'name'     => $name,
                'ip'       => '',
                'queue_id' => '',
                'message'  => $msg_text,
                'file'     => $file,
                'type'     => 'received '.$info['type']
            ];
        }
    }
}

// ================== Sort all messages by datetime ==================
usort($messages, function($a, $b) {
    return strcmp($b['dt'], $a['dt']);
});
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SMS/MMS viewer</title>
<style>
body { font-family: sans-serif; background:#f0d5b8; margin:0; }
header { background:#2b2a28; color:white; padding:15px; position:sticky; top:0; display:flex; justify-content:space-between; align-items:center; }
h1 { margin:0; font-size:20px; }
.actions { display:flex; gap:15px; align-items:center; }
.actions a { color:white; text-decoration:none; font-size:16px; }
.filters { margin-top:10px; display:flex; gap:10px; flex-wrap:wrap; }
.filters input, .filters select { padding:8px; border-radius:6px; font-size:14px; border:1px solid #ccc; }
.filters input { flex:2; }
.filters select { flex:1; }
table { width:100%; border-collapse:collapse; font-size:15px; margin-top:10px; }
thead th { background:#e0c0a0; padding:8px; text-align:left; position:sticky; top:80px; }
tbody td { padding:10px; border-bottom:1px solid #ddd; vertical-align:top; }
tbody tr:nth-child(even) { background:#f9e6d2; }
.muted { color:#666; font-size:12px; }
.wrap { word-break:break-word; white-space:pre-wrap; font-size:15px; }
.type-cell { color:white; text-align:center; border-radius:6px; padding:2px 4px; font-size:11px; }
.type-sent { background:#4a7bd4; }
.type-rsms { background:#5ca85c; }
.type-rmms { background:#c75c5c; }
.icon { width:28px; height:28px; margin-left:12px; transition: transform 0.2s; }
.icon:hover { transform: scale(1.1); }

.queue-pill {
  background:#000;
  color:white;
  padding:2px 6px;
  border-radius:12px;
  font-size:13px;
}

.phone-link {
  color:#003300;
  text-decoration:none;
}
.phone-link:hover {
  text-decoration:underline;
}
</style>
</head>
<body>
<header>
  <h1>SMS/MMS viewer</h1>
  <div class="actions">
    <a href="index.php" title="Send new SMS"><img src="icons/send.png" alt="Send SMS" class="icon"></a>
    <a href="?logout=1" title="Logout"><img src="icons/logout.png" alt="Logout" class="icon"></a>
  </div>
</header>

<div class="filters">
  <input type="search" id="q" placeholder="Filter by any field...">
  <select id="when">
    <option value="">Any time</option>
    <option value="1">Last 24 hours</option>
    <option value="7">Last 7 days</option>
    <option value="30">Last 30 days</option>
  </select>
  <select id="type">
    <option value="">All types</option>
    <option value="sent SMS">Sent SMS</option>
    <option value="received SMS">Received SMS</option>
    <option value="received MMS">Received MMS</option>
  </select>
</div>

<main>
<table id="tbl">
<thead>
<tr>
  <th>Type</th>
  <th>Date/Time</th>
  <th>Phone / Name</th>
  <th>Message</th>
  <th>Sender IP</th>
  <th>Queue ID</th>
</tr>
</thead>
<tbody>
<?php foreach($messages as $m): 
  $typeClass = ($m['type']==='sent SMS' ? 'type-sent' : ($m['type']==='received SMS' ? 'type-rsms' : 'type-rmms'));
  $msgHtml = nl2br(htmlspecialchars($m['message']));
?>
<tr data-dt="<?= htmlspecialchars($m['dt']) ?>" data-type="<?= htmlspecialchars($m['type']) ?>">
<td><span class="type-cell <?= $typeClass ?>"><?= htmlspecialchars($m['type']) ?></span></td>
<td><?= htmlspecialchars($m['dt']) ?></td>
<td>
  <div>
    <a href="index.php?number=<?= urlencode($m['phone']) ?>" class="phone-link">
      <strong><?= htmlspecialchars($m['phone']) ?></strong>
    </a>
  </div>
  <div class="muted"><?= htmlspecialchars($m['name']) ?></div>
</td>
<td class="wrap"><?= $msgHtml ?></td>
<td><?= htmlspecialchars($m['ip']) ?></td>
<td>
  <?php if ($m['queue_id']): ?>
    <span class="queue-pill"><?= htmlspecialchars($m['queue_id']) ?></span>
  <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</main>

<script>
const q = document.getElementById('q');
const when = document.getElementById('when');
const type = document.getElementById('type');
const tbl = document.getElementById('tbl');
q.addEventListener('input', filter);
when.addEventListener('change', filter);
type.addEventListener('change', filter);

function filter() {
    const qVal = q.value.toLowerCase();
    const days = parseInt(when.value);
    const typeVal = type.value;
    const now = new Date();
    for (let row of tbl.tBodies[0].rows) {
        let show = true;
        const dt = new Date(row.dataset.dt);
        if (!isNaN(days) && days > 0) {
            const diff = (now - dt) / (1000*60*60*24);
            if (diff > days) show = false;
        }
        if (typeVal && row.dataset.type!==typeVal) show=false;
        if (qVal) {
            let txt = Array.from(row.cells).map(c=>c.innerText.toLowerCase()).join(' ');
            if (!txt.includes(qVal)) show=false;
        }
        row.style.display = show?'':'none';
    }
}
</script>
</body>
</html>
