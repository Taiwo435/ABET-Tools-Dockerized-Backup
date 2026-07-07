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
from utils.webdriver import init_webdriver
from utils.webdriver import PROJECT_DIR

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
    driver.get(f"{WEBSITE_URL}/login")
    driver.implicitly_wait(2)  # Wait for the page to load
    assert driver.current_url == f"{WEBSITE_URL}/login", "User not redirected to login page on initial load"
    print("Got the website")

    email_input = WebDriverWait(driver, 20).until(
        EC.presence_of_element_located((By.ID, "email"))
    )

    password_input = driver.find_element(By.ID, "password")
    login_button = driver.find_element(By.CLASS_NAME, "btn-submit")

    email_input.send_keys("invaliduser")
    password_input.send_keys("invalidpass")
    login_button.click()
    
    driver.implicitly_wait(2)  # Wait for the next page to load

    expect_route(driver, "/login")

@pytest.mark.order(1)
def test_register_and_login_valid_credentials_logout(driver):
    """
    tests the register and login funcitonality for a valid login
    + added test to test logout as well
    """

    driver.get(f"{WEBSITE_URL}/register")
    driver.implicitly_wait(2)  # Wait for the page to load
    print("Got the website")

    expect_route(driver, "/register")

    try:
        email_input = WebDriverWait(driver, 20).until(
            EC.presence_of_element_located((By.ID, "email"))
        )
    except AssertionError:
        print("===================ERROR=================")
        print(driver.page_source)
        print("===================END PAGE SOURCE=================")
        raise AssertionError


    # Fill out the registration form
    password_input = driver.find_element(By.ID, "password")
    confirm_password_input = driver.find_element(By.ID, "confirm_password")
    register_button = driver.find_element(By.CLASS_NAME, "btn-submit")

    email_input.send_keys(EMAIL_ADDRESS)
    password_input.send_keys(PASSWORD)
    confirm_password_input.send_keys(PASSWORD)
    register_button.click()
    driver.implicitly_wait(2)  # Wait for the next page to load

    # sleep(2) 

    # check if there's a success message
    success_elements = driver.find_elements(By.CLASS_NAME, "success")

    webdriver_wait = WebDriverWait(driver, 5)
    webdriver_wait.until(EC.any_of(
        EC.presence_of_element_located((By.CLASS_NAME, "success")),
        EC.presence_of_element_located((By.CLASS_NAME, "error"))
    ))

    print(success_elements)
    if len(success_elements) > 0:
        innerHTML = success_elements[0].get_attribute('innerHTML')
        assert innerHTML.strip() == "<strong>Success!</strong> Account created. You can now sign in.", "Success message not found after registration"
    else:
        # if not, check for error message about existing account
        error_message_element = driver.find_element(By.CLASS_NAME, "error")
        print(error_message_element.text)
        assert error_message_element.text == "An account with that email already exists.", "Error message element not found after attempting to register with existing email"

    driver.get(f"{WEBSITE_URL}/login")

    email_input = driver.find_element(By.ID, "email")
    password_input = driver.find_element(By.ID, "password")
    login_button = driver.find_element(By.CLASS_NAME, "btn-submit")

    email_input.send_keys(EMAIL_ADDRESS)
    password_input.send_keys(PASSWORD)
    login_button.click()
    driver.implicitly_wait(2)  # Wait for the next page to load

    expect_route(driver, "/home2")

# @with_webdriver
def test_navigation(driver):
    """
    test using the new decorator i just made
    should navigate to the homepage and some profile stuff
    """
    driver.get(f"{WEBSITE_URL}")
    driver.implicitly_wait(2)  # Wait for the page to load
    print("Got the website")

    driver.get(f"{WEBSITE_URL}/login")

    email_input = WebDriverWait(driver, 20).until(
        EC.presence_of_element_located((By.ID, "email"))
    )

    password_input = driver.find_element(By.ID, "password")
    login_button = driver.find_element(By.CLASS_NAME, "btn-submit")

    email_input.send_keys(EMAIL_ADDRESS)
    password_input.send_keys(PASSWORD)
    login_button.click()
    WebDriverWait(driver, 10).until(lambda d: "Log Out" in d.page_source)  # Wait for the next page to load

    expect_route(driver, "/home2")

    ###############################################
    # ACCOUNT PAGES
    # /account/me
    ###############################################
    def open_dropdown(driver): 
        profile_button = driver.find_element(By.CLASS_NAME, "auth-btn");
        profile_button.click()

    open_dropdown(driver)

    # go to me
    my_profile_link = driver.find_element(By.XPATH, "/html/body/header/div[2]/div/div/div/div/a");
    my_profile_link.click()

    expect_route(driver, "/account/me/")

    back_link = driver.find_element(By.XPATH, "/html/body/div/div[1]/div[1]/a");
    back_link.click()

    expect_route(driver, "/home2")

    ###############################################
    # /account/profile
    ###############################################

    open_dropdown(driver)

    # go to edit
    edit_profile_link = driver.find_element(By.XPATH, "/html/body/header/div[2]/div/div/div/a[1]");
    edit_profile_link.click()

    expect_route(driver, "/account/profile/")

    # go to home
    back_link = driver.find_element(By.XPATH, "/html/body/div/div/div[2]/a[1]");
    back_link.click()

    expect_route(driver, "/home2")

    ###############################################
    # /account/settings
    ###############################################

    open_dropdown(driver)

    account_settings_link = driver.find_element(By.XPATH, "/html/body/header/div[2]/div/div/div/a[2]")
    account_settings_link.click()

    expect_route(driver, "/account/settings/")

    back_link = driver.find_element(By.CLASS_NAME, "back-btn")
    back_link.click()

    expect_route(driver, "/home2")

    ###############################################
    # /account/privacy
    ###############################################

    open_dropdown(driver)

    privacy_faq_link = driver.find_element(By.XPATH, "/html/body/header/div[2]/div/div/div/a[3]")
    privacy_faq_link.click()

    expect_route(driver, "/account/privacy/")

    back_link = driver.find_element(By.CLASS_NAME, "back-btn")
    back_link.click()

    expect_route(driver, "/home2")

    ###############################################
    # /account/help
    ###############################################

    open_dropdown(driver)

    help_faq_link = driver.find_element(By.XPATH, "/html/body/header/div[2]/div/div/div/a[4]")
    help_faq_link.click()

    expect_route(driver, "/account/help/")

    back_link = driver.find_element(By.CLASS_NAME, "back-btn")
    back_link.click()

    expect_route(driver, "/home2")

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
