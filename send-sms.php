<?php
/*********************************************************************
 *  Chan_Dongle SMS Script v.0.04
 *  for The Raspberry Asterisk
 *
 *   Author: Troy Nahrwold
 *    Email: Troy(at)eternalworks(dot)com
 *  Company: Eternal Works
 *  Website: www.eternalworks.com
 *
 *  Disclaimer:  
 *   This product is solely a private production of the above named 
 *   author, and is neither endorsed nor supported by Eternal Works.
 *   Although this product has been thoroughly tested, it is 
 *   distributed AS IS, and the author assumes no liability for any 
 *   damages this script may cause to your system.  The author 
 *   has provided full source code and encourages you to review the
 *   source code to determine any effects it may have on your system.
 *
 *   (c) Copyright 2011, Troy A Nahrwold, Eternal Works, LLC.  
 *       All Rights Reserved.
 *
 *  Script design updated to be mobile friendly by:
 *   Matej Kovacic, https://telefoncek.si in 2025.
 *********************************************************************/

$dongle = "dongle sms dongle0 ";
$ini = "'";
$password = '579b6757c7b4d23d354a11bb61d6339aaa87bdf2';

/* Default password: '579b6757c7b4d23d354a11bb61d6339aaa87bdf2';
*  Default password is: myNEWpassword
*
*  If you forgot password, just open a terminal and write:
*  echo -n "myNEWpassword" | sha1sum
*  You will get SHA1 hash of a password.
 *********************************************************************/

session_start();
if (!isset($_SESSION['loggedIn'])) {
    $_SESSION['loggedIn'] = false;
}

if (isset($_POST['password'])) {
    if (sha1($_POST['password']) == $password) {
        $_SESSION['loggedIn'] = true;
    } else {
        die ('Incorrect password');
    }
} 

if (!$_SESSION['loggedIn']): ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login to SMS Gateway</title>
  <style>
    body {
      font-family: sans-serif;
      background-color: #f0d5b8;
      color: #2b2a28;
      margin: 0;
      padding: 20px;
      font-size: 18px;
    }
    .container {
      max-width: 600px;
      margin: 0 auto;
      background: white;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #2b2a28;
      font-size: 1.8em;
      margin-top: 0;
    }
    input, button {
      width: 100%;
      padding: 18px;
      margin: 15px 0;
      border: 2px solid #ddd;
      border-radius: 8px;
      font-size: 1.1em;
      box-sizing: border-box;
    }
    button {
      background: #2b2a28;
      color: white;
      border: none;
      font-weight: bold;
      font-size: 1.2em;
    }
    @media (max-width: 480px) {
      body { padding: 15px; font-size: 20px; }
      .container { padding: 20px; }
      input, button { padding: 20px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Login to SMS Gateway</h2>
    <form method="post">
      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required>
      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>
<?php
exit();
endif;

if(isset($_REQUEST['phonenumbers']) && !empty($_REQUEST['phonenumbers']) && !empty($_REQUEST['message'])) {
   $message = substr($_REQUEST['message'],0,160);
   $phonenumberarray1 = explode(' ',$_REQUEST['phonenumbers']);
   $phonenumberarray2 = array();
   $phonenumberarray3 = array();

   foreach ($phonenumberarray1 as $phonenumber) {
     $phonenumberarray2 = array_merge($phonenumberarray2,explode(',',$phonenumber));
   }
   foreach ($phonenumberarray2 as $phonenumber) {
     $phonenumberarray3 = array_merge($phonenumberarray3,explode("\n",$phonenumber));
   }

   $output = "Message: $message<br><br>\n";
   foreach ($phonenumberarray3 as $phonenumber) {
      $runcommand = '/usr/sbin/asterisk -rx' . $ini . $dongle . $phonenumber . " " . $message . $ini;
      $output .= "Sending message to: $phonenumber<br>\n";
      exec($runcommand);
   }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SMS Gateway</title>
  <style>
    body {
      font-family: sans-serif;
      background-color: #f0d5b8;
      color: #2b2a28;
      margin: 0;
      padding: 20px;
      font-size: 18px;
    }
    .container {
      max-width: 600px;
      margin: 0 auto;
      background: white;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #2b2a28;
      font-size: 1.8em;
      margin-top: 0;
    }
    .output {
      background: #f8f8f8;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 25px;
      font-size: 1.1em;
    }
    label {
      display: block;
      font-weight: bold;
      margin: 20px 0 8px;
      font-size: 1.2em;
    }
    textarea {
      width: 100%;
      padding: 18px;
      border: 2px solid #ddd;
      border-radius: 8px;
      font-size: 1.1em;
      min-height: 120px;
      box-sizing: border-box;
    }
    .note {
      font-size: 0.9em;
      color: #666;
      margin: 5px 0 15px;
    }
    button {
      width: 100%;
      padding: 20px;
      background: #2b2a28;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.2em;
      font-weight: bold;
      margin: 10px 0;
      cursor: pointer;
    }
    @media (max-width: 480px) {
      body { padding: 15px; font-size: 20px; }
      .container { padding: 20px; }
      textarea { padding: 20px; min-height: 150px; }
      button { padding: 22px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Send SMS Message</h2>
    
    <?php if(isset($output)): ?>
      <div class="output">
        <?php echo $output; ?>
      </div>
    <?php endif; ?>
    
    <form action="index.php" method="post">
      <label for="phonenumbers">Phone Numbers:</label>
      <p class="note">(Format: NXXNXXXXXX (example: +38646123456). Separate with commas or newlines.)</p>
      <textarea id="phonenumbers" name="phonenumbers" required></textarea>
      
      <label for="message">Message:</label>
      <p class="note">(Max 160 characters, will be truncated.)</p>
      <textarea id="message" name="message" maxlength="160" required></textarea>
      
      <button type="submit">Send SMS</button>
    </form>
    
    <form action="index.php" method="get">
      <button type="submit">Clear form</button>
    </form>
  </div>
</body>
</html>
