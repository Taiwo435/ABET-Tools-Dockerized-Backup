"""
NOT THE ACTUAL TESTING FILE. THIS IS A TEMPLATE FOR TESTING. DO NOT RUN THIS FILE DIRECTLY.
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

# Template function for testing. The new name should start with "test_" to be recognized by pytest.
def test_template(): 
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

def main():
    print("Wrong file. Read the README.md for instructions on how to run the tests.")



if __name__ == "__main__":
    main()
