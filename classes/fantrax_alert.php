<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

function send_stale_client_alert($script_name, $environment)
{
    // Flag file is unique per script so skater and goalie alerts are tracked separately
    $flag_file = sys_get_temp_dir() . '/stale_client_' . basename($script_name, '.php') . '.flag';

    // If flag exists, alert already sent — do not send again
    if (file_exists($flag_file)) {
        return;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'monitoring.st.leon@gmail.com';
        $mail->Password   = 'ardploencgvanzug';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('no-reply@rawpool.stleon.ca', 'RAW Hockey Pool');
        $mail->addAddress('pareech@gmail.com');

        $mail->isHTML(true);
        $mail->Subject = "WARNING: Fantrax Version Mismatch - {$script_name} ({$environment})";
        $mail->Body    = "
            <p>The Fantrax API is returning a <strong>STALE_CLIENT</strong> error in <strong>{$script_name}</strong> on the <strong>{$environment}</strong> environment.</p>
            <p>Stats are <strong>not updating</strong>. You need to update the version number in <strong>{$script_name}</strong>.</p>
            <p><strong>To fix:</strong></p>
            <ol>
                <li>Open Chrome</li>
                <li>Click <strong>View</strong> &rarr; <strong>Developer Tools</strong></li>
                <li>Click the <strong>Network</strong> tab on the right side</li>
                <li>Go to <a href=\"https://www.fantrax.com/news/nhl/stats/players;scKindId=3010;seasonId=31l\">https://www.fantrax.com/news/nhl/stats/players;scKindId=3010;seasonId=31l</a></li>
                <li>In the filter area type <strong>fxpa</strong></li>
                <li>Select the <strong>req</strong> entry with the largest size</li>
                <li>Click on <strong>Payload</strong></li>
                <li>Find the value for <code>v:</code> and update the <code>&quot;v&quot;</code> field in the script payload of <strong>{$script_name}</strong></li>
            </ol>
            <br>
            <img src=\"cid:rawlogo\" alt=\"RAW Pool Logo\" style=\"width:120px;height:auto;\">
            <br>
            <p><em>This is a non-monitored email address.</em><br>no-reply@rawpool.stleon.ca</p>
        ";

        $mail->addEmbeddedImage(__DIR__ . '/../misc_files/raw_logo.png', 'rawlogo');
        $mail->send();

        // Write flag file so the alert is not sent again until it is fixed
        file_put_contents($flag_file, date('Y-m-d H:i:s'));

    } catch (Exception $e) {
        // Silent fail — logging will still catch it
    }
}

function clear_stale_client_flag($script_name)
{
    $flag_file = sys_get_temp_dir() . '/stale_client_' . basename($script_name, '.php') . '.flag';

    if (file_exists($flag_file)) {
        unlink($flag_file);
    }
}
