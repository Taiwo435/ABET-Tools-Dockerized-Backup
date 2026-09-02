"""Browser coverage for every destination linked from the admin panel."""

import os

import pytest
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

from utils.seeder import ROLE_ALL, add_db_user, remove_db_user
from utils.webdriver import init_webdriver, login_via_backend


EMAIL = "admin-links@example.com"
PASSWORD = "superSecretPassword1!"
WEBSITE_URL = f"http://{os.getenv('WEBSERVER_HOSTNAME', 'php_apache')}"


@pytest.fixture
def driver():
    browser = init_webdriver()
    yield browser
    browser.quit()


def test_every_admin_panel_link_and_back_button(driver):
    add_db_user(EMAIL, PASSWORD, permissions=ROLE_ALL)
    try:
        login_via_backend(driver, EMAIL, PASSWORD)
        driver.get(f"{WEBSITE_URL}/admin")

        destinations = [
            link.get_attribute("href")
            for link in driver.find_elements(By.CSS_SELECTOR, ".admin-panel-grid a.action-link")
        ]
        assert destinations == [
            f"{WEBSITE_URL}/tool/assignmentsgrades",
            f"{WEBSITE_URL}/admin/users",
            f"{WEBSITE_URL}/admin/queue",
            f"{WEBSITE_URL}/admin/templates",
            f"{WEBSITE_URL}/admin/syllabus-templates",
        ]

        for destination in destinations:
            driver.get(destination)
            WebDriverWait(driver, 10).until(
                lambda browser: browser.execute_script("return document.readyState") == "complete"
            )
            exception = driver.find_elements(By.CSS_SELECTOR, ".exception-message")
            error_detail = exception[0].text if exception else destination
            assert "Internal Server Error" not in driver.title, error_detail
            assert "Internal Server Error" not in driver.page_source, destination

            back_button = WebDriverWait(driver, 10).until(
                EC.element_to_be_clickable((By.CSS_SELECTOR, "a[href='/admin']"))
            )
            assert "Back to Admin Panel" in back_button.text
            viewport_width = driver.execute_script("return window.innerWidth")
            assert back_button.rect["x"] + (back_button.rect["width"] / 2) > viewport_width / 2
            driver.execute_script("arguments[0].click()", back_button)
            WebDriverWait(driver, 10).until(EC.url_to_be(f"{WEBSITE_URL}/admin"))
    finally:
        remove_db_user(EMAIL)
