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

