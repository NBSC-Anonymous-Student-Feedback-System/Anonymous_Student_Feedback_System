<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendApprovalEmail($toEmail, $toName, $feedbackId, $adminNotes = '') {
    $config = require __DIR__ . '/../config/mail.php';
    $mail   = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = 'tls';
        $mail->Port       = $config['port'];

        $mail->setFrom($config['from'], $config['from_name']);
$mail->addAddress($toEmail, $toName);

$mail->isHTML(true);
$mail->Subject = 'Feedback Access Approved — NBSC Anonymous Feedback System';
$mail->Body    = "
<div style='font-family:Inter,sans-serif;max-width:600px;margin:auto;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;'>

    <div style='background:linear-gradient(135deg,#1e40af,#0ea5e9);padding:28px 32px;'>
        <h2 style='color:#fff;margin:0;font-size:20px;'>NBSC Anonymous Feedback System</h2>
        <p style='color:rgba(255,255,255,0.75);margin:4px 0 0;font-size:13px;'>North Bukidnon State College</p>
    </div>

    <div style='padding:32px;background:#ffffff;'>
        <p style='font-size:15px;color:#111827;margin:0 0 8px;'>Hello, <strong>$toName</strong>!</p>
        <p style='font-size:14px;color:#374151;margin:0 0 20px;'>
            Your feedback has been addressed. A manager has been granted access to review 
            <strong>Feedback #$feedbackId</strong> submitted by you, and this access has been 
            officially <strong style='color:#16a34a;'>approved</strong> by the administrator.
        </p>

        <div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px 20px;margin-bottom:20px;'>
            <p style='margin:0;font-size:13px;color:#166534;'>
                ✅ <strong>Status:</strong> Approved<br>
                📋 <strong>Feedback ID:</strong> #$feedbackId<br>
                👤 <strong>Addressed to:</strong> $toName
            </p>
        </div>

        " . ($adminNotes ? "
        <div style='background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px 20px;margin-bottom:20px;'>
            <p style='margin:0;font-size:13px;color:#1e40af;'>
                📝 <strong>Admin Note:</strong> $adminNotes
            </p>
        </div>" : "") . "

        <p style='font-size:13.5px;color:#374151;margin:0 0 8px;'>
            You may log in to the system to view further updates regarding your feedback.
        </p>
        <p style='font-size:13px;color:#6b7280;margin:0;'>
            If you believe this was sent in error or have concerns, please contact your administrator.
        </p>
    </div>

    <div style='background:#f9fafb;border-top:1px solid #e5e7eb;padding:16px 32px;text-align:center;'>
        <p style='margin:0;font-size:11.5px;color:#9ca3af;'>
            This is an automated message from the NBSC Anonymous Feedback System. Please do not reply to this email.
        </p>
    </div>

</div>
";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}