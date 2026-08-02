<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/vendor/autoload.php';

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Clerk is used to prove a user owns the email address they sign in with,
 * via an emailed one-time code — replacing the old 6-digit email code
 * flow that needed a mail provider we don't have.
 */

function clerk_publishable_key(): string
{
    $key = trim((string) getenv('CLERK_PUBLISHABLE_KEY'));

    if ($key === '' || !preg_match('/^pk_(test|live)_[A-Za-z0-9_-]+$/', $key)) {
        http_response_code(500);
        exit('Clerk is not configured correctly.');
    }

    return $key;
}

function clerk_frontend_api_domain(): string
{
    $publishableKey = clerk_publishable_key();
    $parts = explode('_', $publishableKey, 3);

    if (count($parts) !== 3 || $parts[2] === '') {
        throw new RuntimeException('Invalid Clerk publishable key format.');
    }

    $encoded = strtr($parts[2], '-_', '+/');
    $remainder = strlen($encoded) % 4;

    if ($remainder !== 0) {
        $encoded .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode($encoded, true);

    if ($decoded === false) {
        throw new RuntimeException('Unable to decode Clerk Frontend API domain.');
    }

    $domain = rtrim($decoded, '$');

    if (!preg_match(
        '/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/',
        $domain
    )) {
        throw new RuntimeException('Invalid Clerk Frontend API domain.');
    }

    return strtolower($domain);
}

/**
 * Overwrites the default CSP from security_headers.php (call this AFTER
 * requiring it) so Clerk's hosted script/iframe/API origins are allowed on
 * pages that embed the Clerk widget.
 */
function clerk_browser_csp(): void
{
    $frontendApi = clerk_frontend_api_domain();

    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://{$frontendApi} https://challenges.cloudflare.com https://*.protect.clerk.com; "
        . "connect-src 'self' https://{$frontendApi} https://api.clerk.com https://clerk-telemetry.com https://*.clerk-telemetry.com https://*.protect.clerk.com https://challenges.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: https://img.clerk.com https://cms.asuonline.asu.edu; "
        . "font-src 'self' data: https:; "
        . "frame-src 'self' https://challenges.cloudflare.com https://*.protect.clerk.com; "
        . "worker-src 'self' blob:; "
        . "child-src 'self' blob:; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self';",
        true
    );
}

function clerk_api_get(string $path): array
{
    $secretKey = trim((string) getenv('CLERK_SECRET_KEY'));

    if ($secretKey === '' || !preg_match('/^sk_(test|live)_[A-Za-z0-9_-]+$/', $secretKey)) {
        throw new RuntimeException('Clerk secret key is missing or invalid.');
    }

    $response = HttpClient::create([
        'timeout' => 8,
        'max_redirects' => 0,
    ])->request('GET', 'https://api.clerk.com/v1' . $path, [
        'headers' => [
            'Authorization' => 'Bearer ' . $secretKey,
            'Accept' => 'application/json',
        ],
    ]);

    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('Clerk API request failed with HTTP ' . $response->getStatusCode() . '.');
    }

    $data = $response->toArray(false);

    if (!is_array($data)) {
        throw new RuntimeException('Clerk returned an invalid API response.');
    }

    return $data;
}

function clerk_verified_primary_email(string $clerkUserId): string
{
    $user = clerk_api_get('/users/' . rawurlencode($clerkUserId));

    $primaryEmailId = $user['primary_email_address_id'] ?? null;

    if (!is_string($primaryEmailId) || $primaryEmailId === '') {
        throw new RuntimeException('The Clerk user has no primary email address.');
    }

    foreach ($user['email_addresses'] ?? [] as $emailAddress) {
        if (!is_array($emailAddress)) {
            continue;
        }

        $isPrimary = ($emailAddress['id'] ?? null) === $primaryEmailId;
        $isVerified = ($emailAddress['verification']['status'] ?? null) === 'verified';
        $email = strtolower(trim((string) ($emailAddress['email_address'] ?? '')));

        if ($isPrimary && $isVerified && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
    }

    throw new RuntimeException('A verified primary email address is required.');
}

function clerk_request_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($host === '' || preg_match('/[\r\n\/\\\\]/', $host)) {
        throw new RuntimeException('Unable to determine application origin.');
    }

    return $scheme . '://' . $host;
}

/**
 * Verifies the Clerk session JWT's signature, issuer, and origin, and
 * returns its claims. Does not authorize anything by itself — callers must
 * still check the resulting verified email against local app state.
 */
function clerk_verify_session_token(string $token, string $expectedOrigin): object
{
    $jwks = clerk_api_get('/jwks');

    JWT::$leeway = 5;

    $claims = JWT::decode($token, JWK::parseKeySet($jwks, 'RS256'));

    $expectedIssuer = 'https://' . clerk_frontend_api_domain();

    if (!isset($claims->iss) || !is_string($claims->iss) || !hash_equals($expectedIssuer, $claims->iss)) {
        throw new RuntimeException('Invalid Clerk token issuer.');
    }

    if (!isset($claims->azp) || !is_string($claims->azp) || !hash_equals($expectedOrigin, $claims->azp)) {
        throw new RuntimeException('The Clerk token was issued for another application origin.');
    }

    if (!isset($claims->sub) || !is_string($claims->sub) || !preg_match('/^user_[A-Za-z0-9]+$/D', $claims->sub)) {
        throw new RuntimeException('Invalid Clerk user identity.');
    }

    return $claims;
}
