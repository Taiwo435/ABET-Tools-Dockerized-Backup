"""
The test_login.py file contains Selenium-based tests for the login functionality of the ABET Tools web application. 
This is meant to run with the dockerized Selenium Grid setup defined in the docker-compose.yml file.

contains a template file for future tests .

implemented tests:
    - login with invalid credentials
"""
import os
from sys import exit
from traceback import print_exc

from selenium import webdriver
from selenium.webdriver.common.by import By

from dotenv import load_dotenv
import pytest

EMAIL_ADDRESS = os.getenv("TEST_EMAIL", "test@example.com")
PASSWORD = os.getenv("TEST_PASSWORD", "superSecretPassword1!")

load_dotenv("../../docker/.env")
os.environ["PATH"] += os.pathsep + os.pathsep.join([
    "/home/danny/ASU/ABET-Tools-Frontend/src/test/drivers",
])

def init_webdriver():
    try:
        driver = webdriver.Remote(
            command_executor="http://localhost:4444/wd/hub",
            options=webdriver.FirefoxOptions(),
        )
        return driver
    except Exception as e:
        print(f"An error occurred while initializing WebDriver: {e}")
        print_exc()
        exit(1)

def test_login_invalid_credentials():
    driver = init_webdriver()
    try:
        driver.get(f"http://{os.getenv('APP_CONTAINERNAME')}")
        driver.implicitly_wait(2)  # Wait for the page to load
        assert driver.current_url == f"http://{os.getenv('APP_CONTAINERNAME')}/login", "User not redirected to login page on initial load"
        print("Got the website")

        email_input = driver.find_element(By.ID, "email")
        password_input = driver.find_element(By.ID, "password")
        login_button = driver.find_element(By.CLASS_NAME, "btn-submit")

        email_input.send_keys("invaliduser")
        password_input.send_keys("invalidpass")
        login_button.click()
        
        driver.implicitly_wait(2)  # Wait for the next page to load

        assert driver.current_url == f"http://{os.getenv('APP_CONTAINERNAME')}/login", "User not redirected to login page after invalid credentials"
    except Exception as e:
        print(f"An error occurred: {e}")
        print_exc()
        exit(1)
    finally:
        driver.quit()

@pytest.mark.order(1)
def test_register_and_login_valid_credentials():
    driver = init_webdriver()
    try:
        driver.get(f"http://{os.getenv('APP_CONTAINERNAME')}/register")
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

        # check if there's a success message
        if driver.find_elements(By.CLASS_NAME, "success"):
            innerHTML = driver.find_element(By.CLASS_NAME, "success").get_attribute('innerHTML')
            assert innerHTML.strip() == "<strong>Success!</strong> Account created. You can now sign in.", "Success message not found after registration"
        else:
            # if not, check for error message about existing account
            error_message_element = driver.find_element(By.CLASS_NAME, "error")
            print(error_message_element.text)
            assert error_message_element.text == "An account with that email already exists.", "Error message element not found after attempting to register with existing email"

        driver.get(f"http://{os.getenv('APP_CONTAINERNAME')}/login")

        email_input = driver.find_element(By.ID, "email")
        password_input = driver.find_element(By.ID, "password")
        login_button = driver.find_element(By.CLASS_NAME, "btn-submit")

        email_input.send_keys(EMAIL_ADDRESS)
        password_input.send_keys(PASSWORD)
        login_button.click()
        driver.implicitly_wait(2)  # Wait for the next page to load

        assert driver.current_url == f"http://{os.getenv('APP_CONTAINERNAME')}/home", "User not redirected to home after valid login"
    except Exception as e:
        print(f"An error occurred: {e}")
        print_exc()
        exit(1)
    finally:
        driver.quit()
        


def template(): 
    driver = init_webdriver()
    try:
        driver.get(f"http://{os.getenv('APP_CONTAINERNAME')}:80")
        driver.implicitly_wait(2)  # Wait for the page to load
        print("Got the website")

        # Add your test steps here

    except Exception as e:
        print(f"An error occurred: {e}")
        print_exc()
    finally:
        driver.quit()

