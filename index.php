<?php
session_start();

include __DIR__ . '/db_connections/connection_pdo.php';

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// Show the session expired alert once
if (!empty($_SESSION['session_expired'])) {
    echo "<script>alert('Your session has expired. Please log in again.');</script>";
    unset($_SESSION['session_expired']);
}

require_once __DIR__ . '/classes/logging_functions.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAW Hockey Pool – Login</title>
    <link rel="stylesheet" href="css/drop_the_puck.css">
</head>

<body>

    <div class="logo-container">
        <h2>RAW Hockey Pool – Login Page</h2>
        <img src="rhcp.png" alt="RHCP Logo">
    </div>

    <form method="POST" action="">
        <div class="login-form">
            <div>
                <label for="gmlogin">Username</label>
                <input type="text" id="gmlogin" name="gmlogin" placeholder="GM Login Name" required />
            </div>
            <div>
                <label for="password_entered">Password</label>
                <input type="password" id="password_entered" name="password_entered" placeholder="Password" required />
            </div>

            <!-- 🐝 Honeypot field (invisible to users) -->
            <div class="honeypot">
                <label for="email_confirm">The Zone</label>
                <input type="text" id="email" name="email" autocomplete="off">
            </div>

            <div class="button-wrapper">
                <button type="submit" name="submit" class="buttonSet">Login</button>
            </div>
            <div class="forgot_pswd"> <a href="drop_the_puck/forgot_password.php">Forgot Password</a> </div>
        </div>
    </form>

    <?php
    if (isset($_POST['submit'])) {

        // Honeypot trap: if filled, likely a bot
        if (!empty($_POST['email'])) {
            log_honeypot_attempt($_POST['gmlogin'] ?? 'unknown'); // writes to honeypot.log
            log_login_attempt("Bot caught by honeypot", true, $_POST['gmlogin'] ?? 'unknown'); // writes to login_activity.log
            echo "<script>alert('Invalid login attempt.');</script>";
            exit();
        }

        $gm_id = trim($_POST['gmlogin']);
        $gm_password = $_POST['password_entered'];

        $query = $pdo->prepare("SELECT gm_pk, gm_name, user_password, login_disabled, must_change_password
                                FROM gms
                                WHERE LOWER(gm_name) = LOWER(:id_gm)");
        $query->execute(['id_gm' => $gm_id]);
        $gm_data = $query->fetch(PDO::FETCH_ASSOC);

        if (!$gm_data) {
            $_SESSION['login_attempts']++;
            log_login_attempt("Failed Login - Wrong Password or Unknown Login", true, $gm_id);
        } elseif (!empty($gm_data['login_disabled'])) {
            log_login_attempt("Login attempt on disabled account", true, $gm_id);
            echo "<script>
                    alert(\"Your account is locked.\\nYou will need to reset your password.\");
                    </script>";
            // window.location.href = 'index.php';
            exit();
        } else {
            if (password_verify($gm_password, $gm_data['user_password'])) {
                if (password_needs_rehash($gm_data['user_password'], PASSWORD_DEFAULT)) {
                    $new_hash = password_hash($gm_password, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE gms SET user_password = :new_hash WHERE gm_name = :gm_name");
                    $update->execute(['new_hash' => $new_hash, 'gm_name' => $gm_data['gm_name']]);
                }

                $_SESSION['login_attempts'] = 0;
                $_SESSION['gm_name'] = $gm_data['gm_name'];
                $_SESSION['login_time'] = time();

                $pdo->prepare("UPDATE gms SET failed_attempts = 0, last_failed_login = NULL WHERE gm_name = :gm_name")
                    ->execute(['gm_name' => $gm_data['gm_name']]);

                log_login_attempt("Login success", true, $gm_id);

                // Check if GM must change their password
                if ($gm_data['must_change_password'] === true || $gm_data['must_change_password'] === 't') {
                    $_SESSION['gm_name'] = $gm_data['gm_name'];
                    $_SESSION['gm_id']   = $gm_data['gm_pk'];
                    header("Location: drop_the_puck/change_password.php?msg=pwchange");
                    exit();
                }

                // Otherwise, normal login flow
                // header("Location: gm_listings/gm_info.php?gm=" . urlencode($gm_data['gm_name']));
                header("Location: player_scoring/leaderboard.php");

                exit();
            } else {
                $_SESSION['login_attempts']++;
                log_login_attempt("Failed Login - Wrong Password", true, $gm_id);

                $updateFail = $pdo->prepare("UPDATE gms SET failed_attempts = failed_attempts + 1, last_failed_login = NOW() WHERE gm_name = :gm_name");
                $updateFail->execute(['gm_name' => $gm_data['gm_name']]);

                $checkFails = $pdo->prepare("SELECT failed_attempts FROM gms WHERE gm_name = :gm_name");
                $checkFails->execute(['gm_name' => $gm_data['gm_name']]);
                $failData = $checkFails->fetch(PDO::FETCH_ASSOC);

                if ($failData && $failData['failed_attempts'] >= 5) {
                    $lockUser = $pdo->prepare("UPDATE gms SET login_disabled = 'x' WHERE gm_name = :gm_name");
                    $lockUser->execute(['gm_name' => $gm_data['gm_name']]);

                    echo "<script>
                            alert('Your account has been locked due to multiple failed login attempts.\\nYou will need to reset your password.');
                            </script>";
                    // window.location.href = 'index.php';
                    exit();
                }
            }
        }

        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_attempts'] = 0;
            echo "<script>
                    alert('Too many failed login attempts.\\nYour account has been locked.\\n\\nTo re-enable your account, reset your password.');
                    </script>";
            // window.location.href = 'index.php';
            exit();
        }
    }
?>

</body>

</html>