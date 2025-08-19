<?php
// auth.php
session_start();

// --- CONFIG ---
// Define valid users here (username => bcrypt hash)
$USERS = [
    "admin" => '$2y$10$D0FeVomZHGimeJ6cNaQLA.jOT1bfCQB6L.KRXLPNhY3B/rSQmKC.a', 
    // hash for "ChangeYourPassword"
    // IMPORTANT: you can change default (or forgotten) password by typing this command to terminal:
    // php -r "echo password_hash('ChangeYourPassword', PASSWORD_DEFAULT) . PHP_EOL;"

];
// --------------

// Helper: check login state
function check_login() {
    if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        show_login_form();
        exit;
    }
}

// Show login modal if not logged in
function show_login_form($error = "") {
    ?>
    <!DOCTYPE html>
    <html lang="sl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login to RasPBX SMS system</title>
        <style>
            body {
                margin: 0;
                font-family: sans-serif;
                background: #f0d5b8; /* same background as your app */
            }

            .container {
                max-width: 400px;
                width: 90%; /* shrink on small screens */
                margin: 60px auto;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 6px rgba(0,0,0,0.15);
                background: #fff;
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

            .content {
                background: #fff;
                padding: 20px;
            }

            .content label {
                display: block;
                margin-bottom: 6px;
                font-weight: bold;
            }

            .content input[type="text"],
            .content input[type="password"] {
                width: 100%;
                padding: 10px;
                margin-bottom: 14px;
                border: 1px solid #ccc;
                border-radius: 5px;
                box-sizing: border-box;
                font-size: 16px;
            }

            .content button {
                width: 100%;
                padding: 12px;
                background: #000;
                color: #fff;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                transition: opacity 0.2s ease;
            }

            .content button:hover {
                opacity: 0.85;
            }

            .error {
                color: #c00;
                margin-bottom: 12px;
            }

            /* Responsive tweaks */
            @media (max-width: 480px) {
                .container {
                    margin: 20px auto;
                }
                .header h2 {
                    font-size: 18px;
                }
                .content {
                    padding: 16px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Login to RasPBX SMS system</h2>
            </div>
            <div class="content">
                <?php if ($error) { echo "<div class='error'>".htmlspecialchars($error)."</div>"; } ?>
                <form method="post">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="username">

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">

                    <button type="submit">Login</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Process login request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['username'], $_POST['password'])) {
    global $USERS;
    $user = $_POST['username'];
    $pass = $_POST['password'];

    if (isset($USERS[$user]) && password_verify($pass, $USERS[$user])) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $user;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        show_login_form("Wrong username or password.");
        exit;
    }
}

