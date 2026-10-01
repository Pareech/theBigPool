<?php
// === DB Connection and Logging ===
include __DIR__ . '/../db_connections/connection_pdo.php';
require_once __DIR__ . '/../classes/logging_functions.php';

// === PHPMailer includes (SMTP) ===
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

// === Determine environment for reset link ===
$host = $_SERVER['HTTP_HOST'];
$scriptPath = '/drop_the_puck/reset_password.php';

// Default to DEV
$baseUrl = "http://vader.local/raw_hockeypool/current_season_dev" . $scriptPath;

if (stripos($host, 'rawpool.stleon.ca') !== false) {
    $baseUrl = "https://rawpool.stleon.ca/current_season" . $scriptPath;
    if (strpos($_SERVER['REQUEST_URI'], '/current_season_pre_prod/') !== false) {
        $baseUrl = "https://rawpool.stleon.ca/current_season_pre_prod" . $scriptPath;
    }
} else {
    if (strpos($_SERVER['REQUEST_URI'], '/current_season_pre_prod/') !== false) {
        $baseUrl = "http://vader.local/raw_hockeypool/current_season_pre_prod" . $scriptPath;
    } elseif (strpos($_SERVER['REQUEST_URI'], '/current_season/') !== false) {
        $baseUrl = "http://vader.local/raw_hockeypool/current_season" . $scriptPath;
    }
}

// === Handle form submission ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gm_name'])) {
    $gm_name = trim($_POST['gm_name']);

    // Look for GM in DB
    $stmt = $pdo->prepare("SELECT gm_pk, gm_name, email FROM gms WHERE gm_name ILIkE :gm_name");
    $stmt->execute(['gm_name' => $gm_name]);
    $gm = $stmt->fetch();

    if ($gm) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $stmt = $pdo->prepare("UPDATE gms
                               SET reset_token = :token, reset_token_expiry = :expiry
                               WHERE gm_pk = :gm_pk");
        $stmt->execute([
            'token' => $token,
            'expiry' => $expiry,
            'gm_pk' => $gm['gm_pk']
        ]);

        log_password_reset("Password reset token generated", true, $gm['gm_name']);

        // --- Send email via PHPMailer ---
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
            $mail->Subject = 'RAW Hockey Pool Password Reset';
            $mail->Body = "
                Hello {$gm['gm_name']},<br><br>
                You requested a password reset. Please click the link below to reset your password:<br><br>
                <a href=\"{$baseUrl}?token={$token}\">{$baseUrl}?token={$token}</a><br><br>
                If you cannot click the link, please copy and paste it into your browser.<br><br>
                This link will expire in 1 hour.<br><br>
                If you did not request this, you can ignore this email.<br><br><br><br>
                <img src=\"cid:rawlogo\" alt=\"RAW Pool Logo\" style=\"width:120px;height:auto;\"><br>
                <em>This is a non-monitored email address. If needed, reach out to Ian directly for any issues.</em>
                <br><br>
            ";

            // Add the embedded image AFTER $mail is created
            $mail->addEmbeddedImage(__DIR__ . '/../misc_files/raw_logo.png', 'rawlogo');
            $mail->send();
        } catch (Exception $e) {
            error_log("Password reset email could not be sent. PHPMailer Error: {$mail->ErrorInfo}");
        }
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        log_password_reset("Password reset attempt for non-existent GM ($gm_name - $ip)", false);
    }

    echo
    "<script>
        alert('If this account exists, a password reset email has been sent.\\n\\nCheck your inbox (and spam folder) for the reset link.');
        window.location.href='../';
    </script>";
    exit();
} else {
    // === Show the form ===
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Forgot Password</title>
        <link rel="stylesheet" href="../css/change_password.css">
    </head>

    <body>
        <div class="container">
            <h2>Request Password Reset</h2>
            <form method="POST">
                <div class="input-container">
                    <input type="text" name="gm_name" id="gm_name" placeholder="Enter your GM Name" required>
                </div>
                <div class="input-container">
                    <input type="submit" value="Request Password Reset">
                </div>
            </form>
            <div class="note">
                After submitting, check your email for a reset link.<br><br>
                If you cannot click the link, copy and paste it into your browser.
                <a href="../" class="back-link">Back to Login Page</a>
            </div>
        </div>
    </body>

    </html>
<?php
}
?>