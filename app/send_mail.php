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
        $mail->Subject = 'Your Feedback Access Request Has Been Approved';
        $mail->Body    = "
            <p>Hi <strong>$toName</strong>,</p>
            <p>Your request to access <strong>Feedback #$feedbackId</strong> has been <strong style='color:green;'>approved</strong> by the admin.</p>
            " . ($adminNotes ? "<p><strong>Admin Note:</strong> $adminNotes</p>" : "") . "
            <p>You may now log in to view the decrypted feedback.</p>
            <br>
            <small style='color:#888;'>NBSC Anonymous Feedback System</small>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}