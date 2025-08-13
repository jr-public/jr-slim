<?php
namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $fromEmail,
        private readonly string $fromName
    ) {}

    public function send(string $to, string $subject, string $body, string $toName = ''): bool
    {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->SMTPAuth = !empty($this->username);
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->Port = $this->port;

            // Handle MailHog development setup
            if ($this->port === 1025) {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure = false;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to, $toName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
            return true;
            
        } catch (Exception $e) {
            throw new \RuntimeException("Email sending failed: {$mail->ErrorInfo}");
        }
    }

    public function sendPasswordResetEmail(string $email, string $username, string $token): bool
    {
        $subject = "Reset your password";
        $body = $this->buildPasswordResetTemplate($username, $token);
        
        return $this->send($email, $subject, $body, $username);
    }

    public function sendWelcomeEmail(string $email, string $username): bool
    {
        $subject = "Welcome to our platform!";
        $body = $this->buildWelcomeTemplate($username);
        
        return $this->send($email, $subject, $body, $username);
    }

    private function buildPasswordResetTemplate(string $username, string $token): string
    {
        // In a real app, you'd have a proper frontend URL
        $resetUrl = "http://localhost:80/reset-password.php?token={$token}";
        
        return "
            <h2>Password Reset Request</h2>
            <p>Hi {$username},</p>
            <p>You requested a password reset. Click the link below to reset your password:</p>
            <p><a href='{$resetUrl}' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Reset Password</a></p>
            <p>Or copy this link: {$resetUrl}</p>
            <p><small>This link will expire in 30 minutes. If you didn't request this, please ignore this email.</small></p>
        ";
    }

    private function buildWelcomeTemplate(string $username): string
    {
        return "
            <h2>Welcome {$username}!</h2>
            <p>Your account has been successfully created.</p>
            <p>You can now log in and start using our platform.</p>
            <p>If you have any questions, feel free to contact our support team.</p>
        ";
    }
}