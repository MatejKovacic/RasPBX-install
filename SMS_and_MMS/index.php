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
        if (data.status === 'queued') {
            showModal("Message queued. Waiting for confirmation...");
            pollStatus(data.queueId);
            document.getElementById('smsForm').reset();
            updateCharCounter();
        } else if (data.status === 'success') {
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

function pollStatus(queueId, attempts = 0) {
    if (attempts > 10) { // ~20s max wait
        showModal("No response from modem yet (still pending).");
        return;
    }
    fetch("check_sms_status.php?id=" + encodeURIComponent(queueId))
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                showModal("SMS sent successfully!");
            } else if (data.status === "failed") {
                showModal("SMS failed to send.");
            } else {
                setTimeout(() => pollStatus(queueId, attempts + 1), 2000);
            }
        })
        .catch(err => showModal("Error checking status: " + err));
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

  /* Contact search styling */
  #contactSearch {
    width: 100%;
    max-width: 400px;   /* prevents it from being too wide */
    padding: 10px;
    font-size: 1em;
    border: 2px solid #ddd;
    border-radius: 8px;
    margin-bottom: 8px;
  }

  #contactDropdown {
    position: absolute;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 6px;
    max-height: 200px;
    overflow-y: auto;
    width: 100%;
    max-width: 400px;   /* same width as search input */
    z-index: 1000;
    display: none;
  }

  #contactDropdown div {
    padding: 10px;
    cursor: pointer;
    font-size: 0.95em;
  }

  #contactDropdown div:hover {
    background: #f5f5f5;
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

      <label>Search Contact:</label>
      <div style="position: relative; max-width: 400px;">
        <input type="text" id="contactSearch" placeholder="Type a contact name..." autocomplete="off">
        <div id="contactDropdown" class="dropdown"></div>
      </div>

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

<script>
let contacts = [];

async function loadContacts() {
  try {
    const res = await fetch('get_contacts.php');
    contacts = await res.json();
    console.log("Contacts loaded:", contacts); // Debug
  } catch (err) {
    console.error("Failed to load contacts:", err);
  }
}

function filterContacts(query) {
  query = query.toLowerCase();
  return contacts.filter(c => c.name.toLowerCase().includes(query));
}

function showDropdown(results) {
  const dropdown = document.getElementById('contactDropdown');
  dropdown.innerHTML = "";
  if (results.length === 0) {
    dropdown.style.display = "none";
    return;
  }
  results.forEach(c => {
    const div = document.createElement('div');
    div.textContent = c.name + " (" + c.number + ")";
    div.onclick = () => {
      document.getElementById('phonenumbers').value = c.number;
      document.getElementById('contactSearch').value = c.name;
      dropdown.style.display = "none";
    };
    dropdown.appendChild(div);
  });
  dropdown.style.display = "block";
}

document.addEventListener('DOMContentLoaded', async () => {
  await loadContacts();

  const searchInput = document.getElementById('contactSearch');
  const phoneInput = document.getElementById('phonenumbers');

  searchInput.addEventListener('input', () => {
    const query = searchInput.value.trim();

    if (query.length === 0) {
      phoneInput.value = "";
      document.getElementById('contactDropdown').style.display = "none";
      return;
    }

    const results = filterContacts(query);

    if (results.length === 1 && results[0].name.toLowerCase() === query.toLowerCase()) {
      phoneInput.value = results[0].number;
      document.getElementById('contactDropdown').style.display = "none";
      return;
    }

    showDropdown(results);
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('#contactSearch') && !e.target.closest('#contactDropdown')) {
      document.getElementById('contactDropdown').style.display = "none";
    }
  });
});
</script>
</body>
</html>
