<?php
include __DIR__ . '/../db_connections/connection_pdo.php';
require_once __DIR__ . '/../classes/logging_functions.php';

// Add PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

// Map known hosts to environment-specific base URLs
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$loginUrl = '';
switch (true) {
    case str_contains($host, 'vader.local') && str_contains($_SERVER['REQUEST_URI'], 'current_season_dev'):
        $loginUrl = "$scheme://$host/raw_hockeypool/current_season_dev";
        break;
    case str_contains($host, 'vader.local') && str_contains($_SERVER['REQUEST_URI'], 'current_season_pre_prod'):
        $loginUrl = "$scheme://$host/raw_hockeypool/current_season_pre_prod/";
        break;
    case str_contains($host, 'vader.local') && str_contains($_SERVER['REQUEST_URI'], 'current_season'):
        $loginUrl = "$scheme://$host/raw_hockeypool/current_season/";
        break;
    case str_contains($host, 'rawpool.stleon.ca') && str_contains($_SERVER['REQUEST_URI'], 'current_season_pre_prod'):
        $loginUrl = "https://$host/current_season_pre_prod/";
        break;
    case str_contains($host, 'rawpool.stleon.ca'):
        $loginUrl = "https://$host/current_season/";
        break;
    default:
        // $loginUrl = "$scheme://$host/index.php"; // fallback
        $loginUrl = "$scheme://$host/"; // fallback

}

$token = $_GET['token'] ?? null;
$show_form = false;

if (!$token) {
    echo "<script>alert('No reset token provided.');window.location.href='$loginUrl';</script>";
    exit;
}

// Look up GM with this token and get expiry
$stmt = $pdo->prepare("SELECT gm_pk, gm_name, reset_token_expiry, email 
                       FROM gms 
                       WHERE reset_token = :token
                       LIMIT 1");
$stmt->execute(['token' => $token]);
$gm = $stmt->fetch();

if (!$gm) {
    // Log invalid token attempt
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    log_password_reset("Password reset attempt for invalid token ($token - $ip)", false);

    // Show generic message and redirect
    echo
    "<script>
        alert('Invalid or expired reset link.');
        window.location.href='$loginUrl';
    </script>";
    exit;
}

if (strtotime($gm['reset_token_expiry']) <= time()) {
    echo
    "<script>
        alert('Invalid or expired reset link.');
        window.location.href='$loginUrl';
    </script>";
    exit;
}

// Token is valid → proceed to show form and allow password reset
$show_form = true;

if ($show_form) {
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'], $_POST['confirm_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($new_password) || empty($confirm_password)) {
            $message = "Both fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $message = "Passwords do not match.";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{8,}$/', $new_password)) {
            $message = "Password must be at least 8 characters long and include uppercase, lowercase, a number, and a special character.";
        } else {
            // Check new password is not the same as current password
            $stmtCheck = $pdo->prepare("SELECT user_password FROM gms WHERE gm_pk = :gm_pk");
            $stmtCheck->execute(['gm_pk' => $gm['gm_pk']]);
            $currentHash = $stmtCheck->fetchColumn();

            if ($currentHash && password_verify($new_password, $currentHash)) {
                $message = "Your new password cannot be the same as your current password.";
            } else {
                // Update password, clear token
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare("UPDATE gms
                                             SET user_password = :password,
                                                 reset_token = NULL,
                                                 reset_token_expiry = NULL,
                                                 login_disabled = NULL,
                                                 failed_attempts = 0,
                                                 must_change_password = FALSE,
                                                 last_failed_login = NULL
                                             WHERE gm_pk = :gm_pk");
                $stmtUpdate->execute([
                    'password' => $hashedPassword,
                    'gm_pk' => $gm['gm_pk']
                ]);

                // Log successful password reset
                log_password_reset("Password reset successfully for GM", true, $gm['gm_name']);

                // --- PHPMailer email integration start ---
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'monitoring.st.leon@gmail.com';
                    $mail->Password   = 'ardploencgvanzug';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = 587;

                    $mail->setFrom('monitoring.st.leon@gmail.com', 'RAW Hockey Pool');
                    $mail->addAddress($gm['email'], $gm['gm_name']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Successful';
                    $mail->Body = "
                        Hello {$gm['gm_name']},<br><br>
                        Your password has been successfully reset.<br>
                        You can now <a href=\"{$loginUrl}\">log in</a> with your new password.<br><br>
                        If you did not request this reset, please contact the administrator.<br><br><br><br>
                        <img src=\"cid:rawlogo\" alt=\"RAW Pool Logo\" style=\"width:120px;height:auto;\"><br>
                        <em>This is a non-monitored email address. If needed, reach out to Ian directly for any issues.</em>
                    ";

                    // Add the embedded image AFTER $mail is created
                    $mail->addEmbeddedImage(__DIR__ . '/../misc_files/raw_logo.png', 'rawlogo');
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Password reset email could not be sent. PHPMailer Error: {$mail->ErrorInfo}");
                }
                // --- PHPMailer email integration end ---

                // Confirmation popup and redirect to login
                echo
                "<script>
                    alert('Password has been successfully updated. You will be redirected to the login page.');
                    window.location.href='$loginUrl';
                </script>";
                exit();
            }
        }
    }
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Reset Password</title>
        <link rel="stylesheet" href="../css/change_password.css">
        <style>
            /* Add any inline fixes for button or eye icon sizing if needed */
        </style>
    </head>

    <body>
        <div class="container">
            <h2>Reset Your Password</h2>

            <?php if (!empty($message)): ?>
                <div class="error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-container">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <span class="toggle-password" onclick="togglePassword('new_password')">👁️</span>
                </div>
                <div class="input-container">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <span class="toggle-password" onclick="togglePassword('confirm_password')">👁️</span>
                </div>
                <input type="submit" Reset Password>
            </form>

            <a href="<?= $loginUrl ?>" class="back-link">Back to Login Page</a>
        </div>

        <script>
            function togglePassword(fieldId) {
                const input = document.getElementById(fieldId);
                input.type = input.type === 'password' ? 'text' : 'password';
            }
        </script>
    </body>

    </html>

<?php
} // end $show_form
?>