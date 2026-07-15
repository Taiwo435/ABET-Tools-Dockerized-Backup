<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/vendor/autoload.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

/**
 * Sends the account verification email.
 * Falls back to logging the email to a local file when no real
 * MAILER_DSN is configured, so the flow is testable locally
 * without needing real SMTP credentials.
 */
function send_verification_email(string $toEmail, string $verifyUrl): void {
    $dsn = $_ENV['MAILER_DSN'] ?? 'null://null';

    $email = (new Email())
        ->from('no-reply@asu.edu')
        ->to($toEmail)
        ->subject('Verify your ABET Tools account')
        ->text("Click the link below to verify your email and activate your account:\n\n{$verifyUrl}")
        ->html("<p>Click the link below to verify your email and activate your account:</p><p><a href=\"{$verifyUrl}\">{$verifyUrl}</a></p>");

    if ($dsn === 'null://null' || $dsn === '') {
        $logDir = getenv('ABET_PRIVATE_DIR') . '/var/log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logLine = sprintf(
            "[%s] Verification email for %s: %s\n",
            date('c'),
            $toEmail,
            $verifyUrl
        );
        file_put_contents($logDir . '/mail.log', $logLine, FILE_APPEND);
        return;
    }

    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);
    $mailer->send($email);
}
