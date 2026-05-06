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
<div style='font-family:Inter,Arial,sans-serif;max-width:600px;margin:auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);'>

    <!-- Header -->
    <div style='background:linear-gradient(135deg,#1e40af,#0ea5e9);padding:36px 32px 28px;text-align:center;'>
        <div style='width:52px;height:52px;background:rgba(255,255,255,0.15);border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;'>
            <span style='font-size:26px;'>🔔</span>
        </div>
        <h1 style='color:#ffffff;margin:0;font-size:22px;font-weight:700;letter-spacing:-0.3px;'>Feedback Access Approved</h1>
        <p style='color:rgba(255,255,255,0.7);margin:6px 0 0;font-size:13px;'>Northern Bukidnon State College</p>
    </div>

    <!-- Body -->
    <div style='padding:36px 32px 28px;'>

        <p style='font-size:16px;font-weight:600;color:#111827;margin:0 0 6px;'>Hello, $toName!</p>
        <p style='font-size:14px;color:#6b7280;margin:0 0 28px;line-height:1.6;'>
            We're writing to inform you that your anonymous feedback has been officially reviewed and addressed by the administration.
        </p>

        <!-- Status Card -->
        <div style='background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:12px;padding:20px 24px;margin-bottom:28px;'>
            <p style='margin:0 0 12px;font-size:12px;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:0.08em;'>Review Status</p>
            <table style='width:100%;border-collapse:collapse;'>
                <tr>
                    <td style='font-size:13px;color:#374151;padding:5px 0;width:40%;'>📋 Feedback Reference</td>
                    <td style='font-size:13px;color:#111827;font-weight:600;padding:5px 0;'>#$feedbackId</td>
                </tr>
                <tr>
                    <td style='font-size:13px;color:#374151;padding:5px 0;'>✅ Status</td>
                    <td style='font-size:13px;color:#16a34a;font-weight:700;padding:5px 0;'>Approved</td>
                </tr>
                <tr>
                    <td style='font-size:13px;color:#374151;padding:5px 0;'>👤 Recipient</td>
                    <td style='font-size:13px;color:#111827;font-weight:600;padding:5px 0;'>$toName</td>
                </tr>
            </table>
        </div>

        <!-- Message -->
        <div style='background:#f8fafc;border-left:4px solid #0ea5e9;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:28px;'>
            <p style='margin:0;font-size:13.5px;color:#374151;line-height:1.7;'>
                A designated manager has been granted authorized access to review your submitted feedback. 
                This process is conducted in strict accordance with the college's data privacy policy to ensure 
                your concern is properly addressed while maintaining confidentiality.
            </p>
        </div>

        <p style='font-size:13px;color:#6b7280;margin:0 0 6px;line-height:1.6;'>
            You may log in to the <strong>NBSC Anonymous Feedback System</strong> to monitor the status of your feedback.
        </p>
        <p style='font-size:13px;color:#6b7280;margin:0;line-height:1.6;'>
            If you did not submit any feedback or believe this message was sent in error, please contact your administrator immediately.
        </p>

    </div>

    <!-- Divider -->
    <div style='height:1px;background:#e5e7eb;margin:0 32px;'></div>

    <!-- Footer -->
    <div style='padding:20px 32px;text-align:center;background:#f9fafb;'>
        <p style='margin:0 0 4px;font-size:12px;font-weight:600;color:#374151;'>NBSC Anonymous Feedback System</p>
        <p style='margin:0;font-size:11.5px;color:#9ca3af;'>
            This is an automated message. Please do not reply to this email.
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