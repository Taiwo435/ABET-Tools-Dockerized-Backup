"""
The test_login.py file contains Selenium-based tests for testing the database's permissions required for each spot. 
This is meant to run with the dockerized Selenium Grid setup defined in the docker-compose.yml file.

contains a template file for future tests.

permissions refer to the Permissions enum at abet_private/database/entities/User.php

planned tests:
    - tools/admin-panel/ requires admin role OR AdminPanel perm
    - AssignmentsGrades/ requires GradeDataTool
    - report-generator/ requires ReportGenTool 
    - faculty-form/ requires FacultyFormTool
    - coordinator-form/ requires CoordinatorFormTool
    - cgi-bin and more are inaccessible
    
"""
import os
from sys import exit
from traceback import print_exc

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from time import sleep

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

# I could make this a decorator...
def template(): 
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

