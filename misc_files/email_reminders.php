<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

// === DB Connection ===
include __DIR__ . '/../db_connections/connection_pdo.php';

// === PHPMailer includes (SMTP) ===
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';
include __DIR__ . '/../classes/logging_functions.php';

// === Date Setup ===
$tomorrow = new DateTime('tomorrow');
$tomorrowStr = $tomorrow->format('Y-m-d');

// === Deadline labels ===
$labels = [
    'waiver1'         => 'Waiver 1',
    'waiver2'         => 'Waiver 2',
    'waiver3'         => 'Waiver 3',
    'protection_list' => 'Protection List / Trade',
    'franchise_due'   => 'Franchise Player'
];

// === Query deadlines ===
$stmt = $pdo->query("SELECT waiver1, waiver2, waiver3, protection_list, franchise_due FROM base_numbers");
$deadlines = $stmt->fetch(PDO::FETCH_ASSOC);

// === Query emails (TEST mode) ===
$emails = $pdo->query("SELECT email FROM gms WHERE email IN ('pareech@gmail.com')")->fetchAll(PDO::FETCH_COLUMN);

// === Query emails ===
// $emails = $pdo->query("SELECT email FROM gms")->fetchAll(PDO::FETCH_COLUMN);

// === Loop deadlines ===
foreach ($deadlines as $column => $date) {
    if ($date === $tomorrowStr) {
        $label = $labels[$column] ?? ucfirst(str_replace('_', ' ', $column));
        $dateObj = new DateTime($date);
        $formattedDate = $dateObj->format('l, F jS, Y'); // e.g. Monday, November 7th, 2025
        $subjectLine_date = $dateObj->format('M. jS, Y'); // e.g. Nov. 7, 2025

        // $subject = "$label Deadline Reminder -$label_date";
        $subject = "Reminder: $label Submission Deadline is Tomorrow - $subjectLine_date";

        $body = "
            <p>$label submissions are due no later than <strong>23h59 on $formattedDate</strong>.</p>
            <p><a href=\"https://rawpool.stleon.ca\">RAW Hockey Pool</a></p><br><br>
            <img src=\"cid:rawlogo\" alt=\"RAW Pool Logo\" style=\"width:120px;height:auto;\">
            <br>
            <p><em>This is a non-monitored email address.</em><br>no-reply@rawpool.stleon.ca</p>
        ";

        // === Send emails ===
        foreach ($emails as $to) {
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
                $mail->addAddress($to);

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $body;

                // Add the embedded image AFTER $mail is created
                $mail->addEmbeddedImage(__DIR__ . '/raw_logo.png', 'rawlogo');

                $mail->send();
                log_deadline_email("Success: Email sent to $to for $label deadline");
            } catch (Exception $e) {
                log_deadline_email("ERROR sending to $to: {$mail->ErrorInfo}");
            }
        }
    }
}
