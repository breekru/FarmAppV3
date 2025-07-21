<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../mailer/PHPMailer.php';
require_once __DIR__ . '/../mailer/SMTP.php';
require_once __DIR__ . '/../mailer/Exception.php';

function sendResetEmail($toEmail, $resetLink) {
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = 'mail.blkfarms.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no_reply@blkfarms.com';
        $mail->Password = 'r{MJkq4zt$kM0~7#'; // <-- INSERT YOUR PASSWORD HERE
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        // Sender & recipient
        $mail->setFrom('no_reply@blkfarms.com', 'FarmApp');
        $mail->addAddress($toEmail);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'FarmApp Password Reset';
        $mail->Body = "
            <p>Hello,</p>
            <p>You requested a password reset. Click the link below to reset your password:</p>
            <p><a href='$resetLink'>$resetLink</a></p>
            <p>This link will expire in 1 hour.</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    }
}
