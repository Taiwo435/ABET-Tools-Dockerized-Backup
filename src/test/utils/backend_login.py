"""
Logs a seeded test user in through the real /login endpoint (form_login)
via a direct HTTP POST — the same request a browser form submission would
make. Used instead of driving the UI form directly so tests don't need a
full page interaction just to get authenticated.

Returns the resulting PHPSESSID so it can be handed to the Selenium driver.
"""
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request


def login_and_get_session_cookie(website_url: str, email: str, password: str) -> str:
    cookie_jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

    login_page = opener.open(f"{website_url}/login").read().decode("utf-8")

    csrf_match = re.search(r'name="_csrf_token"[^>]*value="([^"]*)"', login_page)
    if not csrf_match:
        raise RuntimeError("Could not find _csrf_token on /login page")
    csrf_token = csrf_match.group(1)

    data = urllib.parse.urlencode({
        "_username": email,
        "_password": password,
        "_csrf_token": csrf_token,
    }).encode("utf-8")

    request = urllib.request.Request(
        f"{website_url}/login",
        data=data,
        headers={
            "Content-Type": "application/x-www-form-urlencoded",
            "Origin": website_url,
            "Referer": f"{website_url}/login",
        },
        method="POST",
    )

    try:
        opener.open(request)
    except urllib.error.HTTPError:
        # form_login redirects (3xx) on both success and failure; either way
        # we just need whatever session cookie resulted from the attempt.
        pass

    for cookie in cookie_jar:
        if cookie.name == "PHPSESSID":
            return cookie.value

    raise RuntimeError("Login did not produce a PHPSESSID cookie — check credentials")
