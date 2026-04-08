"""
provides template functions for all other tests

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
import git

def get_git_root(path):
    git_repo = git.Repo(path, search_parent_directories=True)
    git_root = git_repo.git.rev_parse("--show-toplevel")
    return git_root

EMAIL_ADDRESS = os.getenv("TEST_EMAIL", "test@example.com")
PASSWORD = os.getenv("TEST_PASSWORD", "superSecretPassword1!")
# WEBSITE_URL = f"http://localhost:{os.getenv('APP_PORT', '8080')}"
WEBSITE_URL = f"http://{os.getenv('WEBSERVER_HOSTNAME', 'php_apache')}"
PROJECT_DIR = get_git_root(".")

# I don't remember adding this???
# os.environ["PATH"] += os.pathsep + os.pathsep.join([
#     "/home/danny/ASU/ABET-Tools-Frontend/src/test/drivers",
# ])

def init_webdriver():
    try:
        browser = os.getenv("SELENIUM_BROWSER")
        # for name, value in os.environ.items():
        #     print(f"{name}: {value}")
        options = None
        match browser:
            case 'firefox':
                options = webdriver.FirefoxOptions()
            case 'chrome':
                options = webdriver.ChromeOptions()
            case _:
                raise EnvironmentError("invalid value for SELENIUM_BROWSER")
        driver = webdriver.Remote(
            command_executor=f"http://localhost:{os.getenv('SELENIUM_PORT', '4444')}/wd/hub",
            options=options,
        )
        return driver
    except Exception as e:
        print(f"An error occurred while initializing WebDriver: {e}")
        print_exc()
        exit(1)