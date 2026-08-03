"""End-to-end coverage for the coordinator syllabus administration workflow."""

import os
import uuid

import pytest
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.select import Select
from selenium.webdriver.support.wait import WebDriverWait

from utils.webdriver import init_webdriver


WEBSITE_URL = f"http://{os.getenv('WEBSERVER_HOSTNAME', 'php_apache')}"
PASSWORD = "SyllabusWorkflow1!"


@pytest.fixture
def driver():
    browser = init_webdriver()
    yield browser
    browser.quit()


def register_and_login(driver, email: str, role: str) -> None:
    driver.get(f"{WEBSITE_URL}/register")
    WebDriverWait(driver, 20).until(
        EC.presence_of_element_located((By.ID, "email"))
    ).send_keys(email)
    Select(driver.find_element(By.ID, "role")).select_by_value(role)
    driver.find_element(By.ID, "password").send_keys(PASSWORD)
    driver.find_element(By.ID, "confirm_password").send_keys(PASSWORD)
    driver.find_element(By.ID, "submitBtn").click()

    WebDriverWait(driver, 10).until(
        EC.text_to_be_present_in_element((By.CLASS_NAME, "success"), "Account created")
    )

    driver.get(f"{WEBSITE_URL}/login2")
    WebDriverWait(driver, 20).until(
        EC.presence_of_element_located((By.ID, "email"))
    ).send_keys(email)
    driver.find_element(By.ID, "password").send_keys(PASSWORD)
    driver.find_element(By.CLASS_NAME, "btn-submit").click()
    WebDriverWait(driver, 10).until(EC.url_to_be(f"{WEBSITE_URL}/home2"))


def click_button(driver, label: str) -> None:
    button = driver.find_element(By.XPATH, f"//button[normalize-space()='{label}']")
    driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", button)
    WebDriverWait(driver, 10).until(lambda _driver: button.is_displayed() and button.is_enabled())
    button.click()


def test_admin_can_create_and_publish_syllabus_template_while_faculty_is_denied(driver):
    run_id = uuid.uuid4().hex[:8]
    faculty_email = f"task115-faculty-{run_id}@example.com"
    admin_email = f"task115-admin-{run_id}@example.com"

    register_and_login(driver, faculty_email, "faculty")
    driver.get(f"{WEBSITE_URL}/admin/syllabus-templates")
    WebDriverWait(driver, 10).until(
        lambda browser: browser.execute_script("return document.readyState") == "complete"
    )
    denied_page = driver.find_element(By.TAG_NAME, "body").text
    assert "Shared Syllabus Templates" not in denied_page
    assert driver.current_url == f"{WEBSITE_URL}/admin/syllabus-templates"
    assert any(marker in denied_page for marker in ("Access Denied", "403", "Forbidden")), denied_page

    driver.delete_all_cookies()
    register_and_login(driver, admin_email, "admin")
    driver.get(f"{WEBSITE_URL}/admin/syllabus-templates")
    WebDriverWait(driver, 10).until(
        lambda browser: browser.execute_script("return document.readyState") == "complete"
    )
    admin_page = driver.find_element(By.TAG_NAME, "body").text
    assert "Shared Syllabus Templates" in admin_page, f"{driver.current_url}\n{admin_page}"

    driver.get(f"{WEBSITE_URL}/admin/syllabus-templates/new")
    WebDriverWait(driver, 20).until(
        EC.presence_of_element_located((By.ID, "coordinator_template_program"))
    )

    Select(driver.find_element(By.ID, "coordinator_template_program")).select_by_index(1)
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
