"""
The test_navigation (previously test_login.py) file contains Selenium-based tests for navigating through the UI to ensure the functionality of the ABET Tools web application. 
This is meant to run with the dockerized Selenium Grid setup defined in the docker-compose.yml file.

contains a template file for future tests .

implemented tests:
    - login with invalid credentials
"""
import os
from sys import exit
from traceback import print_exc
from functools import wraps
from time import sleep

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import subprocess
from pathlib import Path

from dotenv import load_dotenv
import pytest

EMAIL_ADDRESS = os.getenv("TEST_EMAIL", "test@example.com")
PASSWORD = os.getenv("TEST_PASSWORD", "superSecretPassword1!")
# WEBSITE_URL = f"http://localhost:{os.getenv('APP_PORT', '8080')}"
WEBSITE_URL = f"http://{os.getenv('WEBSERVER_HOSTNAME', 'php_apache')}"
from utils.webdriver import init_webdriver, login_via_backend
from utils.webdriver import PROJECT_DIR
from utils.seeder import add_db_user, remove_db_user

load_dotenv(f"{PROJECT_DIR}/docker/.env")
# os.environ["PATH"] += os.pathsep + os.pathsep.join([
#     "/home/danny/ASU/ABET-Tools-Frontend/src/test/drivers",
# ])

# def init_webdriver():
#     """
#     Initializes the webdriver in a completely fresh state
#     ensures that there is no leftover session state
#     """
#     try:
#         driver = webdriver.Remote(
#             command_executor=f"http://localhost:{os.getenv('SELENIUM_PORT', '4444')}/wd/hub",
#             options=webdriver.FirefoxOptions(),
#         )
#         return driver
#     except Exception as e:
#         print(f"An error occurred while initializing WebDriver: {e}")
#         print_exc()
#         exit(1)

def expect_route(driver, path):
    try:
        WebDriverWait(driver, 5).until(EC.url_to_be(f"{WEBSITE_URL}{path}"))
    except:
        pass
    assert driver.current_url == f"{WEBSITE_URL}{path}", f"User not at {path} after button pressed"

@pytest.fixture
def driver():
    driver = init_webdriver()
    yield driver
    driver.quit()

# @pytest.hookimpl(hookwrapper=True)
# def pytest_runtest_makereport(item, call):
#     outcome = yield
#     report = outcome.get_result()

#     # Only act on test failure
#     if report.when == "call" and report.failed:
#         test_name = item.name

#         output_file = ARTIFACTS_DIR / f"{test_name}_curl.txt"

#         try:
#             result = subprocess.run(
#                 ["curl", "-i", "-L", WEBSITE_URL],
#                 capture_output=True,
#                 text=True,
#                 timeout=30,
#             )

#             with open(output_file, "w") as f:
#                 f.write(result.stdout)
#                 f.write("\n\n--- STDERR ---\n\n")
#                 f.write(result.stderr)

#         except Exception as e:
#             with open(output_file, "w") as f:
#                 f.write(f"Failed to run curl: {e}")
# def template(): 
#     """
#     deprecated template for making new tests
#     """
#     driver = init_webdriver()
#     try:
#         driver.get(f"{WEBSITE_URL}")
#         driver.implicitly_wait(2)  # Wait for the page to load
#         print("Got the website")

#         # Add your test steps here

#     except Exception as e:
#         print(f"An error occurred: {e}")
#         print_exc()
#     finally:
#         driver.quit()


def test_login_invalid_credentials(driver):
    """
    tests that users cannot simply access the website without having a valid session
    """
    from utils.backend_login import login_and_get_session_cookie

    backend_url = os.getenv("BACKEND_URL", WEBSITE_URL)
    session_id = login_and_get_session_cookie(backend_url, "invaliduser@asu.edu", "invalidpass")

    driver.get(WEBSITE_URL)
    driver.delete_cookie("PHPSESSID")
    driver.add_cookie({"name": "PHPSESSID", "value": session_id, "path": "/"})

    driver.get(f"{WEBSITE_URL}/home")
    driver.implicitly_wait(2)  # Wait for the page to load

    expect_route(driver, "/login")

@pytest.mark.order(1)
def test_register_and_login_valid_credentials_logout(driver):
    """
    tests login with valid credentials reaches /home, and that logout works.

    Account creation requires completing email verification, which can't be
    scripted end-to-end by Selenium (no way to read the code from a real
    inbox in CI), so the account is seeded directly, already active, in the
    database instead — see utils/seeder.py. Login itself still goes through
    the real, unmodified /login endpoint.
    """
    add_db_user(EMAIL_ADDRESS, PASSWORD)

    login_via_backend(driver, EMAIL_ADDRESS, PASSWORD)
    driver.get(f"{WEBSITE_URL}/home")
    driver.implicitly_wait(2)  # Wait for the page to load

    expect_route(driver, "/home")

    profile_button = driver.find_element(By.CLASS_NAME, "auth-btn")
    profile_button.click()

    logout_link = driver.find_element(By.CLASS_NAME, "logout-item")
    logout_link.click()
    driver.implicitly_wait(2)

    expect_route(driver, "/login")

    remove_db_user(EMAIL_ADDRESS)

# @with_webdriver
def test_navigation(driver):
    """
    test using the new decorator i just made
    should navigate to the homepage and some profile stuff
    """
    add_db_user(EMAIL_ADDRESS, PASSWORD)

    driver.get(f"{WEBSITE_URL}")
    driver.implicitly_wait(2)  # Wait for the page to load
    print("Got the website")

    login_via_backend(driver, EMAIL_ADDRESS, PASSWORD)
    driver.get(f"{WEBSITE_URL}/home")
    WebDriverWait(driver, 10).until(lambda d: "Log Out" in d.page_source)  # Wait for the next page to load

    expect_route(driver, "/home")

    ###############################################
    # ACCOUNT PAGES
    # /account/me
    ###############################################
    def open_dropdown(driver): 
        profile_button = driver.find_element(By.CLASS_NAME, "auth-btn");
        profile_button.click()

    # NOTE: these steps used to locate dropdown links by absolute, index-based
    # XPath (e.g. ".../div[2]/div/div/div/a[1]"), which silently drifted out
    # of sync with base.html.twig's actual header markup (an extra "Request
    # Access" link was added to the dropdown at some point, shifting every
    # sibling index after it) and even pointed at a route ("/account/me/")
    # the "My Profile" link no longer goes to (it's "/account/overview/"
    # now). Selecting by href/class instead is robust to reordering.

    open_dropdown(driver)

    # go to my profile overview
    my_profile_link = driver.find_element(By.ID, "nav-my-profile");

    my_profile_link.click()

    expect_route(driver, "/account/overview/")

    # account/me.html.twig has no dedicated back button — use the header's
    # home icon, present on every page via base.html.twig.
    back_link = driver.find_element(By.CSS_SELECTOR, "a.home-link")
    back_link.click()

    expect_route(driver, "/home")

    ###############################################
    # /account/profile
    ###############################################

    open_dropdown(driver)

    # go to edit
    edit_profile_link = driver.find_element(By.ID, "nav-edit-profile");

    edit_profile_link.click()

    expect_route(driver, "/account/profile/")

    # go to home
    back_link = driver.find_element(By.LINK_TEXT, "Cancel")
    back_link.click()

    expect_route(driver, "/home")

    ###############################################
    # /account/settings
    ###############################################

    open_dropdown(driver)

    account_settings_link = driver.find_element(By.ID, "nav-account-settings")

    account_settings_link.click()

    expect_route(driver, "/account/settings/")

    back_link = driver.find_element(By.CLASS_NAME, "back-btn")
    back_link.click()

    expect_route(driver, "/home")

    ###############################################
    # /account/privacy
    ###############################################

    open_dropdown(driver)

    privacy_faq_link = driver.find_element(By.ID, "nav-privacy")

    privacy_faq_link.click()

    expect_route(driver, "/account/privacy/")

    back_link = driver.find_element(By.CLASS_NAME, "back-btn")
    back_link.click()

    expect_route(driver, "/home")

    ###############################################
    # /account/help
    ###############################################

    open_dropdown(driver)

    help_faq_link = driver.find_element(By.ID, "nav-help")

    help_faq_link.click()

    expect_route(driver, "/account/help/")

    back_link = driver.find_element(By.CLASS_NAME, "back-btn")
    back_link.click()

    expect_route(driver, "/home")

    ###############################################
    # Tools (no buttons because they are expected to change)
    ###############################################
    def navigate_and_expect(driver, path):
        driver.get(f"{WEBSITE_URL}{path}")
        expect_route(driver, path)

    navigate_and_expect(driver, "/AssignmentsGrades/tool1.php")
    navigate_and_expect(driver, "/faculty-form/")
    navigate_and_expect(driver, "/coordinator-form/")
    navigate_and_expect(driver, "/report-generator/index.php")

    remove_db_user(EMAIL_ADDRESS)
