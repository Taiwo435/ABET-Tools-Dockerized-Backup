"""End-to-end coverage for the coordinator syllabus administration workflow."""

import os
import uuid

import pytest
from dotenv import load_dotenv
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.select import Select
from selenium.webdriver.support.wait import WebDriverWait
from utils.seeder import (
    DEFAULT_TEST_PASSWORD,
    ROLE_ADMIN,
    ROLE_FACULTY_FORM,
    add_db_program,
    add_db_user,
)
from utils.webdriver import PROJECT_DIR, init_webdriver, login_via_backend

load_dotenv(f"{PROJECT_DIR}/docker/.env")
WEBSITE_URL = f"http://{os.getenv('WEBSERVER_HOSTNAME', 'php_apache')}"
PASSWORD = DEFAULT_TEST_PASSWORD


@pytest.fixture
def driver():
    browser = init_webdriver()
    yield browser
    browser.quit()


def login(driver, email: str) -> None:
    login_via_backend(driver, email, PASSWORD)
    driver.get(f"{WEBSITE_URL}/home")
    WebDriverWait(driver, 10).until(EC.url_to_be(f"{WEBSITE_URL}/home"))


def click_button(driver, label: str) -> None:
    button = driver.find_element(By.XPATH, f"//button[normalize-space()='{label}']")
    driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", button)
    WebDriverWait(driver, 10).until(lambda _driver: button.is_displayed() and button.is_enabled())
    driver.execute_script("arguments[0].click();", button)


def test_admin_can_create_and_publish_syllabus_template_while_faculty_is_denied(driver):
    run_id = uuid.uuid4().hex[:8]
    faculty_email = f"task115-faculty-{run_id}@example.com"
    admin_email = f"task115-admin-{run_id}@example.com"
    program_id = add_db_program("Task 115 Browser Test Program", "T115", "2026")

    add_db_user(faculty_email, PASSWORD, permissions=ROLE_FACULTY_FORM)
    add_db_user(admin_email, PASSWORD, permissions=ROLE_ADMIN)

    login(driver, faculty_email)
    driver.get(f"{WEBSITE_URL}/admin/syllabus-templates")
    WebDriverWait(driver, 10).until(
        lambda browser: browser.execute_script("return document.readyState") == "complete"
    )
    denied_page = driver.find_element(By.TAG_NAME, "body").text
    assert "Shared Syllabus Templates" not in denied_page
    assert driver.current_url == f"{WEBSITE_URL}/admin/syllabus-templates"
    assert any(marker in denied_page for marker in ("Access Denied", "403", "Forbidden")), denied_page

    driver.delete_all_cookies()
    login(driver, admin_email)
    driver.get(f"{WEBSITE_URL}/admin/syllabus-templates")
    WebDriverWait(driver, 10).until(
        lambda browser: browser.execute_script("return document.readyState") == "complete"
    )
    admin_page = driver.find_element(By.TAG_NAME, "body").text
    assert "Shared Syllabus Templates" in admin_page, f"{driver.current_url}\n{admin_page}"

    driver.get(f"{WEBSITE_URL}/admin/syllabus-templates/new?program={program_id}")
    WebDriverWait(driver, 20).until(
        EC.presence_of_element_located((By.ID, "coordinator_template_program"))
    )

    Select(driver.find_element(By.ID, "coordinator_template_program")).select_by_value(str(program_id))
    driver.find_element(By.ID, "coordinator_template_courseSubject").send_keys("CSE")
    driver.find_element(By.ID, "coordinator_template_courseNumber").send_keys(f"T{run_id}")
    driver.find_element(By.ID, "coordinator_template_courseName").send_keys("Syllabus Workflow Test")
    Select(driver.find_element(By.ID, "coordinator_template_deliveryType")).select_by_visible_text("Online")
    driver.find_element(By.ID, "coordinator_template_creditHours").send_keys("3")
    driver.find_element(By.ID, "coordinator_template_courseCoordinators").send_keys("Kalyanam Test")
    driver.find_element(By.ID, "coordinator_template_creditCategorization").send_keys("3 credits")
    click_button(driver, "Create Draft")

    WebDriverWait(driver, 20).until(
        EC.url_matches(r"/admin/syllabus-templates/\d+/edit$")
    )
    assert "Shared syllabus template draft created." in driver.page_source

    click_button(driver, "Publish Current Revision")

    WebDriverWait(driver, 20).until(
        EC.text_to_be_present_in_element((By.TAG_NAME, "body"), f"T{run_id}")
    )
    assert "/admin/syllabus-templates" in driver.current_url
    assert "/edit" not in driver.current_url
    assert "Published" in driver.find_element(By.TAG_NAME, "body").text
