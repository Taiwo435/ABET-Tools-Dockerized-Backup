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

