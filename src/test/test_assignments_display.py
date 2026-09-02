"""End-to-end coverage for the Canvas assignment display flow."""

import os

import pytest
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

from utils.seeder import add_db_user, remove_db_user
from utils.webdriver import init_webdriver, login_via_backend


EMAIL = "assignments-display@example.com"
PASSWORD = "superSecretPassword1!"
WEBSITE_URL = f"http://{os.getenv('WEBSERVER_HOSTNAME', 'php_apache')}"


@pytest.fixture
def driver():
    browser = init_webdriver()
    yield browser
    browser.quit()


def test_token_course_assignment_display_and_refresh(driver):
    add_db_user(EMAIL, PASSWORD)
    try:
        login_via_backend(driver, EMAIL, PASSWORD)
        driver.get(f"{WEBSITE_URL}/tool/assignmentsgrades")

        token_input = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, "input[placeholder*='Canvas access token']"))
        )
        token_input.send_keys("mock_token")
        driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()

        continue_link = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.LINK_TEXT, "Continue to Select Courses →"))
        )
        continue_link.click()

        course_card = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.CSS_SELECTOR, ".class-card"))
        )
        course_card.click()
        driver.find_element(By.ID, "next-btn").click()

        WebDriverWait(driver, 10).until(
            lambda current: "courseId=240102" in current.current_url
        )
        WebDriverWait(driver, 10).until(
            lambda current: len(current.find_elements(By.CSS_SELECTOR, "#assignmentsTableBody tr")) == 2
        )

        assert "Mock Published Assignment" in driver.page_source
        assert "Mock Assignment Without Due Date" in driver.page_source
        assert "Assignments" in driver.page_source
        assert "25" in driver.page_source
        assert "21.5" in driver.page_source
        assert "No graded submissions" in driver.page_source
        abet_inputs = driver.find_elements(By.CSS_SELECTOR, ".ag-abet-score-input")
        assert len(abet_inputs) == 2
        assert all(score_input.get_attribute("max") == "7" for score_input in abet_inputs)
        abet_inputs[0].send_keys("99")
        assert abet_inputs[0].get_attribute("value") == "7"
        headers = [header.text for header in driver.find_elements(By.CSS_SELECTOR, "#assignmentsTable th")]
        assert "DUE DATE" not in headers
        assert "PUBLISHED" not in headers
        assert "AVERAGE GRADE" in headers
        assert "ABET SCORE" in headers

        # Symfony's development toolbar can overlap the bottom of the viewport;
        # dispatch the same browser click without relying on screen coordinates.
        driver.execute_script("arguments[0].click()", driver.find_element(By.ID, "refreshAssignments"))
        WebDriverWait(driver, 10).until(
            lambda current: len(current.find_elements(By.CSS_SELECTOR, "#assignmentsTableBody tr")) == 2
        )
        assert len(driver.find_elements(By.CSS_SELECTOR, "#assignmentsTableBody tr")) == 2
    finally:
        remove_db_user(EMAIL)


def test_invalid_course_id_state(driver):
    add_db_user(EMAIL, PASSWORD)
    try:
        login_via_backend(driver, EMAIL, PASSWORD)
        driver.get(f"{WEBSITE_URL}/AssignmentsGrades/assignments?courseId=invalid")
        state = WebDriverWait(driver, 10).until(
            EC.visibility_of_element_located((By.ID, "assignmentsState"))
        )
        assert "Invalid course ID" in state.text
        assert driver.find_element(By.ID, "assignmentsTable").get_attribute("hidden") is not None
    finally:
        remove_db_user(EMAIL)
