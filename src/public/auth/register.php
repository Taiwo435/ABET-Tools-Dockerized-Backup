<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/clerk.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';

start_session();

/**
 * Account creation is entirely Google/Clerk-driven now (see
 * auth/clerk_login.php, which auto-creates an account the first time a new
 * verified Google email signs in) — this page is just the "Create Account"
 * landing screen with the same email + Continue with Google flow as
 * /login, so there's no separate password form to fill out.
 */

$publishableKey = null;
$frontendApi = null;

try {
    $key = trim((string) getenv('CLERK_PUBLISHABLE_KEY'));
    if ($key !== '' && preg_match('/^pk_(test|live)_[A-Za-z0-9_-]+$/', $key)) {
        $publishableKey = $key;
        $frontendApi = clerk_frontend_api_domain();
        clerk_browser_csp();
        header('Cross-Origin-Opener-Policy: same-origin');
    }
} catch (\Throwable) {
    $publishableKey = null;
    $frontendApi = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ASU ABET Tools | Create Account</title>
  <link rel="icon" type="image/svg" href="/assets/img/favicon.svg" />
  <link href="/assets/css/auth.css" rel="stylesheet">

  <?php if ($publishableKey !== null): ?>
  <script defer crossorigin="anonymous"
    src="https://<?= htmlspecialchars($frontendApi, ENT_QUOTES, 'UTF-8') ?>/npm/@clerk/ui@1/dist/ui.browser.js"
    type="text/javascript"></script>
  <script defer crossorigin="anonymous"
    data-clerk-publishable-key="<?= htmlspecialchars($publishableKey, ENT_QUOTES, 'UTF-8') ?>"
    src="https://<?= htmlspecialchars($frontendApi, ENT_QUOTES, 'UTF-8') ?>/npm/@clerk/clerk-js@6/dist/clerk.browser.js"
    type="text/javascript"></script>
  <?php endif; ?>
</head>
<body>

  <div class="register-container">

    <div class="brand-section">
      <div class="brand-content">
        <h2>Join the Community</h2>
        <p>Create your account to start managing ABET accreditation data and tools.</p>
        <div style="width: 60px; height: 4px; background: var(--asu-gold); margin-top: 20px;"></div>
      </div>
    </div>

    <div class="form-section">
      <a href="#" class="help-link">Need Help?</a>

      <div class="form-header">
        <h1>Create Account</h1>
        <p>Enter your email, then verify with Google.</p>
      </div>

      <?php if ($publishableKey !== null): ?>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input id="email" type="email" placeholder="asurite@asu.edu" autocomplete="email" autofocus />
      </div>

      <button id="clerk-register-btn" class="btn-submit" type="button" disabled>
        Loading…
      </button>
      <p id="clerk-register-status" class="msg error" role="alert" aria-live="polite" hidden></p>
      <?php else: ?>
      <div class="msg error">Account creation is temporarily unavailable. Please try again shortly.</div>
      <?php endif; ?>

      <div class="footer-links">
        <span>Already have an account?</span>
        <a href="/login">Sign In</a>
      </div>
    </div>

  </div>

<script>
'use strict';
window.addEventListener('load', async function () {
  const button = document.getElementById('clerk-register-btn');
  const status = document.getElementById('clerk-register-status');
  if (!button || !status) { return; }

  function showError(message) {
    status.textContent = message || 'Google sign-in could not be completed.';
    status.hidden = false;
    button.disabled = false;
    button.textContent = 'Continue with Google';
  }

  let exchangeInProgress = false;

  async function exchangeSession(session) {
    if (exchangeInProgress || !session || session.status !== 'active') { return; }
    exchangeInProgress = true;
    status.hidden = true;
    button.disabled = true;
    button.textContent = 'Creating account…';

    try {
      const token = await session.getToken({ skipCache: true });
      if (!token) { throw new Error('Google sign-in did not return a token.'); }

      const response = await fetch('/auth/clerk_login.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token })
      });
      const data = await response.json();

      if (!response.ok) {
        const err = new Error(data.error || 'Sign-in failed.');
        err.status = response.status;
        throw err;
      }

      try { await window.Clerk.signOut(); } catch (_) {}
      window.location.replace(data.redirect || '/home');
    } catch (exception) {
      console.error('Clerk sign-up failed:', exception);
      exchangeInProgress = false;
      showError(exception instanceof Error ? exception.message : 'Google sign-in could not be completed.');
    }
  }

  function openGoogleSignIn() {
    status.hidden = true;
    window.Clerk.openSignIn({
      oauthFlow: 'redirect',
      routing: 'hash',
      withSignUp: true,
      forceRedirectUrl: '/register',
      fallbackRedirectUrl: '/register',
      signUpForceRedirectUrl: '/register',
      signUpFallbackRedirectUrl: '/register',
      appearance: {
        layout: { socialButtonsPlacement: 'top', socialButtonsVariant: 'blockButton' }
      }
    });
  }

  try {
    if (!window.Clerk || typeof window.__internal_ClerkUICtor !== 'function') {
      throw new Error('Clerk did not load.');
    }
    await window.Clerk.load({ ui: { ClerkUI: window.__internal_ClerkUICtor } });

    window.Clerk.addListener(function ({ session }) {
      if (session && session.status === 'active') { void exchangeSession(session); }
    });

    if (window.Clerk.session && window.Clerk.session.status === 'active') {
      await exchangeSession(window.Clerk.session);
      return;
    }

    button.disabled = false;
    button.textContent = 'Continue with Google';
    button.addEventListener('click', openGoogleSignIn);
  } catch (exception) {
    console.error('Clerk initialization failed:', exception);
    button.textContent = 'Google sign-in unavailable';
    showError(exception instanceof Error ? exception.message : 'Google sign-in could not load.');
  }
});
</script>

</body>
</html>
