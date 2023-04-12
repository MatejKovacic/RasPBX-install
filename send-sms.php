<?php
/*********************************************************************
 *  Chan_Dongle SMS Script v.0.01
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
 *   Although this product has been thuroughly tested, it is 
 *   distributed AS IS, and the author assumes no liability for any 
 *   damages this script may cause to your system.  The author 
 *   has provided full source code and encourages you to review the
 *   source code to determine any effects it may have on your system.
 *
 *   (c) Copyright 2011, Troy A Nahrwold, Eternal Works, LLC.  
 *       All Rights Reserved.
 *
 *  Script design updated by:
 *   Matej Kovacic, https://telefoncek.si in 2023.
 * 
 *********************************************************************/

$dongle = "dongle sms dongle0 ";
$ini = "'";
$password = '579b6757c7b4d23d354a11bb61d6339aaa87bdf2'; 
/* Defaut original password: '579b6757c7b4d23d354a11bb61d6339aaa87bdf2'; */

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

    <html>
    <head>
      <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
      <title>Send SMS message from RasPBX</title>
    <link rel="stylesheet" href="style.css" type="text/css">
    </head>
    <body bgcolor="#f0d5b8" text="#ea7900" link="#262421" style="font-family: sans-serif">

    <h2 align="center" style="color: #2b2a28">Login to send SMS message</h2>
  
    <table border="5" cellspacing="0" cellpadding="1" width="600" bgcolor="#2b2a28" align="center">
      <tr>
        <td>
          <table border="0" cellspacing="0" cellpadding="3" width="100%" bgcolor="#ffffff" align="center">
  <tr>
    <p>&nbsp;</p>
    <form method="post">
      Please enter your password: <input type="password" name="password"> <br />
      <p></p>
  </tr>
      <p></td>
            </tr>
            <tr>
              <td bgcolor="#2b2a28" align=right><input type="submit" name="submit" value="Login"></td>
              </form>
            </tr>
          </table>
        </td>
      </tr>
    </table>
    <p>
    </body>
    </html>

<?php
exit();
endif;

if(isset($_REQUEST['phonenumbers']) && !empty($_REQUEST['phonenumbers']) && !empty($_REQUEST['message']))
 {
   $message           = substr($_REQUEST['message'],0,160);
   $phonenumberarray1 = explode(' ',$_REQUEST['phonenumbers']);
   $phonenumberarray2 = array();
   $phonenumberarray3 = array();

   foreach ($phonenumberarray1 as $phonenumber)
   {
     $phonenumberarray2 = array_merge($phonenumberarray2,explode(',',$phonenumber));
   }
   foreach ($phonenumberarray2 as $phonenumber)
   {
     $phonenumberarray3 = array_merge($phonenumberarray3,explode("\n",$phonenumber));
   }

   $output = "Message: $message<br><br>\n";
   foreach ($phonenumberarray3 as $phonenumber)
   {
      $runcommand = '/usr/sbin/asterisk -rx' . $ini . $dongle . $phonenumber . " " . $message . $ini;
     $output .= "Sending message to: $phonenumber<br>\n";
     exec($runcommand);
   }
 }
?>
    <html>
    <head>
      <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
      <title>Send SMS message from RasPBX</title>
    <link rel="stylesheet" href="style.css" type="text/css">

    </head>
    <body bgcolor="#f0d5b8" text="#ea7900" link="#262421" style="font-family: sans-serif">

    <h2 align="center" style="color: #2b2a28">Send SMS message</h2>
  
    <table border="5" cellspacing="0" cellpadding="1" width="600" bgcolor="#2b2a28" align="center">
      <tr>
        <td>
          <table border="0" cellspacing="0" cellpadding="3" width="100%" bgcolor="#ffffff" align="center">
            <tr bgcolor="#abcdef">
             <td><b><?php echo $output; ?></b></td>
            </tr>
  <tr><form action="index.php" method="post">
    <p><b>Phone number(s):</b> <br><font size="-2">(Format: NXXNXXXXXX. You can separate phone numbers with commas or newline.)</font></p>
    <textarea id="phonenumbers" name="phonenumbers" rows="3" cols="30"></textarea>
    <p><b>SMS message:</b> <br> <font size="-2">(Your message will be truncated to 160 characters.) </font></p>
    <textarea id="message" name="message" size="160" rows="4" cols="50"></textarea><br /><br />
    <button type="submit">Send SMS message</button><br /><br />
  </form></tr>
          </table>

        </dd>
      <p></td>

            </tr>

            <tr>
              <td bgcolor="#2b2a28" align=right><a href="index.php"><button>Send another SMS message</button></a></td>
            </tr>
          </table>

        </td>
      </tr>
    </table>
  
    <p>
  
    </body>
    </html>
