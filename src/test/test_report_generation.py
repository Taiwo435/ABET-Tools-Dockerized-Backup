import os
import pytest

from selenium.webdriver.common.by import By
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

from utils.webdriver import init_webdriver, login_via_backend, PROJECT_DIR
from utils.seeder import add_db_user
from dotenv import load_dotenv

EMAIL_ADDRESS = os.getenv("TEST_EMAIL", "test@example.com")
PASSWORD = os.getenv("TEST_PASSWORD", "superSecretPassword1!")
WEBSITE_URL = f"http://{os.getenv('WEBSERVER_HOSTNAME', 'php_apache')}"
load_dotenv(f"{PROJECT_DIR}/docker/.env")

@pytest.fixture
def driver():
    driver = init_webdriver()
    yield driver
    driver.quit()


def login(driver):
    """
    Account creation requires completing email verification, which can't be
    scripted end-to-end by Selenium, so this seeds a user directly, already
    active (utils/seeder.py), and logs in through the real /login endpoint
    directly (utils/backend_login.py) instead of filling the form by hand.
    """
    add_db_user(EMAIL_ADDRESS, PASSWORD)
    login_via_backend(driver, EMAIL_ADDRESS, PASSWORD)

    driver.get(f"{WEBSITE_URL}/home")
    WebDriverWait(driver, 10).until(
        EC.url_to_be(f"{WEBSITE_URL}/home")
    )


def test_standard_report_generation(driver):
    login(driver)

    driver.get(f"{WEBSITE_URL}/report-generator/index.php")

    WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.ID, "jsonFile"))
    )

    test_json_path = os.path.abspath(
        os.path.join(
            PROJECT_DIR,
            "src",
            "public",
            "cgi-bin",
            "input_jsons",
            "test_input.json",
        )
    )

    file_input = driver.find_element(By.ID, "jsonFile")
    file_input.send_keys(test_json_path)

    filename_el = driver.find_element(By.ID, "fileName")
    assert "test_input.json" in filename_el.text

    generate_btn = driver.find_element(By.ID, "generateBtn")
    generate_btn.click()

    status_el = WebDriverWait(driver, 60).until(
        EC.presence_of_element_located(
            (By.CSS_SELECTOR, ".status.ok")
        )
    )

    assert "Report generated successfully" in status_el.text

    results_el = driver.find_element(By.ID, "results")
    assert "show" in results_el.get_attribute("class")

    download_docx_btn = driver.find_element(
        By.ID,
        "downloadDocxBtn"
    )

    assert download_docx_btn.is_displayed()


def test_long_report_generation(driver):
    login(driver)

    driver.get(f"{WEBSITE_URL}/report-generator/index.php")

    appendix_fixture_path = os.path.abspath(
        os.path.join(
            PROJECT_DIR,
            "src",
            "test",
            "fixtures",
            "appendix_a_courses_v1.json",
        )
    )

    appendix_file_input = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located(
            (By.ID, "appendixContractFile")
        )
    )
    appendix_file_input.send_keys(appendix_fixture_path)

    WebDriverWait(driver, 10).until(
        lambda current_driver: (
            "1 contract selected"
            in current_driver.find_element(
                By.ID,
                "appendixContractFileName",
            ).text
        )
    )

    lr_btn = WebDriverWait(driver, 10).until(
        EC.element_to_be_clickable((By.ID, "LRbtn"))
    )

    lr_btn.click()

    lr_status_el = WebDriverWait(driver, 120).until(
        EC.presence_of_element_located(
            (By.CSS_SELECTOR, "#lrStatus.ok")
        )
    )

    assert (
        "Long report generated successfully"
        in lr_status_el.text
    )

    download_btn = driver.find_element(
        By.ID,
        "downloadLongDocxBtn"
    )

    assert download_btn.is_displayed()


def test_canvas_token_validation(driver):
    login(driver)

    driver.get(f"{WEBSITE_URL}/tool/assignmentsgrades")

    token_input = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located(
            (By.ID, "access_token_token")
        )
    )

    token_input.send_keys(
        os.getenv("CANVAS_ACCESS_TOKEN", "mock_token")
    )

    connect_btn = driver.find_element(
        By.ID,
        "access_token_submit"
    )

    connect_btn.click()

    success_alert = WebDriverWait(driver, 10).until(
        EC.visibility_of_element_located(
            (By.ID, "successAlert")
        )
    )

    assert success_alert.is_displayed()
