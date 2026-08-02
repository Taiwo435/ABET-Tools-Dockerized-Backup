<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/clerk.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';

start_session();

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$email = strtolower(trim($_GET['email'] ?? ''));

$publishableKey = clerk_publishable_key();
$frontendApi = clerk_frontend_api_domain();

clerk_browser_csp();
header('Cross-Origin-Opener-Policy: same-origin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ASU ABET Tools | Verify Email</title>
  <link rel="icon" type="image/svg" href="/assets/img/favicon.svg" />
  <link href="/assets/css/auth.css" rel="stylesheet">

  <script defer crossorigin="anonymous"
    src="https://<?= e($frontendApi) ?>/npm/@clerk/ui@1/dist/ui.browser.js"
    type="text/javascript"></script>

  <script defer crossorigin="anonymous"
    data-clerk-publishable-key="<?= e($publishableKey) ?>"
    src="https://<?= e($frontendApi) ?>/npm/@clerk/clerk-js@6/dist/clerk.browser.js"
    type="text/javascript"></script>
</head>
<body>

  <div class="register-container">

    <div class="brand-section">
      <div class="brand-content">
        <h2>Arizona State University</h2>
        <p>Enterprise Technology &amp; ABET Accreditation Tools.</p>
        <div style="width: 60px; height: 4px; background: var(--asu-gold); margin-top: 20px;"></div>
      </div>
    </div>

    <div class="form-section">
      <div class="form-header">
        <h1>Verify Your Email</h1>
        <p>
          <?php if ($email !== ''): ?>
            Verify <strong><?= e($email) ?></strong> with a one-time code to confirm you own this address.
          <?php else: ?>
            Verify the same email address you registered with using a one-time code.
          <?php endif; ?>
        </p>
      </div>

      <button id="clerk-verify-btn" class="btn-submit" type="button" disabled>
        Loading verification…
      </button>

      <p id="clerk-status" class="msg" role="status" aria-live="polite" hidden></p>
    </div>

  </div>

<script>
'use strict';

window.addEventListener('load', async function () {
  const button = document.getElementById('clerk-verify-btn');
  const status = document.getElementById('clerk-status');

  function showStatus(message, isError) {
    status.textContent = message;
    status.className = 'msg ' + (isError ? 'error' : 'success');
    status.hidden = false;
  }

  async function exchangeSession(session) {
    if (!session || session.status !== 'active') {
      return;
    }

    button.disabled = true;
    button.textContent = 'Verifying…';

    try {
      const token = await session.getToken({ skipCache: true });

      if (!token) {
        throw new Error('Sign-in did not return a token.');
      }

      const response = await fetch('/auth/clerk_verify_email.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token })
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Verification failed.');
      }

      try { await window.Clerk.signOut(); } catch (_) {
        // Best effort only — the app session was already created server-side.
      }

      showStatus('Email verified! Redirecting…', false);
      window.setTimeout(function () {
        window.location.replace(data.redirect || '/home');
      }, 600);
    } catch (exception) {
      console.error('Clerk verification failed:', exception);
      showStatus(
        exception instanceof Error ? exception.message : 'Verification could not be completed.',
        true
      );
      button.disabled = false;
      button.textContent = 'Try Again';
    }
  }

  try {
    if (!window.Clerk || typeof window.__internal_ClerkUICtor !== 'function') {
      throw new Error('Clerk did not load.');
    }

    await window.Clerk.load({ ui: { ClerkUI: window.__internal_ClerkUICtor } });

    window.Clerk.addListener(function ({ session }) {
      if (session && session.status === 'active') {
        void exchangeSession(session);
      }
    });

    // Already signed in to Clerk in this browser (e.g. just verified a
    // moment ago) — verify immediately instead of making them go through
    // email verification again.
    if (window.Clerk.session && window.Clerk.session.status === 'active') {
      await exchangeSession(window.Clerk.session);
      return;
    }

    function openEmailSignIn() {
      status.hidden = true;
      window.Clerk.openSignIn({
        routing: 'hash',
        withSignUp: true,
        forceRedirectUrl: '/verify-email<?= $email !== '' ? '?email=' . urlencode($email) : '' ?>',
        fallbackRedirectUrl: '/verify-email<?= $email !== '' ? '?email=' . urlencode($email) : '' ?>',
        signUpForceRedirectUrl: '/verify-email<?= $email !== '' ? '?email=' . urlencode($email) : '' ?>',
        signUpFallbackRedirectUrl: '/verify-email<?= $email !== '' ? '?email=' . urlencode($email) : '' ?>'
      });
    }

    button.disabled = false;
    button.textContent = 'Verify with Email';
    button.addEventListener('click', openEmailSignIn);

    // Go straight to the verification modal instead of waiting for a click.
    openEmailSignIn();
  } catch (exception) {
    console.error('Clerk initialization failed:', exception);
    button.textContent = 'Verification unavailable';
    showStatus(
      exception instanceof Error ? exception.message : 'Verification could not load.',
      true
    );
  }
});
</script>

</body>
</html>
