<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/vendor/autoload.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

/**
 * Builds the absolute verify-email link from the configured APP_URL, rather
 * than trusting the request's Host header (which is attacker-controlled and
 * can be used to poison links sent in emails).
 */
function build_verify_url(string $token): string {
    $appUrl = rtrim((string)(getenv('APP_URL') ?: 'http://localhost:8000'), '/');
    return $appUrl . '/verify-email?token=' . $token;
}

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

/**
 * Sends a confirmation email after an admin approves a user's permission request.
 * Uses the same null-transport-logs-to-file fallback as send_verification_email().
 *
 * @param list<string> $grantedPermissions Human-readable names of the permissions granted
 */
function send_permission_approved_email(string $toEmail, array $grantedPermissions): void {
    $dsn = $_ENV['MAILER_DSN'] ?? 'null://null';
    $permissionsList = implode(', ', array_map(
        static fn (string $name): string => str_replace('_', ' ', $name),
        $grantedPermissions
    ));

    $email = (new Email())
        ->from('no-reply@asu.edu')
        ->to($toEmail)
        ->subject('Your ABET Tools access request was approved')
        ->text("Your requested access has been approved. You now have: {$permissionsList}")
        ->html("<p>Your requested access has been approved. You now have:</p><p>{$permissionsList}</p>");

    if ($dsn === 'null://null' || $dsn === '') {
        $logDir = getenv('ABET_PRIVATE_DIR') . '/var/log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logLine = sprintf(
            "[%s] Permission approval email for %s: %s\n",
            date('c'),
            $toEmail,
            $permissionsList
        );
        file_put_contents($logDir . '/mail.log', $logLine, FILE_APPEND);
        return;
    }

    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);
    $mailer->send($email);
}
