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

from dotenv import load_dotenv
import pytest

EMAIL_ADDRESS = os.getenv("TEST_EMAIL", "test@example.com")
PASSWORD = os.getenv("TEST_PASSWORD", "superSecretPassword1!")
# WEBSITE_URL = f"http://localhost:{os.getenv('APP_PORT', '8080')}"
WEBSITE_URL = f"http://{os.getenv('WEBSERVER_HOSTNAME', 'php_apache')}"

load_dotenv("../../docker/.env")
os.environ["PATH"] += os.pathsep + os.pathsep.join([
    "/home/danny/ASU/ABET-Tools-Frontend/src/test/drivers",
])

def init_webdriver():
    """
    Initializes the webdriver in a completely fresh state
    ensures that there is no leftover session state
    """
    try:
        driver = webdriver.Remote(
            command_executor=f"http://localhost:{os.getenv('SELENIUM_PORT', '4444')}/wd/hub",
            options=webdriver.FirefoxOptions(),
        )
        return driver
    except Exception as e:
        print(f"An error occurred while initializing WebDriver: {e}")
        print_exc()
        exit(1)


def with_webdriver(func):
    """
    A decorator to shorten the lengths of tests by removing repeated webdriver setup
    """
    @wraps(func)
    def wrapper(*args, **kwargs):
        driver = init_webdriver()
        try:
            return func(driver, *args, **kwargs)
        except Exception as e:
            print(f"An error occurred: {e}")
            print_exc()
        finally:
            driver.quit()
    return wrapper

def template(): 
    """
    deprecated template for making new tests
    """
    driver = init_webdriver()
    try:
        driver.get(f"{WEBSITE_URL}")
        driver.implicitly_wait(2)  # Wait for the page to load
        print("Got the website")

        # Add your test steps here

    except Exception as e:
        print(f"An error occurred: {e}")
        print_exc()
    finally:
        driver.quit()


def test_login_invalid_credentials(driver):
    """
    tests that users cannot simply access the website without having a valid session
    """
    driver = init_webdriver()
    try:
        driver.get(f"{WEBSITE_URL}/login")
        driver.implicitly_wait(2)  # Wait for the page to load
        assert driver.current_url == f"{WEBSITE_URL}/login", "User not redirected to login page on initial load"
        print("Got the website")

        email_input = driver.find_element(By.ID, "email")
        password_input = driver.find_element(By.ID, "password")
        login_button = driver.find_element(By.CLASS_NAME, "btn-submit")

        email_input.send_keys("invaliduser")
        password_input.send_keys("invalidpass")
        login_button.click()
        
        driver.implicitly_wait(2)  # Wait for the next page to load

        assert driver.current_url == f"{WEBSITE_URL}/login", "User not redirected to login page after invalid credentials"
    except Exception as e:
        print(f"An error occurred: {e}")
        print_exc()
        exit(1)
    finally:
        driver.quit()

@pytest.mark.order(1)
def test_register_and_login_valid_credentials():
    """
    tests the register and login funcitonality for a valid login
    """
    driver = init_webdriver()
    try:
        driver.get(f"{WEBSITE_URL}/register")
        driver.implicitly_wait(2)  # Wait for the page to load
        print("Got the website")

        # Fill out the registration form
        email_input = driver.find_element(By.ID, "email")
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

        assert driver.current_url == f"{WEBSITE_URL}/home", "User not redirected to home after valid login"
    except Exception as e:
        print(f"An error occurred: {e}")
        print_exc()
        exit(1)
    finally:
        driver.quit()
        


@with_webdriver
def test_navigation(driver): 
    """
    test using the new decorator i just made
    should navigate to the homepage and some profile stuff
    """
    driver.get(f"{WEBSITE_URL}")
    driver.implicitly_wait(2)  # Wait for the page to load
    print("Got the website")

    driver.get(f"{WEBSITE_URL}/login")

    email_input = driver.find_element(By.ID, "email")
    password_input = driver.find_element(By.ID, "password")
    login_button = driver.find_element(By.CLASS_NAME, "btn-submit")

    email_input.send_keys(EMAIL_ADDRESS)
    password_input.send_keys(PASSWORD)
    login_button.click()
    driver.implicitly_wait(2)  # Wait for the next page to load

    assert driver.current_url == f"{WEBSITE_URL}/home", "User not redirected to home after valid login"

    # open profile dropdown
    profile_button = driver.find_element(By.CLASS_NAME, "auth-btn");
    profile_button.click()

    # go to me
    my_profile_link = driver.find_element(By.XPATH, "/html/body/header/div[2]/div/div/div/div/a");
    my_profile_link.click()

    assert driver.current_url == f"{WEBSITE_URL}/account/me", "User not at /account/me after button pressed"

    back_to_dashboard_link = driver.find_element(By.XPATH, "/html/body/div/div[1]/div[1]/a");
    back_to_dashboard_link.click()

    assert driver.current_url == f"{WEBSITE_URL}/home", "User not at /home after navigation back pressed"


    # Add your test steps here
