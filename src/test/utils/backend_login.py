"""
Logs a seeded test user in via /auth/test_login.php — a test-only endpoint
(see src/public/auth/test_login.php) that only responds when APP_ENV=test,
404s in every other environment. This exists because /login's UI is now
Google/email(Clerk)-driven, which Selenium can't drive end-to-end (no way
to script a real Google account or read a Clerk email code in CI).

Returns the resulting PHPSESSID so it can be handed to the Selenium driver.
"""
import http.cookiejar
import json
import urllib.error
import urllib.request


def login_and_get_session_cookie(website_url: str, email: str, password: str) -> str:
    cookie_jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

    data = json.dumps({"email": email, "password": password}).encode("utf-8")

    request = urllib.request.Request(
        f"{website_url}/auth/test_login.php",
        data=data,
        headers={"Content-Type": "application/json"},
        method="POST",
    )

    try:
        opener.open(request)
    except urllib.error.HTTPError as e:
        if e.code == 404:
            raise RuntimeError(
                "/auth/test_login.php returned 404 — the web server's "
                "APP_ENV must be 'test' for this endpoint to respond "
                "(not just the PHPUnit/CLI env override)."
            ) from e
        # 401 (bad credentials) etc — fall through, caller gets a clear
        # "no PHPSESSID" error below rather than a raw HTTPError.

    for cookie in cookie_jar:
        if cookie.name == "PHPSESSID":
            return cookie.value

    raise RuntimeError("Login did not produce a PHPSESSID cookie — check credentials")
