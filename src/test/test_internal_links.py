"""Authenticated crawl of every safe internal link reachable from the app."""

import os
import re
from collections import deque
from urllib.parse import urljoin, urlparse, urlunparse

import pytest
from selenium.webdriver.common.by import By

from utils.seeder import ROLE_ALL, add_db_user, remove_db_user
from utils.webdriver import init_webdriver, login_via_backend


EMAIL = "internal-links@example.com"
PASSWORD = "superSecretPassword1!"
WEBSITE_URL = f"http://{os.getenv('WEBSERVER_HOSTNAME', 'php_apache')}"
SKIP_PATHS = {
    "/logout",
    "/tool/assignmentsgrades/remove_token",
}


@pytest.fixture
def driver():
    browser = init_webdriver()
    yield browser
    browser.quit()


def normalize_internal_url(href):
    if not href or href.startswith(("#", "javascript:", "mailto:", "tel:")):
        return None
    parsed = urlparse(urljoin(WEBSITE_URL, href))
    base = urlparse(WEBSITE_URL)
    if parsed.netloc != base.netloc or parsed.scheme not in {"http", "https"}:
        return None
    if (
        parsed.path in SKIP_PATHS
        or parsed.path.endswith(".json")
        or parsed.path.startswith(("/_profiler", "/_wdt"))
    ):
        return None
    return urlunparse((base.scheme, base.netloc, parsed.path or "/", "", parsed.query, ""))


def test_all_reachable_internal_links(driver):
    add_db_user(EMAIL, PASSWORD, permissions=ROLE_ALL)
    try:
        login_via_backend(driver, EMAIL, PASSWORD)
        pending = deque([f"{WEBSITE_URL}/home", f"{WEBSITE_URL}/admin"])
        visited = set()

        while pending:
            url = pending.popleft()
            if url in visited:
                continue
            visited.add(url)
            assert len(visited) <= 200, "Internal link crawl exceeded 200 unique pages"

            driver.get(url)
            title = driver.title
            source = driver.page_source
            exception = driver.find_elements(By.CSS_SELECTOR, ".exception-message")
            error_detail = exception[0].text if exception else url
            assert "Internal Server Error" not in title, error_detail
            assert "Not Found" not in title, url
            assert "HTTP 404" not in source, url
            assert urlparse(driver.current_url).path != "/login", f"Unexpected login redirect from {url}"

            discovered = [element.get_attribute("href") for element in driver.find_elements(By.CSS_SELECTOR, "a[href]")]
            for element in driver.find_elements(By.CSS_SELECTOR, "[onclick]"):
                match = re.search(r"(?:window\.)?location\.href\s*=\s*['\"]([^'\"]+)", element.get_attribute("onclick") or "")
                if match:
                    discovered.append(match.group(1))

            for href in discovered:
                normalized = normalize_internal_url(href)
                if normalized and normalized not in visited:
                    pending.append(normalized)

        assert len(visited) >= 10
    finally:
        remove_db_user(EMAIL)


def test_logout_link(driver):
    add_db_user(EMAIL, PASSWORD, permissions=ROLE_ALL)
    try:
        login_via_backend(driver, EMAIL, PASSWORD)
        driver.get(f"{WEBSITE_URL}/home")
        driver.get(f"{WEBSITE_URL}/logout")
        assert urlparse(driver.current_url).path == "/login"
    finally:
        remove_db_user(EMAIL)


def test_reset_canvas_token_link(driver):
    add_db_user(EMAIL, PASSWORD, permissions=ROLE_ALL)
    try:
        login_via_backend(driver, EMAIL, PASSWORD)
        driver.get(f"{WEBSITE_URL}/tool/assignmentsgrades")
        token_input = driver.find_element(By.CSS_SELECTOR, "input[placeholder*='Canvas access token']")
        token_input.send_keys("mock_token")
        driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()
        driver.get(f"{WEBSITE_URL}/tool/assignmentsgrades/remove_token")
        assert urlparse(driver.current_url).path == "/tool/assignmentsgrades"
        assert "already registered a token" not in driver.page_source
    finally:
        remove_db_user(EMAIL)
