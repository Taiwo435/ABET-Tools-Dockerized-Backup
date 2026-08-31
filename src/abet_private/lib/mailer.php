<?php
// DO NOT IMPORT THIS DIRECTLY!!!!
// Use it as a SERVICE!!! 
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/vendor/autoload.php';
// Permissions is declared inside User.php, so it isn't found by PSR-4
// autoloading on its own — require it explicitly, same as auth.php.
require_once getenv('ABET_PRIVATE_DIR') . '/src/Entity/User.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

/**
 * Sends the account verification email containing a 6-digit code.
 * Falls back to logging the email to a local file when no real
 * MAILER_DSN is configured, so the flow is testable locally
 * without needing real SMTP credentials.
 */
function send_verification_email(string $toEmail, string $code): void {
    // $_ENV isn't populated from real container env vars here (php.ini's
    // variables_order lacks "E"), so $_ENV['MAILER_DSN'] is always unset
    // even when the container genuinely has MAILER_DSN set — getenv() is
    // the one that actually sees it.
    $dsn = getenv('MAILER_DSN') ?: ($_ENV['MAILER_DSN'] ?? 'null://null');

    $email = (new Email())
        ->from('no-reply@asu.edu')
        ->to($toEmail)
        ->subject('Your ABET Tools verification code')
        ->text("Your verification code is: {$code}\n\nEnter this code on the verification page to activate your account. This code expires in 15 minutes.")
        ->html("<p>Your verification code is:</p><p style=\"font-size: 28px; font-weight: bold; letter-spacing: 4px;\">{$code}</p><p>Enter this code on the verification page to activate your account. This code expires in 15 minutes.</p>");

    if ($dsn === 'null://null' || $dsn === '') {
        $logDir = getenv('ABET_PRIVATE_DIR') . '/var/log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logLine = sprintf(
            "[%s] Verification code for %s: %s\n",
            date('c'),
            $toEmail,
            $code
        );
        file_put_contents($logDir . '/mail.log', $logLine, FILE_APPEND);
        return;
    }

    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);
    $mailer->send($email);
}

/**
 * Sends the password reset email containing the reset link.
 * Uses the same null-transport-logs-to-file fallback as
 * send_verification_email(). Matches the signature that
 * reset_password_lib.php's rp_send_password_reset_email() expects.
 *
 */
function send_password_reset_email(string $toEmail, string $subject, string $htmlBody, string $textBody): bool {
    $dsn = getenv('MAILER_DSN') ?: ($_ENV['MAILER_DSN'] ?? 'null://null');

    $email = (new \Symfony\Component\Mime\Email())
        ->from('no-reply@asu.edu')
        ->to($toEmail)
        ->subject($subject)
        ->text($textBody)
        ->html($htmlBody);

    if ($dsn === 'null://null' || $dsn === '') {
        $logDir = getenv('ABET_PRIVATE_DIR') . '/var/log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logLine = sprintf(
            "[%s] Password reset email for %s (see reset_password_lib's own dev_reset_link fallback for the actual link)\n",
            date('c'),
            $toEmail
        );
        file_put_contents($logDir . '/mail.log', $logLine, FILE_APPEND);
        return false;
    }

    $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
    $mailer = new \Symfony\Component\Mailer\Mailer($transport);
    $mailer->send($email);
    return true;
}

/**
 * Sends a confirmation email after an admin approves a user's permission request.
 * Uses the same null-transport-logs-to-file fallback as send_verification_email().
 *
 * @param list<string> $grantedPermissions Human-readable names of the permissions granted
 */
function send_permission_approved_email(string $toEmail, array $grantedPermissions): void {
    // $_ENV isn't populated from real container env vars here (php.ini's
    // variables_order lacks "E"), so $_ENV['MAILER_DSN'] is always unset
    // even when the container genuinely has MAILER_DSN set — getenv() is
    // the one that actually sees it.
    $dsn = getenv('MAILER_DSN') ?: ($_ENV['MAILER_DSN'] ?? 'null://null');
    $permissionsList = implode(', ', array_map(
        static function (string $name): string {
            foreach (\App\Entity\Permissions::cases() as $permission) {
                if ($permission->name === $name) {
                    return $permission->label();
                }
            }
            return $name;
        },
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
